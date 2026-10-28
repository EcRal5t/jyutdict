export const sheetEntryPath = key => /^>[0-9]{1,15}$/.test(String(key)) ? `/sheet/entry/${String(key).slice(1)}` : null
export const sheetDirectoryPath = page => `/sheet/entries${page > 1 ? `/${page}` : ''}`
export const sheetEntryLabel = row => /[^?？!！\s]/u.test((row['繁'] || '').trim()) ? row['繁'].trim() : '未定字頭'
export const sheetEntryIndexable = row => Boolean(sheetEntryPath(row['鍵']) && row['釋義']?.trim() && row['釋']?.trim())

export function sheetEntryMeta(row, siteName) {
  const label = sheetEntryLabel(row)
  const pron = (row['綜'] || '').trim()
  const meaning = (row['釋'] || row['釋義'] || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim()
  return {
    title: `${label}${pron ? `（${pron}）` : ''}的意思與各地讀音｜泛粵字表｜${siteName}`,
    description: Array.from(`${label}${pron ? `〔${pron}〕` : ''}：${meaning}。泛粵字表的完整釋義、例詞與各地讀音記錄。`).slice(0, 160).join(''),
    noindex: !sheetEntryIndexable(row),
    term: label,
  }
}
