let requestSequence = 0

export function runCommonWorker(type, data, { transfer = [], onProgress = null } = {}) {
    const worker = new Worker(
        new URL('../workers/commonSheet.worker.js', import.meta.url),
        { type: 'module' }
    )
    const requestId = `${Date.now()}-${++requestSequence}`
    return new Promise((resolve, reject) => {
        worker.onmessage = event => {
            if (event.data?.requestId !== requestId) return
            if (event.data.type === 'progress') {
                onProgress?.(event.data)
                return
            }
            worker.terminate()
            if (event.data.type === 'result') resolve(event.data.result)
            else reject(new Error(event.data.error || '背景工作失敗'))
        }
        worker.onerror = event => {
            worker.terminate()
            reject(new Error(event.message || '背景工作失敗'))
        }
        worker.postMessage({ type, requestId, ...data }, transfer)
    })
}
