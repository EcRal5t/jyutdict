import * as XLSX from 'xlsx'
import { columnSpecToIndexes, convertGrid } from '@/utils/commonConverter.js'
import { buildPhonologyPayload } from '@/utils/phonologyBuilder.js'

const postProgress = (requestId, phase, percent, message) => {
    self.postMessage({ type: 'progress', requestId, phase, percent, message })
}

function getDenseCell(sheet, row, column) {
    if (Array.isArray(sheet['!data'])) return sheet['!data'][row]?.[column]
    return sheet[XLSX.utils.encode_cell({ r: row, c: column })]
}

function readWorkbook(buffer, config, requestId) {
    postProgress(requestId, 'workbook', 5, '正在讀取活頁簿')
    const workbook = XLSX.read(buffer, {
        type: 'array',
        dense: true,
        cellFormula: true,
        cellNF: false,
        cellText: false,
        cellDates: false,
    })
    if (!workbook.SheetNames.length) throw new Error('活頁簿沒有工作表')
    const requested = String(config.sheetName || '').trim()
    const candidates = [requested, 'Sheet1', '主表', '字表', workbook.SheetNames[0]].filter(Boolean)
    const sheetName = candidates.find(name => workbook.SheetNames.includes(name))
    const sheet = workbook.Sheets[sheetName]
    if (!sheet?.['!ref']) throw new Error(`工作表「${sheetName}」沒有資料`)
    const range = XLSX.utils.decode_range(sheet['!ref'])
    const rowCount = range.e.r + 1
    const columnCount = range.e.c + 1
    if (rowCount > 100001) throw new Error(`工作表共有 ${rowCount - 1} 行，超過 100000 行上限`)
    if (columnCount > 256) throw new Error(`工作表共有 ${columnCount} 欄，超過 256 欄上限`)

    const mappedColumns = uniqueColumns(config)
    if (!mappedColumns.length || mappedColumns.some(column => column < 0 || column >= 256)) {
        throw new Error('欄位設定必須使用 A 至 IV 範圍內的 Excel 欄名')
    }
    const startRow = Math.max(2, Number(config.startRow || 2)) - 1
    const missingFormulaCache = []
    const formulaErrors = []
    for (let row = startRow; row <= range.e.r; row += 1) {
        for (const column of mappedColumns) {
            const cell = getDenseCell(sheet, row, column)
            if (!cell) continue
            if (cell.f && (cell.v === undefined || cell.v === null)) {
                missingFormulaCache.push(XLSX.utils.encode_cell({ r: row, c: column }))
            }
            if (cell.t === 'e') formulaErrors.push(XLSX.utils.encode_cell({ r: row, c: column }))
        }
        if (missingFormulaCache.length >= 20 || formulaErrors.length >= 20) break
    }
    if (missingFormulaCache.length) {
        throw new Error(`公式缺少快取值：${missingFormulaCache.join('、')}。請先用 Excel/LibreOffice 重新計算並儲存。`)
    }
    if (formulaErrors.length) {
        throw new Error(`映射欄含公式錯誤：${formulaErrors.join('、')}`)
    }

    postProgress(requestId, 'workbook', 25, `正在抽取「${sheetName}」`)
    const rows = Array.from({ length: rowCount }, (_, row) => {
        const values = []
        for (const column of mappedColumns) {
            const cell = getDenseCell(sheet, row, column)
            values[column] = !cell || cell.v === undefined || cell.v === null
                ? ''
                : String(cell.v)
        }
        return values
    })
    return {
        rows,
        sheetName,
        sheetNames: workbook.SheetNames,
        warning: requested && requested !== sheetName
            ? `找不到工作表「${requested}」，已改用「${sheetName}」`
            : '',
    }
}

function uniqueColumns(config) {
    return [...new Set([
        ...columnSpecToIndexes(config.charColumn),
        ...columnSpecToIndexes(config.pronColumns),
        ...columnSpecToIndexes(config.secondaryPronColumns),
        ...columnSpecToIndexes(config.meaningColumns),
        ...columnSpecToIndexes(config.ipaColumns),
    ])]
}

self.onmessage = event => {
    const { type, requestId } = event.data || {}
    try {
        if (type === 'parse-workbook') {
            const { buffer, config, ruleBundle } = event.data
            const workbook = readWorkbook(buffer, config, requestId)
            postProgress(requestId, 'convert', 40, '正在套用讀音與轉寫規則')
            const converted = convertGrid(workbook.rows, {
                ...config,
                sheetName: workbook.sheetName,
            }, ruleBundle)
            if (workbook.warning) converted.warnings.unshift(workbook.warning)
            postProgress(requestId, 'convert', 100, '轉換完成')
            self.postMessage({
                type: 'result',
                requestId,
                result: {
                    ...converted,
                    sourceSheet: workbook.sheetName,
                    sheetNames: workbook.sheetNames,
                },
            })
            return
        }
        if (type === 'build-phonology') {
            postProgress(requestId, 'phonology', 10, '正在配對中古音地位')
            const payload = buildPhonologyPayload(
                event.data.entries,
                event.data.middleChinese,
                event.data.area
            )
            postProgress(requestId, 'phonology', 100, '四張音系表已生成')
            self.postMessage({ type: 'result', requestId, result: payload })
            return
        }
        throw new Error('未知的背景工作')
    } catch (error) {
        self.postMessage({
            type: 'error',
            requestId,
            error: error?.message || String(error),
        })
    }
}
