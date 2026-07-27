export function normalizeLocationName(value) {
    return String(value || '')
        .normalize('NFKC')
        .replace(/[\s·・._\-—–()（）[\]【】]+/g, '')
        .toLowerCase()
}

export function locationDisplayName(area) {
    return [area.second, area.third].filter(Boolean).join('') || area.first || ''
}

function locationMatchScore(area, key) {
    const candidates = [
        [500, area.detailed_name],
        [400, locationDisplayName(area)],
        [350, [area.first, area.second, area.third].filter(Boolean).join('')],
        [300, area.third],
        [200, area.second],
        [100, area.first],
    ]
    return candidates.find(([, value]) => normalizeLocationName(value) === key)?.[0] || 0
}

export function findBestLocation(locations, localeName) {
    const key = normalizeLocationName(localeName)
    if (!key) return null

    const ranked = (locations || [])
        .map(area => ({ area, score: locationMatchScore(area, key) }))
        .filter(match => match.score > 0)
        .sort((left, right) => right.score - left.score)
    if (!ranked.length || ranked[1]?.score === ranked[0].score) return null
    return ranked[0].area
}
