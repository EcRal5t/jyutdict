import test from 'node:test'
import assert from 'node:assert/strict'
import { createSeoManifest } from './seo-build.js'
import { resolveSeo } from '../src/utils/seo.js'
import { sheetEntryPath, sheetEntryMeta, sheetEntryIndexable } from '../src/utils/sheetEntry.js'

const catalog = await createSeoManifest()
const route = (path, query = {}, name = '', params = {}) => ({ path, query, name, params })

test('generated public pages contain real text, unique canonicals and no hash routes', () => {
  for (const [path, page] of Object.entries(catalog.pages)) {
    if (path !== '/') assert.match(page.html, /<h1>/)
    assert.ok(page.description)
    assert.ok(!page.canonical.includes('#'))
    assert.ok(!page.canonical.startsWith('//'))
    assert.ok(page.title.includes('泛粵大典'))
  }
  assert.match(catalog.pages['/about/jpp'].html, /粵拼/)
  assert.match(catalog.pages['/articles/n-l'].html, /<table>/)
  assert.equal(catalog.pages['/about'].canonical, '/about/intro')
})

test('single-character entries normalize tracking but filtered and multi-character searches are not indexed', () => {
  assert.equal(resolveSeo(route('/detail', { chara: ' 粵 ', utm_source: 'share' }), catalog).url, 'https://jyutdict.org/detail?chara=%E7%B2%B5')
  assert.equal(resolveSeo(route('/detail', { chara: '𠮷' }), catalog).noindex, false)
  assert.equal(resolveSeo(route('/detail', { chara: '粵語' }), catalog).noindex, true)
  assert.equal(resolveSeo(route('/detail', { chara: '粵', areas: '1,2' }), catalog).noindex, true)
  assert.equal(resolveSeo(route('/pronunciation', { in: 'g', nu: 'aa' }), catalog).noindex, true)
  assert.equal(resolveSeo(route('/sheet', { utm_source: 'share', fbclid: 'example' }), catalog).noindex, false)
})

test('private pages and unknown routes cannot inherit indexable home metadata', () => {
  assert.equal(resolveSeo(route('/admin', {}, 'admin'), catalog).noindex, true)
  assert.equal(resolveSeo(route('/user', {}, 'user-center'), catalog).noindex, true)
  assert.equal(resolveSeo(route('/not-a-real-page', {}, 'not-found'), catalog).noindex, true)
  assert.equal(resolveSeo(route('/locations'), catalog).noindex, false)
})

test('location canonical preserves one path segment and drops tracking', () => {
  assert.equal(resolveSeo(route('/location-article/廣州', { utm_source: 'share' }, 'location-article', { locationName: '廣州' }), catalog).url,
    'https://jyutdict.org/location-article/%E5%BB%A3%E5%B7%9E')
})

test('sheet entry identity preserves leading zeros and ignores mutable glyph/pronunciation', () => {
  const row = { '鍵': '>030390861753651', '繁': '星', '綜': 'sing1!', '釋': '排行第七', '釋義': '排行第七：老～' }
  assert.equal(sheetEntryPath(row['鍵']), '/sheet/entry/030390861753651')
  assert.equal(sheetEntryPath('>1<script>'), null)
  assert.equal(sheetEntryPath('>1234567890123456'), null)
  assert.equal(sheetEntryPath(row['鍵']), sheetEntryPath({ ...row, '繁': '𠮷', '綜': 'new1' }['鍵']))
  assert.match(sheetEntryMeta(row, catalog.name).title, /星（sing1!）/)
  assert.equal(sheetEntryIndexable({ ...row, '釋': '' }), false)
  assert.equal(resolveSeo(route('/sheet/entry/030390861753651', {}, 'sheet-entry', { key: '030390861753651' }), catalog).canonical, '/sheet/entry/030390861753651')
  assert.equal(resolveSeo(route('/sheet/entries/2', {}, 'sheet-directory', { page: '2' }), catalog).canonical, '/sheet/entries/2')
})
