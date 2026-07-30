# Elementor Template CSS Bundle v1.0.0

[English](RELEASE-NOTES-v1.0.0.md) | **繁體中文**

首個獨立發布版本。

## 功能

- 管理 Elementor 範本群組、名稱、順序及範本 ID
- 將多個 Loop／Template CSS 合併為單一內容雜湊 bundle
- Token-safe CSS 壓縮
- Last-known-good manifest 切換
- 前台穩定 enqueue 與原始 CSS fallback
- Elementor 儲存／清快取自動重建
- 每日 fingerprint 稽核
- 管理列 bundle 狀態
- 「工具 → Template CSS」管理頁
- 手動重建與恢復預設清單

## 發布檔案

- GitHub source：外掛原始碼、測試及專案文件
- Release asset：`elementor-template-css-bundle-1.0.0.zip`

## 不包含

- Elementor／Elementor Pro
- `Ref. Plugin/`
- 舊版外掛、舊版 ZIP 或遷移層
- 網站資料、憑證或環境設定

## 驗證

- PHP 8.4 syntax check：通過
- 外掛 boot：通過
- 客製群組正規化：通過
- 管理頁欄位與排序控制輸出：通過
- 重複 boot 防護：通過
- Rodest hook 隔離：通過

完整發布規範請參閱 [發布架構書](RELEASE-ARCHITECTURE.zh-TW.md)。
