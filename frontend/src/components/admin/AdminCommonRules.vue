<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import adminApi from '@/api/admin.js'
import { convertRuleText } from '@/utils/commonConverter.js'

const BOOKS = [
    { key: 'i2i', short: 'I→I', label: 'IPA → IPA', input: 'IPA', output: 'IPA', file: 'rule_i2i.csv' },
    { key: 'i2j', short: 'I→J', label: 'IPA → J++', input: 'IPA', output: 'J++', file: 'rule_i2j.csv' },
    { key: 'j2i', short: 'J→I', label: 'J++ → IPA', input: 'J++', output: 'IPA', file: 'rule_j2i.csv' },
    { key: 'j2j', short: 'J→J', label: 'J++ → J++', input: 'J++', output: 'J++', file: 'rule_j2j.csv' },
    { key: 'tone-j2i', short: '調→I', label: 'J++ → IPA 聲調', tone: true, file: 'rule_tone_j2i.csv' },
    { key: 'tone-j2j', short: '調→J', label: 'J++ → J++ 聲調', tone: true, file: 'rule_tone_j2j.csv' },
]
const SEGMENT_KEYS = ['i2i', 'i2j', 'j2i', 'j2j']
const TONE_KEYS = ['tone-j2i', 'tone-j2j']
const CATEGORIES = ['舒聲', '入聲']
const DIRECTION_OPTIONS = [
    { value: 'j2j', label: 'J++ → J++' },
    { value: 'j2i', label: 'J++ → IPA' },
    { value: 'i2i', label: 'IPA → IPA' },
    { value: 'i2j', label: 'IPA → J++' },
]

const loading = ref(true)
const saving = ref(false)
const current = ref(null)
const history = ref([])
const payloadBase = shallowRef(null)
const rowsByBook = ref(Object.fromEntries(BOOKS.map(book => [book.key, []])))
const locations = ref([])
const editableProfiles = ref(null)
const canManageProfiles = ref(false)
const appendProfiles = ref(['0', '1'])
const version = ref('')
const activeKey = ref('i2i')
const activeProfile = ref('')
const profileSearch = ref('')
const ruleSearch = ref('')
const selectedIds = ref(new Set())
const error = ref('')
const success = ref('')
const dirty = ref(false)
const recoveryDraft = ref(null)
const showPublish = ref(false)
const showPaste = ref(false)
const pasteText = ref('')
const pasteCategory = ref('舒聲')
const showPlayground = ref(true)
const testProfiles = ref([])
const testProfileToAdd = ref('')
const testInput = ref('')
const testDirection = ref('j2j')
const testResults = ref([])
const activeTestResults = ref([])
const testRan = ref(false)
const csvInput = ref(null)
const jsonInput = ref(null)
const undoStack = ref([])
const redoStack = ref([])
const dragRowId = ref(null)
const dragTestIndex = ref(null)
let rowSequence = 0
let loadingState = false
let snapshotTimer = null
let draftTimer = null

const activeBook = computed(() => BOOKS.find(book => book.key === activeKey.value) || BOOKS[0])
const currentRows = computed(() => rowsByBook.value[activeKey.value] || [])
const normalizeLocationKey = value => String(value || '').replace(/\s+/g, '').toLowerCase()
const profileLocations = computed(() => {
    const matched = new Map()
    locations.value.forEach((area, order) => {
        const second = String(area.second || '').trim()
        const third = String(area.third || '').trim()
        const candidates = [
            area.detailed_name,
            `${second}${third}`,
            third,
            second,
        ].map(normalizeLocationKey).filter(Boolean)
        for (const candidate of candidates) {
            if (!matched.has(candidate)) matched.set(candidate, { ...area, catalogOrder: order })
        }
    })
    return matched
})
const locationForProfile = profile => profileLocations.value.get(normalizeLocationKey(profile)) || null
const locationLabel = area => {
    if (!area) return ''
    return [area.second, area.third].filter((value, index, values) => value && values.indexOf(value) === index).join(' · ')
}
const allProfiles = computed(() => {
    const names = new Set(appendProfiles.value.map(String))
    for (const rows of Object.values(rowsByBook.value)) {
        rows.forEach(row => {
            if (row.profile) names.add(row.profile)
        })
    }
    return [...names].sort((a, b) => {
        const publicA = appendProfiles.value.indexOf(a)
        const publicB = appendProfiles.value.indexOf(b)
        if (publicA >= 0 || publicB >= 0) {
            if (publicA < 0) return 1
            if (publicB < 0) return -1
            return publicA - publicB
        }
        const areaA = locationForProfile(a)
        const areaB = locationForProfile(b)
        if (areaA || areaB) {
            if (!areaA) return 1
            if (!areaB) return -1
            if (areaA.catalogOrder !== areaB.catalogOrder) return areaA.catalogOrder - areaB.catalogOrder
        }
        return a.localeCompare(b, undefined, { numeric: true })
    })
})
const profileChoices = computed(() => editableProfiles.value === null
    ? allProfiles.value
    : allProfiles.value.filter(profile => editableProfiles.value.includes(profile)))
const canEditActiveProfile = computed(() => canManageProfiles.value || editableProfiles.value?.includes(activeProfile.value))
const filteredProfiles = computed(() => {
    const needle = profileSearch.value.trim().toLowerCase()
    return profileChoices.value.filter(profile => !needle || profile.toLowerCase().includes(needle))
})
const activeProfileCounts = computed(() => Object.fromEntries(
    BOOKS.map(book => [book.key, rowsByBook.value[book.key].filter(row => row.profile === activeProfile.value).length])
))
const profileCounts = computed(() => Object.fromEntries(allProfiles.value.map(profile => [
    profile,
    Object.fromEntries(BOOKS.map(book => [
        book.key,
        rowsByBook.value[book.key].filter(row => row.profile === profile).length,
    ])),
])))
const visibleRows = computed(() => {
    const needle = ruleSearch.value.trim().toLowerCase()
    return currentRows.value.filter(row => {
        if (row.profile !== activeProfile.value) return false
        if (!needle) return true
        const values = row.tone
            ? [row.category, row.from, row.to]
            : [...row.fields, row.force ? '強制覆寫' : '']
        return values.some(value => String(value ?? '').toLowerCase().includes(needle))
    })
})
const visibleToneRows = computed(() => Object.fromEntries(
    CATEGORIES.map(category => [category, visibleRows.value.filter(row => row.category === category)])
))
const draftStorageKey = computed(() => {
    const identity = current.value?.payload_hash || current.value?.hash || current.value?.id || current.value?.version || 'unknown'
    return `jyutdict.common-rules.draft.${identity}`
})
const validationIssues = computed(validateDraft)
const validationErrors = computed(() => validationIssues.value.filter(issue => issue.level === 'error'))
const validationWarnings = computed(() => validationIssues.value.filter(issue => issue.level === 'warning'))
const publishDiff = computed(calculateDiff)
const testOutputText = computed(() => testResults.value.map(item =>
    item.ok ? `${item.input} → ${item.output}` : `${item.input}：錯誤－${item.error}`
).join('\n'))
const activeTestOutputText = computed(() => activeTestResults.value.map(item =>
    item.ok ? `${item.input} → ${item.output}` : `${item.input}：錯誤－${item.error}`
).join('\n'))
const testHasDifference = computed(() => testRan.value && testOutputText.value !== activeTestOutputText.value)

function makeRow(data) {
    return { id: ++rowSequence, ...data }
}

function stringToRuleColor(value, dark = false) {
    const text = String(value ?? '')
    if (text === '*') return dark ? 'hsl(215 22% 25%)' : 'hsl(215 30% 90%)'
    if (text === '') return dark ? 'hsl(38 18% 23%)' : 'hsl(38 35% 92%)'
    let hash = 2166136261
    for (const char of Array.from(text)) {
        hash ^= char.codePointAt(0)
        hash = Math.imul(hash, 16777619)
    }
    const unsigned = hash >>> 0
    const hue = unsigned % 360
    const saturation = 52 + ((unsigned >>> 9) % 17)
    const lightness = dark
        ? 21 + ((unsigned >>> 17) % 5)
        : 88 + ((unsigned >>> 17) % 4)
    return `hsl(${hue} ${saturation}% ${lightness}%)`
}

function ruleValueStyle(value) {
    return {
        '--rule-value-bg': stringToRuleColor(value),
        '--rule-value-bg-dark': stringToRuleColor(value, true),
    }
}

function cloneState() {
    return {
        rowsByBook: JSON.parse(JSON.stringify(rowsByBook.value)),
        appendProfiles: [...appendProfiles.value],
        version: version.value,
    }
}

function stateString() {
    return JSON.stringify(cloneState())
}

function applyState(state, mark = true) {
    loadingState = true
    rowsByBook.value = JSON.parse(JSON.stringify(state.rowsByBook))
    appendProfiles.value = [...state.appendProfiles]
    version.value = state.version
    rowSequence = Math.max(0, ...Object.values(rowsByBook.value).flat().map(row => Number(row.id) || 0))
    loadingState = false
    selectedIds.value = new Set()
    if (!profileChoices.value.includes(activeProfile.value)) activeProfile.value = profileChoices.value[0] || ''
    if (mark) {
        dirty.value = true
        scheduleDraftSave()
    }
}

function scheduleSnapshot() {
    if (loadingState) return
    dirty.value = true
    success.value = ''
    clearTimeout(snapshotTimer)
    snapshotTimer = setTimeout(() => {
        const serialized = stateString()
        if (undoStack.value.at(-1) !== serialized) {
            undoStack.value.push(serialized)
            if (undoStack.value.length > 80) undoStack.value.shift()
            redoStack.value = []
        }
    }, 250)
    scheduleDraftSave()
}

function scheduleDraftSave() {
    clearTimeout(draftTimer)
    draftTimer = setTimeout(() => {
        if (!dirty.value || loadingState) return
        localStorage.setItem(draftStorageKey.value, JSON.stringify({
            savedAt: new Date().toISOString(),
            state: cloneState(),
        }))
    }, 400)
}

function undo() {
    clearTimeout(snapshotTimer)
    if (undoStack.value.length < 2) return
    const currentState = undoStack.value.pop()
    redoStack.value.push(currentState)
    applyState(JSON.parse(undoStack.value.at(-1)))
    dirty.value = undoStack.value.at(-1) !== undoStack.value[0]
    if (!dirty.value) localStorage.removeItem(draftStorageKey.value)
}

function redo() {
    const next = redoStack.value.pop()
    if (!next) return
    undoStack.value.push(next)
    applyState(JSON.parse(next))
    dirty.value = undoStack.value.at(-1) !== undoStack.value[0]
}

function onGlobalKeydown(event) {
    const editing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)
    if (editing) return
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
        event.preventDefault()
        event.shiftKey ? redo() : undo()
    } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'y') {
        event.preventDefault()
        redo()
    }
}

function nextVersionName(name) {
    const now = new Date()
    const stamp = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}-${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}`
    return `${String(name || 'rules').replace(/-\d{8}(?:-\d{4})?$/, '')}-${stamp}`
}

function rowsFromPayload(payload) {
    const result = Object.fromEntries(BOOKS.map(book => [book.key, []]))
    for (const key of SEGMENT_KEYS) {
        for (const [profile, rules] of Object.entries(payload.rules?.[key] || {})) {
            for (const rule of rules || []) {
                result[key].push(makeRow({
                    tone: false,
                    profile,
                    fields: Array.from({ length: 6 }, (_, index) => String(rule[index] ?? '')),
                    force: rule[6] === '!',
                }))
            }
        }
    }
    for (const [toneKey, bookKey] of [['j2i', 'tone-j2i'], ['j2j', 'tone-j2j']]) {
        for (const [profile, categories] of Object.entries(payload.tones?.[toneKey] || {})) {
            for (const [category, mapping] of Object.entries(categories || {})) {
                for (const [from, to] of Object.entries(mapping || {})) {
                    result[bookKey].push(makeRow({
                        tone: true,
                        profile,
                        category,
                        from: String(from),
                        to: String(to),
                    }))
                }
            }
        }
    }
    return result
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        const [response, catalogResponse] = await Promise.all([
            adminApi.getCommonRules(),
            adminApi.getCatalogLocations().catch(() => ({ data: { locations: [] } })),
        ])
        current.value = response.data.active
        history.value = response.data.history || []
        editableProfiles.value = response.data.permissions?.editable_profiles ?? null
        canManageProfiles.value = Boolean(response.data.permissions?.can_manage_profiles)
        locations.value = Array.isArray(catalogResponse.data?.locations)
            ? catalogResponse.data.locations
            : []
        payloadBase.value = structuredClone(response.data.active.payload)
        loadingState = true
        rowsByBook.value = rowsFromPayload(payloadBase.value)
        appendProfiles.value = (payloadBase.value.appendProfiles || ['0', '1']).map(String)
        version.value = nextVersionName(response.data.active.version)
        activeProfile.value = profileChoices.value.find(profile => !appendProfiles.value.includes(profile)) || profileChoices.value[0] || ''
        testProfiles.value = defaultTestProfiles(activeProfile.value)
        loadingState = false
        dirty.value = false
        undoStack.value = [stateString()]
        redoStack.value = []
        const saved = localStorage.getItem(draftStorageKey.value)
        recoveryDraft.value = saved ? JSON.parse(saved) : null
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '載入規則書失敗'
        loadingState = false
    } finally {
        loading.value = false
    }
}

function defaultTestProfiles(profile) {
    return [...new Set([profile, ...appendProfiles.value, '999'].map(String).filter(Boolean))]
        .filter(value => allProfiles.value.includes(value))
}

function restoreDraft() {
    if (!recoveryDraft.value?.state) return
    applyState(recoveryDraft.value.state)
    undoStack.value = [stateString()]
    redoStack.value = []
    recoveryDraft.value = null
    success.value = '已恢復本機草稿。'
}

function abandonRecovery() {
    localStorage.removeItem(draftStorageKey.value)
    recoveryDraft.value = null
}

function setActiveProfile(profile) {
    activeProfile.value = profile
    selectedIds.value = new Set()
    ruleSearch.value = ''
    testProfiles.value = defaultTestProfiles(profile)
}

function addProfile() {
    const name = window.prompt('新規則組名稱')
    if (name == null) return
    const clean = name.trim()
    if (!clean) return
    if (!canManageProfiles.value && !editableProfiles.value?.includes(clean)) {
        error.value = '只能新增已獲授權地點所對應的規則組'
        return
    }
    if (allProfiles.value.includes(clean)) {
        error.value = `規則組「${clean}」已存在`
        return
    }
    rowsByBook.value[activeKey.value].push(activeBook.value.tone
        ? makeRow({ tone: true, profile: clean, category: '舒聲', from: '', to: '' })
        : makeRow({ tone: false, profile: clean, fields: ['*', '*', '*', '*', '*', '*'], force: false }))
    setActiveProfile(clean)
    scheduleSnapshot()
}

function renameProfile() {
    if (!canManageProfiles.value) return
    const from = activeProfile.value
    const name = window.prompt(`把規則組「${from}」改名為：`, from)
    if (name == null || name.trim() === from) return
    const to = name.trim()
    if (!to) return
    if (allProfiles.value.includes(to)) {
        error.value = `規則組「${to}」已存在`
        return
    }
    for (const rows of Object.values(rowsByBook.value)) {
        rows.forEach(row => {
            if (row.profile === from) row.profile = to
        })
    }
    appendProfiles.value = appendProfiles.value.map(profile => profile === from ? to : profile)
    setActiveProfile(to)
    scheduleSnapshot()
}

function deleteProfile() {
    if (!canEditActiveProfile.value) return
    const profile = activeProfile.value
    const counts = BOOKS.map(book => `${book.label} ${profileCounts.value[profile]?.[book.key] || 0}`).join('、')
    const publicWarning = appendProfiles.value.includes(profile) ? '\n這是公共規則組，刪除後也會從追加規則列表移除。' : ''
    if (!window.confirm(`刪除規則組「${profile}」？\n受影響：${counts}${publicWarning}\n可立即使用「撤銷」恢復。`)) return
    for (const key of BOOKS.map(book => book.key)) {
        rowsByBook.value[key] = rowsByBook.value[key].filter(row => row.profile !== profile)
    }
    appendProfiles.value = appendProfiles.value.filter(value => value !== profile)
    activeProfile.value = profileChoices.value[0] || ''
    scheduleSnapshot()
}

function setFieldSpecial(row, index, value) {
    row.fields[index] = value
    scheduleSnapshot()
}

function valueTags(value) {
    if (!value || value === '*') return []
    return [...new Set(String(value).split('|').map(item => item.trim()).filter(Boolean))]
}

function addRow(category = '舒聲', after = null) {
    const row = activeBook.value.tone
        ? makeRow({ tone: true, profile: activeProfile.value, category, from: '', to: '' })
        : makeRow({ tone: false, profile: activeProfile.value, fields: ['*', '*', '*', '*', '*', '*'], force: false })
    const rows = currentRows.value
    const index = after ? rows.indexOf(after) + 1 : rows.length
    rows.splice(index, 0, row)
    scheduleSnapshot()
}

function duplicateRow(row) {
    const copy = makeRow(row.tone
        ? { tone: true, profile: row.profile, category: row.category, from: row.from, to: row.to }
        : { tone: false, profile: row.profile, fields: [...row.fields], force: row.force })
    currentRows.value.splice(currentRows.value.indexOf(row) + 1, 0, copy)
    scheduleSnapshot()
}

function toggleSelected(id) {
    const next = new Set(selectedIds.value)
    next.has(id) ? next.delete(id) : next.add(id)
    selectedIds.value = next
}

function selectVisible() {
    selectedIds.value = new Set(visibleRows.value.map(row => row.id))
}

function deleteSelected() {
    if (!selectedIds.value.size) return
    rowsByBook.value[activeKey.value] = currentRows.value.filter(row => !selectedIds.value.has(row.id))
    selectedIds.value = new Set()
    scheduleSnapshot()
}

function removeRow(row) {
    rowsByBook.value[activeKey.value] = currentRows.value.filter(item => item.id !== row.id)
    scheduleSnapshot()
}

function onDragStart(row) {
    dragRowId.value = row.id
}

function onDrop(target) {
    const source = currentRows.value.find(row => row.id === dragRowId.value)
    if (!source || source.id === target.id || source.profile !== activeProfile.value || target.profile !== activeProfile.value) return
    const rows = currentRows.value
    const sourceIndex = rows.indexOf(source)
    const targetIndex = rows.indexOf(target)
    rows.splice(sourceIndex, 1)
    rows.splice(targetIndex, 0, source)
    dragRowId.value = null
    scheduleSnapshot()
}

function splitAlternatives(value) {
    return [...new Set(String(value ?? '').split('|').map(item => item.trim()))]
}

function expandInputs(fields) {
    let combinations = [[]]
    for (const value of fields.slice(0, 3)) {
        combinations = combinations.flatMap(prefix =>
            splitAlternatives(value).map(item => [...prefix, item])
        )
    }
    return combinations.map(input => [...input, ...fields.slice(3)])
}

function toneValue(value) {
    const text = String(value)
    return /^(0|[1-9]\d*)$/.test(text) ? Number(text) : text
}

function buildPayload() {
    const payload = structuredClone(payloadBase.value)
    payload.rules = {}
    for (const key of SEGMENT_KEYS) {
        const profiles = {}
        for (const row of rowsByBook.value[key]) {
            const profile = row.profile.trim()
            if (!profiles[profile]) profiles[profile] = []
            for (const fields of expandInputs(row.fields.map(value => String(value)))) {
                profiles[profile].push([...fields, ...(row.force ? ['!'] : [])])
            }
        }
        payload.rules[key] = profiles
    }
    payload.tones = {}
    for (const [key, bookKey] of [['j2i', 'tone-j2i'], ['j2j', 'tone-j2j']]) {
        const profiles = {}
        for (const row of rowsByBook.value[bookKey]) {
            const profile = row.profile.trim()
            if (!profiles[profile]) profiles[profile] = {}
            if (!profiles[profile][row.category]) profiles[profile][row.category] = {}
            profiles[profile][row.category][row.from.trim()] = toneValue(row.to.trim())
        }
        payload.tones[key] = profiles
    }
    payload.appendProfiles = [...appendProfiles.value]
    payload.bundleVersion = version.value.trim()
    return payload
}

function validateDraft() {
    const issues = []
    if (!/^[A-Za-z0-9._-]+$/.test(version.value.trim())) {
        issues.push({ level: 'error', message: '版本號只能使用英文字母、數字、點、底線和連字號。' })
    }
    if (!allProfiles.value.length) issues.push({ level: 'error', message: '至少需要一個規則組。' })
    for (const publicProfile of appendProfiles.value) {
        if (!allProfiles.value.includes(publicProfile)) {
            issues.push({ level: 'error', message: `公共規則組「${publicProfile}」不存在。` })
        }
    }
    for (const key of SEGMENT_KEYS) {
        const seen = new Set()
        rowsByBook.value[key].forEach((row, index) => {
            if (!row.profile.trim()) issues.push({ level: 'error', message: `${bookLabel(key)} 第 ${index + 1} 行缺少規則組。` })
            if (row.fields.some(value => typeof value !== 'string')) issues.push({ level: 'error', message: `${bookLabel(key)} 第 ${index + 1} 行含無效欄位。` })
            const identity = JSON.stringify([row.profile, row.fields, row.force])
            if (seen.has(identity)) issues.push({ level: 'warning', message: `${bookLabel(key)}「${row.profile}」有完全重複規則。` })
            seen.add(identity)
        })
    }
    for (const key of TONE_KEYS) {
        const seen = new Set()
        rowsByBook.value[key].forEach((row, index) => {
            if (!row.profile.trim() || !CATEGORIES.includes(row.category) || !row.from.trim()) {
                issues.push({ level: 'error', message: `${bookLabel(key)} 第 ${index + 1} 行有空白必填值。` })
            }
            const identity = JSON.stringify([row.profile, row.category, row.from.trim()])
            if (seen.has(identity)) issues.push({ level: 'error', message: `${bookLabel(key)}「${row.profile}」${row.category}的原調 ${row.from} 重複。` })
            seen.add(identity)
        })
    }
    return issues
}

function bookLabel(key) {
    return BOOKS.find(book => book.key === key)?.label || key
}

function canonicalBook(payload, key) {
    if (SEGMENT_KEYS.includes(key)) return payload.rules?.[key] || {}
    return payload.tones?.[key.replace('tone-', '')] || {}
}

function countLeaves(value) {
    if (Array.isArray(value)) return value.length
    if (!value || typeof value !== 'object') return 0
    return Object.values(value).reduce((sum, item) =>
        sum + (item && typeof item === 'object' && !Array.isArray(item) ? countLeaves(item) : 1), 0)
}

function calculateDiff() {
    if (!payloadBase.value) return []
    let draft
    try {
        draft = buildPayload()
    } catch {
        return []
    }
    const rows = []
    const profiles = new Set([...allProfiles.value])
    for (const book of BOOKS) {
        const beforeBook = canonicalBook(payloadBase.value, book.key)
        const afterBook = canonicalBook(draft, book.key)
        Object.keys(beforeBook).forEach(profile => profiles.add(profile))
        Object.keys(afterBook).forEach(profile => profiles.add(profile))
        for (const profile of profiles) {
            const before = beforeBook[profile]
            const after = afterBook[profile]
            const beforeText = JSON.stringify(before ?? null)
            const afterText = JSON.stringify(after ?? null)
            if (beforeText === afterText) continue
            const beforeCount = countLeaves(before)
            const afterCount = countLeaves(after)
            const reordered = beforeCount === afterCount && beforeCount > 0
            rows.push({
                profile,
                book: book.label,
                added: Math.max(0, afterCount - beforeCount),
                deleted: Math.max(0, beforeCount - afterCount),
                modified: reordered ? 0 : Math.min(beforeCount, afterCount),
                reordered,
            })
        }
    }
    return rows
}

function openPublish() {
    if (validationErrors.value.length) {
        error.value = '請先修正驗證錯誤，再建立版本。'
        return
    }
    showPublish.value = true
}

async function save() {
    saving.value = true
    error.value = ''
    success.value = ''
    try {
        const response = await adminApi.saveCommonRules(version.value.trim(), buildPayload())
        localStorage.removeItem(draftStorageKey.value)
        showPublish.value = false
        success.value = `規則版本 ${response.data.active.version} 已建立並啟用；已有字表不會自動改變。`
        await load()
    } catch (caught) {
        error.value = caught.response?.data?.error || caught.message || '儲存規則書失敗'
    } finally {
        saving.value = false
    }
}

async function discardChanges() {
    if (dirty.value && !window.confirm('放棄尚未建立版本的全部修改？')) return
    localStorage.removeItem(draftStorageKey.value)
    await load()
}

function applyTsv() {
    const lines = pasteText.value.split(/\r?\n/).filter(line => line.trim())
    if (!lines.length) return
    try {
        const additions = lines.map((line, index) => {
            const values = line.split('\t').map(value => value.trim())
            if (activeBook.value.tone) {
                if (values.length !== 2) throw new Error(`第 ${index + 1} 行需要兩欄：原調、新調。`)
                return makeRow({ tone: true, profile: activeProfile.value, category: pasteCategory.value, from: values[0], to: values[1] })
            }
            if (values.length < 6 || values.length > 7) throw new Error(`第 ${index + 1} 行需要六欄，或第七欄填 !。`)
            return makeRow({
                tone: false,
                profile: activeProfile.value,
                fields: values.slice(0, 6),
                force: values[6] === '!',
            })
        })
        currentRows.value.push(...additions)
        pasteText.value = ''
        showPaste.value = false
        scheduleSnapshot()
    } catch (caught) {
        error.value = caught.message
    }
}

function csvCell(value) {
    const text = String(value ?? '')
    return /[",\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text
}

function exportCurrentCsv() {
    const rows = currentRows.value.map(row => {
        const values = row.tone
            ? [row.profile, row.category, row.from, row.to]
            : [row.profile, ...row.fields, ...(row.force ? ['!'] : [])]
        return values.map(csvCell).join(',')
    }).join('\r\n')
    downloadBlob(`${rows}\r\n`, 'text/csv;charset=utf-8', activeBook.value.file)
}

function parseCsv(text) {
    const rows = []
    let row = []
    let cell = ''
    let quoted = false
    for (let index = 0; index < text.length; index += 1) {
        const char = text[index]
        if (quoted) {
            if (char === '"' && text[index + 1] === '"') {
                cell += '"'
                index += 1
            } else if (char === '"') quoted = false
            else cell += char
        } else if (char === '"') quoted = true
        else if (char === ',') {
            row.push(cell)
            cell = ''
        } else if (char === '\n') {
            row.push(cell.replace(/\r$/, ''))
            if (row.some(value => value !== '')) rows.push(row)
            row = []
            cell = ''
        } else cell += char
    }
    row.push(cell.replace(/\r$/, ''))
    if (row.some(value => value !== '')) rows.push(row)
    if (quoted) throw new Error('CSV 有未閉合的引號。')
    return rows
}

async function importCurrentCsv(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    try {
        const parsed = parseCsv(await file.text())
        const next = parsed.filter(values => values.some(Boolean)).map((values, index) => {
            if (activeBook.value.tone) {
                if (values.length !== 4) throw new Error(`CSV 第 ${index + 1} 行需要四欄。`)
                return makeRow({ tone: true, profile: values[0].trim(), category: values[1].trim(), from: values[2].trim(), to: values[3].trim() })
            }
            if (values.length < 7 || values.length > 8) throw new Error(`CSV 第 ${index + 1} 行需要七或八欄。`)
            return makeRow({ tone: false, profile: values[0].trim(), fields: values.slice(1, 7).map(value => value.trim()), force: values[7]?.trim() === '!' })
        })
        if (!window.confirm(`用 CSV 的 ${next.length} 行取代「${activeBook.value.label}」全部規則？`)) return
        rowsByBook.value[activeKey.value] = next
        scheduleSnapshot()
    } catch (caught) {
        error.value = caught.message || '匯入 CSV 失敗'
    }
}

function exportFullJson() {
    try {
        downloadBlob(JSON.stringify(buildPayload(), null, 2), 'application/json;charset=utf-8', `${current.value?.version || 'common-rules'}.json`)
    } catch (caught) {
        error.value = caught.message
    }
}

async function importFullJson(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    try {
        const payload = JSON.parse(await file.text())
        if (!payload.rules || !payload.tones) throw new Error('JSON 缺少 rules 或 tones。')
        loadingState = true
        rowsByBook.value = rowsFromPayload(payload)
        appendProfiles.value = (payload.appendProfiles || ['0', '1']).map(String)
        version.value = payload.bundleVersion || nextVersionName(current.value?.version)
        loadingState = false
        activeProfile.value = allProfiles.value[0] || ''
        scheduleSnapshot()
    } catch (caught) {
        loadingState = false
        error.value = caught.message || '匯入 JSON 失敗'
    }
}

function downloadBlob(text, type, filename) {
    const blob = new Blob([text], { type })
    const anchor = document.createElement('a')
    anchor.href = URL.createObjectURL(blob)
    anchor.download = filename
    anchor.click()
    URL.revokeObjectURL(anchor.href)
}

function addTestProfile() {
    const profile = testProfileToAdd.value
    if (!profile || testProfiles.value.includes(profile)) return
    testProfiles.value.push(profile)
    testProfileToAdd.value = ''
}

function moveTestProfile(index, direction) {
    const target = index + direction
    if (target < 0 || target >= testProfiles.value.length) return
    testProfiles.value.splice(target, 0, testProfiles.value.splice(index, 1)[0])
}

function dropTestProfile(targetIndex) {
    const sourceIndex = dragTestIndex.value
    if (sourceIndex == null || sourceIndex === targetIndex) return
    testProfiles.value.splice(targetIndex, 0, testProfiles.value.splice(sourceIndex, 1)[0])
    dragTestIndex.value = null
}

function runTest() {
    error.value = ''
    try {
        const draft = buildPayload()
        testResults.value = convertRuleText(draft, testProfiles.value, testDirection.value, testInput.value)
        activeTestResults.value = convertRuleText(payloadBase.value, testProfiles.value, testDirection.value, testInput.value)
        testRan.value = true
    } catch (caught) {
        error.value = caught.message || '轉換失敗'
    }
}

watch(rowsByBook, scheduleSnapshot, { deep: true, flush: 'sync' })
watch(appendProfiles, scheduleSnapshot, { deep: true, flush: 'sync' })
watch(version, scheduleSnapshot, { flush: 'sync' })

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown)
    load()
})
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown)
    clearTimeout(snapshotTimer)
    clearTimeout(draftTimer)
})
</script>

<template>
    <section class="space-y-4">
        <header class="border-l-4 border-accent bg-white/80 p-4 dark:bg-slate-800/80">
            <h2 class="font-bold">轉寫規則書</h2>
            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                以規則組編輯六份規則書。新版本只影響之後解析的 Excel，不會自動重算已有字表。
            </p>
        </header>

        <p v-if="error" class="border-l-4 border-red-500 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ error }}</p>
        <p v-if="success" class="border-l-4 border-emerald-500 bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">{{ success }}</p>
        <p v-if="editableProfiles !== null" class="border-l-4 border-blue-500 bg-blue-50 p-3 text-xs text-blue-700 dark:bg-blue-950/30 dark:text-blue-300">
            編纂者只能修改已獲授權地點的規則組；公共規則和其他地點規則由管理員維護。
        </p>

        <div v-if="recoveryDraft" class="flex flex-wrap items-center gap-2 border-l-4 border-amber-500 bg-amber-50 p-3 text-sm dark:bg-amber-950/30">
            <span class="mr-auto">找到 {{ new Date(recoveryDraft.savedAt).toLocaleString() }} 保存的本機草稿。</span>
            <button class="border border-amber-600 px-3 py-1 font-bold" @click="restoreDraft">恢復草稿</button>
            <button class="border px-3 py-1" @click="abandonRecovery">放棄</button>
        </div>

        <template v-if="!loading">
            <div class="flex flex-wrap items-center gap-2 border border-slate-200 bg-white/80 p-3 dark:border-slate-700 dark:bg-slate-800/80">
                <label class="flex min-w-[16rem] flex-1 items-center gap-2 text-xs text-slate-500">
                    新版本號
                    <input v-model.trim="version" class="min-w-0 flex-1 border-2 border-slate-200 p-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-900" />
                </label>
                <span v-if="dirty" class="text-xs font-bold text-amber-600">有未發布修改</span>
                <button :disabled="undoStack.length < 2" class="toolbar-button" @click="undo">撤銷</button>
                <button :disabled="!redoStack.length" class="toolbar-button" @click="redo">重做</button>
                <button class="toolbar-button" @click="discardChanges">放棄修改</button>
                <button :disabled="saving || !dirty || validationErrors.length" class="bg-accent px-4 py-2 text-sm font-bold text-white disabled:opacity-40" @click="openPublish">
                    建立並啟用
                </button>
            </div>

            <div class="lg:hidden">
                <label class="text-xs font-bold text-slate-500">規則組</label>
                <select :value="activeProfile" class="mt-1 w-full border-2 border-slate-200 p-2 dark:border-slate-700 dark:bg-slate-900" @change="setActiveProfile($event.target.value)">
                    <option v-for="profile in profileChoices" :key="profile" :value="profile">{{ profile }}{{ appendProfiles.includes(profile) ? '（公共）' : '' }}</option>
                </select>
            </div>

            <div class="grid gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <aside class="hidden max-h-[78vh] border border-slate-200 bg-white/80 lg:sticky lg:top-4 lg:flex lg:flex-col lg:self-start dark:border-slate-700 dark:bg-slate-800/80">
                    <div class="border-b border-slate-200 p-2.5 dark:border-slate-700">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold">規則組</h3>
                            <button v-if="canManageProfiles || editableProfiles?.some(profile => !allProfiles.includes(profile))" class="bg-accent px-2 py-1 text-xs font-bold text-white" @click="addProfile">新增</button>
                        </div>
                        <input v-model="profileSearch" placeholder="搜尋規則組" class="mt-1.5 w-full border p-1.5 text-sm dark:border-slate-700 dark:bg-slate-900" />
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <button v-for="profile in filteredProfiles" :key="profile" class="w-full border-b border-slate-100 px-2.5 py-1.5 text-left dark:border-slate-700/70"
                            :class="activeProfile === profile ? 'bg-accent/10 shadow-[inset_4px_0_0_#D32913]' : 'hover:bg-slate-50 dark:hover:bg-slate-900/50'"
                            @click="setActiveProfile(profile)">
                            <div class="flex items-center gap-1.5">
                                <span v-if="locationForProfile(profile)" class="h-2.5 w-2.5 shrink-0 border border-black/20"
                                    :style="{ backgroundColor: locationForProfile(profile).color || '#cccccc' }"
                                    :title="`地點目錄：${locationLabel(locationForProfile(profile))}`"></span>
                                <span v-else-if="!appendProfiles.includes(profile)" class="h-2.5 w-2.5 shrink-0 border border-dashed border-slate-300 dark:border-slate-600"
                                    title="未匹配地點目錄"></span>
                                <span v-else class="h-2.5 w-2.5 shrink-0"></span>
                                <span class="min-w-0 flex-1 truncate font-mono font-bold">{{ profile }}</span>
                                <span v-if="appendProfiles.includes(profile)" class="bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">公共規則組</span>
                            </div>
                            <div class="mt-0.5 grid grid-cols-6 gap-x-1 pl-4 text-[9px] leading-tight text-slate-400">
                                <span v-for="book in BOOKS" :key="book.key" class="truncate">{{ book.short }} {{ profileCounts[profile]?.[book.key] || 0 }}</span>
                            </div>
                        </button>
                    </div>
                </aside>

                <main class="min-w-0 space-y-3">
                    <div class="flex flex-wrap items-center gap-2 border-l-4 border-accent bg-white/80 p-3 dark:bg-slate-800/80">
                        <div class="mr-auto">
                            <h3 class="font-mono text-lg font-bold">{{ activeProfile }}</h3>
                            <p class="text-xs text-slate-400">{{ appendProfiles.includes(activeProfile) ? '公共規則組：會成為 Excel 導入預設啟用的規則組。' : '規則組' }}</p>
                        </div>
                        <button v-if="canManageProfiles" class="toolbar-button" @click="renameProfile">全局改名</button>
                        <button v-if="activeProfile && canEditActiveProfile" class="border border-red-300 px-3 py-2 text-xs font-bold text-red-600" @click="deleteProfile">刪除規則組</button>
                    </div>

                    <nav class="overflow-x-auto border-b-2 border-slate-200 dark:border-slate-700">
                        <div class="flex min-w-max gap-1">
                            <button v-for="book in BOOKS" :key="book.key" class="border-x border-t px-3 py-2 text-xs font-bold"
                                :class="activeKey === book.key ? 'border-accent bg-accent text-white' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                @click="activeKey = book.key; selectedIds = new Set(); ruleSearch = ''">
                                {{ book.label }} <span class="opacity-70">{{ activeProfileCounts[book.key] }}</span>
                            </button>
                        </div>
                    </nav>

                    <div class="space-y-3 border border-slate-200 bg-white/80 p-3 dark:border-slate-700 dark:bg-slate-800/80">
                        <div class="flex flex-wrap gap-2">
                            <input v-model="ruleSearch" placeholder="搜尋目前規則（只過濾顯示）" class="min-w-[15rem] flex-1 border-2 border-slate-200 p-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                            <button class="toolbar-button" @click="addRow()">新增一行</button>
                            <button class="toolbar-button" @click="selectVisible">全選顯示</button>
                            <button :disabled="!selectedIds.size" class="border border-red-300 px-3 py-2 text-xs text-red-600 disabled:opacity-40" @click="deleteSelected">刪除所選 {{ selectedIds.size || '' }}</button>
                            <button class="toolbar-button" @click="showPaste = !showPaste">批量粘貼 TSV</button>
                        </div>

                        <div v-if="showPaste" class="border-l-4 border-amber-400 bg-amber-50 p-3 dark:bg-amber-950/20">
                            <div v-if="activeBook.tone" class="mb-2 flex items-center gap-2 text-xs">
                                聲類
                                <select v-model="pasteCategory" class="border p-1 dark:bg-slate-900"><option v-for="category in CATEGORIES" :key="category">{{ category }}</option></select>
                                <span>每行粘貼「原調 Tab 新調」</span>
                            </div>
                            <p v-else class="mb-2 text-xs text-slate-500">每行六欄：輸入聲母、韻核、韻尾、輸出聲母、韻核、韻尾；可加第七欄 <code>!</code>。多值用 <code>|</code>，會顯示成標籤。</p>
                            <textarea v-model="pasteText" rows="5" class="w-full border p-2 font-mono text-xs dark:bg-slate-900" placeholder="從 Excel 選取儲存格後直接粘貼"></textarea>
                            <button class="mt-2 bg-accent px-3 py-1.5 text-xs font-bold text-white" @click="applyTsv">加入目前規則組</button>
                        </div>

                        <p v-if="!activeBook.tone" class="text-xs text-slate-500">
                            拖動左側把手排序。特殊值可用上方快捷按鈕；直接在下方輸入即為具體值，多值用 <code>|</code>。輸入框留空等同空值／置空。「強制覆寫」會覆蓋先前規則已產生的欄位。
                        </p>

                        <div v-if="!activeBook.tone" class="max-h-[58vh] overflow-auto border border-slate-200 dark:border-slate-700">
                            <table class="w-full min-w-[780px] border-collapse text-xs">
                                <thead class="sticky top-0 z-10 bg-slate-100 text-left dark:bg-slate-900">
                                    <tr>
                                        <th class="w-14 p-2">選取</th>
                                        <th v-for="label in [`${activeBook.input} 聲母`, `${activeBook.input} 韻核`, `${activeBook.input} 韻尾`, `${activeBook.output} 聲母`, `${activeBook.output} 韻核`, `${activeBook.output} 韻尾`]" :key="label" class="p-2">{{ label }}</th>
                                        <th class="w-24 p-2">強制覆寫</th>
                                        <th class="w-24 p-2">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in visibleRows" :key="row.id" draggable="true" class="border-t border-slate-200 dark:border-slate-700"
                                        @dragstart="onDragStart(row)" @dragover.prevent @drop="onDrop(row)">
                                        <td class="p-1 text-center">
                                            <span class="mr-1 cursor-grab text-slate-400" title="拖動排序">⠿</span>
                                            <input type="checkbox" :checked="selectedIds.has(row.id)" @change="toggleSelected(row.id)" />
                                        </td>
                                        <td v-for="index in 6" :key="index" class="rule-cell p-1">
                                            <div class="grid grid-cols-2 gap-1">
                                                <button type="button" class="field-mode-button"
                                                    :class="{ 'field-mode-button-active': row.fields[index - 1] === '*' }"
                                                    @click="setFieldSpecial(row, index - 1, '*')">
                                                    {{ index <= 3 ? '任意' : '保持' }}
                                                </button>
                                                <button type="button" class="field-mode-button"
                                                    :class="{ 'field-mode-button-active': row.fields[index - 1] === '' }"
                                                    @click="setFieldSpecial(row, index - 1, '')">
                                                    {{ index <= 3 ? '空值' : '置空' }}
                                                </button>
                                            </div>
                                            <input :value="row.fields[index - 1] === '*' ? '' : row.fields[index - 1]"
                                                class="rule-value-input mt-1 w-full border p-1 font-mono"
                                                :style="ruleValueStyle(row.fields[index - 1])"
                                                :placeholder="index <= 3 ? '具體值／值1|值2' : '指定值'"
                                                @input="row.fields[index - 1] = $event.target.value"
                                                @keydown.enter.prevent="addRow('舒聲', row)" @keydown.ctrl.d.prevent="duplicateRow(row)" />
                                            <div v-if="valueTags(row.fields[index - 1]).length > 1" class="mt-1 flex flex-wrap gap-1">
                                                <span v-for="tag in valueTags(row.fields[index - 1])" :key="tag"
                                                    class="rule-value-tag px-1 font-mono" :style="ruleValueStyle(tag)">{{ tag }}</span>
                                            </div>
                                        </td>
                                        <td class="p-1 text-center"><input v-model="row.force" type="checkbox" title="覆寫較早規則產生的欄位" /></td>
                                        <td class="whitespace-nowrap p-1">
                                            <button class="border px-2 py-1" title="複製" @click="duplicateRow(row)">⧉</button>
                                            <button class="ml-1 border border-red-300 px-2 py-1 text-red-600" title="刪除" @click="removeRow(row)">×</button>
                                        </td>
                                    </tr>
                                    <tr v-if="!visibleRows.length"><td colspan="9" class="p-8 text-center text-slate-400">目前規則組在本書沒有符合條件的規則</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="grid gap-4 xl:grid-cols-2">
                            <section v-for="category in CATEGORIES" :key="category" class="border border-slate-200 dark:border-slate-700">
                                <div class="flex items-center justify-between bg-slate-100 p-2 dark:bg-slate-900">
                                    <h4 class="font-bold">{{ category }}</h4>
                                    <button class="border px-2 py-1 text-xs" @click="addRow(category)">新增映射</button>
                                </div>
                                <table class="w-full text-xs">
                                    <thead><tr><th class="p-2 text-left">選取</th><th class="p-2 text-left">原調</th><th class="p-2 text-left">新調</th><th class="p-2"></th></tr></thead>
                                    <tbody>
                                        <tr v-for="row in visibleToneRows[category]" :key="row.id" draggable="true" class="border-t dark:border-slate-700"
                                            @dragstart="onDragStart(row)" @dragover.prevent @drop="onDrop(row)">
                                            <td class="p-1 text-center"><span class="mr-2 cursor-grab text-slate-400">⠿</span><input type="checkbox" :checked="selectedIds.has(row.id)" @change="toggleSelected(row.id)" /></td>
                                            <td class="p-1"><input v-model="row.from" class="rule-value-input w-full border p-1.5 font-mono" :style="ruleValueStyle(row.from)" @keydown.enter.prevent="addRow(category, row)" @keydown.ctrl.d.prevent="duplicateRow(row)" /></td>
                                            <td class="p-1"><input v-model="row.to" class="rule-value-input w-full border p-1.5 font-mono" :style="ruleValueStyle(row.to)" @keydown.enter.prevent="addRow(category, row)" @keydown.ctrl.d.prevent="duplicateRow(row)" /></td>
                                            <td class="p-1 whitespace-nowrap"><button class="border px-2 py-1" @click="duplicateRow(row)">⧉</button><button class="ml-1 border border-red-300 px-2 py-1 text-red-600" @click="removeRow(row)">×</button></td>
                                        </tr>
                                        <tr v-if="!visibleToneRows[category]?.length"><td colspan="4" class="p-6 text-center text-slate-400">沒有映射</td></tr>
                                    </tbody>
                                </table>
                            </section>
                        </div>

                        <details class="border-t border-slate-200 pt-3 text-xs dark:border-slate-700">
                            <summary class="cursor-pointer font-bold">高級操作：CSV／整包 JSON</summary>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button class="toolbar-button" @click="exportCurrentCsv">匯出本書 CSV</button>
                                <button class="toolbar-button" @click="csvInput?.click()">匯入 CSV 並取代本書</button>
                                <button class="toolbar-button" @click="exportFullJson">匯出整包 JSON</button>
                                <button v-if="canManageProfiles" class="toolbar-button" @click="jsonInput?.click()">匯入整包 JSON</button>
                                <input ref="csvInput" type="file" accept=".csv,text/csv" class="hidden" @change="importCurrentCsv" />
                                <input ref="jsonInput" type="file" accept=".json,application/json" class="hidden" @change="importFullJson" />
                            </div>
                        </details>
                    </div>
                    <section class="min-w-0 border border-slate-200 bg-white/80 dark:border-slate-700 dark:bg-slate-800/80">
                    <button class="flex w-full items-center justify-between p-3 text-left font-bold lg:cursor-default" @click="showPlayground = !showPlayground">
                        <span>真實轉換測試場</span><span class="lg:hidden">{{ showPlayground ? '−' : '+' }}</span>
                    </button>
                    <div v-show="showPlayground" class="border-t border-slate-200 p-3 dark:border-slate-700">
                        <div class="grid gap-3 xl:grid-cols-[minmax(16rem,0.8fr)_minmax(0,1.2fr)]">
                            <div class="space-y-3">
                        <div>
                            <label class="text-xs font-bold">規則列表（首項負責聲調）</label>
                            <div class="mt-1 space-y-1">
                                <div v-for="(profile, index) in testProfiles" :key="`${profile}-${index}`" draggable="true"
                                    class="flex items-center gap-1 border bg-slate-50 px-2 py-1 text-xs dark:bg-slate-900"
                                    @dragstart="dragTestIndex = index" @dragover.prevent @drop="dropTestProfile(index)">
                                    <span class="cursor-grab text-slate-400">⠿</span>
                                    <span class="min-w-0 flex-1 truncate font-mono">{{ profile }}</span>
                                    <span v-if="index === 0" class="text-[10px] text-accent">主</span>
                                    <button :disabled="index === 0" @click="moveTestProfile(index, -1)">↑</button>
                                    <button :disabled="index === testProfiles.length - 1" @click="moveTestProfile(index, 1)">↓</button>
                                    <button class="text-red-500" @click="testProfiles.splice(index, 1)">×</button>
                                </div>
                            </div>
                            <div class="mt-1 flex">
                                <select v-model="testProfileToAdd" class="min-w-0 flex-1 border p-1 text-xs dark:bg-slate-900">
                                    <option value="">選擇規則組</option>
                                    <option v-for="profile in allProfiles.filter(item => !testProfiles.includes(item))" :key="profile" :value="profile">{{ profile }}</option>
                                </select>
                                <button class="border border-l-0 px-2 text-xs" @click="addTestProfile">加入</button>
                            </div>
                        </div>
                        <label class="block text-xs font-bold">輸入文本
                            <textarea v-model="testInput" rows="5" class="mt-1 w-full border p-2 font-mono font-normal dark:bg-slate-900" placeholder="多個音節可用空格或換行分隔"></textarea>
                        </label>
                        <label class="block text-xs font-bold">方向
                            <select v-model="testDirection" class="mt-1 w-full border p-2 font-normal dark:bg-slate-900">
                                <option v-for="option in DIRECTION_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </label>
                        <button class="w-full bg-accent px-3 py-2 text-sm font-bold text-white" @click="runTest">轉換</button>
                            </div>
                            <div class="space-y-3">
                        <label class="block text-xs font-bold">草稿結果
                            <textarea :value="testOutputText" readonly rows="5" class="mt-1 w-full border bg-slate-50 p-2 font-mono font-normal dark:bg-slate-900"></textarea>
                        </label>
                        <div v-if="testHasDifference" class="border-l-4 border-amber-500 bg-amber-50 p-2 text-xs dark:bg-amber-950/30">
                            <b>與線上啟用版本不同</b>
                            <pre class="mt-1 whitespace-pre-wrap font-mono">{{ activeTestOutputText }}</pre>
                        </div>
                        <div v-if="testRan" class="space-y-1">
                            <details v-for="item in testResults" :key="item.input" class="border p-2 text-xs">
                                <summary class="cursor-pointer font-mono">{{ item.input }} {{ item.ok ? `→ ${item.output}` : '（錯誤）' }}</summary>
                                <p v-if="!item.ok" class="mt-2 text-red-600">{{ item.error }}</p>
                                <ol v-else class="mt-2 space-y-1">
                                    <li v-for="(hit, index) in item.trace" :key="index" class="border-l-2 border-slate-300 pl-2 dark:border-slate-600">
                                        <span v-if="hit.type === 'segment'">{{ bookLabel(hit.book) }} · {{ hit.profile }} · 第 {{ hit.index }} 條</span>
                                        <span v-else>{{ hit.book }} · {{ hit.profile }} · {{ hit.category }}</span>
                                        <span v-if="hit.force" class="ml-1 text-accent">強制覆寫</span>
                                        <div v-for="change in hit.changes" :key="change.field" class="font-mono text-slate-500">{{ change.field }}：{{ change.from || '∅' }} → {{ change.to || '∅' }}</div>
                                    </li>
                                    <li v-if="!item.trace.length" class="text-slate-400">未命中自訂規則，只使用基本映射。</li>
                                </ol>
                            </details>
                        </div>
                    </div>
                        </div>
                    </div>
                    </section>
                </main>
            </div>

            <div v-if="validationIssues.length" class="grid gap-3 md:grid-cols-2">
                <section v-if="validationErrors.length" class="border-l-4 border-red-500 bg-red-50 p-3 text-xs dark:bg-red-950/20">
                    <h3 class="font-bold">需要修正（{{ validationErrors.length }}）</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5"><li v-for="issue in validationErrors.slice(0, 20)" :key="issue.message">{{ issue.message }}</li></ul>
                </section>
                <section v-if="validationWarnings.length" class="border-l-4 border-amber-500 bg-amber-50 p-3 text-xs dark:bg-amber-950/20">
                    <h3 class="font-bold">提示（{{ validationWarnings.length }}）</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5"><li v-for="issue in validationWarnings.slice(0, 20)" :key="issue.message">{{ issue.message }}</li></ul>
                </section>
            </div>

            <details class="border border-slate-200 bg-white/80 p-3 text-xs dark:border-slate-700 dark:bg-slate-800/80">
                <summary class="cursor-pointer font-bold">版本記錄（目前：{{ current?.version }}）</summary>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in history" :key="item.id" class="border-l-2 border-slate-200 pl-2 dark:border-slate-700">
                        <div class="font-mono font-bold">{{ item.version }}</div><div class="text-slate-400">{{ item.created_at }}</div>
                        <span v-if="item.is_active" class="text-emerald-600">目前啟用</span>
                    </div>
                </div>
            </details>
        </template>

        <div v-if="showPublish" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="showPublish = false">
            <section class="max-h-[85vh] w-full max-w-3xl overflow-auto border-2 border-slate-900 bg-white p-5 shadow-[8px_8px_0_rgba(0,0,0,.35)] dark:border-slate-500 dark:bg-slate-900">
                <h3 class="text-lg font-bold">發布差異：{{ version }}</h3>
                <p class="mt-1 text-xs text-slate-500">建立後會成為新的不可變啟用版本，不會重算既有字表。</p>
                <div v-if="publishDiff.length" class="mt-4 overflow-auto border">
                    <table class="w-full min-w-[620px] text-xs">
                        <thead class="bg-slate-100 dark:bg-slate-800"><tr><th class="p-2 text-left">規則組</th><th class="p-2 text-left">規則書</th><th>新增</th><th>修改</th><th>刪除</th><th>重排</th></tr></thead>
                        <tbody><tr v-for="(row, index) in publishDiff" :key="index" class="border-t dark:border-slate-700"><td class="p-2 font-mono">{{ row.profile }}</td><td class="p-2">{{ row.book }}</td><td class="text-center">{{ row.added }}</td><td class="text-center">{{ row.modified }}</td><td class="text-center">{{ row.deleted }}</td><td class="text-center">{{ row.reordered ? '是' : '—' }}</td></tr></tbody>
                    </table>
                </div>
                <p v-else class="mt-4 border p-4 text-center text-slate-400">規則內容沒有變化。</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button class="toolbar-button" @click="showPublish = false">取消</button>
                    <button :disabled="saving" class="bg-accent px-4 py-2 text-sm font-bold text-white disabled:opacity-40" @click="save">{{ saving ? '建立中…' : '確認建立並啟用' }}</button>
                </div>
            </section>
        </div>
    </section>
</template>

<style scoped>
.toolbar-button {
    border: 1px solid rgb(203 213 225);
    padding: .5rem .75rem;
    font-size: .75rem;
    font-weight: 600;
}
.toolbar-button:hover:not(:disabled) {
    border-color: #D32913;
    color: #D32913;
}
.toolbar-button:disabled {
    opacity: .4;
}
.rule-cell {
    min-width: 6.5rem;
    vertical-align: top;
}
.rule-value-input,
.rule-value-tag {
    background-color: var(--rule-value-bg);
    color: rgb(30 41 59);
    transition: background-color .12s ease;
}
:global(.dark) .rule-value-input,
:global(.dark) .rule-value-tag {
    background-color: var(--rule-value-bg-dark);
    color: rgb(241 245 249);
}
.rule-value-input:focus {
    border-color: #D32913;
    outline: 1px solid #D32913;
}
.field-mode-button {
    min-width: 0;
    border: 1px solid rgb(203 213 225);
    padding: .2rem .25rem;
    overflow: hidden;
    color: rgb(100 116 139);
    font-size: .65rem;
    line-height: 1rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.field-mode-button:hover {
    border-color: #D32913;
    color: #D32913;
}
.field-mode-button-active {
    border-color: #D32913;
    background: rgb(211 41 19 / .1);
    color: #D32913;
    font-weight: 700;
}
</style>
