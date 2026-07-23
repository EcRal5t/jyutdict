import adminApi from '@/api/admin.js'
import { runCommonWorker } from '@/utils/commonWorkerClient.js'
import { prepareJsonGzip } from '@/utils/commonTransfer.js'

let middleChinesePromise = null

function loadMiddleChinese() {
    if (!middleChinesePromise) {
        middleChinesePromise = fetch('/data/middle-chinese.v1.json', { cache: 'force-cache' })
            .then(response => {
                if (!response.ok) throw new Error('中古音資料載入失敗')
                return response.json()
            })
    }
    return middleChinesePromise
}

export async function rebuildLocationPhonology(areaId, onProgress = null) {
    const entries = []
    let after = 0
    let area = null
    do {
        onProgress?.({ phase: 'download', message: `正在讀取字表（已讀 ${entries.length} 行）` })
        const response = await adminApi.getPhonologyRebuildSource(areaId, after)
        if (!area) area = response.data.area
        if (Number(response.data.area.current_release_id) !== Number(area.current_release_id)) {
            throw new Error('下載期間字表版本已改變，請重新開始')
        }
        entries.push(...response.data.entries)
        after = response.data.next_after
    } while (after)
    const middleChinese = await loadMiddleChinese()
    const payload = await runCommonWorker('build-phonology', {
        entries,
        middleChinese,
        area,
    }, { onProgress })
    onProgress?.({ phase: 'compress', message: '正在壓縮音系表' })
    const prepared = await prepareJsonGzip(payload)
    onProgress?.({
        phase: 'upload',
        message: `正在發佈音系表（${Math.round(prepared.payload.size / 1024)} KB）`,
    })
    const response = await adminApi.publishPhonology(
        area.id,
        area.current_release_id,
        prepared.payload,
        prepared.hash
    )
    return { payload, report: response.data.report }
}

