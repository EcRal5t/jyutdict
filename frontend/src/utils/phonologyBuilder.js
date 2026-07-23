import { splitJpp } from './commonConverter.js'
import { blake2bBytes, normaliseCheckedFinal } from './phonologyDisplay.js'

export const PHONOLOGY_GENERATOR_VERSION = 'jyutdict-phonology-rebuild-1.1.0'

const MC_INITIALS = Array.from('幫滂並明端透定泥來知徹澄孃精清從心邪莊初崇生俟章昌常書船日見溪羣疑影曉匣云以')
const MC_RHYMES = Array.from('東冬鍾江支脂之微魚虞模齊祭泰佳皆夬灰咍廢眞臻文欣元魂痕寒刪山仙先蕭宵肴豪歌麻陽唐庚耕清青蒸登尤侯幽侵覃談鹽添咸銜嚴凡')
const descriptionPattern = new RegExp(`^([${MC_INITIALS.join('')}])([開合])?([一二三四])?([AB])?([${MC_RHYMES.join('')}])([平上去入])$`, 'u')

const GROUPS = {
    幫: '幫滂並明', 端: '端透定泥', 知: '知徹澄孃', 來: '來',
    精: '精清從心邪', 莊: '莊初崇生俟', 章: '章昌常書船',
    日: '日', 見: '見溪羣疑', 影: '影云曉匣', 以: '以',
}
const INITIAL_TO_GROUP = Object.fromEntries(
    Object.entries(GROUPS).flatMap(([group, initials]) => Array.from(initials).map(initial => [initial, group]))
)
const VOICING = {
    ...Object.fromEntries(Array.from('幫端知精心莊生章書見影曉').map(value => [value, '全清'])),
    ...Object.fromEntries(Array.from('滂透徹清初昌溪').map(value => [value, '次清'])),
    ...Object.fromEntries(Array.from('並定澄從邪崇俟常船羣匣').map(value => [value, '全濁'])),
    ...Object.fromEntries(Array.from('明泥孃來日疑云以').map(value => [value, '次濁'])),
}
const SHE = {
    通: '東冬鍾', 江: '江', 止: '支脂之微', 遇: '魚虞模',
    蟹: '齊佳皆灰咍祭泰夬廢', 臻: '眞臻文欣元魂痕', 山: '寒刪山先仙',
    效: '蕭宵肴豪', 果: '歌', 假: '麻', 宕: '唐陽', 梗: '庚耕清青',
    曾: '登蒸', 流: '侯尤幽', 深: '侵', 咸: '覃談鹽添咸銜嚴凡',
}
const RHYME_TO_SHE = Object.fromEntries(
    Object.entries(SHE).flatMap(([she, rhymes]) => Array.from(rhymes).map(rhyme => [rhyme, she]))
)
const NEUTRAL_RHYMES = new Set(Array.from('東冬鍾江虞模尤幽'))
const OPEN_RHYMES = new Set(Array.from('咍痕欣嚴之魚臻蕭宵肴豪侯侵覃談鹽添咸銜'))
const CLOSED_RHYMES = new Set(Array.from('灰魂文凡'))
const DIVISIONS = {
    一: '冬模泰咍灰痕魂寒豪唐登侯覃談',
    二: '江佳皆夬刪山肴耕咸銜',
    三: '鍾支脂之微魚虞祭廢眞臻欣元文仙宵陽清蒸尤幽侵鹽嚴凡',
    四: '齊先蕭青添',
}
const RHYME_TO_DIVISION = Object.fromEntries(
    Object.entries(DIVISIONS).flatMap(([division, rhymes]) =>
        Array.from(rhymes).map(rhyme => [rhyme, division])
    )
)
const FEATURE_LABELS = {
    openness: '呼', she: '攝', division: '等', chongniu: '重紐',
    rhyme: '韻', initial: '聲母', group: '組', voicing: '清濁',
    tone: '調', modern_initial: '現聲', modern_final: '現韻',
}
const FEATURE_COSTS = {
    openness: 0, division: 0.05, chongniu: 0.05, she: 0.2,
    group: 0.05, voicing: 0.05, rhyme: 1.4, initial: 0.6,
    tone: 0.05, modern_initial: 0.6, modern_final: 1.2,
}
const DIVISION_ORDER = { 一: 0, 二: 1, 三: 2, 四: 3 }
const CHONGNIU_ORDER = { A: 0, B: 1, 無: 2 }

const sum = values => values.reduce((total, value) => total + value, 0)
const unique = values => [...new Set(values)]
const compareText = (left, right) => left < right ? -1 : left > right ? 1 : 0

function parsePosition(description) {
    const match = String(description || '').match(descriptionPattern)
    if (!match) return null
    const [, initial, openness = '', division = '', chongniu = '', rhyme, tone] = match
    let effectiveOpenness = openness
    if (!effectiveOpenness && NEUTRAL_RHYMES.has(rhyme)) effectiveOpenness = '中'
    if (!effectiveOpenness && OPEN_RHYMES.has(rhyme)) effectiveOpenness = '開'
    if (!effectiveOpenness && CLOSED_RHYMES.has(rhyme)) effectiveOpenness = '合'
    return {
        description, initial, openness, division, chongniu, rhyme, tone,
        group: INITIAL_TO_GROUP[initial] || '',
        voicing: VOICING[initial] || '',
        she: RHYME_TO_SHE[rhyme] || '',
        effectiveOpenness,
        effectiveDivision: division || RHYME_TO_DIVISION[rhyme] || '',
    }
}

function normaliseCheckedCoda(final) {
    return normaliseCheckedFinal(final)
}

function feature(observation, name) {
    const position = observation.position
    const values = {
        openness: position.effectiveOpenness,
        she: position.she,
        division: position.effectiveDivision,
        chongniu: position.chongniu,
        rhyme: position.rhyme,
        initial: position.initial,
        group: position.group,
        voicing: position.voicing,
        tone: position.tone,
        modern_initial: observation.modernInitial,
        modern_final: normaliseCheckedCoda(observation.modernFinal),
    }
    const value = values[name] || '無'
    return name === 'openness' && value === '中' ? '開' : value
}

function weightedCounts(observations, display = false) {
    const counts = new Map()
    for (const observation of observations) {
        const outcome = display ? observation.displayOutcome : observation.outcome
        counts.set(outcome, (counts.get(outcome) || 0) + observation.weight)
    }
    return counts
}

function entropy(observations) {
    const counts = [...weightedCounts(observations).values()]
    const total = sum(counts)
    if (total <= 0) return 0
    return -sum(counts.filter(Boolean).map(count => {
        const probability = count / total
        return probability * Math.log2(probability)
    }))
}

const weight = observations => sum(observations.map(observation => observation.weight))

function purity(observations) {
    const counts = [...weightedCounts(observations).values()]
    const total = sum(counts)
    return total ? Math.max(0, ...counts) / total : 0
}

function modes(observations, limit = 4, display = false) {
    const ranked = [...weightedCounts(observations, display).entries()]
        .sort((left, right) => right[1] - left[1] || compareText(left[0], right[0]))
    if (!ranked.length) return []
    const floor = ranked[0][1] * 0.2
    return ranked.filter(([, count]) => count >= floor).slice(0, limit).map(([outcome]) => outcome)
}

function allOutcomes(observations) {
    return [...weightedCounts(observations).entries()]
        .sort((left, right) => right[1] - left[1] || compareText(left[0], right[0]))
        .map(([outcome]) => outcome)
}

function sameArray(left, right) {
    return left.length === right.length && left.every((value, index) => value === right[index])
}

function mergeInformationLoss(left, right) {
    const leftWeight = weight(left)
    const rightWeight = weight(right)
    const total = leftWeight + rightWeight
    if (!total) return Number.POSITIVE_INFINITY
    return entropy([...left, ...right]) -
        (leftWeight / total * entropy(left) + rightWeight / total * entropy(right))
}

function baseFamily(component, observations) {
    if (!observations.length) return ''
    if (component === 'initial') return observations[0].position.group
    if (component === 'final') return observations[0].position.she
    return ''
}

function groupBaseObservations(grouped, baseOrder, component) {
    const orderIndex = new Map(baseOrder.map((base, index) => [base, index]))
    const clusters = baseOrder
        .filter(base => grouped.has(base))
        .map(base => ({ bases: [base], rows: [...grouped.get(base)] }))
    while (true) {
        let best = null
        for (let left = 0; left < clusters.length; left += 1) {
            for (let right = left + 1; right < clusters.length; right += 1) {
                const a = clusters[left]
                const b = clusters[right]
                if (a.bases.length + b.bases.length > 5) continue
                if (baseFamily(component, a.rows) !== baseFamily(component, b.rows)) continue
                if (!sameArray(modes(a.rows, 1), modes(b.rows, 1))) continue
                const loss = mergeInformationLoss(a.rows, b.rows)
                if (!best || loss < best.loss) best = { loss, left, right }
            }
        }
        if (!best || best.loss > 0.18) break
        const left = clusters[best.left]
        const right = clusters[best.right]
        left.bases.push(...right.bases)
        left.rows.push(...right.rows)
        clusters.splice(best.right, 1)
    }
    return clusters.map(cluster => ({
        bases: cluster.bases.sort((a, b) => orderIndex.get(a) - orderIndex.get(b)),
        rows: cluster.rows,
    }))
}

function valueSortKey(name, value) {
    if (name === 'division') return [DIVISION_ORDER[value] ?? 99, value]
    if (name === 'chongniu') return [CHONGNIU_ORDER[value] ?? 99, value]
    return [0, value]
}

function compareKeys(left, right) {
    return left[0] - right[0] || compareText(String(left[1]), String(right[1]))
}

function sortValues(name, values) {
    return [...values].sort((left, right) => compareKeys(valueSortKey(name, left), valueSortKey(name, right)))
}

function partitions(observations, featureName, minimumLeafWeight) {
    const raw = new Map()
    for (const observation of observations) {
        const value = feature(observation, featureName)
        if (!raw.has(value)) raw.set(value, [])
        raw.get(value).push(observation)
    }
    const large = []
    const small = []
    for (const [value, rows] of raw) {
        if (weight(rows) >= minimumLeafWeight || (weight(rows) >= 2 && purity(rows) >= 0.75)) {
            large.push({ values: [value], rows: [...rows] })
        } else {
            small.push(value)
        }
    }
    if (small.length) {
        const orderedSmall = sortValues(featureName, small)
        const smallRows = orderedSmall.flatMap(value => raw.get(value))
        if (weight(smallRows) >= minimumLeafWeight) {
            large.push({ values: orderedSmall, rows: smallRows })
        } else if (large.length) {
            const smallModes = modes(smallRows)
            const compatible = large.find(branch => sameArray(modes(branch.rows), smallModes))
            const target = compatible || [...large].sort((a, b) => weight(b.rows) - weight(a.rows))[0]
            target.values = sortValues(featureName, [...target.values, ...orderedSmall])
            target.rows.push(...smallRows)
        }
    }
    const folded = new Map()
    for (const branch of large) {
        const key = JSON.stringify(modes(branch.rows))
        if (!folded.has(key)) folded.set(key, { values: [], rows: [] })
        folded.get(key).values.push(...branch.values)
        folded.get(key).rows.push(...branch.rows)
    }
    return [...folded.values()].map(branch => ({
        values: sortValues(featureName, unique(branch.values)),
        rows: branch.rows,
    }))
}

function buildTree(observations, features, options, depth = 0) {
    const node = { observations, splitFeature: null, children: null }
    const parentEntropy = entropy(observations)
    const totalWeight = weight(observations)
    if (depth >= options.maxDepth || parentEntropy < 0.08 ||
        totalWeight < options.minimumLeafWeight * 2) return node
    let best = null
    for (const featureName of features) {
        if (featureName === 'chongniu' &&
            !sameArray(unique(observations.map(row => row.position.effectiveDivision)), ['三'])) continue
        const branches = partitions(observations, featureName, options.minimumLeafWeight)
        if (branches.length < 2) continue
        const covered = sum(branches.map(branch => weight(branch.rows)))
        if (covered < totalWeight * 0.9) continue
        const conditionalEntropy = sum(branches.map(branch =>
            weight(branch.rows) / covered * entropy(branch.rows)
        ))
        const gain = parentEntropy - conditionalEntropy
        const penalty = options.complexityStrength * (branches.length - 1) *
            Math.log2(totalWeight + 1) / totalWeight
        const penalized = gain - penalty - FEATURE_COSTS[featureName] / (depth + 1)
        if (!best || penalized > best.penalized ||
            (penalized === best.penalized && gain > best.gain)) {
            best = { penalized, gain, featureName, branches }
        }
    }
    if (!best || best.penalized <= 0 || best.gain < options.minimumGainBits ||
        best.gain / Math.max(parentEntropy, 1e-12) < options.minimumRelativeGain) return node
    const remaining = features.filter(value => value !== best.featureName)
    const children = best.branches.map(branch => ({
        values: branch.values,
        node: buildTree(branch.rows, remaining, options, depth + 1),
    }))
    const merged = []
    const leafGroups = new Map()
    for (const child of children) {
        if (!child.node.splitFeature) {
            const key = JSON.stringify(modes(child.node.observations))
            if (!leafGroups.has(key)) leafGroups.set(key, [])
            leafGroups.get(key).push(child)
        } else {
            merged.push(child)
        }
    }
    for (const group of leafGroups.values()) {
        merged.push({
            values: unique(group.flatMap(child => child.values)).sort(compareText),
            node: { observations: group.flatMap(child => child.node.observations), splitFeature: null, children: null },
        })
    }
    if (merged.length < 2) return node
    node.splitFeature = best.featureName
    node.children = merged
    return node
}

function branchLabel(values, featureName) {
    const ordered = sortValues(featureName, values)
    if (featureName === 'division') return ordered.join('/')
    if (featureName === 'chongniu') return ordered.map(value => value === '無' ? 'C' : value).join('/')
    return ordered.length <= 3 ? ordered.join('/') : `其餘${ordered.length}類`
}

function appendCondition(conditions, featureName, value) {
    if (featureName !== 'chongniu') return [...conditions, [FEATURE_LABELS[featureName], value]]
    const combined = value.split('/').map(part => `三${part}`).join('/')
    const rendered = conditions.map(condition => [...condition])
    const division = rendered.find(condition => condition[0] === FEATURE_LABELS.division && condition[1] === '三')
    if (division) {
        division[1] = combined
        return rendered
    }
    return [...rendered, [FEATURE_LABELS.division, combined]]
}

const contextKey = values => JSON.stringify(values)
const pairKey = pair => contextKey([pair.char, pair.pronunciation.raw, pair.position.description])
const signatureKey = values => contextKey(values)

function initialContexts(position) {
    return [
        [position.initial, position.rhyme, position.effectiveOpenness, position.effectiveDivision, position.chongniu],
        [position.initial, position.rhyme, position.effectiveOpenness, position.effectiveDivision],
        [position.initial, position.rhyme, position.effectiveOpenness],
        [position.initial, position.rhyme],
        [position.initial, position.she],
        [position.initial],
        [position.group, position.she],
        [position.group],
    ]
}

function finalContexts(position) {
    return [
        [position.rhyme, position.effectiveOpenness, position.effectiveDivision, position.chongniu, position.initial],
        [position.rhyme, position.effectiveOpenness, position.effectiveDivision, position.chongniu, position.group],
        [position.rhyme, position.effectiveOpenness, position.effectiveDivision, position.chongniu],
        [position.rhyme, position.effectiveOpenness, position.effectiveDivision, position.group],
        [position.rhyme, position.effectiveOpenness, position.effectiveDivision],
        [position.rhyme, position.effectiveOpenness, position.group],
        [position.rhyme, position.effectiveOpenness],
        [position.rhyme, position.group],
        [position.rhyme],
        [position.she, position.group],
        [position.she],
    ]
}

function toneContexts(position) {
    return [
        [position.tone, position.voicing, position.group],
        [position.tone, position.voicing],
        [position.tone, position.group],
        [position.tone],
    ]
}

class HierarchicalDistribution {
    constructor(alpha = 6, beta = 0.5) {
        this.alpha = alpha
        this.beta = beta
        this.globalCounts = new Map()
        this.levelCounts = []
    }

    fit(rows) {
        const cached = [...rows]
        const levelCount = Math.max(0, ...cached.map(([, contexts]) => contexts.length))
        this.globalCounts = new Map()
        this.levelCounts = Array.from({ length: levelCount }, () => new Map())
        for (const [outcome, contexts, rowWeight] of cached) {
            this.globalCounts.set(outcome, (this.globalCounts.get(outcome) || 0) + rowWeight)
            contexts.forEach((context, index) => {
                const key = contextKey(context)
                if (!this.levelCounts[index].has(key)) this.levelCounts[index].set(key, new Map())
                const counts = this.levelCounts[index].get(key)
                counts.set(outcome, (counts.get(outcome) || 0) + rowWeight)
            })
        }
    }

    probability(outcome, contexts) {
        const vocabulary = Math.max(this.globalCounts.size + 1, 2)
        const total = sum([...this.globalCounts.values()])
        let probability = ((this.globalCounts.get(outcome) || 0) + this.beta) /
            (total + this.beta * vocabulary)
        for (let index = Math.min(contexts.length, this.levelCounts.length) - 1; index >= 0; index -= 1) {
            const counts = this.levelCounts[index].get(contextKey(contexts[index]))
            if (!counts) continue
            probability = ((counts.get(outcome) || 0) + this.alpha * probability) /
                (sum([...counts.values()]) + this.alpha)
        }
        return Math.max(probability, 1e-12)
    }
}

function weightedComponentObservations(pairs, evidence) {
    const baseKeys = new Set(pairs.map(pairKey))
    const extraKeys = new Set()
    const extras = []
    for (const pair of evidence || []) {
        const key = pairKey(pair)
        if (baseKeys.has(key) || extraKeys.has(key)) continue
        extraKeys.add(key)
        extras.push(pair)
    }
    const effectivePairs = [...pairs, ...extras]
    const counts = new Map()
    effectivePairs.forEach(pair => counts.set(pair.char, (counts.get(pair.char) || 0) + 1))
    return effectivePairs.map(pair => ({ pair, weight: 1 / counts.get(pair.char) }))
}

class CorrespondenceModel {
    constructor(pairs, componentEvidence = {}) {
        if (!pairs.length) throw new Error('不能在沒有種子對應的情況下重建音系')
        this.initial = new HierarchicalDistribution()
        this.final = new HierarchicalDistribution()
        this.tone = new HierarchicalDistribution()
        this.componentObservations = Object.fromEntries(
            ['initial', 'final', 'tone'].map(component => [
                component,
                weightedComponentObservations(pairs, componentEvidence[component]),
            ])
        )
        const contexts = { initial: initialContexts, final: finalContexts, tone: toneContexts }
        const outcomes = {
            initial: pair => pair.pronunciation.initial,
            final: pair => pair.pronunciation.final,
            tone: pair => pair.pronunciation.tone,
        }
        for (const component of ['initial', 'final', 'tone']) {
            this[component].fit(this.componentObservations[component].map(observation => [
                outcomes[component](observation.pair),
                contexts[component](observation.pair.position),
                observation.weight,
            ]))
        }
    }

    scoreParts(pronunciation, position) {
        return [
            -Math.log2(this.initial.probability(pronunciation.initial, initialContexts(position))),
            -Math.log2(this.final.probability(pronunciation.final, finalContexts(position))),
            -Math.log2(this.tone.probability(pronunciation.tone, toneContexts(position))),
        ]
    }

    score(pronunciation, position) {
        return sum(this.scoreParts(pronunciation, position))
    }
}

function foldForChar(char, folds = 5) {
    const digest = blake2bBytes(char, 4)
    const value = (((digest[0] << 24) >>> 0) + (digest[1] << 16) + (digest[2] << 8) + digest[3]) >>> 0
    return value % folds
}

function quantile(values, percentile) {
    const ordered = [...values].sort((left, right) => left - right)
    if (!ordered.length) return Number.NaN
    const index = (ordered.length - 1) * percentile
    const lower = Math.floor(index)
    const upper = Math.ceil(index)
    if (lower === upper) return ordered[lower]
    return ordered[lower] * (upper - index) + ordered[upper] * (index - lower)
}

function crossValidatedThresholds(pairs, folds = 5) {
    const totals = []
    const components = [[], [], []]
    for (let fold = 0; fold < folds; fold += 1) {
        const train = pairs.filter(pair => foldForChar(pair.char, folds) !== fold)
        const validation = pairs.filter(pair => foldForChar(pair.char, folds) === fold)
        if (!train.length) continue
        const model = new CorrespondenceModel(train)
        for (const pair of validation) {
            const parts = model.scoreParts(pair.pronunciation, pair.position)
            totals.push(sum(parts))
            parts.forEach((value, index) => components[index].push(value))
        }
    }
    if (!totals.length) throw new Error('可靠中古音種子不足，不能進行交叉驗證')
    return {
        totalP95: quantile(totals, 0.95),
        componentsP95: components.map(values => quantile(values, 0.95)),
        totalP99: quantile(totals, 0.99),
        componentsP99: components.map(values => quantile(values, 0.99)),
    }
}

const componentContexts = {
    initial: position => [0, 3, 4, 5, 6].map(index => [`@${index}`, ...initialContexts(position)[index]]),
    final: position => [0, 1, 2, 7, 8, 9, 10].map(index => [`@${index}`, ...finalContexts(position)[index]]),
    tone: position => [0, 1, 3].map(index => [`@${index}`, ...toneContexts(position)[index]]),
}
const componentOutcomes = {
    initial: pronunciation => pronunciation.initial,
    final: pronunciation => pronunciation.final,
    tone: pronunciation => pronunciation.tone,
}
const componentSignatures = {
    initial: pronunciation => [pronunciation.final, pronunciation.tone],
    final: pronunciation => [pronunciation.initial, pronunciation.tone],
    tone: pronunciation => [pronunciation.initial, pronunciation.final],
}

function addNestedSet(map, key, value) {
    if (!map.has(key)) map.set(key, new Set())
    map.get(key).add(value)
}

function sortedPronunciations(dialect, char) {
    return [...(dialect.get(char)?.values() || [])].sort((left, right) => compareText(left.raw, right.raw))
}

function discoverSystematicComponentEvidence(
    dialect,
    middleChinese,
    provisionalModel,
    {
        minimumOtherCharSupport = 6,
        minimumReversePurity = 0.7,
        minimumForwardRate = 0.12,
    } = {}
) {
    const components = ['initial', 'final', 'tone']
    const alternationChars = Object.fromEntries(components.map(component => [component, new Map()]))
    for (const char of [...dialect.keys()].sort(compareText)) {
        const pronunciations = sortedPronunciations(dialect, char)
        for (let leftIndex = 0; leftIndex < pronunciations.length; leftIndex += 1) {
            for (let rightIndex = leftIndex + 1; rightIndex < pronunciations.length; rightIndex += 1) {
                const left = pronunciations[leftIndex]
                const right = pronunciations[rightIndex]
                for (const component of components) {
                    if (signatureKey(componentSignatures[component](left)) !==
                        signatureKey(componentSignatures[component](right))) continue
                    const outcomes = [
                        componentOutcomes[component](left),
                        componentOutcomes[component](right),
                    ].sort(compareText)
                    if (outcomes[0] !== outcomes[1]) {
                        addNestedSet(alternationChars[component], signatureKey(outcomes), char)
                    }
                }
            }
        }
    }
    const qualifiedAlternations = Object.fromEntries(components.map(component => [
        component,
        new Set(
            [...alternationChars[component]]
                .filter(([, chars]) => chars.size - 1 >= minimumOtherCharSupport)
                .map(([key]) => key)
        ),
    ]))
    const alternationEvidence = Object.fromEntries(components.map(component => [component, []]))
    if (provisionalModel) {
        for (const char of [...dialect.keys()].filter(value => middleChinese.has(value)).sort(compareText)) {
            const positions = [...middleChinese.get(char)].sort((left, right) =>
                compareText(left.description, right.description)
            )
            const pronunciations = sortedPronunciations(dialect, char)
            for (let leftIndex = 0; leftIndex < pronunciations.length; leftIndex += 1) {
                for (let rightIndex = leftIndex + 1; rightIndex < pronunciations.length; rightIndex += 1) {
                    const left = pronunciations[leftIndex]
                    const right = pronunciations[rightIndex]
                    for (const component of components) {
                        if (signatureKey(componentSignatures[component](left)) !==
                            signatureKey(componentSignatures[component](right))) continue
                        const outcomePair = [
                            componentOutcomes[component](left),
                            componentOutcomes[component](right),
                        ].sort(compareText)
                        if (!qualifiedAlternations[component].has(signatureKey(outcomePair))) continue
                        const distribution = provisionalModel[component]
                        const contextFunction = component === 'initial' ? initialContexts :
                            component === 'final' ? finalContexts : toneContexts
                        const score = pronunciation => Math.min(...positions.map(position =>
                            -Math.log2(distribution.probability(
                                componentOutcomes[component](pronunciation),
                                contextFunction(position)
                            ))
                        ))
                        const leftScore = score(left)
                        const rightScore = score(right)
                        if (Math.abs(leftScore - rightScore) < 0.75) continue
                        const target = leftScore > rightScore ? left : right
                        const anchor = leftScore > rightScore ? right : left
                        const positionScores = positions.map(position => ({
                            score: -Math.log2(distribution.probability(
                                componentOutcomes[component](anchor),
                                contextFunction(position)
                            )),
                            position,
                        }))
                        const best = Math.min(...positionScores.map(item => item.score))
                        positionScores
                            .filter(item => item.score <= best + 0.75)
                            .forEach(item => alternationEvidence[component].push({
                                char, pronunciation: target, position: item.position,
                            }))
                    }
                }
            }
        }
    }

    const candidates = Object.fromEntries(components.map(component => [component, []]))
    const relationChars = Object.fromEntries(components.map(component => [component, new Map()]))
    const contextChars = Object.fromEntries(components.map(component => [component, new Map()]))
    const outcomeChars = Object.fromEntries(components.map(component => [component, new Map()]))
    for (const char of [...dialect.keys()].filter(value => middleChinese.has(value)).sort(compareText)) {
        const positions = middleChinese.get(char)
        if (positions.length !== 1) continue
        const position = positions[0]
        for (const pronunciation of dialect.get(char).values()) {
            const pair = { char, pronunciation, position }
            for (const component of components) {
                const outcome = componentOutcomes[component](pronunciation)
                candidates[component].push(pair)
                for (const context of componentContexts[component](position)) {
                    const relationKey = contextKey([context, outcome])
                    addNestedSet(relationChars[component], relationKey, char)
                    addNestedSet(contextChars[component], contextKey(context), char)
                }
                addNestedSet(outcomeChars[component], outcome, char)
            }
        }
    }

    const evidence = {}
    const qualifiedRelations = {}
    const evidencePairs = {}
    for (const component of components) {
        const qualified = new Set()
        for (const [relationKey, chars] of relationChars[component]) {
            const [context, outcome] = JSON.parse(relationKey)
            const support = chars.size
            if (support - 1 < minimumOtherCharSupport) continue
            const forwardRate = (support - 1) /
                Math.max(contextChars[component].get(contextKey(context)).size - 1, 1)
            const reversePurity = (support - 1) /
                Math.max(outcomeChars[component].get(outcome).size - 1, 1)
            if (forwardRate >= minimumForwardRate || reversePurity >= minimumReversePurity) {
                qualified.add(relationKey)
            }
        }
        const alternationKeys = new Set(alternationEvidence[component].map(pairKey))
        const seen = new Set()
        const rows = []
        for (const pair of [...candidates[component], ...alternationEvidence[component]]) {
            const outcome = componentOutcomes[component](pair.pronunciation)
            const qualifies = componentContexts[component](pair.position).some(context =>
                qualified.has(contextKey([context, outcome]))
            )
            const key = pairKey(pair)
            if ((qualifies || alternationKeys.has(key)) && !seen.has(key)) {
                seen.add(key)
                rows.push(pair)
            }
        }
        evidence[component] = rows
        qualifiedRelations[component] = qualified.size
        evidencePairs[component] = rows.length
    }
    return {
        evidence,
        statistics: {
            minimumOtherCharSupport,
            minimumReversePurity,
            minimumForwardRate,
            qualifiedRelations,
            evidencePairs,
            qualifiedAlternations: Object.fromEntries(
                components.map(component => [component, qualifiedAlternations[component].size])
            ),
        },
    }
}

function makeSeedPairs(dialect, middleChinese) {
    const pairs = []
    for (const char of [...dialect.keys()].sort(compareText)) {
        const pronunciations = dialect.get(char)
        const positions = middleChinese.get(char) || []
        if (pronunciations.size === 1 && positions.length === 1) {
            pairs.push({
                char,
                pronunciation: pronunciations.values().next().value,
                position: positions[0],
            })
        }
    }
    return pairs
}

function iterativeAugment(seeds, dialect, middleChinese, threshold, componentEvidence, maxRounds = 6) {
    const accepted = [...seeds]
    const usedChars = new Set(accepted.map(pair => pair.char))
    const rounds = []
    for (let roundNumber = 1; roundNumber <= maxRounds; roundNumber += 1) {
        const model = new CorrespondenceModel(accepted, componentEvidence)
        const additions = []
        const kinds = {}
        for (const char of [...dialect.keys()]
            .filter(value => middleChinese.has(value) && !usedChars.has(value))
            .sort(compareText)) {
            const pronunciations = sortedPronunciations(dialect, char)
            const positions = [...middleChinese.get(char)].sort((left, right) =>
                compareText(left.description, right.description)
            )
            if (!pronunciations.length || pronunciations.length > 6 ||
                !positions.length || positions.length > 6) continue
            let chosen = []
            for (const pronunciation of pronunciations) {
                const scored = positions.map(position => ({
                    score: model.score(pronunciation, position),
                    position,
                })).sort((left, right) =>
                    left.score - right.score ||
                    compareText(left.position.description, right.position.description)
                )
                const bestScore = scored[0].score
                const plausible = scored.filter(item =>
                    item.score <= threshold && item.score <= bestScore + 0.75
                )
                if (!plausible.length) {
                    chosen = []
                    break
                }
                chosen.push(...plausible.map(item => ({ char, pronunciation, position: item.position })))
            }
            if (!chosen.length) continue
            chosen = [...new Map(chosen.map(pair => [pairKey(pair), pair])).values()]
            additions.push(...chosen)
            usedChars.add(char)
            const kind = pronunciations.length > 1 && positions.length > 1 ? 'manyToMany' :
                pronunciations.length > 1 ? 'oneMcPosition' :
                    positions.length > 1 ? 'onePronunciation' : 'oneToOne'
            kinds[kind] = (kinds[kind] || 0) + 1
        }
        rounds.push({
            round: roundNumber,
            newPairs: additions.length,
            newChars: new Set(additions.map(pair => pair.char)).size,
            ...kinds,
        })
        accepted.push(...additions)
        if (!additions.length) break
    }
    return { pairs: accepted, rounds }
}

function fitCorrespondence(dialect, middleChinese) {
    const seeds = makeSeedPairs(dialect, middleChinese)
    if (seeds.length < 50) {
        throw new Error(`可靠中古音種子只有 ${seeds.length} 個，至少需要 50 個`)
    }
    const thresholds = crossValidatedThresholds(seeds)
    const discovered = discoverSystematicComponentEvidence(
        dialect,
        middleChinese,
        new CorrespondenceModel(seeds)
    )
    const augmented = iterativeAugment(
        seeds,
        dialect,
        middleChinese,
        thresholds.totalP95,
        discovered.evidence
    )
    return {
        model: new CorrespondenceModel(augmented.pairs, discovered.evidence),
        thresholds,
        seedCount: seeds.length,
        augmentedPairCount: augmented.pairs.length,
        augmentedCharCount: new Set(augmented.pairs.map(pair => pair.char)).size,
        rounds: [
            { systematicComponentEvidence: discovered.statistics },
            ...augmented.rounds,
        ],
    }
}

const exampleLimit = count => Math.min(5, Math.max(1, Math.ceil(Math.sqrt(count))))
const roundHalfToEven = value => {
    const lower = Math.floor(value)
    const fraction = value - lower
    if (Math.abs(fraction - 0.5) < Number.EPSILON * 4) return lower % 2 === 0 ? lower : lower + 1
    return Math.round(value)
}

function coarsenReverseFinal(observations) {
    const byShe = new Map()
    for (const observation of observations) {
        if (!byShe.has(observation.position.she)) byShe.set(observation.position.she, [])
        byShe.get(observation.position.she).push(observation)
    }
    const collapsed = new Set()
    for (const [she, rows] of byShe) {
        if (!she || weight(rows) < 6) continue
        const rhymeCounts = new Map()
        for (const row of rows) {
            rhymeCounts.set(row.position.rhyme, (rhymeCounts.get(row.position.rhyme) || 0) + row.weight)
        }
        if (rhymeCounts.size < 2) continue
        const counts = [...rhymeCounts.values()]
        const total = sum(counts)
        const valueEntropy = -sum(counts.map(count => {
            const probability = count / total
            return probability * Math.log2(probability)
        }))
        const normalized = valueEntropy / Math.log2(counts.length)
        const dominant = Math.max(...counts) / total
        if (normalized >= 0.55 && dominant <= 0.8) collapsed.add(she)
    }
    return observations.map(observation => collapsed.has(observation.position.she)
        ? { ...observation, outcome: observation.position.she, outcomeLevel: 'she' }
        : observation
    )
}

function outcomeDetails(observations, outcomes, component) {
    const byOutcome = new Map()
    for (const observation of observations) {
        if (!byOutcome.has(observation.outcome)) byOutcome.set(observation.outcome, [])
        byOutcome.get(observation.outcome).push(observation)
    }
    const finalComponent = ['final', 'reverse_final'].includes(component)
    const leafHasChecked = finalComponent && observations.some(row => /[ptk]$/.test(row.displayOutcome))
    return outcomes.map(outcome => {
        const rows = byOutcome.get(outcome) || []
        const checked = rows.filter(row => finalComponent && /[ptk]$/.test(row.displayOutcome))
        const regular = rows.filter(row => !checked.includes(row))
        const showChecked = leafHasChecked &&
            (component === 'reverse_final' || /(?:m|n|ng)$/.test(outcome))
        const regularChars = new Set(regular.map(row => row.char))
        const checkedChars = new Set(checked.map(row => row.char))
        const charWeights = new Map()
        const pronunciations = new Map()
        const notes = new Map()
        for (const row of rows) {
            charWeights.set(row.char, (charWeights.get(row.char) || 0) + row.weight)
            if (!pronunciations.has(row.char)) pronunciations.set(row.char, new Set())
            pronunciations.get(row.char).add(row.pronunciation)
            if (row.note) {
                if (!notes.has(row.char)) notes.set(row.char, [])
                if (!notes.get(row.char).includes(row.note)) notes.get(row.char).push(row.note)
            }
        }
        const rank = chars => [...chars].sort((a, b) =>
            charWeights.get(b) - charWeights.get(a) || compareText(a, b)
        )
        const regularRanked = rank(regularChars)
        const checkedRanked = rank(checkedChars)
        const allRanked = rank(charWeights.keys())
        const limit = exampleLimit(charWeights.size)
        let selected
        if (regularRanked.length && checkedRanked.length && limit > 1) {
            let regularSlots = roundHalfToEven(
                limit * regularChars.size / (regularChars.size + checkedChars.size)
            )
            regularSlots = Math.min(limit - 1, Math.max(1, regularSlots))
            selected = [
                ...regularRanked.slice(0, regularSlots),
                ...checkedRanked.slice(0, limit - regularSlots),
            ]
        } else {
            selected = allRanked.slice(0, limit)
        }
        selected = unique(selected)
        for (const char of allRanked) {
            if (selected.length >= limit) break
            if (!selected.includes(char)) selected.push(char)
        }
        return {
            value: outcome,
            charCount: showChecked ? regularChars.size : charWeights.size,
            checkedCharCount: showChecked ? checkedChars.size : null,
            examples: selected.map(char => ({
                char,
                pronunciations: [...pronunciations.get(char)].sort(compareText),
                note: (notes.get(char) || []).join('；'),
            })),
            level: rows.find(row => row.outcomeLevel && row.outcomeLevel !== 'category')?.outcomeLevel || 'category',
        }
    })
}

function rulesFromTree(base, node, component, conditions = [], baseEntropy = null) {
    const initialEntropy = baseEntropy ?? entropy(node.observations)
    if (!node.splitFeature || !node.children) {
        const rows = component === 'reverse_final' ? coarsenReverseFinal(node.observations) : node.observations
        const outcomes = allOutcomes(rows)
        return [{
            base,
            conditions,
            outcomes: outcomeDetails(rows, outcomes, component),
            entropyBits: entropy(node.observations),
            informationGainBits: Math.max(initialEntropy - entropy(node.observations), 0),
            charCount: new Set(node.observations.map(row => row.char)).size,
        }]
    }
    return node.children
        .sort((a, b) => {
            const left = sortValues(node.splitFeature, a.values)[0]
            const right = sortValues(node.splitFeature, b.values)[0]
            return compareKeys(valueSortKey(node.splitFeature, left), valueSortKey(node.splitFeature, right))
        })
        .flatMap(child => rulesFromTree(
            base,
            child.node,
            component,
            appendCondition(conditions, node.splitFeature, branchLabel(child.values, node.splitFeature)),
            initialEntropy
        ))
}

function mergeEquivalentRules(rules) {
    const grouped = new Map()
    for (const rule of rules) {
        const key = JSON.stringify([rule.base, rule.conditions])
        if (!grouped.has(key)) grouped.set(key, [])
        grouped.get(key).push(rule)
    }
    const result = []
    for (const group of grouped.values()) {
        if (group.length === 1) {
            result.push(group[0])
            continue
        }
        const details = new Map()
        for (const rule of group) {
            for (const outcome of rule.outcomes) {
                const key = `${outcome.value}\u001f${outcome.level}`
                if (!details.has(key)) details.set(key, [])
                details.get(key).push(outcome)
            }
        }
        const mergedOutcomes = [...details.values()].map(items => {
            const pronunciations = new Map()
            const notes = new Map()
            for (const item of items) {
                for (const example of item.examples) {
                    if (!pronunciations.has(example.char)) pronunciations.set(example.char, new Set())
                    example.pronunciations.forEach(pron => pronunciations.get(example.char).add(pron))
                    if (example.note) {
                        if (!notes.has(example.char)) notes.set(example.char, [])
                        if (!notes.get(example.char).includes(example.note)) notes.get(example.char).push(example.note)
                    }
                }
            }
            const charCount = sum(items.map(item => item.charCount))
            const hasChecked = items.some(item => item.checkedCharCount != null)
            const checkedCharCount = hasChecked ? sum(items.map(item => item.checkedCharCount || 0)) : null
            return {
                value: items[0].value,
                charCount,
                checkedCharCount,
                examples: [...pronunciations].slice(0, exampleLimit(charCount + (checkedCharCount || 0)))
                    .map(([char, values]) => ({
                        char,
                        pronunciations: [...values].sort(compareText),
                        note: (notes.get(char) || []).join('；'),
                    })),
                level: items[0].level,
            }
        }).sort((a, b) =>
            (b.charCount + (b.checkedCharCount || 0)) - (a.charCount + (a.checkedCharCount || 0)) ||
            compareText(a.value, b.value)
        )
        const total = sum(group.map(rule => rule.charCount))
        result.push({
            base: group[0].base,
            conditions: group[0].conditions,
            outcomes: mergedOutcomes,
            entropyBits: total
                ? sum(group.map(rule => rule.entropyBits * rule.charCount)) / total : 0,
            informationGainBits: total
                ? sum(group.map(rule => rule.informationGainBits * rule.charCount)) / total : 0,
            charCount: total,
        })
    }
    return result
}

function buildRules(model, component) {
    let modelComponent
    let baseFunction
    let outcomeFunction
    let displayFunction
    let featureOrder
    let baseOrder
    let maxDepth = 3
    if (component === 'initial') {
        modelComponent = 'initial'
        baseFunction = pair => pair.position.initial
        outcomeFunction = pair => pair.pronunciation.initial
        displayFunction = outcomeFunction
        featureOrder = ['tone', 'openness', 'she', 'division', 'chongniu', 'rhyme']
        baseOrder = MC_INITIALS
    } else if (component === 'final') {
        modelComponent = 'final'
        baseFunction = pair => pair.position.rhyme
        outcomeFunction = pair => pair.pronunciation.final
        displayFunction = outcomeFunction
        featureOrder = ['modern_initial', 'tone', 'group', 'voicing', 'initial', 'openness', 'division', 'chongniu']
        baseOrder = MC_RHYMES
    } else if (component === 'reverse_initial') {
        modelComponent = 'initial'
        baseFunction = pair => pair.pronunciation.initial || '∅'
        outcomeFunction = pair => pair.position.initial
        displayFunction = pair => pair.pronunciation.initial
        featureOrder = ['openness', 'she', 'division', 'chongniu', 'rhyme']
        maxDepth = 2
    } else {
        modelComponent = 'final'
        baseFunction = pair => normaliseCheckedCoda(pair.pronunciation.final)
        outcomeFunction = pair => pair.position.rhyme
        displayFunction = pair => pair.pronunciation.final
        featureOrder = ['modern_initial', 'group', 'voicing', 'initial', 'openness', 'division', 'chongniu']
        maxDepth = 2
    }
    const grouped = new Map()
    for (const weightedPair of model.componentObservations[modelComponent]) {
        const pair = weightedPair.pair
        const displayOutcome = displayFunction(pair)
        const outcome = component === 'final'
            ? normaliseCheckedCoda(displayOutcome)
            : outcomeFunction(pair)
        const observation = {
            char: pair.char,
            outcome,
            displayOutcome,
            pronunciation: pair.pronunciation.raw,
            note: pair.pronunciation.suppressNote ? '' : pair.pronunciation.notes.join('；'),
            modernInitial: pair.pronunciation.initial,
            modernFinal: pair.pronunciation.final,
            position: pair.position,
            weight: weightedPair.weight,
            outcomeLevel: 'category',
            modelComponent,
        }
        const base = baseFunction(pair)
        if (!grouped.has(base)) grouped.set(base, [])
        grouped.get(base).push(observation)
    }
    if (!baseOrder) {
        baseOrder = [...grouped.keys()].sort((a, b) =>
            weight(grouped.get(b)) - weight(grouped.get(a)) || compareText(a, b)
        )
    }
    if (component === 'reverse_final') {
        for (const [base, rows] of grouped) grouped.set(base, coarsenReverseFinal(rows))
    }
    const options = {
        minimumLeafWeight: 6,
        minimumGainBits: 0.15,
        minimumRelativeGain: 0.12,
        complexityStrength: 0.8,
        maxDepth,
    }
    const rules = []
    for (const cluster of groupBaseObservations(grouped, baseOrder, component)) {
        const tree = buildTree(cluster.rows, featureOrder, options)
        rules.push(...rulesFromTree(cluster.bases.join('/'), tree, component))
    }
    return mergeEquivalentRules(rules)
}

function prepareCorrespondenceInputs(entries, middleChinese) {
    const dialect = new Map()
    for (const entry of entries) {
        const raw = `${entry.initial || ''}${entry.nuclei || ''}${entry.coda || ''}${entry.tone || ''}`
        const parts = splitJpp(raw)
        if (!parts.nuclei || !parts.tone ||
            `${parts.initial}${parts.nuclei}${parts.coda}${parts.tone}` !== raw) continue
        const char = String(entry.chara || '').normalize('NFC')
        if (!char) continue
        if (!dialect.has(char)) dialect.set(char, new Map())
        const readings = dialect.get(char)
        if (!readings.has(raw)) {
            readings.set(raw, {
                raw,
                initial: parts.initial,
                final: `${parts.nuclei}${parts.coda}`,
                tone: parts.tone,
                notes: [],
                suppressNote: false,
            })
        }
        const note = String(entry.note || '').trim()
        if (note && !readings.get(raw).notes.includes(note)) readings.get(raw).notes.push(note)
    }
    for (const readings of dialect.values()) {
        const values = [...readings.values()]
        const onlyToneDiffers = values.length > 1 &&
            new Set(values.map(value => `${value.initial}\u001f${value.final}`)).size === 1
        values.forEach(value => { value.suppressNote = onlyToneDiffers })
    }
    const resolvedMiddleChinese = new Map()
    let matchedCharacters = 0
    for (const char of dialect.keys()) {
        const descriptions = new Set(middleChinese.positions?.[char] || [])
        for (const alias of middleChinese.strictAliases?.[char] || []) {
            for (const description of middleChinese.positions?.[alias] || []) descriptions.add(description)
        }
        const positions = [...descriptions]
            .map(parsePosition)
            .filter(Boolean)
            .sort((left, right) => compareText(left.description, right.description))
        if (positions.length) {
            matchedCharacters += 1
            resolvedMiddleChinese.set(char, positions)
        }
    }
    return { dialect, resolvedMiddleChinese, matchedCharacters }
}

export function buildPhonologyPayload(entries, middleChinese, area) {
    const { dialect, resolvedMiddleChinese, matchedCharacters } =
        prepareCorrespondenceInputs(entries, middleChinese)
    const fitted = fitCorrespondence(dialect, resolvedMiddleChinese)
    const sections = [
        { id: 'initials', label: '正向聲母', baseLabel: '中古聲母', outcomeLabel: '現代聲母', component: 'initial' },
        { id: 'finals', label: '正向韻母', baseLabel: '中古韻', outcomeLabel: '現代韻母', component: 'final' },
        { id: 'reverse-initials', label: '反查聲母', baseLabel: '現代聲母', outcomeLabel: '中古聲母', component: 'reverse_initial' },
        { id: 'reverse-finals', label: '反查韻母', baseLabel: '現代韻母', outcomeLabel: '中古韻', component: 'reverse_final' },
    ].map(section => ({
        id: section.id,
        label: section.label,
        baseLabel: section.baseLabel,
        outcomeLabel: section.outcomeLabel,
        rules: buildRules(fitted.model, section.component),
    }))
    return {
        schemaVersion: 1,
        generatorVersion: PHONOLOGY_GENERATOR_VERSION,
        middleChineseVersion: middleChinese.sourceVersion,
        areaId: Number(area.id),
        sourceReleaseId: Number(area.current_release_id),
        locationName: area.name || '',
        generatedAt: new Date().toISOString(),
        statistics: {
            sourceEntryCount: entries.length,
            dialectCharacterCount: dialect.size,
            middleChineseMatchedCharacterCount: matchedCharacters,
            seedPairCount: fitted.seedCount,
            augmentedPairCount: fitted.augmentedPairCount,
            augmentedCharacterCount: fitted.augmentedCharCount,
            thresholds: fitted.thresholds,
            reconstructionRounds: fitted.rounds,
            ruleCount: sections.reduce((total, section) => total + section.rules.length, 0),
        },
        sections,
    }
}
