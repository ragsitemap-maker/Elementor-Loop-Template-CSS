# Elementor Loop Template CSS 發布架構書

[English](RELEASE-ARCHITECTURE.md) | **繁體中文**

## 1. 文件目的

本文件定義 **Elementor Loop Template CSS** 外掛的功能範圍、執行架構、
GitHub repository 結構、上傳／更新檔案範圍、版本發布流程與驗證標準。

外掛在 WordPress 後台顯示名稱為 **Elementor Template CSS Bundle**。

## 2. 發布範圍

### 2.1 包含

- WordPress 外掛主程式
- Elementor 範本 CSS bundle 核心
- 後台範本群組管理介面
- WordPress 外掛 `readme.txt`
- 自動化 smoke test
- GitHub 專案說明
- 發布架構書
- 各版本發布說明
- GitHub Release 安裝 ZIP

### 2.2 明確排除

- `Ref. Plugin/`
- Elementor 或 Elementor Pro 原始碼
- 本機開發參考外掛
- WordPress 核心
- 網站資料庫、上傳檔、憑證或環境設定
- 開發過程中的舊版 ZIP

`Ref. Plugin/` 僅供開發時確認 Elementor API、class 與 hook 使用方式，
不屬於此外掛的原始碼、相依套件或發布內容。Repository 的 `.gitignore`
必須持續排除該目錄。

## 3. 核心功能

### 3.1 範本群組管理

管理員可在「工具 → Template CSS」：

- 新增、刪除及重新命名群組
- 調整群組順序
- 編輯每組 Elementor 範本 ID
- 使用換行、空格或逗號分隔 ID
- 查看每組有效 ID 數量
- 恢復外掛內建預設清單

群組及 ID 的排列順序就是 CSS 串接順序。相同 ID 若跨群組重複，
只保留第一次出現的位置。個別群組可以為空，但整份設定至少要包含一個有效 ID。

### 3.2 CSS Bundle 建置

外掛依序讀取各 Elementor 範本的 CSS，經過安全壓縮後合併為單一 bundle。
檔名包含內容 SHA-256 雜湊的前 16 碼，並寫入：

`wp-content/uploads/elementor/template-css-bundle/`

檔名格式：

`elementor-template-css-{hash}.css`

### 3.3 Last-known-good

新 bundle 必須完成下列步驟後才切換 manifest：

1. 完成所有範本 CSS 讀取。
2. 完成 CSS 合併及壓縮。
3. 寫入暫存檔。
4. 驗證檔案大小。
5. 原子 rename 為正式檔。
6. 更新 manifest。

若建置失敗，既有有效檔案不會被覆寫。

### 3.4 前台載入與 Fallback

- Manifest 有效：前台 enqueue 單一穩定 bundle。
- Manifest 無效或檔案不存在：標記頁面不可快取、排入重建，並暫時 enqueue 原始 Elementor CSS。
- 訪客請求不直接產檔；重建改由排程執行。

### 3.5 自動重建與稽核

重建觸發來源：

- Elementor 編輯器儲存指定範本
- Elementor 清除 CSS cache
- 後台儲存群組設定
- 後台手動重建
- 自訂重建 action
- 每日 fingerprint 稽核

同一 request 最多排入一次重建，並使用 option lock 避免多程序同時產檔。

## 4. 執行架構

```text
後台群組設定
    ↓
設定正規化與 ID 去重
    ↓
Elementor 範本 CSS 讀取
    ↓
Token-safe CSS 壓縮
    ↓
依群組／範本順序合併
    ↓
暫存檔寫入與驗證
    ↓
內容雜湊正式檔
    ↓
Manifest 切換
    ↓
前台 enqueue 單一 bundle
```

## 5. GitHub Repository 結構

```text
Elementor-Loop-Template-CSS/
├─ .gitignore
├─ README.md
├─ README.zh-TW.md
├─ RELEASE-ARCHITECTURE.md
├─ RELEASE-ARCHITECTURE.zh-TW.md
├─ RELEASE-NOTES-v1.0.0.md
├─ RELEASE-NOTES-v1.0.0.zh-TW.md
├─ elementor-template-css-bundle/
│  ├─ elementor-template-css-bundle.php
│  ├─ readme.txt
│  └─ includes/
│     └─ class-elementor-template-css-bundle.php
└─ tests/
   └─ plugin-smoke.php
```

安裝 ZIP 不提交到 Git history，而是作為 GitHub Release asset 發布。

## 6. 上傳與更新檔案

| 檔案 | 用途 | 何時更新 |
|---|---|---|
| `.gitignore` | 排除參考外掛與 ZIP | 排除規則變更時 |
| `README.md` | 英文主要 GitHub 首頁、功能與安裝說明 | 功能或操作方式變更時 |
| `README.zh-TW.md` | 繁中 GitHub 首頁 | 隨英文 README 同步更新 |
| `RELEASE-ARCHITECTURE.md` | 英文主要發布結構與流程規範 | 架構、檔案範圍或流程變更時 |
| `RELEASE-ARCHITECTURE.zh-TW.md` | 繁中發布架構書 | 隨英文架構書同步更新 |
| `RELEASE-NOTES-vX.Y.Z.md` | 英文主要版本說明 | 每次發布 |
| `RELEASE-NOTES-vX.Y.Z.zh-TW.md` | 繁中版本說明 | 每次發布 |
| `elementor-template-css-bundle.php` | 外掛標頭、版本與載入器 | 每次版本更新 |
| `includes/class-elementor-template-css-bundle.php` | 核心功能 | 功能或修正變更時 |
| `readme.txt` | WordPress 外掛說明與 changelog | 每次版本更新 |
| `tests/plugin-smoke.php` | 基本載入與隔離驗證 | 行為或 hook 變更時 |
| Release ZIP | WordPress 可安裝成品 | 每次發布 |

## 7. 版本發布流程

1. 更新功能程式與測試。
2. 同步主程式 `Version`、版本常數、`readme.txt` Stable tag 與 changelog。
3. 先更新英文 `README.md`、發布架構書及版本說明，再同步繁中版本。
4. 執行 PHP syntax check。
5. 執行 smoke test。
6. 建立只包含 `elementor-template-css-bundle/` 的 ZIP。
7. 檢查 ZIP 根目錄、路徑分隔符與必要檔案。
8. Commit 並推送 GitHub。
9. 建立 `vX.Y.Z` tag 與 GitHub Release。
10. 上傳 ZIP 作為 Release asset。

## 8. 發布驗證標準

每次發布至少通過：

- 外掛主程式無 PHP 語法錯誤
- 核心 class 無 PHP 語法錯誤
- 外掛正常 boot
- 群組設定端點已註冊
- 預設清單可讀取
- 群組排序、空群組及重複 ID 正規化正確
- 管理頁輸出必要欄位與排序控制
- 重複 boot 不會重複註冊 hook
- Rodest `remove_action()` 等獨立程式碼仍可運作
- ZIP 只包含外掛目錄與三個發布檔案
- `Ref. Plugin/` 未被 commit、tag 或打包
- 英文文件為主要版本，繁中文件已同步

## 9. 回復策略

- GitHub 保留每一版本 tag 與 Release ZIP。
- 發布失敗時可重新安裝上一版 ZIP。
- Bundle 建置失敗時使用 last-known-good 或 Elementor 原始 CSS fallback。
- 外掛停用時清除排程 hook，但不主動刪除已產生的 CSS。

## 10. v1.0.0 發布內容

- 全新、獨立的 Elementor Template CSS Bundle 外掛
- 全新 option、hook、class、bundle 目錄與檔名命名空間
- 可客製化 Elementor 範本群組清單
- 穩定內容雜湊 bundle
- 管理列狀態與後台管理頁
- 自動稽核、排程重建與 fallback
- 不包含任何舊版遷移或相容層
