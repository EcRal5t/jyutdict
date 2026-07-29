import * as OpenCC from 'opencc-js'

export const COMMON_CONVERTER_VERSION = 'jyutdict-web-1.1.0'

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
const tonePattern = /[0-9]?[0-9*]0?[0-9*'ABCD]?(`\d+)?$/
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

export function splitIpa(raw) {
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

function termObject(rule, meta = null) {
    return {
        beforeInitial: String(rule[0] ?? ''),
        beforeVowel: String(rule[1] ?? ''),
        beforeCoda: String(rule[2] ?? ''),
        afterInitial: String(rule[3] ?? ''),
        afterVowel: String(rule[4] ?? ''),
        afterCoda: String(rule[5] ?? ''),
        important: rule.length === 7 && rule[6] === '!',
        meta,
    }
}

export function selectRuleProfiles(bundle, profiles) {
    const orderedProfiles = [...new Set((profiles || []).map(value => String(value).trim()).filter(Boolean))]
    const primaryProfile = orderedProfiles[0] || ''
    const selected = {}
    for (const name of ['i2i', 'i2j', 'j2i', 'j2j']) {
        const source = bundle.rules?.[name] || {}
        selected[name] = orderedProfiles.flatMap(profile =>
            (source[profile] || []).map((rule, index) =>
                termObject(rule, { book: name, profile, index: index + 1 })
            )
        )
    }
    selected.toneJ2i = bundle.tones?.j2i?.[primaryProfile] || {}
    selected.toneJ2j = bundle.tones?.j2j?.[primaryProfile] || {}
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

export function ruleProfileNames(bundle) {
    const names = new Set(
        (Array.isArray(bundle?.appendProfiles) ? bundle.appendProfiles : []).map(String)
    )
    for (const book of ['i2i', 'i2j', 'j2i', 'j2j']) {
        for (const profile of Object.keys(bundle?.rules?.[book] || {})) names.add(profile)
    }
    for (const book of ['j2i', 'j2j']) {
        for (const profile of Object.keys(bundle?.tones?.[book] || {})) names.add(profile)
    }
    return [...names]
}

export function defaultRuleProfiles(bundle, primaryProfile = '') {
    const primary = String(primaryProfile || '').trim()
    const append = Array.isArray(bundle?.appendProfiles)
        ? bundle.appendProfiles.map(String)
        : ['0', '1']
    const available = new Set(ruleProfileNames(bundle))
    return [...new Set([primary, ...append, '999'].filter(Boolean))]
        .filter(profile => profile === primary || available.has(profile))
}

function selectRules(bundle, config) {
    const explicit = Array.isArray(config.ruleProfiles)
        ? [...new Set(config.ruleProfiles.map(value => String(value).trim()).filter(Boolean))]
        : null
    const profiles = explicit ?? defaultRuleProfiles(bundle, config.localeName)
    if (!profiles.length) throw new Error('至少選擇一個規則組')
    return selectRuleProfiles(bundle, profiles)
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

function pronTranslate(rules, input, direction, trace = null) {
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
        const changes = []
        if (rule.afterInitial !== '*' && (rule.important || initial === undefined)) {
            changes.push({ field: '聲母', from: initial ?? input.initial, to: rule.afterInitial })
            initial = rule.afterInitial
        }
        if (rule.afterVowel !== '*' && (rule.important || nuclei === undefined)) {
            changes.push({ field: '韻核', from: nuclei ?? input.nuclei, to: rule.afterVowel })
            nuclei = rule.afterVowel
        }
        if (rule.afterCoda !== '*' && (rule.important || coda === undefined)) {
            changes.push({ field: '韻尾', from: coda ?? input.coda, to: rule.afterCoda })
            coda = rule.afterCoda
        }
        if (trace && (changes.length || rule.meta)) {
            trace.push({
                type: 'segment',
                ...rule.meta,
                force: rule.important,
                changes,
            })
        }
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

function isCheckedCoda(coda, ipa = false) {
    return ipa
        ? ['p', 't', 'k', 'ʔ'].includes(coda)
        : ['p', 't', 'k', 'h'].includes(coda)
}

function traceTone(trace, book, profile, category, before, after) {
    if (!trace || before === after) return
    trace.push({
        type: 'tone',
        book,
        profile,
        category,
        changes: [{ field: '聲調', from: before, to: after }],
    })
}

export function convertRuleSyllable(bundle, profiles, direction, raw) {
    const input = String(raw || '').trim()
    if (!input) throw new Error('輸入音節為空')
    const orderedProfiles = [...new Set((profiles || []).map(value => String(value).trim()).filter(Boolean))]
    if (!orderedProfiles.length) throw new Error('至少選擇一個規則組')
    const primary = orderedProfiles[0]
    const rules = selectRuleProfiles(bundle, orderedProfiles)
    const trace = []
    let result = ''

    if (direction === 'j2j' || direction === 'j2i') {
        const source = splitJpp(input)
        const normalized = pronTranslate(rules.j2j, source, null, trace)
        const checked = isCheckedCoda(source.coda)
        const category = checked ? '入聲' : '舒聲'
        const normalizedTone = toneTranslate(rules.toneJ2j, checked, source.tone, true)
        traceTone(trace, 'tone-j2j', primary, category, source.tone, normalizedTone)
        if (direction === 'j2j') {
            result = `${normalized.initial}${normalized.nuclei}${normalized.coda}${normalizedTone}`
        } else {
            const ipa = pronTranslate(rules.j2i, normalized, 'ipa', trace)
            const ipaTone = toneTranslate(rules.toneJ2i, checked, normalizedTone)
            traceTone(trace, 'tone-j2i', primary, category, normalizedTone, ipaTone)
            result = `${ipa.initial}${ipa.nuclei}${ipa.coda}${ipaTone}`
        }
    } else if (direction === 'i2i' || direction === 'i2j') {
        const source = splitIpa(input)
        const normalized = pronTranslate(rules.i2i, source, null, trace)
        if (direction === 'i2i') {
            result = `${normalized.initial}${normalized.nuclei}${normalized.coda}${source.tone}`
        } else {
            const jpp = pronTranslate(rules.i2j, normalized, 'jpp', trace)
            const checked = isCheckedCoda(source.coda, true)
            const category = checked ? '入聲' : '舒聲'
            const tone = toneTranslate(rules.toneI2j, checked, source.tone)
            traceTone(trace, 'tone-i2j', primary, category, source.tone, tone)
            result = `${jpp.initial}${jpp.nuclei}${jpp.coda}${tone}`
        }
    } else {
        throw new Error(`未知轉換方向：${direction}`)
    }
    return { input, output: result, trace }
}

export function convertRuleText(bundle, profiles, direction, text) {
    const tokens = String(text || '').split(/[\s,，;；]+/u).map(value => value.trim()).filter(Boolean)
    return tokens.map(input => {
        try {
            return { ok: true, ...convertRuleSyllable(bundle, profiles, direction, input) }
        } catch (error) {
            return { ok: false, input, output: '', error: error?.message || String(error), trace: [] }
        }
    })
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

function pairReadings(prons, ipas) {
    const count = Math.max(prons.length, ipas.length)
    const valueAt = (values, index) => {
        if (values.length === 1 && count > 1) return values[0]
        return values[index] || ''
    }
    return Array.from({ length: count }, (_, index) => ({
        pron: valueAt(prons, index),
        ipa: valueAt(ipas, index),
    }))
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
            const readings = []
            const seenReadings = new Set()
            for (const reading of [
                ...pairReadings(left.prons, left.ipas),
                ...pairReadings(right.prons, right.ipas),
            ]) {
                const key = `${reading.pron}\u001f${reading.ipa}`
                if (seenReadings.has(key)) continue
                const incomplete = readings.findIndex(candidate =>
                    candidate.pron === reading.pron && !candidate.ipa && reading.ipa
                )
                if (incomplete >= 0) {
                    seenReadings.delete(`${readings[incomplete].pron}\u001f`)
                    readings[incomplete] = reading
                    seenReadings.add(key)
                    continue
                }
                if (!reading.ipa && readings.some(candidate =>
                    candidate.pron === reading.pron && candidate.ipa
                )) continue
                seenReadings.add(key)
                readings.push(reading)
            }
            merged[i] = {
                ...left,
                prons: readings.map(reading => reading.pron),
                ipas: readings.map(reading => reading.ipa),
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
                            const normalized = pronTranslate(rules.j2j, parts, null)
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
                    const transformed = pairReadings(group.prons, group.ipas)
                        .map(reading => {
                            if (!reading.pron) throw new Error('J++ 讀音爲空')
                            const parts = splitJpp(reading.pron)
                            if (!parts.nuclei) throw new Error(`J++ 韻核不存在：${reading.pron}`)
                            const normalized = pronTranslate(rules.j2j, parts, null)
                            const checked = ['p', 't', 'k', 'h'].includes(parts.coda)
                            const tone = toneTranslate(rules.toneJ2j, checked, parts.tone, true)
                            return {
                                pron: `${normalized.initial}${normalized.nuclei}${normalized.coda}${tone}`,
                                ipa: reading.ipa,
                            }
                        })
                        .sort((a, b) => {
                            const left = splitJpp(a.pron)
                            const right = splitJpp(b.pron)
                            return `${left.nuclei}${left.coda}`.localeCompare(`${right.nuclei}${right.coda}`)
                        })
                    group.prons = transformed.map(value => value.pron)
                    group.ipas = transformed.map(value => value.ipa)
                } else if (hasIpa) {
                    const transformed = group.ipas
                        .map(splitIpa)
                        .sort((a, b) => `${a.nuclei}${a.coda}`.localeCompare(`${b.nuclei}${b.coda}`))
                        .map(parts => {
                            const normalized = pronTranslate(rules.i2i, parts, null)
                            const jpp = pronTranslate(rules.i2j, normalized, 'jpp')
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
    const rules = selectRules(ruleBundle, config)
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
                if (!pron.nuclei) {
                    const sourceRow = group.sourceRows[0] || null
                    throw new Error(
                        `第 ${sourceRow || '未知'} 行「${entry.chara}」的 J++ 未能解析出韻核`
                    )
                }
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
