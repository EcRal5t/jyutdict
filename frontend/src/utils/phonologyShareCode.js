export const PHONOLOGY_SHARE_CODE_PREFIX = 'JDP1:';
export const PHONOLOGY_SHARE_PAYLOAD_TYPE = 'jyutdict-phonology-options';
export const PHONOLOGY_SHARE_PAYLOAD_VERSION = 1;
export const PHONOLOGY_SHARE_MODES = ['efficient', 'simplified', 'complete'];

const bytesToBase64Url = (bytes) => {
    let binary = '';
    for (let offset = 0; offset < bytes.length; offset += 0x8000) {
        binary += String.fromCharCode(...bytes.subarray(offset, offset + 0x8000));
    }
    return btoa(binary).replaceAll('+', '-').replaceAll('/', '_').replace(/=+$/u, '');
};

const base64UrlToBytes = (value) => {
    const base64 = value.replaceAll('-', '+').replaceAll('_', '/');
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
    const binary = atob(padded);
    return Uint8Array.from(binary, character => character.charCodeAt(0));
};

const assertPayload = (payload) => {
    if (payload?.type !== PHONOLOGY_SHARE_PAYLOAD_TYPE
        || payload?.version !== PHONOLOGY_SHARE_PAYLOAD_VERSION) {
        throw new Error('不是可識別的粵語探針分享碼');
    }
    if (!PHONOLOGY_SHARE_MODES.includes(payload.mode)) {
        throw new Error('分享碼中的模式無效');
    }
    if (!payload.selections || typeof payload.selections !== 'object' || Array.isArray(payload.selections)) {
        throw new Error('分享碼中的選項格式無效');
    }
    for (const [pair, value] of Object.entries(payload.selections)) {
        if (![-1, 0, 1].includes(value)) throw new Error(`選項「${pair}」的值無效`);
    }
    return payload;
};

export const encodePhonologyShareCode = async (payload) => {
    if (typeof CompressionStream === 'undefined') throw new Error('瀏覽器不支援壓縮分享碼');
    const source = new TextEncoder().encode(JSON.stringify(payload));
    const stream = new Blob([source]).stream().pipeThrough(new CompressionStream('gzip'));
    const compressed = new Uint8Array(await new Response(stream).arrayBuffer());
    return `${PHONOLOGY_SHARE_CODE_PREFIX}${bytesToBase64Url(compressed)}`;
};

export const decodePhonologyShareCode = async (value) => {
    const trimmed = String(value || '').trim();
    let payload;
    if (trimmed.startsWith('{')) {
        payload = JSON.parse(trimmed);
    } else {
        if (!trimmed.startsWith(PHONOLOGY_SHARE_CODE_PREFIX)) {
            throw new Error('沒有可識別的分享碼');
        }
        if (typeof DecompressionStream === 'undefined') throw new Error('瀏覽器不支援解壓縮分享碼');
        const compressed = base64UrlToBytes(trimmed.slice(PHONOLOGY_SHARE_CODE_PREFIX.length));
        const stream = new Blob([compressed]).stream().pipeThrough(new DecompressionStream('gzip'));
        payload = JSON.parse(new TextDecoder().decode(await new Response(stream).arrayBuffer()));
    }
    return assertPayload(payload);
};
