<?php
defined( 'ABSPATH' ) || exit;

class TCG_Vendor_Profile {

	public function __construct() {
		// These run immediately since we're already past early init.
		$this->register_meta();
		$this->add_rewrite_rules();
		$this->maybe_flush_rewrites();
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_store_page' ] );
	}

	public function register_meta() {
		$fields = [
			'_tcg_shop_name'        => 'string',
			'_tcg_shop_slug'        => 'string',
			'_tcg_shop_description' => 'string',
			'_tcg_payment_info'     => 'string',
			'_tcg_commission_rate'  => 'string',
		];

		foreach ( $fields as $key => $type ) {
			register_meta( 'user', $key, [
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => $type === 'integer' ? 'absint' : 'sanitize_text_field',
			] );
		}
	}

	public function add_rewrite_rules() {
		add_rewrite_rule(
			'^tienda/vendor/([^/]+)/?$',
			'index.php?tcg_vendor_store=$matches[1]',
			'top'
		);
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'tcg_vendor_store';
		return $vars;
	}

	public function maybe_flush_rewrites() {
		if ( get_option( 'tcg_manager_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( 'tcg_manager_flush_rewrite' );
		}
	}

	/**
	 * Handle store page requests.
	 */
	public function handle_store_page() {
		$store_slug = get_query_var( 'tcg_vendor_store' );
		if ( ! $store_slug ) {
			return;
		}

		// Find vendor by shop slug.
		$users = get_users( [
			'meta_key'   => '_tcg_shop_slug',
			'meta_value' => sanitize_title( $store_slug ),
			'number'     => 1,
			'role'       => 'tcg_vendor',
		] );

		if ( empty( $users ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		$vendor = $users[0];

		// Set global for templates.
		set_query_var( 'tcg_vendor_user', $vendor );

		// Load template.
		$template = locate_template( 'tcg-manager/store-page.php' );
		if ( ! $template ) {
			$template = TCG_MANAGER_PATH . 'templates/store/store-page.php';
		}

		if ( file_exists( $template ) ) {
			include $template;
			exit;
		}
	}

	/**
	 * Get store URL for a vendor.
	 */
	public static function get_store_url( $user_id ) {
		$slug = get_user_meta( $user_id, '_tcg_shop_slug', true );
		if ( ! $slug ) {
			return '';
		}
		return home_url( '/tienda/vendor/' . $slug . '/' );
	}

	/**
	 * Get shop name with fallback.
	 */
	public static function get_shop_name( $user_id ) {
		$name = get_user_meta( $user_id, '_tcg_shop_name', true );
		if ( $name ) {
			return $name;
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : '';
	}
}
