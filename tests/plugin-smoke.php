<?php

declare( strict_types=1 );

define( 'ABSPATH', '/' );

$GLOBALS['test_actions']         = [];
$GLOBALS['test_action_counts']   = [];
$GLOBALS['test_deactivation']    = null;
$GLOBALS['test_enqueued_styles'] = [];
$GLOBALS['test_removed']         = [];
$GLOBALS['test_options']         = [];
$GLOBALS['test_style_queue']     = [];

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
	$GLOBALS['test_actions'][ $hook ][ $priority ][] = [
		'callback'      => $callback,
		'accepted_args' => $accepted_args,
	];
}

function did_action( $hook ): int {
	return (int) ( $GLOBALS['test_action_counts'][ $hook ] ?? 0 );
}

function is_admin(): bool {
    return false;
}

function get_option( $name, $default = false ) {
    return array_key_exists( $name, $GLOBALS['test_options'] )
        ? $GLOBALS['test_options'][ $name ]
        : $default;
}

function sanitize_text_field( $value ): string {
    return trim( strip_tags( (string) $value ) );
}

function absint( $value ): int {
    return abs( (int) $value );
}

function apply_filters( $hook, $value ) {
    return $value;
}

function current_user_can( $capability ): bool {
    return true;
}

function admin_url( $path = '' ): string {
    return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

function home_url( $path = '' ): string {
    return 'https://example.test/' . ltrim( (string) $path, '/' );
}

function add_query_arg( ...$args ): string {
    if ( 1 === count( $args ) && is_array( $args[0] ) ) {
        return '';
    }

    $query = is_array( $args[0] ) ? $args[0] : [ $args[0] => $args[1] ];
    $url   = is_array( $args[0] ) ? ( $args[1] ?? '' ) : ( $args[2] ?? '' );

    return $url . '?' . http_build_query( $query );
}

function wp_nonce_url( $url, $action ): string {
    return $url . '&_wpnonce=test';
}

function wp_nonce_field( $action ): void {
    echo '<input type="hidden" name="_wpnonce" value="test">';
}

function esc_attr( $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $value ): string {
    return (string) $value;
}

function esc_textarea( $value ): string {
    return htmlspecialchars( (string) $value, ENT_NOQUOTES, 'UTF-8' );
}

function number_format_i18n( $number, $decimals = 0 ): string {
    return number_format( (float) $number, (int) $decimals );
}

function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ): void {
	if ( ! in_array( $handle, $GLOBALS['test_style_queue'], true ) ) {
		$GLOBALS['test_style_queue'][] = $handle;
	}

	$GLOBALS['test_enqueued_styles'][ $handle ] = [
		'src'   => $src,
		'deps'  => $deps,
		'ver'   => $ver,
		'media' => $media,
	];
}

function register_deactivation_hook( $file, $callback ): void {
	$GLOBALS['test_deactivation'] = [
		'file'     => $file,
		'callback' => $callback,
	];
}

function wp_clear_scheduled_hook( $hook ): void {
	// Only needed by the registered deactivation callback.
}

function is_singular( $post_type ): bool {
	return 'post' === $post_type;
}

function remove_action( $hook, $callback, $priority = 10 ): bool {
	$GLOBALS['test_removed'][] = [ $hook, $callback, $priority ];
	return true;
}

function test_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$plugin_file = dirname( __DIR__ )
	. '/elementor-template-css-bundle/elementor-template-css-bundle.php';

require $plugin_file;

test_assert(
	class_exists( 'Elementor_Template_CSS_Bundle', false ),
	'The plugin class was not loaded.'
);
test_assert(
	isset( $GLOBALS['test_actions']['wp_footer'][19] ),
	'The late bundle enqueue hook was not registered before WordPress footer styles.'
);
test_assert(
    is_array( $GLOBALS['test_deactivation'] ),
    'The deactivation hook was not registered.'
);
test_assert(
    isset( $GLOBALS['test_actions']['admin_post_elementor_template_css_bundle_save_groups'][10] ),
    'The group settings endpoint was not registered.'
);

$hook_count_before = count( $GLOBALS['test_actions']['wp_footer'][19] );
Elementor_Template_CSS_Bundle::boot();
$hook_count_after = count( $GLOBALS['test_actions']['wp_footer'][19] );

test_assert(
	$hook_count_before === $hook_count_after,
	'Loading the class file twice registered duplicate hooks.'
);

$manifest_property = new ReflectionProperty(
	'Elementor_Template_CSS_Bundle',
	'manifest'
);
$manifest_property->setAccessible( true );
$manifest_property->setValue(
	null,
	[
		'url'   => 'https://example.test/template-bundle.css',
		'hash'  => 'content-hash',
		'fonts' => [],
		'icons' => [],
	]
);

$footer_callback = $GLOBALS['test_actions']['wp_footer'][19][0]['callback'];
$GLOBALS['test_style_queue'] = [ 'widget-nested-tabs' ];

call_user_func( $footer_callback );

test_assert(
	[ 'widget-nested-tabs' ] === $GLOBALS['test_style_queue'],
	'The bundle was enqueued without the Elementor front-end admission action.'
);

$GLOBALS['test_action_counts']['elementor/frontend/after_enqueue_styles'] = 1;
call_user_func( $footer_callback );

test_assert(
	[ 'widget-nested-tabs', 'elementor-template-css-bundle' ] === $GLOBALS['test_style_queue'],
	'The recovery bundle was not queued after the late-discovered widget stylesheet.'
);
test_assert(
	[
		'src'   => 'https://example.test/template-bundle.css',
		'deps'  => [ 'elementor-frontend' ],
		'ver'   => 'content-hash',
		'media' => 'all',
	] === $GLOBALS['test_enqueued_styles']['elementor-template-css-bundle'],
	'The late bundle enqueue did not preserve its URL, dependency, hash, or media contract.'
);

call_user_func( $footer_callback );

test_assert(
	1 === count( array_keys( $GLOBALS['test_style_queue'], 'elementor-template-css-bundle', true ) ),
	'Repeated enqueue attempts duplicated the bundle handle.'
);

add_action(
	'wp',
	static function (): void {
		if ( is_singular( 'post' ) ) {
			remove_action(
				'rodest_action_before_page_inner',
				'rodest_blog_single_above_image_media',
				10
			);
		}
	},
	1
);

test_assert(
	isset( $GLOBALS['test_actions']['wp'][1][0]['callback'] ),
	'The independent Rodest snippet was not registered after loading the plugin.'
);

call_user_func( $GLOBALS['test_actions']['wp'][1][0]['callback'] );

test_assert(
    [
        'rodest_action_before_page_inner',
		'rodest_blog_single_above_image_media',
		10,
	] === $GLOBALS['test_removed'][0],
    'The independent Rodest remove_action call did not run as expected.'
);

$configured_method = new ReflectionMethod(
    'Elementor_Template_CSS_Bundle',
    'configured_groups'
);
$sanitize_method = new ReflectionMethod(
    'Elementor_Template_CSS_Bundle',
    'sanitize_group_rows'
);
$configured_method->setAccessible( true );
$sanitize_method->setAccessible( true );

$default_groups = $configured_method->invoke( null );

test_assert(
    isset( $default_groups['Mega Menu - Loop'] )
        && 9 === count( $default_groups['Mega Menu - Loop'] ),
    'The built-in default template groups were not available.'
);

$sanitized_groups = $sanitize_method->invoke(
    null,
    [
        [
            'name' => 'Group',
            'ids'  => "11, 12\n12",
        ],
        [
            'name' => 'Group',
            'ids'  => "12 13",
        ],
        [
            'name' => 'Empty Group',
            'ids'  => '',
        ],
    ]
);

test_assert(
    [
        'Group'       => [ 11, 12 ],
        'Group (2)'   => [ 13 ],
        'Empty Group' => [],
    ] === $sanitized_groups,
    'Group names, order, empty groups, or duplicate IDs were not normalized correctly.'
);

$GLOBALS['wpdb'] = new class() {
    public $posts = 'wp_posts';

    public function get_col( $query ): array {
        return [];
    }
};

ob_start();
Elementor_Template_CSS_Bundle::render_page();
$settings_page = (string) ob_get_clean();

test_assert(
    false !== strpos( $settings_page, 'id="etcb-group-editor"' )
        && false !== strpos( $settings_page, 'name="template_groups[0][name]"' )
        && false !== strpos( $settings_page, '儲存清單並重建' )
        && false !== strpos( $settings_page, '`template_groups[${index}][name]`' ),
    'The editable group settings page did not render the expected fields or reorder script.'
);

echo "PASS: plugin boot, late bundle order, configurable groups, duplicate-load guard, and Rodest hook isolation.\n";
