import * as OpenCC from 'opencc-js'

export const COMMON_CONVERTER_VERSION = 'jyutdict-web-1.0.0'

const LEGACY_KEEP_CHARS = new Set(Array.from(
    '干后系历板表丑范丰刮胡回伙姜借克困里帘面蔑千秋松咸向余郁御愿云芸沄致制朱筑准辟别卜斗谷划几据卷了累朴仆曲舍胜术台吁佣折征症采吃床峰杠恒栗秘凶熏肴占苧咨粽并雇广么霉群抬涂托涌游灶皂庄岩叶坏厘尸个冲巩碱种岳于网万糍夸荐杰晒痴姹麽昵蘖唇虱宁膻厂'
))

const JPP_TO_IPA_INITIAL = {
    '': '', m: 'm', n: 'n', nj: 'ȵ', ng: 'ŋ',
    b: 'p', d: 't', g: 'k', p: 'pʰ', t: 'tʰ', k: 'kʰ', q: 'ʔ',
    bb: 'ɓ', dd: 'ɗ', s: 's', sh: 'ʃ', sr: 'ʂ', sj: 'ɕ',
    z: 'ʦ', zh: 'ʧ', zr: 'ʈʂ', zj: 'ʨ', c: 'ʦʰ', ch: 'ʧʰ',
    cr: 'ʈʂʰ', cj: 'ʨʰ', ph: 'ɸ', f: 'f', v: 'ʋ', th: 'θ',
    h: 'h', w: 'w', j: 'j', sl: 'ɬ', zl: 'tɬ', cl: 'tɬʰ', l: 'l',
    gw: 'kʷ', kw: 'kʷʰ', hw: 'hʷ', gv: 'kᵛ', kv: 'kᵛʰ',
    hv: 'hᵛ', rh: 'ɦ',
}

const JPP_TO_IPA_VOWEL = {
    i: 'i', yu: 'y', y: 'y', ur: 'ɯ', u: 'u', ee: 'e', eo: 'ɵ',
    oo: 'o', ea: 'ə', e: 'ɛ', oe: 'œ', o: 'ɔ', ae: 'æ', a: 'ɐ',
    aa: 'a', oa: 'ɒ', z: 'z', ir: 'ɿ', ew: 'ø',
    m: 'm̩', n: 'n̩', ng: 'ŋ̍',
}

const JPP_TO_IPA_CODA = {
    m: 'm', n: 'n', ng: 'ŋ', gn: 'ɲ', p: 'p', t: 't', k: 'k',
    h: 'ʔ', nn: '̃', '': '',
}

const IPA_TO_JPP_INITIAL = Object.fromEntries(
    Object.entries(JPP_TO_IPA_INITIAL).map(([key, value]) => [value, key])
)
Object.assign(IPA_TO_JPP_INITIAL, {
    kw: 'gw', kwh: 'kw', 'kʰʷ': 'kw', kv: 'gv', kvh: 'kv', 'kʰᵛ': 'kv',
    'ʋ': 'v', ts: 'z', 'tsʰ': 'c', tsh: 'c',
    'ʃ': 'sh', 'tʃ': 'zh', 'tʃʰ': 'ch', tʃh: 'ch',
    'ɕ': 'sj', 'tɕ': 'zj', 'tɕʰ': 'cj', tɕh: 'cj',
})

const IPA_TO_JPP_VOWEL = Object.fromEntries(
    Object.entries(JPP_TO_IPA_VOWEL).map(([key, value]) => [value, key])
)
Object.assign(IPA_TO_JPP_VOWEL, {
    m: 'm', n: 'n', 'ŋ': 'ng', 'ʌ': 'a', 'ɑ': 'aa', 'ɜ': 'ea',
})

const IPA_TO_JPP_CODA = {
    '': '', m: 'm', n: 'n', 'ŋ': 'ng', 'ɲ': 'gn', p: 'p', t: 't',
    k: 'k', 'ʔ': 'h', '̃': 'nn', 'm̚': 'm', 'n̚': 'n', 'ŋ̚': 'ng',
    'p̚': 'p', 't̚': 't', 'k̚': 'k',
}

const initialPattern = /^(mb?|n[jrd]?|ngg?|[bdg]{1,2}|g[hn]?|r[bdgzscrh]|[zcs][hrjl]?|[ptkvw]h?|[hqfjlrx0])([jwv]?)(?=[aeoiuymn])/
const tonePattern = /[0-9]?[0-9*]0?[0-9'ABCD]?(`\d+)?$/
const vowelTokens = Object.keys(JPP_TO_IPA_VOWEL).sort((a, b) => b.length - a.length)
const ipaVowelPattern = /([iyɯueɵoɤəɛøœɔæɐaɑɒʌɿɪʊᵃə̯a̯ɜɯ̜]+|ŋ̩|n̩|m̩|ŋ̍)/

const cleanCell = value => value == null ? '' : String(value).trim()

export function columnSpecToIndexes(spec) {
    const value = String(spec || '').replace(/\s+/g, '')
    if (!value) return []
    const parts = /[,;]/.test(value)
        ? value.split(/[,;]/).filter(Boolean)
        : Array.from(value)
    return parts.map(part => {
        if (/^\d+$/.test(part)) return Number(part)
        const upper = part.toUpperCase()
        if (!/^[A-Z]+$/.test(upper)) throw new Error(`無效欄位：${part}`)
        let index = 0
        for (const char of upper) index = index * 26 + char.charCodeAt(0) - 64
        return index - 1
    })
}

export function splitJpp(raw) {
    const syllable = String(raw || '').trim()
    const initial = syllable.match(initialPattern)?.[0] || ''
    const tone = syllable.match(tonePattern)?.[0] || ''
    const withoutTone = tone ? syllable.slice(0, -tone.length) : syllable
    const rest = withoutTone.slice(initial.length)
    const codaMatch = rest.match(/([aoreiwuy])(nng|ng|n|m|p|t|k|h)$/)
    const coda = codaMatch?.[2] || ''
    const nuclei = coda ? rest.slice(0, -coda.length) : rest
    return { initial, nuclei, coda, tone }
}

function splitIpa(raw) {
    let syllable = String(raw || '').trim()
    const tone = syllable.match(/(\d*)$/)?.[0] || ''
    if (tone) syllable = syllable.slice(0, -tone.length)
    const coda = syllable.match(/(m̚?|n̚?|ŋ̚?|p̚?|t̚?|k̚?|ʔ)$/)?.[0] || ''
    if (coda) syllable = syllable.slice(0, -coda.length)
    const matches = Array.from(syllable.matchAll(new RegExp(ipaVowelPattern.source, 'gu')))
    const nuclei = matches.at(-1)?.[0] || (['m', 'n', 'ŋ'].includes(coda) ? coda : '')
    const effectiveCoda = nuclei === coda ? '' : coda
    if (!nuclei) throw new Error(`IPA 元音不存在：${raw}`)
    return {
        initial: syllable.slice(0, syllable.length - nuclei.length),
        nuclei,
        coda: effectiveCoda,
        tone,
    }
}

function normJpp(parts) {
    let { initial, nuclei, coda } = parts
    if (initial === '0') initial = ''
    if (initial && nuclei.length >= 2) {
        if ((nuclei === 'ie' && coda) || nuclei === 'ieu') nuclei = nuclei.slice(1)
        if (initial.endsWith('j') && (
            ['ia', 'ie', 'io'].includes(nuclei.slice(0, 2)) || (nuclei === 'io' && coda)
        )) nuclei = nuclei.slice(1)
        if (initial.endsWith('w') && (
            ['ua', 'ue', 'uo'].includes(nuclei.slice(0, 2)) || (nuclei === 'ui' && coda)
        )) nuclei = nuclei.slice(1)
    }
    return { initial, nuclei, coda }
}

function termObject(rule) {
    return {
        beforeInitial: String(rule[0] ?? ''),
        beforeVowel: String(rule[1] ?? ''),
        beforeCoda: String(rule[2] ?? ''),
        afterInitial: String(rule[3] ?? ''),
        afterVowel: String(rule[4] ?? ''),
        afterCoda: String(rule[5] ?? ''),
        important: rule.length === 7 && rule[6] === '!',
    }
}

function selectRules(bundle, locale) {
    const append = Array.isArray(bundle.appendProfiles) ? bundle.appendProfiles.map(String) : ['0', '1']
    const selected = {}
    for (const name of ['i2i', 'i2j', 'j2i', 'j2j']) {
        const source = bundle.rules?.[name] || {}
        selected[name] = [
            ...(source[locale] || []),
            ...append.flatMap(profile => source[profile] || []),
        ].map(termObject)
    }
    selected.toneJ2i = bundle.tones?.j2i?.[locale] || {}
    selected.toneJ2j = bundle.tones?.j2j?.[locale] || {}
    selected.toneI2j = Object.fromEntries(
        Object.entries(selected.toneJ2i).map(([groupName, group]) => [
            groupName,
            Object.fromEntries(
                Object.entries(group || {}).map(([key, value]) => [String(value), String(key)])
            ),
        ])
    )
    return selected
}

function getVowelsIpa(value) {
    if (['ǀ', 'ǂ', 'ǀʷ', 'ǂʷ'].includes(value)) return value.replace('ʷ', 'w')
    let remaining = value
    const reversed = []
    while (remaining) {
        const token = vowelTokens.find(candidate => remaining.endsWith(candidate))
        if (!token) throw new Error(`元音不存在：${remaining}`)
        reversed.push(JPP_TO_IPA_VOWEL[token])
        remaining = remaining.slice(0, -token.length)
    }
    return reversed.reverse().join('')
}

function getVowelsJpp(value) {
    const direct = IPA_TO_JPP_VOWEL[value]
    if (direct !== undefined) return direct
    const result = []
    for (const char of Array.from(value)) {
        if (IPA_TO_JPP_VOWEL[char] === undefined) throw new Error(`IPA 元音不存在：${value}`)
        result.push(IPA_TO_JPP_VOWEL[char])
    }
    return result.join('')
}

function pronTranslate(rules, input, direction) {
    let initial
    let nuclei
    let coda
    for (const rule of rules) {
        if (rule.beforeInitial !== '*' &&
            rule.beforeInitial !== input.initial &&
            (direction !== null || rule.beforeInitial !== initial)) continue
        if (rule.beforeVowel !== '*' &&
            rule.beforeVowel !== input.nuclei &&
            (direction !== null || rule.beforeVowel !== nuclei)) continue
        if (rule.beforeCoda !== '*' &&
            rule.beforeCoda !== input.coda &&
            (direction !== null || rule.beforeCoda !== coda)) continue
        if (rule.afterInitial !== '*' && (rule.important || initial === undefined)) initial = rule.afterInitial
        if (rule.afterVowel !== '*' && (rule.important || nuclei === undefined)) nuclei = rule.afterVowel
        if (rule.afterCoda !== '*' && (rule.important || coda === undefined)) coda = rule.afterCoda
    }
    if (direction === null) {
        return {
            initial: initial ?? input.initial,
            nuclei: nuclei ?? input.nuclei,
            coda: coda ?? input.coda,
        }
    }
    if (direction === 'ipa') {
        if (initial === undefined) {
            if (JPP_TO_IPA_INITIAL[input.initial] !== undefined) {
                initial = JPP_TO_IPA_INITIAL[input.initial]
            } else if (input.initial.endsWith('w') &&
                       JPP_TO_IPA_INITIAL[input.initial.slice(0, -1)] !== undefined) {
                initial = `${JPP_TO_IPA_INITIAL[input.initial.slice(0, -1)]}ʷ`
            } else {
                throw new Error(`聲母不存在：${input.initial}`)
            }
        }
        return {
            initial,
            nuclei: nuclei ?? getVowelsIpa(input.nuclei),
            coda: coda ?? (JPP_TO_IPA_CODA[input.coda] ?? (() => { throw new Error(`韻尾不存在：${input.coda}`) })()),
        }
    }
    if (initial === undefined && IPA_TO_JPP_INITIAL[input.initial] === undefined) {
        throw new Error(`IPA 聲母不存在：${input.initial}`)
    }
    return {
        initial: initial ?? IPA_TO_JPP_INITIAL[input.initial],
        nuclei: nuclei ?? getVowelsJpp(input.nuclei),
        coda: coda ?? (IPA_TO_JPP_CODA[input.coda] ?? (() => { throw new Error(`IPA 韻尾不存在：${input.coda}`) })()),
    }
}

function toneTranslate(groups, checked, tone, skippable = false) {
    const rules = groups?.[checked ? '入聲' : '舒聲'] || {}
    if (Object.prototype.hasOwnProperty.call(rules, tone)) return String(rules[tone])
    if (skippable) return tone
    if (!tone) throw new Error('調號爲空')
    throw new Error(`調號不存在：${tone}`)
}

function readSyllables(values, separator) {
    const elements = values.map(cleanCell)
    if (elements.every(value => value === '') || elements.join('') === '_') return { valid: true, values: [] }
    if (elements[0] === '0.0') elements[0] = ''
    const split = elements.map(value => value.split(separator))
    const counts = split.filter(values => values.length > 1).map(values => values.length - 1)
    if (!counts.length) return { valid: true, values: [elements.join('')] }
    const loops = Math.min(...counts)
    const valid = loops === Math.max(...counts)
    const padded = split.map(values => [
        ...values,
        ...Array.from({ length: loops - values.length + 1 }, () => values.at(-1)),
    ])
    return {
        valid,
        values: Array.from({ length: loops + 1 }, (_, index) =>
            padded.map(values => values[index]).join('')
        ),
    }
}

function mergeDuplicateGroups(groups) {
    if (groups.length < 2) return groups
    const merged = groups.map(group => ({
        ...group,
        prons: [...group.prons],
        ipas: [...group.ipas],
        sourceRows: [...group.sourceRows],
    }))
    for (let i = 0; i < merged.length - 1; i += 1) {
        if (!merged[i].prons.length) continue
        for (let j = i + 1; j < merged.length; j += 1) {
            if (!merged[j].prons.length) continue
            const left = merged[i]
            const right = merged[j]
            const overlaps = left.prons.some(pron => right.prons.includes(pron))
            const equal = (overlaps && left.mean && left.mean === right.mean) ||
                left.prons.every(pron => right.prons.includes(pron)) ||
                right.prons.every(pron => left.prons.includes(pron))
            if (!equal) continue
            let mean = ''
            if (left.mean && right.mean) {
                if (left.prons.length > 1 || right.prons.length > 1) continue
                if (merged.length > 2) mean = left.mean === right.mean ? left.mean : `${left.mean}；${right.mean}`
            } else {
                mean = `${left.mean}${right.mean}`
            }
            merged[i] = {
                ...left,
                prons: [...new Set([...left.prons, ...right.prons])],
                ipas: [...new Set([...left.ipas, ...right.ipas])],
                mean,
                sourceRows: [...new Set([...left.sourceRows, ...right.sourceRows])].sort((a, b) => a - b),
            }
            merged[j].prons = []
        }
    }
    return merged.filter(group => group.prons.length)
}

function normalizeGroups(entries, rules, hasJpp, hasIpa, warnings) {
    for (const entry of entries) {
        for (const group of entry.groups) {
            try {
                if (hasJpp && !hasIpa) {
                    const transformed = group.prons
                        .map(splitJpp)
                        .sort((a, b) => `${a.nuclei}${a.coda}`.localeCompare(`${b.nuclei}${b.coda}`))
                        .map(parts => {
                            const normalized = normJpp(pronTranslate(rules.j2j, parts, null))
                            const checked = ['p', 't', 'k', 'h'].includes(parts.coda)
                            const tone = toneTranslate(rules.toneJ2j, checked, parts.tone, true)
                            const ipaTone = toneTranslate(rules.toneJ2i, checked, tone)
                            const ipa = pronTranslate(rules.j2i, normalized, 'ipa')
                            return {
                                pron: `${normalized.initial}${normalized.nuclei}${normalized.coda}${tone}`,
                                ipa: `${ipa.initial}${ipa.nuclei}${ipa.coda}${ipaTone}`,
                            }
                        })
                    group.prons = transformed.map(value => value.pron)
                    group.ipas = transformed.map(value => value.ipa)
                } else if (hasJpp && hasIpa) {
                    group.prons = group.prons
                        .map(splitJpp)
                        .sort((a, b) => `${a.nuclei}${a.coda}`.localeCompare(`${b.nuclei}${b.coda}`))
                        .map(parts => {
                            const normalized = normJpp(pronTranslate(rules.j2j, parts, null))
                            const checked = ['p', 't', 'k', 'h'].includes(parts.coda)
                            const tone = toneTranslate(rules.toneJ2j, checked, parts.tone, true)
                            return `${normalized.initial}${normalized.nuclei}${normalized.coda}${tone}`
                        })
                } else if (hasIpa) {
                    const transformed = group.ipas
                        .map(splitIpa)
                        .sort((a, b) => `${a.nuclei}${a.coda}`.localeCompare(`${b.nuclei}${b.coda}`))
                        .map(parts => {
                            const normalized = pronTranslate(rules.i2i, parts, null)
                            const jpp = normJpp(pronTranslate(rules.i2j, normalized, 'jpp'))
                            const checked = ['p', 't', 'k', 'ʔ'].includes(parts.coda)
                            const tone = toneTranslate(rules.toneI2j, checked, parts.tone)
                            return {
                                pron: `${jpp.initial}${jpp.nuclei}${jpp.coda}${tone}`,
                                ipa: `${normalized.initial}${normalized.nuclei}${normalized.coda}${parts.tone}`,
                            }
                        })
                    group.prons = transformed.map(value => value.pron)
                    group.ipas = transformed.map(value => value.ipa)
                }
            } catch (error) {
                warnings.push(`第 ${group.sourceRows[0]} 行「${entry.chara}」未能轉寫：${error.message}`)
                if (!group.prons.length) group.prons = Array.from({ length: group.ipas.length }, () => '')
                if (!group.ipas.length) group.ipas = Array.from({ length: group.prons.length }, () => '')
            }
        }
    }
}

function applyLegacyS2t(entries, keepCollision, convertMeanings) {
    const converter = OpenCC.Converter({ from: 'cn', to: 't' })
    const index = new Map(entries.map((entry, position) => [entry.chara, position]))
    for (const entry of entries) {
        const source = entry.chara
        const target = converter(source)
        if (target === source || LEGACY_KEEP_CHARS.has(source)) continue
        if (index.has(target)) {
            if (!keepCollision) entry.skippedByS2t = true
            continue
        }
        entry.chara = target
        index.delete(source)
        index.set(target, entry)
    }
    if (convertMeanings) {
        for (const entry of entries) {
            for (const group of entry.groups) group.mean = converter(group.mean)
        }
    }
}

function parseCharacter(value, rowNumber, warnings) {
    let chara = cleanCell(value).replaceAll('？', '')
    const chars = Array.from(chara)
    if (chars.length > 1) {
        warnings.push(`第 ${rowNumber} 行字頭含多字，已取「${chars[0]}」`)
        chara = chars[0]
    }
    return chara
}

function parseMeaning(values) {
    let meaning = values.map(cleanCell).filter(Boolean).join('｜').replaceAll('\n', '\\n')
    if (/[。；]$/.test(meaning)) meaning = meaning.slice(0, -1)
    return meaning.trim()
}

export function convertGrid(rows, config, ruleBundle) {
    const warnings = []
    const entries = []
    const index = new Map()
    const separator = config.separator || '/'
    const charColumn = columnSpecToIndexes(config.charColumn)[0]
    const pronColumns = columnSpecToIndexes(config.pronColumns)
    const secondaryPronColumns = columnSpecToIndexes(config.secondaryPronColumns)
    const meaningColumns = columnSpecToIndexes(config.meaningColumns)
    const ipaColumns = columnSpecToIndexes(config.ipaColumns)
    const hasJpp = pronColumns.length > 0
    const hasIpa = ipaColumns.length > 0
    if (charColumn == null || (!hasJpp && !hasIpa)) {
        throw new Error('字頭欄及 J++/IPA 至少一組讀音欄必須填寫')
    }
    let skippedRows = 0
    const startRow = Math.max(2, Number(config.startRow || 2))
    for (let indexInSheet = startRow - 1; indexInSheet < rows.length; indexInSheet += 1) {
        const row = rows[indexInSheet] || []
        const sourceRow = indexInSheet + 1
        const chara = parseCharacter(row[charColumn], sourceRow, warnings)
        if (!chara || chara === '□') {
            skippedRows += 1
            continue
        }
        const meaning = parseMeaning(meaningColumns.map(column => row[column]))
        const ipaResult = readSyllables(ipaColumns.map(column => row[column]), separator)
        const pronMain = readSyllables(pronColumns.map(column => row[column]), separator)
        const pronSecondary = readSyllables(secondaryPronColumns.map(column => row[column]), separator)
        if (!ipaResult.valid || !pronMain.valid || !pronSecondary.valid) {
            warnings.push(`第 ${sourceRow} 行分隔符數目不匹配`)
        }
        const prons = [...pronMain.values, ...pronSecondary.values]
        if (!prons.length && !ipaResult.values.length) {
            skippedRows += 1
            continue
        }
        const group = { prons, ipas: ipaResult.values, mean: meaning, sourceRows: [sourceRow] }
        if (!index.has(chara)) {
            index.set(chara, entries.length)
            entries.push({ chara, groups: [group], skippedByS2t: false })
        } else {
            entries[index.get(chara)].groups.push(group)
        }
    }
    for (const entry of entries) {
        if (hasJpp) entry.groups = mergeDuplicateGroups(entry.groups)
    }
    const rules = selectRules(ruleBundle, config.localeName || '')
    normalizeGroups(entries, rules, hasJpp, hasIpa, warnings)
    if (config.s2tMode !== 'off') {
        applyLegacyS2t(entries, Boolean(config.keepS2tCollision), Boolean(config.convertMeanings))
    }
    if (config.removeRedundantMeaning) {
        for (const entry of entries) {
            if (entry.groups.length === 1) entry.groups[0].mean = ''
        }
    }

    const output = []
    let displayOrder = 1
    for (const entry of entries) {
        if (entry.skippedByS2t) {
            skippedRows += entry.groups.reduce((sum, group) => sum + group.sourceRows.length, 0)
            continue
        }
        const groups = config.sortPronunciations
            ? [...entry.groups].sort((a, b) => (a.prons[0] || '').localeCompare(b.prons[0] || ''))
            : entry.groups
        let altGroup = 1
        for (const group of groups) {
            const count = Math.max(group.prons.length, group.ipas.length)
            for (let item = 0; item < count; item += 1) {
                const pron = splitJpp(group.prons[item] || '')
                output.push({
                    row_no: output.length + 1,
                    display_order: displayOrder,
                    chara: entry.chara,
                    initial: pron.initial,
                    nuclei: pron.nuclei,
                    coda: pron.coda,
                    tone: pron.tone,
                    ipa: group.ipas[item] || '',
                    note: group.mean,
                    alt_group: count === 1 ? null : altGroup,
                    source_row: group.sourceRows[0] || null,
                })
                displayOrder += 1
            }
            altGroup += 1
        }
    }
    const characters = new Set(output.map(row => row.chara))
    const syllables = new Set(output.map(row =>
        [row.initial, row.nuclei, row.coda, row.tone].join('\u001f')
    ))
    const toneless = new Set(output.map(row =>
        [row.initial, row.nuclei, row.coda].join('\u001f')
    ))
    return {
        rows: output,
        warnings: warnings.slice(0, 500),
        stats: {
            entry_count: output.length,
            character_count: characters.size,
            syllable_count: syllables.size,
            toneless_syllable_count: toneless.size,
            skipped_row_count: skippedRows,
        },
    }
}
