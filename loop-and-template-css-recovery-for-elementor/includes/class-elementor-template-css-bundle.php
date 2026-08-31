<?php
/**
 * Loop and Template CSS Recovery for Elementor (build v6)
 *
 * 用途：把指定的 Elementor 全域範本 CSS 預先合併成單一內容雜湊檔，
 *       存放於 Elementor 清快取不會觸及的目錄，前台只讀這一支。
 *
 * 設計原則：
 *   1. 單一重建核心 —— 所有觸發點都匯流到 rebuild()，沒有第二條產檔路徑。
 *   2. 乾淨的重建窗口 —— 一個 request 最多重建一次，且延後到 shutdown 執行。
 *   3. 前台零重建 —— 訪客請求絕不產檔，只做 1 次 option 讀取 + 1 次 stat。
 *   4. Last-known-good —— 新檔完整寫入並驗證通過後，才切換 manifest 指標。
 *
 * v6：build 階段加入 token-safe 的 CSS 壓縮（minify_css）。
 *     Elementor 產出的 CSS 本來就是壓平的單行字串，手寫 Custom CSS 則帶著
 *     縮排與註解，混在一起格式不一致。統一壓成單行後，bundle 的結構是
 *     「群組標題 → 每個範本一行」，一組一組清楚分開。
 *
 * v4：範本 ID 改為分組宣告（TEMPLATE_GROUPS）。
 *     群組宣告順序 = CSS 串接順序；產出的 bundle 會標註來源群組，
 *     方便在 DevTools 直接看出某段樣式屬於哪一組範本。
 *
 * 放置位置：由 Loop and Template CSS Recovery for Elementor 外掛載入。
 * 注意：啟用外掛後，請停用 WPCode 內的舊版片段。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Elementor_Template_CSS_Bundle {

    /**
     * ===== 預設範本群組 =====
     *
     * 分組宣告 Elementor 範本 ID。
     * - 群組由上而下、組內由上而下，即為 CSS 的串接順序（後者可覆蓋前者）。
     * - 群組可留空陣列，不影響運作。
     * - 後台尚未儲存客製設定時使用此清單。
     * - 後台可隨時恢復此預設清單。
     */
    private const TEMPLATE_GROUPS = [

        'Mega Menu - Loop' => [
            106680,
            107182,
            107308,
            107379,
            109142,
            107458,
            107527,
            107603,
            107881,
        ],

        'Category Archive' => [
			109039,
        ],

        'Case Studies Archive' => [
            // 107xxx,
        ],

        'Newsroom Archive' => [
            // 107xxx,
        ],

        'Post Templates' => [
            // 107xxx,
        ],

        'Product Templates' => [
            // 107xxx,
        ],

    ];

    /** 手動使既有 bundle 失效：改動此值會強制全站重建。 */
    private const BUILD_VERSION = '6';

    /**
     * 產檔時是否把每個範本的 CSS 壓成單行。
     * 只移除空白與註解，不改動任何有效 token；壓縮後會做不變式檢查，
     * 檢查未通過則自動退回原字串。改動此值會自動觸發重建。
     */
    private const MINIFY = true;

    /**
     * 壓縮時保留 Elementor 的手寫 CSS 邊界註解
     * （/* Start custom CSS ... *\/ 與 /* End custom CSS *\/），
     * 以行內形式保留，不影響單行結構。其餘註解一律移除。
     */
    private const KEEP_CUSTOM_CSS_MARKERS = true;

    private const STABLE_DIR      = 'template-css-bundle';
    private const FILE_PREFIX     = 'elementor-template-css-';
    private const STYLE_HANDLE    = 'elementor-template-css-bundle';

    private const MANIFEST_OPTION = 'elementor_template_css_bundle_manifest';
    private const LOCK_OPTION     = 'elementor_template_css_bundle_lock';
    private const CHECK_TRANSIENT = 'elementor_template_css_bundle_checked';
    private const CONFIG_OPTION   = 'elementor_template_css_bundle_groups';

    private const CRON_REBUILD    = 'elementor_template_css_bundle_rebuild';
    private const CRON_AUDIT      = 'elementor_template_css_bundle_audit';

    /** admin-post.php 的 action 名，同時作為 nonce action。 */
    private const ADMIN_ACTION    = 'elementor_template_css_bundle_rebuild';
    private const SAVE_ACTION     = 'elementor_template_css_bundle_save_groups';
    private const RESULT_ARG      = 'etcb_result';
    private const PAGE_SLUG       = 'elementor-template-css-bundle';

    private const LOCK_TTL        = 180;   // 重建互斥鎖秒數
    private const CHECK_INTERVAL  = 300;   // 後台稽核節流秒數
    private const RETAINED_FILES  = 3;     // 保留舊 bundle 份數（供 CDN 尾流）
    private const MIN_BUNDLE_SIZE = 64;    // 位元組

    /** @var bool  boot() 冪等旗標 */
    private static $booted = false;

    /** @var array|false|null  null=未讀取, false=無效, array=有效 */
    private static $manifest = null;

    /** @var array|null  攤平後的 ID 清單（僅冷路徑使用） */
    private static $ids = null;

    /** @var array|null  後台設定或預設值正規化後的群組表 */
    private static $configured_groups = null;

    /** @var array|null  wp_upload_dir() 的 request 內快取 */
    private static $upload_paths = null;

    /** @var bool  本 request 是否已排入重建 */
    private static $queued = false;

    /** @var bool  本 request 是否已實際執行過重建 */
    private static $built = false;

    /* ---------------------------------------------------------------------
     * 群組存取
     * ------------------------------------------------------------------- */

    /**
     * 攤平後的 ID 清單。
     * 僅用於重建、稽核、editor save 判斷等冷路徑；
     * 前台熱路徑只讀已正規化的群組設定。
     */
    private static function ids(): array {
        if ( null === self::$ids ) {
            $flat = [];

            foreach ( self::configured_groups() as $template_ids ) {
                foreach ( $template_ids as $template_id ) {
                    $flat[ $template_id ] = $template_id;
                }
            }

            self::$ids = array_values( $flat );
        }

        return self::$ids;
    }

    /**
     * 取得後台客製群組；尚未儲存設定時使用程式內建預設值。
     *
     * 空群組會保留供管理頁編輯；無效 ID 與跨群組重複 ID 會移除。
     */
    private static function configured_groups(): array {
        if ( null !== self::$configured_groups ) {
            return self::$configured_groups;
        }

        $saved  = get_option( self::CONFIG_OPTION, null );
        $source = is_array( $saved ) ? $saved : self::TEMPLATE_GROUPS;
        $groups = self::normalize_groups( $source );

        // 避免遭破壞的 option 使整個前台永久失去預設清單。
        if ( ! $groups ) {
            $groups = self::normalize_groups( self::TEMPLATE_GROUPS );
        }

        self::$configured_groups = $groups;

        return self::$configured_groups;
    }

    private static function normalize_groups( array $source ): array {
        $groups   = [];
        $seen_ids = [];

        foreach ( $source as $group => $template_ids ) {
            $group = sanitize_text_field( (string) $group );

            if ( '' === $group || isset( $groups[ $group ] ) ) {
                continue;
            }

            $clean = [];

            foreach ( (array) $template_ids as $template_id ) {
                $template_id = absint( $template_id );

                if ( $template_id && ! isset( $seen_ids[ $template_id ] ) ) {
                    $seen_ids[ $template_id ] = true;
                    $clean[]                  = $template_id;
                }
            }

            $groups[ $group ] = $clean;
        }

        return $groups;
    }

    /** 已去除空群組與無效 ID 的群組表，供重建與狀態頁使用。 */
    private static function groups(): array {
        $active = [];

        foreach ( self::configured_groups() as $group => $template_ids ) {
            if ( $template_ids ) {
                $active[ $group ] = $template_ids;
            }
        }

        return $active;
    }

    /* ---------------------------------------------------------------------
     * Boot
     * ------------------------------------------------------------------- */

    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }

        self::$booted = true;

        // 前台：在 WordPress priority 20 印出 late styles 前最後入列。
        add_action(
            'wp_footer',
            [ self::class, 'enqueue_bundle' ],
            19
        );

        // 觸發點：全部匯流到 queue_rebuild()，由它決定何時執行。
        add_action( 'elementor/editor/after_save',       [ self::class, 'on_editor_save' ], 20 );
        add_action( 'elementor/core/files/clear_cache',  [ self::class, 'queue_rebuild' ], 20 );
        add_action( 'elementor_template_css_bundle_force_rebuild', [ self::class, 'queue_rebuild' ] );

        // 重建核心的直接入口（皆為非訪客情境）。
        add_action( self::CRON_REBUILD, [ self::class, 'rebuild' ] );
        add_action( self::CRON_AUDIT,   [ self::class, 'audit' ] );

        // 管理列 + 手動重建端點。前後台皆註冊。
        add_action( 'admin_bar_menu', [ self::class, 'admin_bar_menu' ], 100 );
        add_action( 'admin_post_' . self::ADMIN_ACTION, [ self::class, 'handle_manual_rebuild' ] );
        add_action( 'admin_post_' . self::SAVE_ACTION, [ self::class, 'handle_save_groups' ] );

        // 後台：管理頁、稽核、結果提示。
        if ( is_admin() ) {
            add_action( 'admin_menu',    [ self::class, 'admin_menu' ] );
            add_action( 'admin_init',    [ self::class, 'admin_audit' ], 30 );
            add_action( 'admin_notices', [ self::class, 'admin_notice' ] );
        }
    }

    /* ---------------------------------------------------------------------
     * 前台熱路徑
     * ------------------------------------------------------------------- */

    /**
     * 成本上限：1 次 autoload option 讀取 + 1 次 stat + 陣列／字串比對。
     * 不做攤平、不做 hash 運算、不做 DB 查詢、不做檔案重建。
     */
    public static function enqueue_bundle(): void {
        // 保留原本的 Elementor admission，避免在非 Elementor 頁面擴大載入。
        if ( ! did_action( 'elementor/frontend/after_enqueue_styles' ) ) {
            return;
        }

        if ( ! apply_filters( 'elementor_template_css_bundle_should_enqueue', true ) ) {
            return;
        }

        $manifest = self::manifest();

        if ( $manifest ) {
            // 字型／圖示先入列，bundle 最後入列以保有最終 cascade 位置。
            self::enqueue_fonts_and_icons( $manifest );

            wp_enqueue_style(
                self::STYLE_HANDLE,
                $manifest['url'],
                [ 'elementor-frontend' ],
                $manifest['hash']
            );
            return;
        }

        // 異常路徑：首次安裝、檔案遭外部刪除、或 manifest 損壞。
        self::mark_response_uncacheable();
        self::queue_rebuild();
        self::fallback_enqueue_original_files();
    }

    /**
     * 只在 build 端做過的驗證，這裡一律不重做。
     * 群組表以後台設定直接比對，任何 ID 增刪或群組調整都會即時失效。
     */
    private static function manifest(): ?array {
        if ( null !== self::$manifest ) {
            return self::$manifest ?: null;
        }

        self::$manifest = false;

        $manifest = get_option( self::MANIFEST_OPTION );

        if (
            ! is_array( $manifest )
            || empty( $manifest['file'] )
            || empty( $manifest['hash'] )
            || self::BUILD_VERSION !== ( $manifest['build'] ?? null )
            || self::configured_groups() !== ( $manifest['groups'] ?? null )
            || self::MINIFY !== ( $manifest['minify'] ?? null )
            || basename( (string) $manifest['file'] ) !== $manifest['file']
        ) {
            return null;
        }

        $paths = self::paths( (string) $manifest['file'] );

        if ( '' === $paths['path'] || ! is_file( $paths['path'] ) ) {
            return null;
        }

        $manifest['path'] = $paths['path'];
        $manifest['url']  = $paths['url'];

        self::$manifest = $manifest;

        return $manifest;
    }

    /** fonts / icons 的有效性已在 build 階段過濾完成，此處只做 enqueue。 */
    private static function enqueue_fonts_and_icons( array $manifest ): void {
        if ( empty( $manifest['fonts'] ) && empty( $manifest['icons'] ) ) {
            return;
        }

        if ( ! class_exists( '\\Elementor\\Plugin' ) || empty( \Elementor\Plugin::$instance->frontend ) ) {
            return;
        }

        $frontend = \Elementor\Plugin::$instance->frontend;

        foreach ( (array) ( $manifest['fonts'] ?? [] ) as $font ) {
            $frontend->enqueue_font( $font );
        }

        foreach ( (array) ( $manifest['icons'] ?? [] ) as $icon_font ) {
            $frontend->enqueue_font( $icon_font );
        }
    }

    /* ---------------------------------------------------------------------
     * 管理列
     * ------------------------------------------------------------------- */

    private static function capability(): string {
        return (string) apply_filters( 'elementor_template_css_bundle_capability', 'manage_options' );
    }

    /**
     * 只讀已 memoize 的 manifest，不做 DB 查詢、不算 fingerprint。
     * admin_bar_menu 僅在管理列實際輸出時觸發，不需再判斷 is_admin_bar_showing()。
     */
    public static function admin_bar_menu( $wp_admin_bar ): void {
        if ( ! is_object( $wp_admin_bar ) || ! current_user_can( self::capability() ) ) {
            return;
        }

        $manifest = self::manifest();
        $locked   = self::is_locked();

        if ( $locked ) {
            $color = '#dba617';
            $state = '重建中';
        } elseif ( $manifest ) {
            $color = '#00a32a';
            $state = '正常';
        } else {
            $color = '#d63638';
            $state = '缺失';
        }

        $wp_admin_bar->add_node( [
            'id'    => 'etcb-css',
            'title' => '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'
                . esc_attr( $color )
                . ';margin-right:6px;vertical-align:middle;"></span>Template CSS',
            'href'  => admin_url( 'tools.php?page=' . self::PAGE_SLUG ),
            'meta'  => [ 'title' => 'Loop 與 Template CSS Recovery：' . $state ],
        ] );

        if ( $manifest ) {
            $built_at = (int) ( $manifest['built_at'] ?? 0 );

            $wp_admin_bar->add_node( [
                'parent' => 'etcb-css',
                'id'     => 'etcb-css-built',
                'title'  => $built_at
                    ? sprintf( '建置於 %s 前', esc_html( human_time_diff( $built_at, time() ) ) )
                    : '建置時間未知',
            ] );

            $wp_admin_bar->add_node( [
                'parent' => 'etcb-css',
                'id'     => 'etcb-css-hash',
                'title'  => sprintf(
                    '%s · %s KB · %d 個範本',
                    esc_html( substr( (string) $manifest['hash'], 0, 8 ) ),
                    esc_html( number_format_i18n( ( (int) ( $manifest['size'] ?? 0 ) ) / 1024, 1 ) ),
                    (int) ( $manifest['template_count'] ?? 0 )
                ),
            ] );

            $wp_admin_bar->add_node( [
                'parent' => 'etcb-css',
                'id'     => 'etcb-css-view',
                'title'  => '檢視 bundle',
                'href'   => esc_url( $manifest['url'] ),
                'meta'   => [ 'target' => '_blank', 'rel' => 'noopener' ],
            ] );
        } else {
            $wp_admin_bar->add_node( [
                'parent' => 'etcb-css',
                'id'     => 'etcb-css-missing',
                'title'  => '無有效 bundle，前台使用 fallback',
            ] );
        }

        $wp_admin_bar->add_node( [
            'parent' => 'etcb-css',
            'id'     => 'etcb-css-rebuild',
            'title'  => $locked ? '重建中，請稍候' : '立即重建',
            'href'   => $locked ? '' : self::rebuild_url(),
        ] );

        $wp_admin_bar->add_node( [
            'parent' => 'etcb-css',
            'id'     => 'etcb-css-page',
            'title'  => '清單與狀態',
            'href'   => admin_url( 'tools.php?page=' . self::PAGE_SLUG ),
        ] );
    }

    /* ---------------------------------------------------------------------
     * 管理頁（保底入口）
     * ------------------------------------------------------------------- */

    public static function admin_menu(): void {
        add_management_page(
            'Loop and Template CSS Recovery for Elementor',
            'Template CSS',
            self::capability(),
            self::PAGE_SLUG,
            [ self::class, 'render_page' ]
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can( self::capability() ) ) {
            return;
        }

        $manifest = self::manifest();
        $locked   = self::is_locked();
        $fresh    = $manifest && ( $manifest['fingerprint'] ?? '' ) === self::fingerprint();
        $declared = self::configured_groups();
        $custom   = is_array( get_option( self::CONFIG_OPTION, null ) );

        echo '<div class="wrap"><h1>Loop 與 Template CSS Recovery for Elementor</h1>';

        // --- 總覽 ---
        echo '<table class="widefat striped" style="max-width:1000px;margin:16px 0;"><tbody>';

        $rows = [
            '狀態'        => $locked ? '重建中' : ( $manifest ? '正常' : '無有效 bundle（前台使用 fallback）' ),
            '新鮮度'      => $manifest ? ( $fresh ? '與範本一致' : '範本已變更，待重建' ) : '—',
            '設定來源'    => $custom ? '後台客製清單' : '外掛內建預設值',
            '檔案'        => $manifest ? $manifest['file'] : '—',
            '大小'        => $manifest ? number_format_i18n( ( (int) ( $manifest['size'] ?? 0 ) ) / 1024, 1 ) . ' KB' : '—',
            '建置時間'    => ( $manifest && ! empty( $manifest['built_at'] ) )
                ? wp_date( 'Y-m-d H:i:s', (int) $manifest['built_at'] ) . '（' . human_time_diff( (int) $manifest['built_at'], time() ) . '前）'
                : '—',
            '群組 / 範本' => count( $declared ) . ' 組 / ' . count( self::ids() ) . ' 個',
            '字型 / 圖示' => $manifest
                ? count( (array) ( $manifest['fonts'] ?? [] ) ) . ' / ' . count( (array) ( $manifest['icons'] ?? [] ) )
                : '—',
        ];

        foreach ( $rows as $label => $value ) {
            printf(
                '<tr><th scope="row" style="width:140px;">%s</th><td>%s</td></tr>',
                esc_html( $label ),
                esc_html( (string) $value )
            );
        }

        echo '</tbody></table>';

        // --- 動作 ---
        echo '<p>';

        if ( $locked ) {
            echo '<button class="button button-primary" disabled>重建中，請稍候</button>';
        } else {
            printf(
                '<a href="%s" class="button button-primary">立即重建</a>',
                esc_url( self::rebuild_url() )
            );
        }

        if ( $manifest ) {
            printf(
                ' <a href="%s" class="button" target="_blank" rel="noopener">檢視 bundle</a>',
                esc_url( $manifest['url'] )
            );
        }

        echo '</p>';

        // --- 可編輯群組清單 ---
        echo '<hr style="margin:24px 0;max-width:1000px;">';
        echo '<h2>範本群組清單</h2>';
        echo '<p class="description" style="max-width:900px;">群組及範本由上而下就是 CSS 串接順序；較下方的樣式可覆蓋較上方。範本 ID 可用換行、空格或逗號分隔，重複 ID 只保留第一次出現的位置。</p>';
        echo '<style>
            #etcb-group-editor{max-width:1000px;margin-top:12px}
            #etcb-group-editor th,#etcb-group-editor td{vertical-align:top}
            #etcb-group-editor .etcb-order{width:76px;white-space:nowrap}
            #etcb-group-editor .etcb-name{width:240px}
            #etcb-group-editor .etcb-count{width:56px;text-align:center}
            #etcb-group-editor .etcb-actions{width:72px;text-align:center}
            #etcb-group-editor textarea{width:100%;min-height:72px;resize:vertical;font-family:Consolas,Monaco,monospace}
            #etcb-group-editor input[type=text]{width:100%}
            #etcb-group-editor .button-link-delete{color:#b32d2e}
        </style>';

        printf(
            '<form method="post" action="%s" id="etcb-groups-form">',
            esc_url( admin_url( 'admin-post.php' ) )
        );
        printf(
            '<input type="hidden" name="action" value="%s">',
            esc_attr( self::SAVE_ACTION )
        );
        wp_nonce_field( self::SAVE_ACTION );

        echo '<table class="widefat striped" id="etcb-group-editor">';
        echo '<thead><tr><th class="etcb-order">順序</th><th class="etcb-name">群組名稱</th><th>Elementor 範本 ID</th><th class="etcb-count">數量</th><th class="etcb-actions">刪除</th></tr></thead>';
        echo '<tbody id="etcb-group-list">';

        $row_index = 0;

        foreach ( $declared as $group => $template_ids ) {
            printf(
                '<tr class="etcb-group-row">
                    <td class="etcb-order">
                        <button type="button" class="button button-small" data-action="up" aria-label="向上移動">↑</button>
                        <button type="button" class="button button-small" data-action="down" aria-label="向下移動">↓</button>
                    </td>
                    <td><input type="text" data-field="name" name="template_groups[%1$d][name]" value="%2$s" required></td>
                    <td><textarea data-field="ids" name="template_groups[%1$d][ids]" aria-label="%3$s 的範本 ID">%4$s</textarea></td>
                    <td class="etcb-count"><span data-count>%5$d</span></td>
                    <td class="etcb-actions"><button type="button" class="button-link-delete" data-action="remove">刪除</button></td>
                </tr>',
                $row_index,
                esc_attr( $group ),
                esc_attr( $group ),
                esc_textarea( implode( "\n", $template_ids ) ),
                count( $template_ids )
            );
            $row_index++;
        }

        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="etcb-add-group">＋ 新增群組</button></p>';

        $disabled = $locked ? ' disabled' : '';
        printf(
            '<p class="submit">
                <button type="submit" class="button button-primary" name="etcb_mode" value="save"%1$s>儲存清單並重建</button>
                <button type="submit" class="button" name="etcb_mode" value="reset" data-reset-groups%1$s>恢復外掛預設清單</button>
            </p>',
            $disabled
        );
        echo '</form>';

        echo '<template id="etcb-group-template">
            <tr class="etcb-group-row">
                <td class="etcb-order">
                    <button type="button" class="button button-small" data-action="up" aria-label="向上移動">↑</button>
                    <button type="button" class="button button-small" data-action="down" aria-label="向下移動">↓</button>
                </td>
                <td><input type="text" data-field="name" value="新群組" required></td>
                <td><textarea data-field="ids" aria-label="新群組的範本 ID"></textarea></td>
                <td class="etcb-count"><span data-count>0</span></td>
                <td class="etcb-actions"><button type="button" class="button-link-delete" data-action="remove">刪除</button></td>
            </tr>
        </template>';

        echo "<script>
        (() => {
            const list = document.getElementById('etcb-group-list');
            const template = document.getElementById('etcb-group-template');
            const addButton = document.getElementById('etcb-add-group');
            const form = document.getElementById('etcb-groups-form');

            if (!list || !template || !addButton || !form) {
                return;
            }

            const updateCount = (row) => {
                const textarea = row.querySelector('[data-field=\"ids\"]');
                const output = row.querySelector('[data-count]');
                const ids = textarea.value.match(/\\d+/g) || [];
                output.textContent = new Set(ids.filter((id) => Number(id) > 0)).size;
            };

            const renumber = () => {
                [...list.querySelectorAll('.etcb-group-row')].forEach((row, index) => {
                    row.querySelector('[data-field=\"name\"]').name = `template_groups[\${index}][name]`;
                    row.querySelector('[data-field=\"ids\"]').name = `template_groups[\${index}][ids]`;
                    updateCount(row);
                });
            };

            addButton.addEventListener('click', () => {
                list.appendChild(template.content.cloneNode(true));
                renumber();
                list.lastElementChild.querySelector('[data-field=\"name\"]').select();
            });

            list.addEventListener('click', (event) => {
                const button = event.target.closest('[data-action]');

                if (!button) {
                    return;
                }

                const row = button.closest('.etcb-group-row');
                const action = button.dataset.action;

                if ('up' === action && row.previousElementSibling) {
                    list.insertBefore(row, row.previousElementSibling);
                } else if ('down' === action && row.nextElementSibling) {
                    list.insertBefore(row.nextElementSibling, row);
                } else if ('remove' === action) {
                    if (list.children.length < 2) {
                        window.alert('至少需要保留一個群組。');
                        return;
                    }
                    row.remove();
                }

                renumber();
            });

            list.addEventListener('input', (event) => {
                if (event.target.matches('[data-field=\"ids\"]')) {
                    updateCount(event.target.closest('.etcb-group-row'));
                }
            });

            form.addEventListener('submit', (event) => {
                const submitter = event.submitter;
                if (submitter && submitter.hasAttribute('data-reset-groups') &&
                    !window.confirm('確定要捨棄客製清單並恢復外掛預設值？')) {
                    event.preventDefault();
                }
            });

            renumber();
        })();
        </script>";

        echo '</div>';
    }

    /* ---------------------------------------------------------------------
     * 群組設定端點
     * ------------------------------------------------------------------- */

    public static function handle_save_groups(): void {
        if ( ! current_user_can( self::capability() ) ) {
            wp_die( '權限不足。', 403 );
        }

        check_admin_referer( self::SAVE_ACTION );

        if ( self::is_locked() ) {
            self::redirect_with_result( 'locked' );
        }

        $mode = isset( $_POST['etcb_mode'] )
            ? sanitize_key( wp_unslash( $_POST['etcb_mode'] ) )
            : 'save';

        if ( 'reset' === $mode ) {
            delete_option( self::CONFIG_OPTION );
            $success_result = 'reset';
            $failure_result = 'reset_fail';
        } else {
            $rows = isset( $_POST['template_groups'] ) && is_array( $_POST['template_groups'] )
                ? wp_unslash( $_POST['template_groups'] )
                : [];
            $configured = self::sanitize_group_rows( $rows );

            if ( ! $configured ) {
                self::redirect_with_result( 'invalid' );
            }

            if ( ! array_filter( $configured ) ) {
                self::redirect_with_result( 'no_ids' );
            }

            update_option( self::CONFIG_OPTION, $configured, false );
            $success_result = 'saved';
            $failure_result = 'saved_fail';
        }

        self::$configured_groups = null;
        self::$ids               = null;
        self::$manifest          = null;
        self::$queued            = false;
        self::$built             = false;

        delete_transient( self::CHECK_TRANSIENT );

        self::redirect_with_result( self::rebuild() ? $success_result : $failure_result );
    }

    private static function sanitize_group_rows( array $rows ): array {
        $groups   = [];
        $seen_ids = [];

        foreach ( array_slice( $rows, 0, 100 ) as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $base_name = isset( $row['name'] )
                ? sanitize_text_field( (string) $row['name'] )
                : '';

            if ( '' === $base_name ) {
                continue;
            }

            $group  = $base_name;
            $suffix = 2;

            while ( isset( $groups[ $group ] ) ) {
                $group = sprintf( '%s (%d)', $base_name, $suffix );
                $suffix++;
            }

            $raw_ids = isset( $row['ids'] ) ? (string) $row['ids'] : '';
            $tokens  = preg_split( '/\D+/', $raw_ids, -1, PREG_SPLIT_NO_EMPTY );
            $clean   = [];

            foreach ( array_slice( (array) $tokens, 0, 1000 ) as $template_id ) {
                $template_id = absint( $template_id );

                if ( $template_id && ! isset( $seen_ids[ $template_id ] ) ) {
                    $seen_ids[ $template_id ] = true;
                    $clean[]                  = $template_id;
                }
            }

            $groups[ $group ] = $clean;
        }

        return $groups;
    }

    private static function redirect_with_result( string $result ): void {
        wp_safe_redirect(
            add_query_arg(
                self::RESULT_ARG,
                sanitize_key( $result ),
                admin_url( 'tools.php?page=' . self::PAGE_SLUG )
            )
        );
        exit;
    }

    /* ---------------------------------------------------------------------
     * 手動重建端點
     * ------------------------------------------------------------------- */

    private static function rebuild_url(): string {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action'   => self::ADMIN_ACTION,
                    'redirect' => rawurlencode( self::current_url() ),
                ],
                admin_url( 'admin-post.php' )
            ),
            self::ADMIN_ACTION
        );
    }

    private static function current_url(): string {
        if ( is_admin() ) {
            global $pagenow;

            $query = ! empty( $_SERVER['QUERY_STRING'] )
                ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] )
                : '';

            return admin_url( ( $pagenow ?: 'index.php' ) . $query );
        }

        return home_url( add_query_arg( [] ) );
    }

    /**
     * 跑在 admin-post.php，屬 admin 情境，因此走 rebuild() 同步執行並回報結果。
     * 仍是同一個重建核心，沒有第二條產檔路徑。
     */
    public static function handle_manual_rebuild(): void {
        if ( ! current_user_can( self::capability() ) ) {
            wp_die( '權限不足。', 403 );
        }

        check_admin_referer( self::ADMIN_ACTION );

        if ( self::is_locked() ) {
            $result = 'locked';
        } else {
            self::$manifest = null;
            $result = self::rebuild() ? 'ok' : 'fail';
        }

        $redirect = isset( $_GET['redirect'] )
            ? rawurldecode( wp_unslash( $_GET['redirect'] ) )
            : '';

        if ( ! $redirect ) {
            $redirect = wp_get_referer() ?: admin_url( 'tools.php?page=' . self::PAGE_SLUG );
        }

        wp_safe_redirect(
            add_query_arg( self::RESULT_ARG, $result, remove_query_arg( self::RESULT_ARG, $redirect ) )
        );
        exit;
    }

    public static function admin_notice(): void {
        if ( empty( $_GET[ self::RESULT_ARG ] ) || ! current_user_can( self::capability() ) ) {
            return;
        }

        $map = [
            'ok'         => [ 'notice-success', 'Elementor 穩定版 CSS 已重建。' ],
            'fail'       => [ 'notice-error',   '重建失敗，既有 bundle 維持不變。請查看 error log。' ],
            'locked'     => [ 'notice-warning', '另一個重建程序進行中，本次略過。' ],
            'saved'      => [ 'notice-success', '範本群組清單已儲存，CSS 已重建。' ],
            'saved_fail' => [ 'notice-error',   '範本群組清單已儲存，但 CSS 重建失敗。前台暫時使用 fallback，請查看 error log。' ],
            'reset'      => [ 'notice-success', '已恢復外掛預設群組清單，CSS 已重建。' ],
            'reset_fail' => [ 'notice-error',   '已恢復外掛預設群組清單，但 CSS 重建失敗。請查看 error log。' ],
            'invalid'    => [ 'notice-error',   '未儲存：至少需要一個有名稱的群組。' ],
            'no_ids'     => [ 'notice-error',   '未儲存：所有群組皆為空，請至少加入一個有效的 Elementor 範本 ID。' ],
        ];

        $key = sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) );

        if ( ! isset( $map[ $key ] ) ) {
            return;
        }

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr( $map[ $key ][0] ),
            esc_html( $map[ $key ][1] )
        );
    }

    /* ---------------------------------------------------------------------
     * 重建窗口
     * ------------------------------------------------------------------- */

    public static function on_editor_save( $post_id ): void {
        $post_id = absint( $post_id );

        if ( ! $post_id ) {
            return;
        }

        if ( ! in_array( $post_id, self::ids(), true ) && $post_id !== self::active_kit_id() ) {
            return;
        }

        self::queue_rebuild();
    }

    /**
     * 所有觸發點的唯一入口。
     *
     * - 一個 request 只會排入一次（存檔同時清快取不會重複產檔）。
     * - 可安全建置的情境（admin / cron / CLI）延後到 shutdown 執行。
     * - 訪客情境一律改排 cron，前台永不產檔。
     */
    public static function queue_rebuild(): void {
        if ( self::$queued || self::$built ) {
            return;
        }

        self::$queued = true;

        if ( self::can_build_here() ) {
            add_action( 'shutdown', [ self::class, 'rebuild' ], 5 );
            return;
        }

        if ( ! wp_next_scheduled( self::CRON_REBUILD ) ) {
            wp_schedule_single_event( time() + 5, self::CRON_REBUILD );
        }
    }

    private static function can_build_here(): bool {
        return ( defined( 'WP_CLI' ) && WP_CLI )
            || wp_doing_cron()
            || is_admin();
    }

    /* ---------------------------------------------------------------------
     * 稽核
     * ------------------------------------------------------------------- */

    public static function admin_audit(): void {
        if ( wp_doing_ajax() || ! is_user_logged_in() ) {
            return;
        }

        // 管理端點自行同步重建，避免同一 request 又排入 shutdown 重建。
        $request_action = isset( $_REQUEST['action'] )
            ? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
            : '';

        if ( in_array( $request_action, [ self::ADMIN_ACTION, self::SAVE_ACTION ], true ) ) {
            return;
        }

        if ( get_transient( self::CHECK_TRANSIENT ) ) {
            return;
        }

        set_transient( self::CHECK_TRANSIENT, 1, self::CHECK_INTERVAL );

        if ( ! wp_next_scheduled( self::CRON_AUDIT ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_AUDIT );
        }

        self::audit();
    }

    /**
     * 群組表只綁 ID 清單，不綁範本內容，
     * 範本經 WP-CLI／匯入／版本還原修改時不會觸發任何 hook，
     * 因此用 post_modified_gmt 指紋做保底比對。
     */
    public static function audit(): void {
        $manifest = self::manifest();

        if ( ! $manifest || ( $manifest['fingerprint'] ?? '' ) !== self::fingerprint() ) {
            self::queue_rebuild();
        }
    }

    private static function fingerprint(): string {
        global $wpdb;

        $ids = self::ids();
        $kit = self::active_kit_id();

        if ( $kit ) {
            $ids[] = $kit;
        }

        if ( ! $ids ) {
            return '';
        }

        // 全部來自整數常數與 absint()，無注入面。
        $in   = implode( ',', array_map( 'absint', $ids ) );
        $rows = $wpdb->get_col(
            "SELECT CONCAT(ID, ':', post_modified_gmt) FROM {$wpdb->posts} WHERE ID IN ({$in}) ORDER BY ID ASC"
        );

        return md5( implode( '|', (array) $rows ) );
    }

    /* ---------------------------------------------------------------------
     * 重建核心（全站唯一產檔路徑）
     * ------------------------------------------------------------------- */

    public static function rebuild(): bool {
        if ( self::$built ) {
            return true;
        }

        self::$built  = true;
        self::$queued = false;

        if ( ! did_action( 'elementor/loaded' ) ) {
            self::log( 'Elementor 尚未載入，略過重建。' );
            return false;
        }

        $groups = self::groups();

        if ( ! $groups ) {
            self::log( '範本群組清單未設定任何有效範本 ID，略過重建。' );
            return false;
        }

        if ( ! self::acquire_lock() ) {
            return (bool) self::manifest();
        }

        try {
            $parts = [];
            $fonts = [];
            $icons = [];
            $count = 0;

            foreach ( $groups as $group => $template_ids ) {
                $parts[] = "/* ===== {$group} ===== */";

                foreach ( $template_ids as $template_id ) {
                    $class = self::source_class( $template_id );

                    if ( ! class_exists( $class ) ) {
                        throw new \RuntimeException(
                            sprintf(
                                '找不到 CSS 類別；群組: %s；template ID: %d；class: %s',
                                $group,
                                $template_id,
                                $class
                            )
                        );
                    }

                    $file = $class::create( $template_id );

                    // 明確 update()，避免既有 meta status=empty 時內容為空。
                    $file->update();

                    $content = trim( (string) $file->get_content() );

                    if ( '' === $content ) {
                        throw new \RuntimeException(
                            sprintf( 'CSS 生成為空；群組: %s；template ID: %d', $group, $template_id )
                        );
                    }

                    if ( self::MINIFY ) {
                        $content = self::minify( $content );
                    }

                    $parts[] = "/* [{$group}] template {$template_id} */\n{$content}";
                    $count++;

                    $meta  = (array) $file->get_meta();
                    $fonts = array_merge( $fonts, (array) ( $meta['fonts'] ?? [] ) );
                    $icons = array_merge( $icons, (array) ( $meta['icons'] ?? [] ) );
                }
            }

            $bundle = implode( "\n\n", $parts ) . "\n";
            $length = strlen( $bundle );

            if ( $length < self::MIN_BUNDLE_SIZE ) {
                throw new \RuntimeException( 'Bundle 內容異常過短。' );
            }

            $hash     = substr( hash( 'sha256', $bundle ), 0, 16 );
            $filename = self::FILE_PREFIX . $hash . '.css';
            $paths    = self::paths( $filename );

            if ( '' === $paths['path'] ) {
                throw new \RuntimeException( '無法解析 uploads 目錄。' );
            }

            // 同 hash 檔已存在且大小相符即視為可用，不重寫。
            if ( ! self::file_matches( $paths['path'], $length ) ) {
                if ( ! self::atomic_write( $paths['path'], $bundle, $length ) ) {
                    throw new \RuntimeException( '無法寫入 stable CSS bundle。' );
                }
            }

            $manifest = [
                'file'           => $filename,
                'hash'           => $hash,
                'size'           => $length,
                'build'          => self::BUILD_VERSION,
                'groups'         => self::configured_groups(),
                'minify'         => self::MINIFY,
                'template_count' => $count,
                'fingerprint'    => self::fingerprint(),
                'built_at'       => time(),
                'fonts'          => self::clean_list( $fonts ),
                'icons'          => self::valid_icons( $icons ),
            ];

            // 只有新檔完整落地後，才切換前台指標。
            update_option( self::MANIFEST_OPTION, $manifest, true );
            self::$manifest = null;

            self::prune_old_bundles( $filename );

            /**
             * page cache / CDN purge 請掛在這裡。
             * 重點：新 bundle 成功後才 purge，絕不先 purge 再生成。
             */
            do_action( 'elementor_template_css_bundle_rebuilt', $manifest );

            return true;

        } catch ( \Throwable $e ) {
            self::log( $e->getMessage() );
            return false;

        } finally {
            self::release_lock();
        }
    }

    /* ---------------------------------------------------------------------
     * CSS 壓縮
     * ------------------------------------------------------------------- */

    /**
     * 壓縮並做不變式檢查。
     *
     * minify_css() 只會移除空白與註解，不動任何有效 token，因此
     * 「移除所有註解與空白後的字串」在壓縮前後必須完全相同。
     * 一旦不同就代表解析出錯，直接退回原字串，絕不輸出可疑的 CSS。
     */
    private static function minify( string $css ): string {
        try {
            $min = self::minify_css( $css );
        } catch ( \Throwable $e ) {
            self::log( 'CSS 壓縮失敗，改用原字串：' . $e->getMessage() );
            return $css;
        }

        $normalize = static function ( string $s ): string {
            $s = (string) preg_replace( '#/\*.*?\*/#s', '', $s );
            return (string) preg_replace( '/\s+/', '', $s );
        };

        if ( $normalize( $css ) !== $normalize( $min ) ) {
            self::log( 'CSS 壓縮不變式檢查未通過，改用原字串。' );
            return $css;
        }

        return $min;
    }

    /**
     * Token-safe CSS minifier。
     *
     * 逐字元狀態機，字串內容原樣保留，並遵守下列安全規則：
     *   - 括號內的空白只整併為單一空格，絕不刪除
     *     （否則 calc( 1 - var(--x) ) 的運算子會失效）
     *   - 只有宣告區塊內的第一個冒號會去掉前後空白，
     *     偽類（.a:hover）與 .a :hover 的後代組合子都不受影響
     *   - 標識符之間的空白一律保留為單一空格（後代選擇器）
     *   - 保留每個宣告結尾的分號，與 Elementor 原生輸出風格一致
     */
    private static function minify_css( string $css ): string {
        $len = strlen( $css );

        if ( 0 === $len ) {
            return '';
        }

        // 巢狀型 at-rule：其直接子層是選擇器而非宣告。
        static $nested_at = [
            'media', 'supports', 'document', 'container', 'layer', 'scope',
            'keyframes', '-webkit-keyframes', '-moz-keyframes', '-o-keyframes',
        ];

        // 這些字元之後不需要再補空白。
        static $no_space_after = "{};,:(>+~ \n";

        $out        = '';
        $paren      = 0;
        $stack      = [];
        $prelude    = '';
        $space      = false;
        $colon_done = false;
        $i          = 0;

        $put = static function ( string $s ) use ( &$out, &$space, $no_space_after ): void {
            if (
                $space
                && '' !== $out
                && false === strpos( $no_space_after, substr( $out, -1 ) )
            ) {
                $out .= ' ';
            }

            $out  .= $s;
            $space = false;
        };

        $trim_sp = static function () use ( &$out ): void {
            $out = rtrim( $out, " \t\n\r" );
        };

        while ( $i < $len ) {
            $c = $css[ $i ];

            /* ---- 註解：預設移除，邊界標記行內保留 ---- */
            if ( '/' === $c && $i + 1 < $len && '*' === $css[ $i + 1 ] ) {
                $end     = strpos( $css, '*/', $i + 2 );
                $end     = ( false === $end ) ? $len : $end + 2;
                $comment = substr( $css, $i, $end - $i );

                if (
                    self::KEEP_CUSTOM_CSS_MARKERS
                    && preg_match( '#^/\*\s*(Start|End) custom CSS#i', $comment )
                ) {
                    $trim_sp();
                    $out  .= preg_replace( '/\s+/', ' ', trim( $comment ) );
                    $space = false;
                } else {
                    // 移除的註解視同空白，避免相鄰 token 被黏在一起。
                    $space = true;
                }

                $i = $end;
                continue;
            }

            /* ---- 字串：原樣保留 ---- */
            if ( '"' === $c || "'" === $c ) {
                $put( $c );
                $q = $c;
                $i++;

                while ( $i < $len ) {
                    $ch = $css[ $i ];

                    if ( '\\' === $ch && $i + 1 < $len ) {
                        $out .= $ch . $css[ $i + 1 ];
                        $i   += 2;
                        continue;
                    }

                    $out .= $ch;
                    $i++;

                    if ( $ch === $q ) {
                        break;
                    }
                }

                continue;
            }

            /* ---- 空白：只記錄，是否輸出由下一個 token 決定 ---- */
            if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c || "\f" === $c ) {
                $space = true;
                $i++;
                continue;
            }

            /* ---- 括號 ---- */
            if ( '(' === $c ) {
                $paren++;
                $trim_sp();
                $out    .= '(';
                $space   = false;
                $prelude .= '(';
                $i++;
                continue;
            }

            if ( ')' === $c ) {
                $paren = max( 0, $paren - 1 );
                $trim_sp();
                $out    .= ')';
                $space   = false;
                $prelude .= ')';
                $i++;
                continue;
            }

            /* ---- 區塊開始 ---- */
            if ( '{' === $c && 0 === $paren ) {
                $p    = ltrim( $prelude );
                $type = 'decl';

                if ( '' !== $p && '@' === $p[0] && preg_match( '/^@([a-z-]+)/i', $p, $m ) ) {
                    $type = in_array( strtolower( $m[1] ), $nested_at, true ) ? 'nested' : 'decl';
                }

                $trim_sp();
                $out       .= '{';
                $stack[]    = $type;
                $prelude    = '';
                $colon_done = false;
                $space      = false;
                $i++;
                continue;
            }

            /* ---- 區塊結束 ---- */
            if ( '}' === $c && 0 === $paren ) {
                array_pop( $stack );
                $trim_sp();
                $out       .= '}';
                $prelude    = '';
                $colon_done = false;
                $space      = false;
                $i++;
                continue;
            }

            /* ---- 宣告結束（保留分號，與 Elementor 原生輸出一致） ---- */
            if ( ';' === $c && 0 === $paren ) {
                $trim_sp();
                $out       .= ';';
                $prelude    = '';
                $colon_done = false;
                $space      = false;
                $i++;
                continue;
            }

            $in_decl = ! empty( $stack ) && 'decl' === end( $stack );

            /* ---- 屬性冒號：只壓縮宣告區塊內的第一個 ---- */
            if ( ':' === $c && 0 === $paren && $in_decl && ! $colon_done ) {
                $trim_sp();
                $out       .= ':';
                $colon_done = true;
                $space      = false;
                $i++;
                continue;
            }

            /* ---- 選擇器組合子：只在選擇器情境壓縮，宣告值內不動 ---- */
            if (
                0 === $paren
                && ! $in_decl
                && ( '>' === $c || '+' === $c || '~' === $c )
            ) {
                $trim_sp();
                $out     .= $c;
                $prelude .= $c;
                $space    = false;
                $i++;
                continue;
            }

            /* ---- 逗號 ---- */
            if ( ',' === $c && 0 === $paren ) {
                $trim_sp();
                $out     .= ',';
                $prelude .= ',';
                $space    = false;
                $i++;
                continue;
            }

            $put( $c );
            $prelude .= $c;
            $i++;
        }

        return trim( $out );
    }

    private static function source_class( int $template_id ): string {
        $loop_class = '\\ElementorPro\\Modules\\LoopBuilder\\Files\\CSS\\Loop';
        $type       = (string) get_post_meta( $template_id, '_elementor_template_type', true );

        $class = in_array( $type, [ 'loop-item', 'loop' ], true ) && class_exists( $loop_class )
            ? $loop_class
            : '\\Elementor\\Core\\Files\\CSS\\Post';

        return (string) apply_filters(
            'elementor_template_css_bundle_source_class',
            $class,
            $template_id,
            $type
        );
    }

    /* ---------------------------------------------------------------------
     * 檔案操作
     * ------------------------------------------------------------------- */

    private static function paths( string $filename = '' ): array {
        if ( null === self::$upload_paths ) {
            $uploads = wp_upload_dir( null, false );

            self::$upload_paths = empty( $uploads['error'] )
                ? [
                    'dir' => trailingslashit( $uploads['basedir'] ) . 'elementor/' . self::STABLE_DIR,
                    'url' => trailingslashit( $uploads['baseurl'] ) . 'elementor/' . self::STABLE_DIR,
                ]
                : [ 'dir' => '', 'url' => '' ];
        }

        $base = self::$upload_paths;

        if ( '' === $base['dir'] ) {
            return [ 'dir' => '', 'path' => '', 'url' => '' ];
        }

        return [
            'dir'  => $base['dir'],
            'path' => $filename ? trailingslashit( $base['dir'] ) . $filename : '',
            'url'  => $filename ? trailingslashit( $base['url'] ) . $filename : '',
        ];
    }

    private static function atomic_write( string $path, string $content, int $length ): bool {
        $dir = dirname( $path );

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return false;
        }

        $tmp = trailingslashit( $dir ) . '.' . basename( $path ) . '.' . wp_generate_uuid4() . '.tmp';

        $bytes = file_put_contents( $tmp, $content, LOCK_EX );

        if ( false === $bytes || $bytes !== $length ) {
            @unlink( $tmp );
            return false;
        }

        if ( defined( 'FS_CHMOD_FILE' ) ) {
            @chmod( $tmp, FS_CHMOD_FILE );
        }

        if ( ! @rename( $tmp, $path ) ) {
            @unlink( $tmp );
            return false;
        }

        clearstatcache( true, $path );

        return self::file_matches( $path, $length );
    }

    /** 比對大小而非僅「非空」，可攔截截斷或部分寫入。 */
    private static function file_matches( string $path, int $length ): bool {
        return is_readable( $path ) && filesize( $path ) === $length;
    }

    private static function prune_old_bundles( string $current ): void {
        $paths = self::paths();

        if ( '' === $paths['dir'] || ! is_dir( $paths['dir'] ) ) {
            return;
        }

        $files = glob( trailingslashit( $paths['dir'] ) . self::FILE_PREFIX . '*.css' );

        if ( ! is_array( $files ) || count( $files ) <= self::RETAINED_FILES ) {
            return;
        }

        usort( $files, static fn( string $a, string $b ): int => filemtime( $b ) <=> filemtime( $a ) );

        $kept = 0;

        foreach ( $files as $file ) {
            if ( basename( $file ) === $current || $kept < self::RETAINED_FILES ) {
                $kept++;
                continue;
            }

            @unlink( $file );
        }
    }

    /* ---------------------------------------------------------------------
     * 異常 fallback
     * ------------------------------------------------------------------- */

    private static function fallback_enqueue_original_files(): void {
        foreach ( self::ids() as $template_id ) {
            $class = self::source_class( $template_id );

            if ( ! class_exists( $class ) ) {
                continue;
            }

            try {
                $file = $class::create( $template_id );
                $file->update();
                $file->enqueue();
            } catch ( \Throwable $e ) {
                self::log( sprintf( 'Fallback 失敗；template ID: %d；%s', $template_id, $e->getMessage() ) );
            }
        }
    }

    /**
     * 此時已在 wp_footer 階段，header 多半送出，只有 DONOTCACHEPAGE 實際有效
     * （多數 page cache 於 output buffer flush 時才判定）。
     */
    private static function mark_response_uncacheable(): void {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        if ( ! headers_sent() ) {
            header( 'X-Elementor-Template-CSS-Bundle: stable-bundle-missing' );
        }
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    private static function clean_list( array $items ): array {
        return array_values( array_unique( array_filter( $items ) ) );
    }

    /** 圖示字型的有效性在 build 端解析一次，前台不再呼叫 Icons_Manager。 */
    private static function valid_icons( array $icons ): array {
        $icons = self::clean_list( $icons );

        if ( ! $icons || ! class_exists( '\\Elementor\\Icons_Manager' ) ) {
            return $icons;
        }

        $tabs = \Elementor\Icons_Manager::get_icon_manager_tabs();

        if ( ! $tabs ) {
            return $icons;
        }

        return array_values( array_filter(
            $icons,
            static fn( $icon ): bool => isset( $tabs[ $icon ] )
        ) );
    }

    private static function active_kit_id(): int {
        if ( ! class_exists( '\\Elementor\\Plugin' ) || empty( \Elementor\Plugin::$instance->kits_manager ) ) {
            return 0;
        }

        return absint( \Elementor\Plugin::$instance->kits_manager->get_active_id() );
    }

    private static function is_locked(): bool {
        $existing = absint( get_option( self::LOCK_OPTION, 0 ) );

        return $existing && $existing > ( time() - self::LOCK_TTL );
    }

    private static function acquire_lock(): bool {
        $now      = time();
        $existing = absint( get_option( self::LOCK_OPTION, 0 ) );

        if ( $existing && $existing > ( $now - self::LOCK_TTL ) ) {
            return false;
        }

        if ( $existing ) {
            delete_option( self::LOCK_OPTION );
        }

        // add_option 對唯一 option_name 具原子性，可作跨請求互斥鎖。
        return add_option( self::LOCK_OPTION, $now, '', 'no' );
    }

    private static function release_lock(): void {
        delete_option( self::LOCK_OPTION );
    }

    private static function log( string $message ): void {
        error_log( '[Loop and Template CSS Recovery for Elementor] ' . $message );
    }
}

Elementor_Template_CSS_Bundle::boot();
