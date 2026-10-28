import { sheetDirectoryPath } from './sheetEntry.js'

export function resolveSeo(route, catalog) {
  const page = catalog.pages[route.path]
  let meta = page ? { ...page, noindex: false } : {
    title: `找不到頁面｜${catalog.name}`, description: catalog.description,
    canonical: route.path, noindex: true,
  }
  const chara = typeof route.query.chara === 'string' ? route.query.chara.trim() : ''
  const contentQueryKeys = Object.keys(route.query).filter(key => !key.startsWith('utm_') && !['gclid', 'fbclid'].includes(key))
  if (route.path === '/detail' && chara) {
    meta = { title: `${chara}的粵語讀音與各地方音｜${catalog.name}`,
      description: `查詢「${chara}」在各地的粵語讀音、擴展粵拼與 IPA，對照歷史韻書記錄。`,
      canonical: `/detail?chara=${encodeURIComponent(chara)}`,
      noindex: !/^[\p{Script=Han}〇]$/u.test(chara) || contentQueryKeys.some(key => key !== 'chara'),
    }
  } else if (route.name === 'sheet-entry') {
    const key = String(route.params.key || '')
    meta = { title: `泛粵字表詞條｜${catalog.name}`, description: '泛粵字表的完整釋義、例詞與各地讀音記錄。', canonical: `/sheet/entry/${key}`, noindex: true }
  } else if (route.name === 'sheet-directory') {
    const page = Number(route.params.page || 1)
    meta = { title: `泛粵詞條目錄 · 第 ${page} 頁｜${catalog.name}`, description: `瀏覽泛粵特色字、釋義與方音的固定詞條頁，第 ${page} 頁。`, canonical: sheetDirectoryPath(page), noindex: true }
  } else if (route.name === 'location-article') {
    const name = String(route.params.locationName || '')
    meta = { title: `${name}粵語介紹｜${catalog.name}`, description: `閱讀${name}的粵語地點介紹、語音特點及相關資料。`, canonical: `/location-article/${encodeURIComponent(name)}`, noindex: false }
  } else if (route.name === 'location-phonology' && route.params.areaId) {
    meta = { ...catalog.pages['/location-phonology'], canonical: `/location-phonology/${encodeURIComponent(route.params.areaId)}`, noindex: false }
  }
  if (['admin', 'user-center'].includes(route.name)) {
    meta.title = `${route.name === 'admin' ? '網站管理' : '用戶中心'}｜${catalog.name}`
    meta.noindex = true
  }
  if (route.name === 'not-found') meta.noindex = true
  // Search/filter combinations are useful interactions, not separate index entries.
  if (route.path === '/detail' && 'chara' in route.query && typeof route.query.chara !== 'string') meta.noindex = true
  if (['/sheet', '/pronunciation', '/locations', '/phonology'].includes(route.path) && contentQueryKeys.length) meta.noindex = true
  return { ...meta, url: catalog.origin + meta.canonical }
}

let currentMeta

export function updateCurrentSeo(overrides, expectedPath) {
  if (window.location.pathname !== expectedPath || !currentMeta) return
  updateSeo({ ...currentMeta, ...overrides })
}

export function updateSeo(meta) {
  currentMeta = meta
  document.documentElement.lang = 'zh-Hant'
  document.title = meta.title
  const tag = (selector, attributes) => {
    let element = document.head.querySelector(selector)
    if (!element) { element = document.createElement(selector.startsWith('link') ? 'link' : 'meta'); document.head.appendChild(element) }
    for (const [key, value] of Object.entries(attributes)) element.setAttribute(key, value)
  }
  tag('meta[name="description"]', { name: 'description', content: meta.description })
  tag('meta[name="robots"]', { name: 'robots', content: meta.noindex ? 'noindex,follow' : 'index,follow,max-image-preview:large' })
  tag('link[rel="canonical"]', { rel: 'canonical', href: meta.url })
  for (const [property, content] of Object.entries({ 'og:title': meta.title, 'og:description': meta.description, 'og:url': meta.url, 'og:type': 'website', 'og:locale': 'zh_HK', 'og:site_name': '泛粵大典 Jyutdict' })) tag(`meta[property="${property}"]`, { property, content })
  for (const [name, content] of Object.entries({ 'twitter:card': 'summary', 'twitter:title': meta.title, 'twitter:description': meta.description })) tag(`meta[name="${name}"]`, { name, content })
  // The server's entry-specific JSON-LD must not linger after a client-side navigation.
  const structured = document.getElementById('jyutdict-structured-data')
  if (structured && (structured.dataset.url !== meta.url || meta.noindex)) structured.remove()
  if (meta.term && !meta.noindex) {
    const script = document.getElementById('jyutdict-structured-data') || document.createElement('script')
    script.id = 'jyutdict-structured-data'
    script.type = 'application/ld+json'
    script.dataset.url = meta.url
    script.textContent = JSON.stringify({ '@context': 'https://schema.org', '@type': 'DefinedTerm', '@id': meta.url, url: meta.url, name: meta.term, description: meta.description,
      inDefinedTermSet: { '@type': 'DefinedTermSet', name: '泛粵字表', url: new URL('/sheet/entries', meta.url).href } })
    if (!script.isConnected) document.head.appendChild(script)
  }
}
