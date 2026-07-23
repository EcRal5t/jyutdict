const MASK_64 = (1n << 64n) - 1n
const BLAKE2B_IV = [
    0x6a09e667f3bcc908n, 0xbb67ae8584caa73bn,
    0x3c6ef372fe94f82bn, 0xa54ff53a5f1d36f1n,
    0x510e527fade682d1n, 0x9b05688c2b3e6c1fn,
    0x1f83d9abfb41bd6bn, 0x5be0cd19137e2179n,
]
const BLAKE2B_SIGMA = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
    [14, 10, 4, 8, 9, 15, 13, 6, 1, 12, 0, 2, 11, 7, 5, 3],
    [11, 8, 12, 0, 5, 2, 15, 13, 10, 14, 3, 6, 7, 1, 9, 4],
    [7, 9, 3, 1, 13, 12, 11, 14, 2, 6, 5, 10, 4, 0, 15, 8],
    [9, 0, 5, 7, 2, 4, 10, 15, 14, 1, 11, 12, 6, 8, 3, 13],
    [2, 12, 6, 10, 0, 11, 8, 3, 4, 13, 7, 5, 15, 14, 1, 9],
    [12, 5, 1, 15, 14, 13, 4, 10, 0, 7, 6, 3, 9, 2, 8, 11],
    [13, 11, 7, 14, 12, 1, 3, 9, 5, 0, 15, 4, 8, 6, 2, 10],
    [6, 15, 14, 9, 11, 3, 0, 8, 12, 2, 13, 7, 1, 4, 10, 5],
    [10, 2, 8, 4, 7, 6, 1, 5, 15, 11, 9, 14, 3, 12, 13, 0],
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
    [14, 10, 4, 8, 9, 15, 13, 6, 1, 12, 0, 2, 11, 7, 5, 3],
]

const add64 = (...values) => values.reduce((total, value) => (total + value) & MASK_64, 0n)
const rotateRight64 = (value, bits) => {
    const shift = BigInt(bits)
    return ((value >> shift) | (value << (64n - shift))) & MASK_64
}

function readUint64LE(bytes, offset) {
    let value = 0n
    for (let index = 0; index < 8; index += 1) {
        value |= BigInt(bytes[offset + index] || 0) << BigInt(index * 8)
    }
    return value
}

function mix(values, a, b, c, d, left, right) {
    values[a] = add64(values[a], values[b], left)
    values[d] = rotateRight64(values[d] ^ values[a], 32)
    values[c] = add64(values[c], values[d])
    values[b] = rotateRight64(values[b] ^ values[c], 24)
    values[a] = add64(values[a], values[b], right)
    values[d] = rotateRight64(values[d] ^ values[a], 16)
    values[c] = add64(values[c], values[d])
    values[b] = rotateRight64(values[b] ^ values[c], 63)
}

function compressBlake2b(hash, block, count, last) {
    const message = Array.from({ length: 16 }, (_, index) => readUint64LE(block, index * 8))
    const values = [...hash, ...BLAKE2B_IV]
    values[12] ^= count & MASK_64
    values[13] ^= count >> 64n
    if (last) values[14] ^= MASK_64
    for (const sigma of BLAKE2B_SIGMA) {
        mix(values, 0, 4, 8, 12, message[sigma[0]], message[sigma[1]])
        mix(values, 1, 5, 9, 13, message[sigma[2]], message[sigma[3]])
        mix(values, 2, 6, 10, 14, message[sigma[4]], message[sigma[5]])
        mix(values, 3, 7, 11, 15, message[sigma[6]], message[sigma[7]])
        mix(values, 0, 5, 10, 15, message[sigma[8]], message[sigma[9]])
        mix(values, 1, 6, 11, 12, message[sigma[10]], message[sigma[11]])
        mix(values, 2, 7, 8, 13, message[sigma[12]], message[sigma[13]])
        mix(values, 3, 4, 9, 14, message[sigma[14]], message[sigma[15]])
    }
    for (let index = 0; index < 8; index += 1) {
        hash[index] = (hash[index] ^ values[index] ^ values[index + 8]) & MASK_64
    }
}

export function blake2bBytes(value, outputLength = 8) {
    const input = new TextEncoder().encode(String(value))
    const hash = [...BLAKE2B_IV]
    hash[0] ^= 0x01010000n ^ BigInt(outputLength)
    let offset = 0
    let count = 0n
    while (offset + 128 < input.length) {
        const block = input.slice(offset, offset + 128)
        count += BigInt(block.length)
        compressBlake2b(hash, block, count, false)
        offset += 128
    }
    const finalBlock = new Uint8Array(128)
    finalBlock.set(input.slice(offset))
    count += BigInt(input.length - offset)
    compressBlake2b(hash, finalBlock, count, true)
    const output = new Uint8Array(outputLength)
    for (let index = 0; index < outputLength; index += 1) {
        output[index] = Number((hash[Math.floor(index / 8)] >> BigInt((index % 8) * 8)) & 0xffn)
    }
    return output
}

export function normaliseCheckedFinal(final) {
    if (final.endsWith('p')) return `${final.slice(0, -1)}m`
    if (final.endsWith('t')) return `${final.slice(0, -1)}n`
    if (final.endsWith('k')) return `${final.slice(0, -1)}ng`
    return final
}

export function phonologyColourFor(value) {
    const digest = blake2bBytes(value, 8)
    const hue = ((digest[0] << 8) | digest[1]) % 360
    const lightSaturation = 56 + digest[2] % 13
    const darkSaturation = 42 + digest[2] % 11
    return {
        accent: `hsl(${hue} ${lightSaturation}% 43%)`,
        accentDark: `hsl(${hue} ${Math.max(darkSaturation - 5, 34)}% 68%)`,
        surface: `hsl(${hue} ${52 + digest[3] % 9}% 91%)`,
        surfaceDark: `hsl(${hue} 18% 21%)`,
        stripe: `hsl(${hue} ${lightSaturation}% 43% / 0.24)`,
        stripeDark: `hsl(${hue} ${Math.max(darkSaturation - 5, 34)}% 68% / 0.18)`,
    }
}
