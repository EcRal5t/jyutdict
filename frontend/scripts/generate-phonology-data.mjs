import { spawnSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import { readFile, writeFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

import * as OpenCC from 'opencc-js'
import * as currentData from '../src/data/characteristics_matrix.js'

const scriptDirectory = dirname(fileURLToPath(import.meta.url))
const outputPath = resolve(scriptDirectory, '../src/data/characteristics_matrix.js')
const defaultMysqlPath = 'D:\\ProgramFiles\\BienCreng\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe'
const mysqlPath = process.env.JYUTDICT_MYSQL || (existsSync(defaultMysqlPath) ? defaultMysqlPath : 'mysql')
const databaseName = process.env.JYUTDICT_DB || 'jyutdict'
const defaultMcpToolsPath = 'D:\\Proj\\Jyutdict\\j++2ipa\\通用表\\sub_proj\\MCPDict\\tools'
const mcpToolsPath = process.env.MCPDICT_TOOLS || defaultMcpToolsPath
const mcpInfoPath = resolve(mcpToolsPath, 'info.geojson')
const mcpOutputPath = resolve(mcpToolsPath, 'tables/output')
const checkOnly = process.argv.includes('--check')
const traditionalConverter = OpenCC.Converter({ from: 'cn', to: 't' })
const mandarinMcpTitle = '普通話'

const efficientMinimumSiteCoverage = 0.90
const efficientMinimumMcpCoverage = 0.72
const efficientMinimumPairCoverage = 0.70
const efficientMinimumTargetPairCoverage = 0.85
const efficientMinimumToneAgreement = 0.95
const efficientMinimumMinoritySize = 4
const efficientMinimumTargetMinoritySize = 8
const efficientCandidateLimit = 4000
const efficientFeatureCount = 30
const efficientPcaComponentCount = 20
const efficientPrimaryDistanceWeight = 0.85
const efficientDistanceDecay = 0.55
const efficientMaximumPrimaryPartitionAgreement = 0.95

const unreliableReadingNotePattern = /(?:^|[\s（(\[【〈＜<，,；;])(?:訓(?:讀|音)?|训(?:读|音)?|錯(?:讀|音)?|错(?:读|音)?|存疑|推導音?|推导音?|文(?:讀|音)?|白(?:讀|音)?|俗(?:讀|音)?|書(?:讀|音)?|书(?:读|音)?|語(?:讀|音)?|语(?:读|音)?|官話調|官话调|[＊*]?變調|[＊*]?变调|習讀|习读|姓氏?|地名|人名)(?=$|[\s）)\]】〉＞>，,；;:：])/u
const externalReliableReadingAnnotations = new Set(['-', '=', '?', '*', '@', '\\', '/'])

const ignoreToneSuffix = ' [×調]'
const pairDefinitions = currentData.CHARACTERISTIC_PAIRS.map((label) => {
    const ignoreTone = label.endsWith(ignoreToneSuffix)
    const pair = (ignoreTone ? label.slice(0, -ignoreToneSuffix.length) : label).split('=')
    if (pair.length !== 2 || pair.some(value => !value)) {
        throw new Error(`Invalid characteristic pair: ${label}`)
    }
    return { label, pair, ignoreTone }
})

function runMysql(sql) {
    const result = spawnSync(mysqlPath, [
        '-u', 'root',
        '--default-character-set=utf8mb4',
        '--batch',
        '--raw',
        '--skip-column-names',
        databaseName,
    ], {
        input: sql,
        encoding: 'utf8',
        maxBuffer: 256 * 1024 * 1024,
    })
    if (result.error) throw result.error
    if (result.status !== 0) {
        throw new Error(result.stderr.trim() || `mysql exited with status ${result.status}`)
    }
    return result.stdout.replace(/\r?\n$/, '').split(/\r?\n/).filter(Boolean)
}

function parseAreas() {
    const lines = runMysql(`
        SELECT id, first, second, third, longitude, latitude, color, is_visible, sort_order
        FROM i_area_list
        WHERE current_release_id IS NOT NULL AND archived_at IS NULL
        ORDER BY sort_order, id;
    `)
    return lines.map((line) => {
        const [id, first, second, third, longitude, latitude, color, isVisible, sortOrder] = line.split('\t')
        return {
            id: Number(id),
            first,
            second,
            third,
            longitude: Number(longitude),
            latitude: Number(latitude),
            color,
            isVisible: isVisible === '1',
            sortOrder: Number(sortOrder),
        }
    })
}

function legacyAreaIds() {
    if (Array.isArray(currentData.LOCATION_AREA_IDS)) {
        if (currentData.LOCATION_AREA_IDS.length !== currentData.LOCATION_NAMES.length) {
            throw new Error('LOCATION_AREA_IDS length does not match LOCATION_NAMES')
        }
        return currentData.LOCATION_AREA_IDS
    }

    // The migrated matrix originally contained database areas 6 through 69,
    // in ID order, followed by the matrix-only Mandarin reference row.
    return currentData.LOCATION_NAMES.map((_, index) => index < 64 ? index + 6 : null)
}

function sqlString(value) {
    return `'${String(value).replaceAll("'", "''")}'`
}

function loadPronunciations(areaIds) {
    const characters = [...new Set(pairDefinitions.flatMap(({ pair }) => pair))]
    const lines = runMysql(`
        SELECT area_id, chara, initial, nuclei, coda, tone
        FROM common_entries
        WHERE area_id IN (${areaIds.join(',')})
          AND chara IN (${characters.map(sqlString).join(',')})
        ORDER BY area_id, chara, id;
    `)
    const readings = new Map()
    for (const line of lines) {
        const [areaIdText, chara, initial, nuclei, coda, tone] = line.split('\t')
        const areaId = Number(areaIdText)
        if (!readings.has(areaId)) readings.set(areaId, new Map())
        const areaReadings = readings.get(areaId)
        if (!areaReadings.has(chara)) areaReadings.set(chara, [])
        areaReadings.get(chara).push({ initial, nuclei, coda, tone })
    }
    return readings
}

function pronunciationSet(rows, ignoreTone) {
    return new Set((rows || []).map(({ initial, nuclei, coda, tone }) =>
        `${initial}\u001f${nuclei}\u001f${coda}${ignoreTone ? '' : `\u001f${tone}`}`
    ))
}

function equalSets(left, right) {
    return left.size === right.size && [...left].every(value => right.has(value))
}

function buildVector(areaReadings) {
    return pairDefinitions.map(({ pair: [leftCharacter, rightCharacter], ignoreTone }) => {
        const left = pronunciationSet(areaReadings?.get(leftCharacter), ignoreTone)
        const right = pronunciationSet(areaReadings?.get(rightCharacter), ignoreTone)
        if (!left.size || !right.size) return 0
        return equalSets(left, right) ? 1 : -1
    })
}

function normaliseCharacter(value) {
    return traditionalConverter(String(value || '').normalize('NFKC').trim())
}

function isSingleHanCharacter(value) {
    return /^\p{Script=Han}$/u.test(value)
}

function addReliableReading(characterReadings, character, pronunciation, segmentalPronunciation, tone) {
    if (!characterReadings.has(character)) characterReadings.set(character, new Map())
    characterReadings.get(character).set(pronunciation, {
        pronunciation,
        segmentalPronunciation,
        tone,
    })
}

function finaliseReliableReadings(characterReadings) {
    const reliableReadings = new Map()
    for (const [character, pronunciations] of characterReadings) {
        const readings = [...pronunciations.values()]
        if (!readings.length) continue
        reliableReadings.set(character, {
            readings,
            pronunciations: new Set(readings.map(reading => reading.pronunciation)),
            segmentalPronunciations: new Set(readings.map(reading => reading.segmentalPronunciation)),
            tones: new Set(readings.map(reading => reading.tone)),
        })
    }
    return reliableReadings
}

function setsIntersect(left, right) {
    const [smaller, larger] = left.size <= right.size ? [left, right] : [right, left]
    return [...smaller].some(value => larger.has(value))
}

function classifyReliableReadingComparison(left, right, ignoreTone = false) {
    if (!left || !right) return 'missing'
    if (ignoreTone) {
        if (!setsIntersect(left.segmentalPronunciations, right.segmentalPronunciations)) return 'different'
        if (equalSets(left.segmentalPronunciations, right.segmentalPronunciations)) return 'same'
        return 'ambiguous'
    }
    if (!setsIntersect(left.pronunciations, right.pronunciations)) return 'different'
    if (equalSets(left.pronunciations, right.pronunciations)) return 'same'
    if (left.segmentalPronunciations.size === 1 &&
        equalSets(left.segmentalPronunciations, right.segmentalPronunciations)) {
        return 'same'
    }
    return 'ambiguous'
}

function compareReliableReadings(left, right, ignoreTone = false) {
    const classification = classifyReliableReadingComparison(left, right, ignoreTone)
    if (classification === 'same') return 1
    if (classification === 'different') return -1
    return 0
}

function loadReliableSiteReadings(areaIds) {
    const lines = runMysql(`
        SELECT area_id, chara, initial, nuclei, coda, tone, HEX(note)
        FROM common_entries
        WHERE area_id IN (${areaIds.join(',')})
        ORDER BY area_id, chara, id;
    `)
    const rawByAreaId = new Map(areaIds.map(areaId => [areaId, new Map()]))
    for (const line of lines) {
        const [areaIdText, rawCharacter, initial, nuclei, coda, tone, noteHex] = line.split('\t')
        const character = normaliseCharacter(rawCharacter)
        if (!isSingleHanCharacter(character)) continue
        const note = Buffer.from(noteHex || '', 'hex').toString('utf8').trim()
        if (unreliableReadingNotePattern.test(note)) continue
        const segmentalPronunciation = `${initial}\u001f${nuclei}\u001f${coda}`
        const pronunciation = `${segmentalPronunciation}\u001f${tone}`
        addReliableReading(
            rawByAreaId.get(Number(areaIdText)),
            character,
            pronunciation,
            segmentalPronunciation,
            tone
        )
    }
    return new Map([...rawByAreaId].map(([areaId, characterReadings]) =>
        [areaId, finaliseReliableReadings(characterReadings)]
    ))
}

const externalReadingAnnotations = new Set(['+', '-', '=', '?', '*', '@', '\\'])

function cleanExternalReading(value) {
    let reading = value.normalize('NFC').trim()
    while (reading && externalReadingAnnotations.has(reading.at(-1))) {
        reading = reading.slice(0, -1)
    }
    return reading
}

function externalPronunciationSet(rows, ignoreTone) {
    return new Set([...(rows || [])].map(reading =>
        ignoreTone ? reading.replace(/[0-9]+[a-z]?$/u, '') : reading
    ).filter(Boolean))
}

function buildExternalVector(characterReadings) {
    return pairDefinitions.map(({ pair: [leftCharacter, rightCharacter], ignoreTone }) => {
        const left = externalPronunciationSet(characterReadings.get(leftCharacter), ignoreTone)
        const right = externalPronunciationSet(characterReadings.get(rightCharacter), ignoreTone)
        if (!left.size || !right.size) return 0
        return equalSets(left, right) ? 1 : -1
    })
}

async function loadMcpCorpus() {
    if (!existsSync(mcpInfoPath) || !existsSync(mcpOutputPath)) {
        throw new Error(`MCPDict corpus not found at ${mcpToolsPath}. Set MCPDICT_TOOLS to its tools directory.`)
    }

    const metadata = JSON.parse(await readFile(mcpInfoPath, 'utf8'))
    if (metadata?.type !== 'FeatureCollection' || !Array.isArray(metadata.features)) {
        throw new Error(`Invalid MCPDict metadata: ${mcpInfoPath}`)
    }

    const features = metadata.features.filter(feature =>
        /^[粤粵]語(?:－|$)/u.test(String(feature?.properties?.['地圖集二分區'] || '').trim())
    )
    const cantoneseTitles = features.map(feature => String(feature?.properties?.title || '').trim())
    if (cantoneseTitles.some(title => !title) || new Set(cantoneseTitles).size !== cantoneseTitles.length) {
        throw new Error('MCPDict Cantonese metadata contains a missing or duplicate title.')
    }

    const rows = []
    for (const title of [...cantoneseTitles, mandarinMcpTitle]) {
        const tablePath = resolve(mcpOutputPath, `${title}.tsv`)
        if (!existsSync(tablePath)) throw new Error(`Missing MCPDict output table: ${tablePath}`)

        const lines = (await readFile(tablePath, 'utf8')).replace(/^\uFEFF/u, '').split(/\r?\n/u)
        if (lines.shift() !== '#漢字\t音標\t解釋') {
            throw new Error(`Unexpected MCPDict output header: ${tablePath}`)
        }

        const characterReadings = new Map()
        const reliableCharacterReadings = new Map()
        for (const line of lines) {
            if (!line) continue
            const [rawCharacter, rawPronunciation, ...explanationParts] = line.split('\t')
            if (!rawCharacter || !rawPronunciation) continue
            const character = normaliseCharacter(rawCharacter)
            if (!isSingleHanCharacter(character)) continue
            if (!characterReadings.has(character)) characterReadings.set(character, new Set())
            const readings = characterReadings.get(character)
            for (const alternative of rawPronunciation.split('/')) {
                const reading = cleanExternalReading(alternative)
                if (reading) readings.add(reading)
            }

            const normalisedPronunciation = rawPronunciation.normalize('NFC').trim()
            const explanation = explanationParts.join('\t').trim()
            if (unreliableReadingNotePattern.test(explanation)) continue
            for (const rawAlternative of normalisedPronunciation.split('/')) {
                if (externalReliableReadingAnnotations.has(rawAlternative.at(-1))) continue
                const reading = cleanExternalReading(rawAlternative)
                const tone = reading.match(/[0-9]+[a-z]?$/u)?.[0]
                if (reading && tone) {
                    addReliableReading(
                        reliableCharacterReadings,
                        character,
                        reading,
                        reading.slice(0, -tone.length),
                        tone
                    )
                }
            }
        }
        rows.push({
            title,
            vector: buildExternalVector(characterReadings),
            reliableReadings: finaliseReliableReadings(reliableCharacterReadings),
        })
    }
    return {
        cantoneseRows: rows.filter(row => row.title !== mandarinMcpTitle),
        mandarinRow: rows.find(row => row.title === mandarinMcpTitle),
    }
}

function comparableCount(vector) {
    return vector.filter(value => value !== 0).length
}

function selectSimplifiedFeatureIndices(matrix) {
    const coverageByFeature = Array.from({ length: pairDefinitions.length }, () => [])
    let totalPairCount = 0
    let distinguishablePairCount = 0
    for (let left = 0; left < matrix.length; left++) {
        for (let right = left + 1; right < matrix.length; right++) {
            totalPairCount++
            const features = []
            for (let feature = 0; feature < pairDefinitions.length; feature++) {
                const leftValue = matrix[left][feature]
                const rightValue = matrix[right][feature]
                if (leftValue !== 0 && rightValue !== 0 && leftValue !== rightValue) {
                    features.push(feature)
                }
            }
            if (!features.length) continue
            for (const feature of features) coverageByFeature[feature].push(distinguishablePairCount)
            distinguishablePairCount++
        }
    }

    const uncovered = new Uint8Array(distinguishablePairCount)
    uncovered.fill(1)
    let uncoveredCount = distinguishablePairCount
    const selected = []
    const selectedSet = new Set()
    const knownCounts = pairDefinitions.map((_, feature) =>
        matrix.reduce((count, vector) => count + Number(vector[feature] !== 0), 0)
    )
    while (uncoveredCount) {
        let bestFeature = -1
        let bestGain = 0
        let bestKnownCount = 0
        for (let feature = 0; feature < pairDefinitions.length; feature++) {
            if (selectedSet.has(feature)) continue
            let gain = 0
            for (const pairIndex of coverageByFeature[feature]) gain += uncovered[pairIndex]
            const knownCount = knownCounts[feature]
            if (gain > bestGain || (gain === bestGain && knownCount > bestKnownCount)) {
                bestFeature = feature
                bestGain = gain
                bestKnownCount = knownCount
            }
        }
        if (bestFeature < 0 || bestGain === 0) break
        selected.push(bestFeature)
        selectedSet.add(bestFeature)
        for (const pairIndex of coverageByFeature[bestFeature]) {
            if (uncovered[pairIndex]) {
                uncovered[pairIndex] = 0
                uncoveredCount--
            }
        }
    }
    if (uncoveredCount) throw new Error(`Failed to cover ${uncoveredCount} distinguishable corpus pairs.`)
    return { indices: selected, distinguishablePairCount, totalPairCount }
}

function compareStrings(left, right) {
    if (left === right) return 0
    return left < right ? -1 : 1
}

function selectEfficientCandidateCharacters(siteSamples, mcpSamples, requiredSamples = []) {
    const coverage = new Map()
    const countSamples = (samples, field) => {
        for (const sample of samples) {
            for (const [character, readingSet] of sample.reliableReadings) {
                if (readingSet.readings.length !== 1) continue
                if (!coverage.has(character)) coverage.set(character, { character, site: 0, mcp: 0 })
                coverage.get(character)[field]++
            }
        }
    }
    countSamples(siteSamples, 'site')
    countSamples(mcpSamples, 'mcp')

    const minimumSiteCount = Math.ceil(siteSamples.length * efficientMinimumSiteCoverage)
    const minimumMcpCount = Math.ceil(mcpSamples.length * efficientMinimumMcpCoverage)
    const candidates = [...coverage.values()]
        .filter(item => item.site >= minimumSiteCount &&
            item.mcp >= minimumMcpCount &&
            requiredSamples.every(sample => sample.reliableReadings.get(item.character)?.readings.length === 1))
        .sort((left, right) =>
            (right.site + right.mcp) - (left.site + left.mcp) ||
            right.site - left.site ||
            compareStrings(left.character, right.character)
        )
    return { candidates, minimumSiteCount, minimumMcpCount }
}

function partitionSignature(vector, invert = false) {
    let signature = ''
    for (const value of vector) signature += String.fromCharCode((invert ? -value : value) + 1)
    return signature
}

function isBetterEfficientCandidate(candidate, current) {
    return candidate.targetIndividualGain > current.targetIndividualGain ||
        (candidate.targetIndividualGain === current.targetIndividualGain && candidate.targetComparableCount > current.targetComparableCount) ||
        (candidate.targetIndividualGain === current.targetIndividualGain && candidate.targetComparableCount === current.targetComparableCount &&
            candidate.toneAgreement > current.toneAgreement) ||
        (candidate.targetIndividualGain === current.targetIndividualGain && candidate.targetComparableCount === current.targetComparableCount &&
            candidate.toneAgreement === current.toneAgreement && candidate.comparableCount > current.comparableCount) ||
        (candidate.targetIndividualGain === current.targetIndividualGain && candidate.targetComparableCount === current.targetComparableCount &&
            candidate.toneAgreement === current.toneAgreement && candidate.comparableCount === current.comparableCount &&
            compareStrings(candidate.label, current.label) < 0)
}

function generateEfficientCandidates(characterCandidates, samples, targetSampleIndices) {
    const minimumComparableCount = Math.ceil(samples.length * efficientMinimumPairCoverage)
    const minimumTargetComparableCount = Math.ceil(targetSampleIndices.length * efficientMinimumTargetPairCoverage)
    const isTargetSample = new Uint8Array(samples.length)
    for (const sampleIndex of targetSampleIndices) isTargetSample[sampleIndex] = 1
    const candidateByPartition = new Map()

    for (let leftIndex = 0; leftIndex < characterCandidates.length; leftIndex++) {
        const leftCharacter = characterCandidates[leftIndex].character
        for (let rightIndex = leftIndex + 1; rightIndex < characterCandidates.length; rightIndex++) {
            const rightCharacter = characterCandidates[rightIndex].character
            const vector = new Int8Array(samples.length)
            let comparableCount = 0
            let sameCount = 0
            let differentCount = 0
            let sameToneCount = 0
            let targetComparableCount = 0
            let targetSameCount = 0
            let targetDifferentCount = 0

            for (let sampleIndex = 0; sampleIndex < samples.length; sampleIndex++) {
                const left = samples[sampleIndex].reliableReadings.get(leftCharacter)
                const right = samples[sampleIndex].reliableReadings.get(rightCharacter)
                const comparison = compareReliableReadings(left, right)
                if (comparison === 0) continue
                comparableCount++
                if (setsIntersect(left.tones, right.tones)) sameToneCount++
                if (comparison === 1) {
                    vector[sampleIndex] = 1
                    sameCount++
                    if (isTargetSample[sampleIndex]) targetSameCount++
                } else {
                    vector[sampleIndex] = -1
                    differentCount++
                    if (isTargetSample[sampleIndex]) targetDifferentCount++
                }
                if (isTargetSample[sampleIndex]) targetComparableCount++
            }

            const minoritySize = Math.min(sameCount, differentCount)
            const targetMinoritySize = Math.min(targetSameCount, targetDifferentCount)
            const toneAgreement = comparableCount ? sameToneCount / comparableCount : 0
            if (comparableCount < minimumComparableCount ||
                targetComparableCount < minimumTargetComparableCount ||
                minoritySize < efficientMinimumMinoritySize ||
                targetMinoritySize < efficientMinimumTargetMinoritySize ||
                toneAgreement < efficientMinimumToneAgreement) {
                continue
            }

            const signature = partitionSignature(vector)
            const inverseSignature = partitionSignature(vector, true)
            const partitionKey = signature < inverseSignature ? signature : inverseSignature
            const candidate = {
                label: `${leftCharacter}=${rightCharacter}`,
                pair: [leftCharacter, rightCharacter],
                vector,
                comparableCount,
                sameCount,
                differentCount,
                targetComparableCount,
                targetSameCount,
                targetDifferentCount,
                toneAgreement,
                individualGain: sameCount * differentCount,
                targetIndividualGain: targetSameCount * targetDifferentCount,
            }
            const current = candidateByPartition.get(partitionKey)
            if (!current || isBetterEfficientCandidate(candidate, current)) {
                candidateByPartition.set(partitionKey, candidate)
            }
        }
    }

    return [...candidateByPartition.values()]
        .sort((left, right) =>
            right.targetIndividualGain - left.targetIndividualGain ||
            right.targetComparableCount - left.targetComparableCount ||
            right.individualGain - left.individualGain ||
            right.comparableCount - left.comparableCount ||
            right.toneAgreement - left.toneAgreement ||
            compareStrings(left.label, right.label)
        )
        .slice(0, efficientCandidateLimit)
}

function vectorDot(left, right) {
    let result = 0
    for (let index = 0; index < left.length; index++) result += left[index] * right[index]
    return result
}

function orthonormalise(vector, basis) {
    for (const direction of basis) {
        const projection = vectorDot(vector, direction)
        for (let index = 0; index < vector.length; index++) vector[index] -= projection * direction[index]
    }
    const length = Math.sqrt(vectorDot(vector, vector))
    if (length < 1e-12) return false
    for (let index = 0; index < vector.length; index++) vector[index] /= length
    return true
}

function calculatePcaImportance(candidates, sampleIndices) {
    const featureCount = candidates.length
    const sampleCount = sampleIndices.length
    const means = new Float64Array(featureCount)
    const standardDeviations = new Float64Array(featureCount)

    for (let featureIndex = 0; featureIndex < featureCount; featureIndex++) {
        let sum = 0
        let knownCount = 0
        for (const sampleIndex of sampleIndices) {
            const value = candidates[featureIndex].vector[sampleIndex]
            if (value === 0) continue
            sum += value
            knownCount++
        }
        means[featureIndex] = knownCount ? sum / knownCount : 0
        let squaredDifference = 0
        for (const sampleIndex of sampleIndices) {
            const value = candidates[featureIndex].vector[sampleIndex]
            if (value !== 0) squaredDifference += (value - means[featureIndex]) ** 2
        }
        standardDeviations[featureIndex] = Math.sqrt(squaredDifference / Math.max(1, sampleCount - 1)) || 1
    }

    const standardisedRows = sampleIndices.map(sampleIndex => {
        const row = new Float64Array(featureCount)
        for (let featureIndex = 0; featureIndex < featureCount; featureIndex++) {
            const value = candidates[featureIndex].vector[sampleIndex]
            row[featureIndex] = value === 0 ? 0 : (value - means[featureIndex]) / standardDeviations[featureIndex]
        }
        return row
    })
    const gram = Array.from({ length: sampleCount }, () => new Float64Array(sampleCount))
    for (let left = 0; left < sampleCount; left++) {
        for (let right = left; right < sampleCount; right++) {
            const covariance = vectorDot(standardisedRows[left], standardisedRows[right]) / Math.max(1, sampleCount - 1)
            gram[left][right] = covariance
            gram[right][left] = covariance
        }
    }

    const basis = []
    const eigenvalues = []
    const importance = new Float64Array(featureCount)
    const componentCount = Math.min(efficientPcaComponentCount, sampleCount - 1, featureCount)
    for (let component = 0; component < componentCount; component++) {
        let direction = Float64Array.from({ length: sampleCount }, (_, index) =>
            Math.sin((index + 1) * (component + 1) * 1.61803398875)
        )
        if (!orthonormalise(direction, basis)) break
        for (let iteration = 0; iteration < 80; iteration++) {
            const next = new Float64Array(sampleCount)
            for (let row = 0; row < sampleCount; row++) next[row] = vectorDot(gram[row], direction)
            if (!orthonormalise(next, basis)) break
            const alignment = Math.abs(vectorDot(next, direction))
            direction = next
            if (1 - alignment < 1e-10) break
        }
        const transformed = new Float64Array(sampleCount)
        for (let row = 0; row < sampleCount; row++) transformed[row] = vectorDot(gram[row], direction)
        const eigenvalue = vectorDot(direction, transformed)
        if (eigenvalue < 1e-9) break
        basis.push(direction)
        eigenvalues.push(eigenvalue)
        for (let featureIndex = 0; featureIndex < featureCount; featureIndex++) {
            let projection = 0
            for (let row = 0; row < sampleCount; row++) {
                projection += standardisedRows[row][featureIndex] * direction[row]
            }
            importance[featureIndex] += projection * projection
        }
    }

    const maximumImportance = Math.max(...importance, 1)
    for (let index = 0; index < importance.length; index++) importance[index] /= maximumImportance
    return { importance, eigenvalues }
}

function buildSamplePairs(sampleIndices) {
    const left = []
    const right = []
    for (let leftIndex = 0; leftIndex < sampleIndices.length; leftIndex++) {
        for (let rightIndex = leftIndex + 1; rightIndex < sampleIndices.length; rightIndex++) {
            left.push(sampleIndices[leftIndex])
            right.push(sampleIndices[rightIndex])
        }
    }
    return { left: Uint16Array.from(left), right: Uint16Array.from(right) }
}

function distanceGain(candidate, pairs, distances) {
    let gain = 0
    for (let pairIndex = 0; pairIndex < pairs.left.length; pairIndex++) {
        const leftValue = candidate.vector[pairs.left[pairIndex]]
        const rightValue = candidate.vector[pairs.right[pairIndex]]
        if (leftValue !== 0 && rightValue !== 0 && leftValue !== rightValue) {
            gain += Math.exp(-efficientDistanceDecay * distances[pairIndex])
        }
    }
    return gain / Math.max(1, pairs.left.length)
}

function addCandidateDistances(candidate, pairs, distances) {
    for (let pairIndex = 0; pairIndex < pairs.left.length; pairIndex++) {
        const leftValue = candidate.vector[pairs.left[pairIndex]]
        const rightValue = candidate.vector[pairs.right[pairIndex]]
        if (leftValue !== 0 && rightValue !== 0 && leftValue !== rightValue) distances[pairIndex]++
    }
}

function partitionRedundancy(left, right, sampleIndices) {
    let sameCount = 0
    let oppositeCount = 0
    for (const sampleIndex of sampleIndices) {
        const leftValue = left.vector[sampleIndex]
        const rightValue = right.vector[sampleIndex]
        if (leftValue === 0 || rightValue === 0) continue
        if (leftValue === rightValue) sameCount++
        else oppositeCount++
    }
    const comparableCount = sameCount + oppositeCount
    return comparableCount
        ? Math.max(sameCount, oppositeCount) / comparableCount
        : 0
}

function selectEfficientFeatures(candidates, primarySampleIndices, externalSampleIndices) {
    const primaryPairs = buildSamplePairs(primarySampleIndices)
    const externalPairs = buildSamplePairs(externalSampleIndices)
    const primaryDistances = new Uint8Array(primaryPairs.left.length)
    const externalDistances = new Uint8Array(externalPairs.left.length)
    const pca = calculatePcaImportance(candidates, primarySampleIndices)
    const selected = []
    const selectedSet = new Set()
    const characterUseCounts = new Map()

    while (selected.length < Math.min(efficientFeatureCount, candidates.length)) {
        let bestCandidateIndex = -1
        let bestScore = -Infinity
        let bestCandidate

        for (let pass = 0; pass < 2 && bestCandidateIndex < 0; pass++) {
            for (let candidateIndex = 0; candidateIndex < candidates.length; candidateIndex++) {
                if (selectedSet.has(candidateIndex)) continue
                const candidate = candidates[candidateIndex]
                if (selected.some(current =>
                    partitionRedundancy(candidate, current, primarySampleIndices) >= efficientMaximumPrimaryPartitionAgreement
                )) {
                    continue
                }
                if (pass === 0 && candidate.pair.some(character => (characterUseCounts.get(character) || 0) >= 3)) {
                    continue
                }
                const primaryGain = distanceGain(candidate, primaryPairs, primaryDistances)
                const externalGain = distanceGain(candidate, externalPairs, externalDistances)
                const score = efficientPrimaryDistanceWeight * primaryGain +
                    (1 - efficientPrimaryDistanceWeight) * externalGain +
                    pca.importance[candidateIndex] * 0.01
                if (score > bestScore ||
                    (score === bestScore && bestCandidate && isBetterEfficientCandidate(candidate, bestCandidate))) {
                    bestCandidateIndex = candidateIndex
                    bestScore = score
                    bestCandidate = candidate
                }
            }
        }

        if (bestCandidateIndex < 0) break
        selected.push(bestCandidate)
        selectedSet.add(bestCandidateIndex)
        for (const character of bestCandidate.pair) {
            characterUseCounts.set(character, (characterUseCounts.get(character) || 0) + 1)
        }
        addCandidateDistances(bestCandidate, primaryPairs, primaryDistances)
        addCandidateDistances(bestCandidate, externalPairs, externalDistances)
    }

    if (selected.length < efficientFeatureCount) {
        throw new Error(
            `Only ${selected.length}/${efficientFeatureCount} efficient features remain below the ` +
            `${efficientMaximumPrimaryPartitionAgreement * 100}% primary partition agreement limit.`
        )
    }

    let maximumPrimaryPartitionAgreement = 0
    for (let left = 0; left < selected.length; left++) {
        for (let right = left + 1; right < selected.length; right++) {
            maximumPrimaryPartitionAgreement = Math.max(
                maximumPrimaryPartitionAgreement,
                partitionRedundancy(selected[left], selected[right], primarySampleIndices)
            )
        }
    }

    return {
        selected,
        maximumPrimaryPartitionAgreement,
        pcaEigenvalues: pca.eigenvalues,
        primarySeparatedPairCount: primaryDistances.filter(distance => distance > 0).length,
        primaryPairCount: primaryDistances.length,
        primaryMeanDistance: primaryDistances.reduce((sum, distance) => sum + distance, 0) / Math.max(1, primaryDistances.length),
        externalSeparatedPairCount: externalDistances.filter(distance => distance > 0).length,
        externalPairCount: externalDistances.length,
    }
}

function buildEfficientVector(reliableReadings, selectedFeatures) {
    return selectedFeatures.map((feature) => {
        const [leftCharacter, rightCharacter] = feature.pair
        const ignoreTone = feature.ignoreTone ?? feature.label.endsWith(ignoreToneSuffix)
        const left = reliableReadings?.get(leftCharacter)
        const right = reliableReadings?.get(rightCharacter)
        return compareReliableReadings(left, right, ignoreTone)
    })
}

function summariseEfficientMultiReadingComparisons(samples, selectedFeatures) {
    const counts = { same: 0, different: 0, ambiguous: 0, missing: 0 }
    const examples = { same: [], different: [], ambiguous: [], missing: [] }
    const formatReadings = readingSet => readingSet
        ? readingSet.readings.map(reading => reading.pronunciation.replaceAll('\u001f', ''))
        : []

    for (const sample of samples) {
        for (const feature of selectedFeatures) {
            const [leftCharacter, rightCharacter] = feature.pair
            const left = sample.reliableReadings?.get(leftCharacter)
            const right = sample.reliableReadings?.get(rightCharacter)
            if ((left?.readings.length || 0) <= 1 && (right?.readings.length || 0) <= 1) continue
            const classification = classifyReliableReadingComparison(left, right)
            counts[classification]++
            if (examples[classification].length < 5) {
                examples[classification].push({
                    location: sample.title,
                    pair: feature.label,
                    left: formatReadings(left),
                    right: formatReadings(right),
                })
            }
        }
    }

    return { counts, examples }
}

function calculateModeNeighbourhoods(matrix, featureCount, names) {
    const counts = matrix.map((sourceVector) => matrix.reduce((count, targetVector) => {
        let comparableCount = 0
        let matchCount = 0
        for (let feature = 0; feature < featureCount; feature++) {
            if (sourceVector[feature] === 0 || targetVector[feature] === 0) continue
            comparableCount++
            if (sourceVector[feature] === targetVector[feature]) matchCount++
        }
        if (!comparableCount) return count
        const rawSimilarity = matchCount / comparableCount * 100
        const confidence = 1 / (featureCount / 3 / (comparableCount * comparableCount) + 1)
        return count + Number(rawSimilarity * confidence > 80)
    }, 0))
    const guangzhouIndex = names.indexOf('廣州')
    return {
        averageOver80: counts.reduce((sum, count) => sum + count, 0) / Math.max(1, counts.length),
        maximumOver80: Math.max(...counts),
        guangzhouOver80: guangzhouIndex >= 0 ? counts[guangzhouIndex] : null,
    }
}

function displayName(area) {
    return `${area.second}${area.third}` || area.first
}

function serialise(name, value) {
    return `export const ${name} = ${JSON.stringify(value)};\n`
}

function serialiseMatrix(name, matrix) {
    return `export const ${name} = [\n${matrix.map(row => `  ${JSON.stringify(row)}`).join(',\n')}\n];\n`
}

const areas = parseAreas()
const areaById = new Map(areas.map(area => [area.id, area]))
const currentAreaIds = legacyAreaIds()
const existingNameByAreaId = new Map()
const preservedRows = []

for (let index = 0; index < currentData.LOCATION_NAMES.length; index++) {
    const areaId = currentAreaIds[index]
    if (areaId === null || areaId === undefined || !areaById.has(Number(areaId))) {
        preservedRows.push({
            areaId: areaId === null || areaId === undefined ? null : Number(areaId),
            name: currentData.LOCATION_NAMES[index],
            position: currentData.LOCATION_POSITION[currentData.LOCATION_NAMES[index]],
            color: currentData.LOCATION_COLOR[currentData.LOCATION_NAMES[index]],
            vector: currentData.CHARACTERISTICS_MATRIX[index],
        })
        continue
    }
    existingNameByAreaId.set(Number(areaId), currentData.LOCATION_NAMES[index])
}

const readings = loadPronunciations(areas.map(area => area.id))
const vectorByAreaId = new Map(areas.map(area => [area.id, buildVector(readings.get(area.id))]))
const existingComparableCounts = [...existingNameByAreaId.keys()]
    .map(areaId => comparableCount(vectorByAreaId.get(areaId)))
    .filter(count => count > 0)
const historicalMinimum = Math.min(...existingComparableCounts)
const includedHistoricalAreas = areas.filter(area =>
    area.first === '歷史音' && area.isVisible && comparableCount(vectorByAreaId.get(area.id)) >= historicalMinimum
)
const selectedAreaIds = new Set([
    ...existingNameByAreaId.keys(),
    ...areas.filter(area => area.isVisible && area.first !== '歷史音').map(area => area.id),
    ...includedHistoricalAreas.map(area => area.id),
])

const generatedRows = areas
    .filter(area => selectedAreaIds.has(area.id))
    .map(area => ({
        areaId: area.id,
        name: existingNameByAreaId.get(area.id) || displayName(area),
        position: [area.longitude, area.latitude],
        color: area.color,
        vector: vectorByAreaId.get(area.id),
    }))
const rows = [...generatedRows, ...preservedRows]
const mcpCorpus = await loadMcpCorpus()
const mcpRows = mcpCorpus.cantoneseRows
const selectionCorpus = [...rows, ...mcpRows]
const simplifiedSelection = selectSimplifiedFeatureIndices(selectionCorpus.map(row => row.vector))
const simplifiedFeatureIndices = simplifiedSelection.indices

if (!mcpCorpus.mandarinRow) throw new Error(`Missing MCPDict ${mandarinMcpTitle} sample.`)
const reliableSiteReadings = loadReliableSiteReadings(generatedRows.map(row => row.areaId))
const efficientSiteSamples = generatedRows.map(row => ({
    title: row.name,
    reliableReadings: reliableSiteReadings.get(row.areaId),
}))
const efficientCharacterSelection = selectEfficientCandidateCharacters(
    efficientSiteSamples,
    mcpCorpus.cantoneseRows,
    [mcpCorpus.mandarinRow]
)
const efficientCorpus = [
    ...efficientSiteSamples,
    ...mcpCorpus.cantoneseRows,
    mcpCorpus.mandarinRow,
]
const efficientPrimarySampleIndices = [
    ...efficientSiteSamples.map((_, index) => index),
    efficientCorpus.length - 1,
]
const efficientExternalSampleIndices = mcpCorpus.cantoneseRows.map((_, index) =>
    efficientSiteSamples.length + index
)
const efficientCandidates = generateEfficientCandidates(
    efficientCharacterSelection.candidates,
    efficientCorpus,
    efficientPrimarySampleIndices
)
const efficientSelection = selectEfficientFeatures(
    efficientCandidates,
    efficientPrimarySampleIndices,
    efficientExternalSampleIndices
)
const efficientFeatures = efficientSelection.selected
const efficientPairs = efficientFeatures.map(feature => feature.label)
const efficientOutputSamples = rows.map(row => ({
    title: row.name,
    reliableReadings: row.areaId === null || row.areaId === undefined
        ? (row.name === mandarinMcpTitle ? mcpCorpus.mandarinRow.reliableReadings : null)
        : reliableSiteReadings.get(row.areaId),
}))
const efficientMatrix = efficientOutputSamples.map(sample =>
    buildEfficientVector(sample.reliableReadings, efficientFeatures)
)
const efficientMultiReadingDiagnostics = summariseEfficientMultiReadingComparisons(
    efficientOutputSamples,
    efficientFeatures
)
const efficientNeighbourhoods = calculateModeNeighbourhoods(efficientMatrix, efficientFeatures.length, rows.map(row => row.name))

for (let index = 0; index < rows.length; index++) {
    const row = rows[index]
    if (row.vector.length !== pairDefinitions.length || row.vector.some(value => ![-1, 0, 1].includes(value))) {
        throw new Error(`Invalid generated vector for ${row.name}`)
    }
    if (efficientMatrix[index].length !== efficientPairs.length ||
        efficientMatrix[index].some(value => ![-1, 0, 1].includes(value))) {
        throw new Error(`Invalid efficient vector for ${row.name}`)
    }
}

const positions = Object.fromEntries(rows.filter(row => row.position).map(row => [row.name, row.position]))
const colors = Object.fromEntries(rows.filter(row => row.color).map(row => [row.name, row.color]))
const output = [
    '// Generated by npm run generate:phonology-data. Do not edit matrix values by hand.\n',
    serialise('LOCATION_AREA_IDS', rows.map(row => row.areaId)),
    serialise('LOCATION_POSITION', positions),
    serialise('LOCATION_COLOR', colors),
    serialise('LOCATION_NAMES', rows.map(row => row.name)),
    serialise('EFFICIENT_CHARACTERISTIC_PAIRS', efficientPairs),
    serialiseMatrix('EFFICIENT_CHARACTERISTICS_MATRIX', efficientMatrix),
    serialise('SIMPLIFIED_CHARACTERISTIC_INDICES', simplifiedFeatureIndices),
    serialise('CHARACTERISTIC_PAIRS', currentData.CHARACTERISTIC_PAIRS),
    serialiseMatrix('CHARACTERISTICS_MATRIX', rows.map(row => row.vector)),
].join('\n')

const currentOutput = await readFile(outputPath, 'utf8')
if (checkOnly) {
    if (currentOutput !== output) {
        console.error('Phonology data is stale. Run npm run generate:phonology-data.')
        process.exitCode = 1
    } else {
        console.log('Phonology data is current.')
    }
} else {
    await writeFile(outputPath, output, 'utf8')
    console.log(`Generated ${rows.length} locations × ${pairDefinitions.length} pairs.`)
    console.log(`Selected ${efficientPairs.length} efficient characteristic pairs from ${efficientCharacterSelection.candidates.length} reliable characters and ${efficientCandidates.length} candidate partitions.`)
    console.log(`Efficient corpus: ${efficientSiteSamples.length} site locations + ${mcpCorpus.cantoneseRows.length} MCPDict Cantonese tables + MCPDict ${mandarinMcpTitle}.`)
    console.log(`Efficient primary separation: ${efficientSelection.primarySeparatedPairCount}/${efficientSelection.primaryPairCount} pairs, mean Hamming distance ${efficientSelection.primaryMeanDistance.toFixed(2)}.`)
    console.log(`Efficient maximum primary partition agreement: ${(efficientSelection.maximumPrimaryPartitionAgreement * 100).toFixed(2)}%.`)
    console.log(`Efficient external separation: ${efficientSelection.externalSeparatedPairCount}/${efficientSelection.externalPairCount} pairs.`)
    console.log(`Efficient PCA components: ${efficientSelection.pcaEigenvalues.length}; over-80 neighbourhood average ${efficientNeighbourhoods.averageOver80.toFixed(2)}, maximum ${efficientNeighbourhoods.maximumOver80}, Guangzhou ${efficientNeighbourhoods.guangzhouOver80}.`)
    console.log(`Efficient tone agreement range: ${Math.min(...efficientFeatures.map(feature => feature.toneAgreement)) * 100}%–${Math.max(...efficientFeatures.map(feature => feature.toneAgreement)) * 100}%.`)
    console.log(`Efficient multi-reading comparisons: ${JSON.stringify(efficientMultiReadingDiagnostics.counts)}.`)
    console.log(`Efficient multi-reading examples: ${JSON.stringify(efficientMultiReadingDiagnostics.examples)}.`)
    console.log(`Selected ${simplifiedFeatureIndices.length} simplified characteristic pairs using ${rows.length} site locations + ${mcpRows.length} MCPDict Cantonese tables.`)
    console.log(`Covered ${simplifiedSelection.distinguishablePairCount}/${simplifiedSelection.distinguishablePairCount} distinguishable corpus pairs (${simplifiedSelection.totalPairCount} total sample pairs).`)
    console.log(`Historical inclusion threshold: ${historicalMinimum} comparable pairs.`)
    console.log(`Included historical locations: ${includedHistoricalAreas.map(displayName).join(', ') || 'none'}.`)
    console.log(`Preserved matrix-only locations: ${preservedRows.map(row => row.name).join(', ') || 'none'}.`)
}
