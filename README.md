# 泛粵大典 Jyutdict

一個粵語字典網站，提供粵語字音查詢功能，支援多種粵語方言點的字音資料。

## 技術棧

- **前端**：Vue 3 + Vite + Vue Router + Pinia + TailwindCSS
- **後端**：PHP 7 + MySQL
- **認證**：Google OAuth

## 專案結構

```
├── frontend/                  # Vue 3 前端
│   ├── src/
│   │   ├── api/              # API 模組
│   │   ├── components/       # 共用元件
│   │   ├── views/            # 頁面元件（見下方說明）
│   │   ├── stores/           # Pinia 狀態管理
│   │   ├── router/           # 路由配置
│   │   ├── utils/            # 工具函數
│   │   └── data/             # 靜態資料
│   └── vite.config.js
│
├── api/                       # PHP API 端點
│   ├── v0.9/                 # 舊版 API
│   │   ├── detail.php        # 字元/讀音詳情
│   │   └── sheet.php         # 字表查詢
│   ├── v1.0/                 # 新版 API
│   │   ├── detail.php
│   │   ├── sheet.php
│   │   ├── articles/         # 文章系統
│   │   ├── comments/         # 評論系統
│   │   ├── locations/        # 地點資料
│   │   ├── user/             # 用戶相關
│   │   └── admin/            # 管理功能
│   ├── auth/                  # 認證模組
│   │   ├── google.php        # Google OAuth
│   │   ├── logout.php
│   │   └── me.php
│   ├── core/                  # 核心類別
│   │   ├── Jyutping.php      # 粵拼解析
│   │   ├── Sim2Trad.php      # 簡繁轉換
│   │   ├── db.php            # 資料庫連線
│   │   └── helpers.php
│   ├── middleware/            # 中介層
│   └── config/                # 配置
│
├── assets/                    # 前端構建產物
├── docs/                      # 開發文檔
├── img/                       # 圖片資源
│
├── index.html                 # 入口頁面
├── migrate_pronunciation.py   # 資料遷移腳本
└── 字表更新日誌.md
```

## 頁面說明

| 頁面                          | 路由                        | 功能                                                                         |
| ----------------------------- | --------------------------- | ---------------------------------------------------------------------------- |
| **HomeView**            | `/`                       | 首頁，提供字詞搜尋入口，可選擇「檢索於通表」或「檢索於粵表」                 |
| **DetailView**          | `/detail`                 | 字元詳情頁，顯示字元在各地粵語方言的讀音、韻書記載、地圖分佈及相關連結       |
| **PronunciationView**   | `/pronunciation`          | 檢音頁，按擴展粵拼（聲母/韻核/韻尾/聲調）搜尋字音，支援模糊匹配              |
| **SheetView**           | `/sheet`                  | 泛粵字表頁，以表格形式查詢字表資料，支援模糊、截取、正則、釋義等多種查詢模式 |
| **ArticleView**         | `/article/:id`            | 紀文頁，展示音韻學相關文章，支援 HTML 和音韻表格區塊                         |
| **LocationListView**    | `/locations`              | 地點介紹列表頁，展示各地點的文章列表及預覽                                   |
| **LocationArticleView** | `/location/:locationName` | 地點文章頁，顯示/編輯特定地點的介紹文章（Markdown），支援版本歷史            |
| **PhonologyView**       | `/phonology`              | 音系探針小玩具，根據用戶選擇的音韻特徵計算與各方言點的相似度                 |
| **UserCenterView**      | `/user`                   | 用戶中心，管理個人資料、查看評論記錄、管理地點文章（編纂者）                 |
| **AdminView**           | `/admin`                  | 後臺管理頁，用戶角色管理及編纂者地點分配（管理員/站長）                      |
| **AboutView**           | `/about/:id`              | 說明頁，展示網站說明文檔（Markdown 格式）                                    |
| **NotFoundView**        | `*`                       | 404 頁面                                                                     |

## 部署與開發

### 前端開發
```bash
cd frontend
npm install
npm run dev      # 啟動開發伺服器
npm run build    # 構建生產版本
```

### 後端 API 部署配置
資料庫密碼與 OAuth 憑證未包含在程式碼倉庫中。
如需在本地或伺服器運行，請依照以下步驟配置：
1. 進入 `api/config/` 目錄
2. 複製 `db.php.example` 並重新命名為 `db.php`
3. 編輯 `db.php`，填寫真實的資料庫連線資訊
4. （可選）複製 `oauth.php.example` 為 `oauth.php`，填寫 Google OAuth 密鑰

## 開源協議與版權聲明

本項目遵循「代碼與資料分離」的版權原則：

本 GitHub 倉庫中包含的前端與後端代碼，均採用 [MIT License](LICENSE) 授權。通過 API 或網頁提供查詢的字表、拼音、釋義等核心數據則保留所有權

