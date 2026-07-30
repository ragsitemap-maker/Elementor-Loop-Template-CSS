# Elementor Template CSS Bundle

[English](README.md) | **繁體中文**

Elementor Template CSS Bundle 是一個**選擇性的 CSS 補救層**，專門處理在
Elementor 編輯器內正常、但到前台卻沒有樣式、responsive rules 不完整或版面
樣式被破壞的範本。

本 repository 是單純的 **Elementor Loop Template CSS 外掛發布專案**。
本機的 Elementor Pro 參考外掛只供開發時查閱，不屬於本專案，也不會上傳或打包。

## 為什麼需要這個外掛

Elementor 一般會依照 document 各自產生及載入 CSS。但在特定邊界情境中，
範本可能在編輯器裡顯示正常，前台卻沒有取得預期的 stylesheet，或只產生部分
responsive CSS。

Elementor 官方 issue tracker 已出現過這些真實案例：

- Loop Item 正常載入 `loop-{id}.css`，卻另外要求不存在的
  `post-{id}.css` 而回傳 404
  ([elementor/elementor#24959](https://github.com/elementor/elementor/issues/24959))；
- 使用 `get_builder_content_for_display()` 載入多個範本後，tablet／mobile
  responsive rules 沒有產生
  ([elementor/elementor#20555](https://github.com/elementor/elementor/issues/20555))；
- 已遺失的 post CSS 不會在前台瀏覽時重新產生
  ([elementor/elementor#7237](https://github.com/elementor/elementor/issues/7237))；
- cache 內的頁面持續引用已被移除的 generated CSS，造成前台版面破壞
  ([elementor/elementor#33057](https://github.com/elementor/elementor/issues/33057))。

此外掛針對的是這些**已確認有問題的特定範本**。管理員只加入前台 CSS
不可靠的 template ID；外掛向 Elementor 取得這些指定範本的 generated CSS，
依設定順序合併、寫入穩定的內容雜湊檔，再透過 Elementor 前台樣式生命週期載入
這份補救 bundle。

## 刻意採用選擇性清單

這**不是**全站 CSS 合併器、通用 minifier，也不是 Elementor 正常資產流程的
替代品。

- 不會掃描並匯入全部 Elementor 範本。
- 正常載入 CSS 的範本不需要加入。
- Bundle 只包含管理員明確選取的 template ID。
- 已正常運作的範本應留在補救清單之外。
- 加入不必要的範本可能造成重複 CSS、cascade 風險及額外頁面負擔。

Elementor 官方資產指南也指出，每一份 stylesheet 都會增加頁面大小，應只在
需要時載入資產
([Elementor Scripts & Styles](https://developers.elementor.com/docs/scripts-styles/))。
因此此外掛使用 allowlist，而不是把全部 template CSS 都收進來。

## 什麼時候才應加入範本

確認範本符合一項以上症狀後，再加入 template ID：

- 編輯器有樣式，但前台無樣式或只剩部分樣式；
- DevTools 顯示 `loop-{id}.css`／`post-{id}.css` 遺失或回傳 404；
- desktop CSS 存在，但 responsive rules 遺失；
- 重新產生 Elementor CSS 或清除 cache 後只暫時恢復；
- 動態插入、nested、Loop、Archive 或 Theme Builder template 沒有取得預期 CSS。

## 外掛實際做什麼

1. 儲存一份有順序的「問題範本 allowlist」。
2. 只為清單內的 template 產生 CSS。
3. 自動移除跨群組重複 ID。
4. 依管理員設定順序壓縮及合併指定 CSS。
5. 發布穩定的內容雜湊 recovery bundle。
6. 重建失敗時保留 last-known-good bundle。
7. 在相關 Elementor 儲存、清快取、手動要求或稽核後重建。
8. 沒有有效 bundle 時，fallback 至 Elementor 原始 CSS。

## 後台功能

啟用後前往「工具 → Template CSS」：

- 查看 bundle 狀態、大小、建置時間、範本數及內容雜湊
- 管理已確認有問題的範本群組與 Elementor template ID
- 透過上下按鈕調整 CSS 串接順序
- 儲存清單並立即重建
- 恢復外掛內建預設清單
- 手動重建及檢視目前 bundle

## 安裝

1. 從 GitHub Releases 下載最新版 ZIP。
2. 在 WordPress 後台前往「外掛 → 安裝外掛 → 上傳外掛」。
3. 停用舊的穩定 Elementor CSS WPCode 片段或外掛。
4. 啟用 **Elementor Template CSS Bundle**。
5. 前往「工具 → Template CSS」。
6. 移除不需要補救的範本，只加入已確認受影響的 ID。
7. 儲存清單並執行一次重建。

## 開發驗證

使用 WordPress PHP Docker image 執行：

```bash
php -l elementor-template-css-bundle/elementor-template-css-bundle.php
php -l elementor-template-css-bundle/includes/class-elementor-template-css-bundle.php
php tests/plugin-smoke.php
```

## 授權

GPL-2.0-or-later
