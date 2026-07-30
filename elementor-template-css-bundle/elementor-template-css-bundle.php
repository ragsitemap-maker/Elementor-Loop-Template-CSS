<?php
/**
 * Plugin Name: Elementor Template CSS Bundle
 * Description: Builds the configured Elementor template CSS into one stable, content-hashed bundle for front-end use.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Site Team
 * License: GPL-2.0-or-later
 * Text Domain: elementor-template-css-bundle
 */

defined( 'ABSPATH' ) || exit;

define( 'SITE_ELEMENTOR_TEMPLATE_CSS_BUNDLE_VERSION', '1.0.0' );
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
