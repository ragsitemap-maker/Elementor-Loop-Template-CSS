# Elementor Template CSS Bundle

[English](README.md) | **繁體中文**

將可設定的 Elementor 範本 CSS 預先合併成單一、內容雜湊命名的穩定 bundle，
並提供 WordPress 後台清單管理、狀態檢查與手動重建功能。

本 repository 是單純的 **Elementor Loop Template CSS 外掛發布專案**。
本機的 Elementor Pro 參考外掛只供開發時查閱，不屬於本專案，也不會上傳或打包。

## 功能

- 新增、刪除、重新命名及排序範本群組
- 編輯 Elementor 範本 ID
- 自動移除跨群組重複 ID
- 儲存清單後立即重建 CSS
- 將多個 Elementor Loop／Template CSS 合併為單一 bundle
- 以內容雜湊產生穩定檔名，方便瀏覽器與 CDN 快取
- 保留 last-known-good bundle
- 提供排程稽核、手動重建及 fallback
- 與其他 WPCode／佈景主題 hook 程式碼隔離

## 後台功能

啟用後前往「工具 → Template CSS」：

- 查看 bundle 狀態、大小、建置時間、範本數及內容雜湊
- 管理群組名稱與 Elementor 範本 ID
- 透過上下按鈕調整 CSS 串接順序
- 儲存清單並立即重建
- 恢復外掛內建預設清單
- 手動重建及檢視目前 bundle

## 安裝

1. 從 GitHub Releases 下載最新版 ZIP。
2. 在 WordPress 後台前往「外掛 → 安裝外掛 → 上傳外掛」。
3. 停用舊的穩定 Elementor CSS WPCode 片段或外掛。
4. 啟用 **Elementor Template CSS Bundle**。
5. 前往「工具 → Template CSS」，確認清單後執行一次重建。

## 專案文件

- [v1.0.0 發布說明](RELEASE-NOTES-v1.0.0.zh-TW.md)

## 開發驗證

使用 WordPress PHP Docker image 執行：

```bash
php -l elementor-template-css-bundle/elementor-template-css-bundle.php
php -l elementor-template-css-bundle/includes/class-elementor-template-css-bundle.php
php tests/plugin-smoke.php
```

## 授權

GPL-2.0-or-later
