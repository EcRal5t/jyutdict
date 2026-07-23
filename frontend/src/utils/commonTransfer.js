const encoder = new TextEncoder()

export function canonicalRowsNdjson(rows) {
    return `${rows.map(row => JSON.stringify({
        row_no: Number(row.row_no),
        display_order: Number(row.display_order),
        chara: String(row.chara),
        initial: String(row.initial),
        nuclei: String(row.nuclei),
        coda: String(row.coda),
        tone: String(row.tone),
        ipa: String(row.ipa),
        note: String(row.note),
        alt_group: row.alt_group == null ? null : Number(row.alt_group),
        source_row: row.source_row == null ? null : Number(row.source_row),
    })).join('\n')}\n`
}

export async function sha256Hex(value) {
    const data = typeof value === 'string' ? encoder.encode(value) : value
    const buffer = data instanceof Blob ? await data.arrayBuffer() :
        (data instanceof ArrayBuffer ? data : data.buffer.slice(data.byteOffset, data.byteOffset + data.byteLength))
    const digest = await crypto.subtle.digest('SHA-256', buffer)
    return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('')
}

export async function gzipText(text) {
    if (typeof CompressionStream === 'undefined') {
        throw new Error('目前瀏覽器不支援 CompressionStream，請改用最新版本的 Chrome、Edge 或 Firefox')
    }
    const stream = new Blob([encoder.encode(text)]).stream()
        .pipeThrough(new CompressionStream('gzip'))
    return new Blob([await new Response(stream).arrayBuffer()], { type: 'application/gzip' })
}

export async function prepareImportTransfer(rows, chunkRows = 750, maxCompressedBytes = 1048576) {
    const ndjson = canonicalRowsNdjson(rows)
    const contentHash = await sha256Hex(ndjson)
    const chunks = []
    async function appendChunk(rowsInChunk) {
        const payload = await gzipText(canonicalRowsNdjson(rowsInChunk))
        if (payload.size > maxCompressedBytes && rowsInChunk.length > 1) {
            const middle = Math.ceil(rowsInChunk.length / 2)
            await appendChunk(rowsInChunk.slice(0, middle))
            await appendChunk(rowsInChunk.slice(middle))
            return
        }
        if (payload.size > maxCompressedBytes) {
            throw new Error(`第 ${rowsInChunk[0].source_row || rowsInChunk[0].row_no} 行壓縮後仍超過伺服器分塊上限`)
        }
        chunks.push({
            number: chunks.length,
            rowCount: rowsInChunk.length,
            payload,
            hash: await sha256Hex(payload),
        })
    }
    for (let start = 0; start < rows.length; start += chunkRows) {
        await appendChunk(rows.slice(start, start + chunkRows))
    }
    return { contentHash, chunks, uncompressedBytes: encoder.encode(ndjson).byteLength }
}

export async function prepareJsonGzip(payload) {
    const text = JSON.stringify(payload)
    return {
        payload: await gzipText(text),
        hash: await sha256Hex(text),
        uncompressedBytes: encoder.encode(text).byteLength,
    }
}
