import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { marked } from 'marked'

const source = fileURLToPath(new URL('../src/data/', import.meta.url))
const readJson = async name => JSON.parse(await readFile(`${source}/${name}`, 'utf8'))
const text = html => html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
const escape = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

export async function createAboutManifest() {
  return Promise.all((await readJson('about_pages.json')).map(async page => ({
    ...page, content: await readFile(`${source}/markdown/${page.file}`, 'utf8'),
  })))
}

export async function createSeoManifest() {
  const config = await readJson('seo.json')
  const pages = {}
  for (const [path, page] of Object.entries(config.pages)) {
    pages[path] = { ...page, title: path === '/' ? page.title : `${page.title}｜${config.name}`, canonical: path,
      html: path === '/' ? '' : `<h1>${escape(page.heading || page.title.split('｜')[0])}</h1>` }
  }
  for (const page of await readJson('about_pages.json')) {
    const markdown = await readFile(`${source}/markdown/${page.file}`, 'utf8')
    const html = marked(markdown).replace(/(src=["']|href=["'])(?:\.\/)?img\//g, '$1/img/')
    const path = `/about/${page.id}`
    pages[path] = { title: `${page.title}｜${config.name}`, description: text(html).slice(0, 150), canonical: path, html: `<h1>${escape(page.title)}</h1>${html}` }
  }
  pages['/about'] = { ...pages['/about/intro'] }
  const articles = await readJson('articles.json')
  for (const article of articles) {
    const path = `/articles/${article.id}`
    const blocks = article.blocks.map(block => {
      if (block.type === 'html') return block.content
      if (block.type !== 'phonology_table') return ''
      return `<table>${block.caption ? `<caption>${escape(block.caption)}</caption>` : ''}<tbody>${block.rows.map(row => `<tr>${row.cells.map(cell => {
        const tag = cell.tag === 'th' ? 'th' : 'td'
        const span = ['colspan', 'rowspan'].map(key => Number(cell[key]) > 1 ? ` ${key}="${Number(cell[key])}"` : '').join('')
        return `<${tag}${span}>${cell.content || ''}</${tag}>`
      }).join('')}</tr>`).join('')}</tbody></table>`
    }).join('\n')
    pages[path] = { title: `${article.title}｜${config.name}`, description: text(blocks).slice(0, 150), canonical: path, html: `<h1>${escape(article.title)}</h1>${blocks}` }
  }
  pages['/articles'] = { ...pages[`/articles/${articles[0].id}`] }
  return { origin: config.origin, name: config.name, description: config.description, pages }
}

export default function seoBuild() {
  let manifest
  const load = async () => manifest ||= await createSeoManifest()
  return {
    name: 'jyutdict-seo',
    resolveId(id) { if (id === 'virtual:jyutdict-seo') return '\0jyutdict-seo' },
    async load(id) {
      if (id !== '\0jyutdict-seo') return
      const data = await load()
      // Keep article bodies out of the initial JavaScript bundle.
      const pages = Object.fromEntries(Object.entries(data.pages).map(([path, { html, text, ...meta }]) => [path, meta]))
      return `export default ${JSON.stringify({ ...data, pages })}`
    },
    async generateBundle() {
      this.emitFile({ type: 'asset', fileName: 'data/seo-pages.json', source: JSON.stringify(await load()) })
      this.emitFile({ type: 'asset', fileName: 'data/about-pages.json', source: JSON.stringify(await createAboutManifest()) })
    }
  }
}
