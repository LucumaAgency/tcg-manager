<?php
/**
 * Plugin Name: TCG Manager
 * Description: Marketplace de cartas YGO — vendors crean productos WooCommerce vinculados al catálogo de cartas.
 * Version:     1.0.9
 * Author:      Lucuma Agency
 * Requires Plugins: woocommerce
 * Text Domain: tcg-manager
 */

defined( 'ABSPATH' ) || exit;

define( 'TCG_MANAGER_VERSION', '1.0.9' );
define( 'TCG_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'TCG_MANAGER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check plugin dependencies — runs on plugins_loaded.
 */
function tcg_manager_check_plugins() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>TCG Manager:</strong> requiere WooCommerce activo.';
			echo '</p></div>';
		} );
		return;
	}

	require_once TCG_MANAGER_PATH . 'includes/class-tcg-setup.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-vendor-role.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-vendor-profile.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-dashboard.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-product-form.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-product-sync.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-orders.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-commissions.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-ajax.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-display.php';
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-listings.php';
	require_once TCG_MANAGER_PATH . 'admin/class-tcg-admin-commissions.php';
	require_once TCG_MANAGER_PATH . 'admin/class-tcg-admin-vendors.php';

	add_action( 'init', 'tcg_manager_boot', 25 );
	add_action( 'admin_init', 'tcg_manager_maybe_add_title_index' );

	// Flush rewrite rules on version change.
	if ( get_option( 'tcg_manager_version' ) !== TCG_MANAGER_VERSION ) {
		update_option( 'tcg_manager_flush_rewrite', 1 );
		update_option( 'tcg_manager_version', TCG_MANAGER_VERSION );
	}
}
add_action( 'plugins_loaded', 'tcg_manager_check_plugins', 20 );

/**
 * Boot after init — ygo_card CPT must exist.
 */
function tcg_manager_boot() {
	if ( ! post_type_exists( 'ygo_card' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>TCG Manager:</strong> requiere el CPT ygo_card (tcg-theme activo).';
			echo '</p></div>';
		} );
		return;
	}

	new TCG_Setup();
	new TCG_Vendor_Role();
	new TCG_Vendor_Profile();
	new TCG_Dashboard();
	new TCG_Product_Form();
	new TCG_Product_Sync();
	new TCG_Orders();
	new TCG_Commissions();
	new TCG_Ajax();
	new TCG_Display();
	new TCG_Listings();

	if ( is_admin() ) {
		new TCG_Admin_Commissions();
		new TCG_Admin_Vendors();
	}
}

/**
 * Activation hook.
 */
function tcg_manager_activate() {
	// Load required files for activation.
	require_once TCG_MANAGER_PATH . 'includes/class-tcg-vendor-role.php';

	// Create vendor role.
	TCG_Vendor_Role::create_role();

	// Seed taxonomy terms.
	$terms = [
		'ygo_condition' => [ 'Near Mint', 'Lightly Played', 'Moderately Played', 'Heavily Played', 'Damaged' ],
		'ygo_printing'  => [ '1st Edition', 'Unlimited', 'Limited' ],
		'ygo_language'  => [ 'English', 'Spanish', 'Japanese', 'Portuguese' ],
	];
	foreach ( $terms as $taxonomy => $names ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'product', [ 'hierarchical' => true ] );
		}
		foreach ( $names as $name ) {
			if ( ! term_exists( $name, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy );
			}
		}
	}

	// Create commissions table.
	tcg_manager_create_tables();

	// DB index.
	tcg_manager_maybe_add_title_index();

	// Flag to flush rewrite rules on next init.
	update_option( 'tcg_manager_flush_rewrite', 1 );
}
register_activation_hook( __FILE__, 'tcg_manager_activate' );

/**
 * Create/upgrade custom tables.
 */
function tcg_manager_create_tables() {
	global $wpdb;

	$table   = $wpdb->prefix . 'tcg_commissions';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		order_id BIGINT UNSIGNED NOT NULL,
		sub_order_id BIGINT UNSIGNED DEFAULT NULL,
		vendor_id BIGINT UNSIGNED NOT NULL,
		product_id BIGINT UNSIGNED NOT NULL,
		sale_total DECIMAL(10,2) NOT NULL,
		commission DECIMAL(10,2) NOT NULL,
		vendor_net DECIMAL(10,2) NOT NULL,
		status VARCHAR(20) DEFAULT 'pending',
		paid_date DATETIME DEFAULT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY idx_vendor (vendor_id),
		KEY idx_order (order_id),
		KEY idx_status (status)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * FULLTEXT index on post_title for fast card search.
 */
function tcg_manager_maybe_add_title_index() {
	global $wpdb;

	$index = 'tcg_ft_post_title';
	$has   = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = %s AND table_name = %s AND index_name = %s",
		DB_NAME,
		$wpdb->posts,
		$index
	) );

	if ( ! $has ) {
		$wpdb->query( "ALTER TABLE {$wpdb->posts} ADD FULLTEXT INDEX {$index} (post_title)" ); // phpcs:ignore
	}
}
