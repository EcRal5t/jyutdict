import { rm, cp } from 'fs/promises'
import { existsSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const root = resolve(__dirname, '..', '..')
const dist = resolve(__dirname, '..', 'dist')

// 清理根目录旧文件
const toDelete = ['assets', 'favicon.ico', 'index.html']
for (const f of toDelete) {
  const p = resolve(root, f)
  if (existsSync(p)) await rm(p, { recursive: true })
}

// 复制新文件
await cp(resolve(dist, 'index.html'), resolve(root, 'index.html'))
await cp(resolve(dist, 'favicon.ico'), resolve(root, 'favicon.ico'))
await cp(resolve(dist, 'assets'), resolve(root, 'assets'), { recursive: true })

// 清理 dist 目录
if (existsSync(dist)) await rm(dist, { recursive: true })

console.log('✓ 已部署到根目录')
