<?php
/**
 * Plugin Name: Elementor Template CSS Bundle
 * Description: Creates a selective CSS recovery bundle for Elementor templates whose styles are missing or broken on the front end.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Site Team
 * License: GPL-2.0-or-later
 * Text Domain: elementor-template-css-bundle
 */

defined( 'ABSPATH' ) || exit;

define( 'SITE_ELEMENTOR_TEMPLATE_CSS_BUNDLE_VERSION', '1.0.1' );
define( 'SITE_ELEMENTOR_TEMPLATE_CSS_BUNDLE_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-elementor-template-css-bundle.php';

/**
 * Stop future rebuild/audit events when the plugin is disabled.
 *
 * The generated bundle and manifest intentionally remain available so a
 * temporary deactivation never destroys the last-known-good CSS file.
 */
register_deactivation_hook(
	__FILE__,
	static function (): void {
		wp_clear_scheduled_hook( 'elementor_template_css_bundle_rebuild' );
		wp_clear_scheduled_hook( 'elementor_template_css_bundle_audit' );
	}
);
