<?php
defined( 'ABSPATH' ) || exit;

class TCG_Vendor_Role {

	public function __construct() {
		// Ensure role exists (in case it was removed).
		add_action( 'init', [ __CLASS__, 'create_role' ], 5 );
	}

	/**
	 * Create the tcg_vendor role with required capabilities.
	 */
	public static function create_role() {
		if ( get_role( 'tcg_vendor' ) ) {
			return;
		}

		add_role( 'tcg_vendor', __( 'Vendedor', 'tcg-manager' ), [
			'read'                      => true,
			'edit_posts'                => true,
			'delete_posts'              => true,
			'publish_posts'             => true,
			'edit_published_posts'      => true,
			'delete_published_posts'    => true,
			'upload_files'              => true,
			// WooCommerce product caps.
			'edit_products'             => true,
			'delete_products'           => true,
			'publish_products'          => true,
			'edit_published_products'   => true,
			'delete_published_products' => true,
			'read_product'              => true,
			'edit_product'              => true,
			'delete_product'            => true,
			'assign_product_terms'      => true,
		] );
	}

	/**
	 * Check if a user is a vendor.
	 */
	public static function is_vendor( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$user = get_userdata( $user_id );
		return $user && in_array( 'tcg_vendor', (array) $user->roles, true );
	}

	/**
	 * Get vendor data from user meta.
	 */
	public static function get_vendor_data( $user_id ) {
		return [
			'shop_name'        => get_user_meta( $user_id, '_tcg_shop_name', true ),
			'shop_slug'        => get_user_meta( $user_id, '_tcg_shop_slug', true ),
			'shop_description' => get_user_meta( $user_id, '_tcg_shop_description', true ),
			'shop_logo_id'     => (int) get_user_meta( $user_id, '_tcg_shop_logo_id', true ),
			'payment_info'     => get_user_meta( $user_id, '_tcg_payment_info', true ),
			'commission_rate'  => get_user_meta( $user_id, '_tcg_commission_rate', true ),
		];
	}
}
