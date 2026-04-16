<?php
defined( 'ABSPATH' ) || exit;

/**
 * Custom WooCommerce shipping method that calculates per-vendor shipping
 * based on the customer's location (Lima Metropolitana vs Provincia).
 *
 * Cart is split into one package per vendor so each vendor's shipping
 * cost and delivery time is displayed separately at checkout.
 */
class TCG_Shipping extends WC_Shipping_Method {

	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'tcg_vendor_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Envío por vendedor (TCG)', 'tcg-manager' );
		$this->method_description = __( 'Calcula el envío automáticamente según las tarifas de cada vendedor.', 'tcg-manager' );
		$this->supports           = [ 'shipping-zones', 'instance-settings' ];
		$this->enabled            = 'yes';

		$this->init();
	}

	public function init() {
		$this->init_settings();
	}

	/**
	 * Calculate shipping for a single-vendor package.
	 */
	public function calculate_shipping( $package = [] ) {
		$vendor_id = $package['tcg_vendor_id'] ?? 0;
		if ( ! $vendor_id ) {
			// Fallback: get vendor from first item.
			$first = reset( $package['contents'] );
			$vendor_id = $first ? (int) get_post_field( 'post_author', $first['product_id'] ) : 0;
		}

		if ( ! $vendor_id ) return;

		$vendor_name = TCG_Vendor_Profile::get_shop_name( $vendor_id );

		// Si el modo es recojo en tienda, emitir rate de recojo (costo 0) y omitir envío.
		if ( class_exists( 'TCG_Pickup' ) && TCG_Pickup::get_mode() === 'pickup' ) {
			$store = TCG_Pickup_Store::get( TCG_Pickup::get_store_id() );
			$label = sprintf( __( 'Recojo en tienda — %s', 'tcg-manager' ), $vendor_name );
			if ( $store ) {
				$label .= ' (' . $store['name'] . ')';
			}
			$this->add_rate( [
				'id'    => $this->get_rate_id() . ':pickup',
				'label' => $label,
				'cost'  => 0,
			] );
			return;
		}

		$destination_state = $package['destination']['state'] ?? '';
		$is_lima           = $this->is_lima( $destination_state );

		$cost = $this->get_vendor_shipping_cost( $vendor_id, $is_lima );
		$days = $this->get_vendor_shipping_days( $vendor_id, $is_lima );

		$label = sprintf( __( 'Envío — %s', 'tcg-manager' ), $vendor_name );
		if ( $days ) {
			$label .= ' (' . $days . ')';
		}

		$this->add_rate( [
			'id'    => $this->get_rate_id(),
			'label' => $label,
			'cost'  => $cost,
		] );
	}

	/**
	 * Determine if a state code is Lima Metropolitana.
	 */
	private function is_lima( $state ) {
		return in_array( strtoupper( $state ), [ 'LIM', 'LMA' ], true );
	}

	/**
	 * Get shipping cost for a vendor based on zone.
	 */
	private function get_vendor_shipping_cost( $vendor_id, $is_lima ) {
		$key  = $is_lima ? '_tcg_shipping_lima_price' : '_tcg_shipping_provincia_price';
		$cost = get_user_meta( $vendor_id, $key, true );
		return $cost !== '' ? (float) $cost : 0;
	}

	/**
	 * Get shipping days string for a vendor based on zone.
	 */
	private function get_vendor_shipping_days( $vendor_id, $is_lima ) {
		$prefix = $is_lima ? '_tcg_shipping_lima' : '_tcg_shipping_provincia';
		$min    = get_user_meta( $vendor_id, $prefix . '_days_min', true );
		$max    = get_user_meta( $vendor_id, $prefix . '_days_max', true );

		if ( $min && $max && $min !== $max ) {
			return sprintf( __( '%s-%s días', 'tcg-manager' ), $min, $max );
		} elseif ( $min ) {
			return sprintf( _n( '%s día', '%s días', (int) $min, 'tcg-manager' ), $min );
		}

		return '';
	}
}

/**
 * Register the shipping method with WooCommerce.
 */
add_filter( 'woocommerce_shipping_methods', function( $methods ) {
	$methods['tcg_vendor_shipping'] = 'TCG_Shipping';
	return $methods;
} );

/**
 * Split cart into one shipping package per vendor.
 *
 * WooCommerce displays each package separately at checkout, so the customer
 * sees individual shipping costs and delivery times per vendor.
 */
add_filter( 'woocommerce_cart_shipping_packages', function( $packages ) {
	// Only split if there's a default package with items.
	if ( empty( $packages ) ) return $packages;

	// Add delivery mode + pickup store to packages so WC cache hash changes
	// when the customer switches between delivery and pickup.
	$delivery_mode = class_exists( 'TCG_Pickup' ) ? TCG_Pickup::get_mode() : '';
	$pickup_store  = class_exists( 'TCG_Pickup' ) ? TCG_Pickup::get_store_id() : 0;

	// Collect all items from all existing packages.
	$all_items = [];
	$base_package = $packages[0];
	foreach ( $packages as $pkg ) {
		foreach ( $pkg['contents'] as $key => $item ) {
			$all_items[ $key ] = $item;
		}
	}

	if ( empty( $all_items ) ) return $packages;

	// Group by vendor.
	$vendor_items = [];
	foreach ( $all_items as $key => $item ) {
		$vendor_id = (int) get_post_field( 'post_author', $item['product_id'] );
		if ( ! $vendor_id ) $vendor_id = 0;
		$vendor_items[ $vendor_id ][ $key ] = $item;
	}

	// If single vendor, no need to split.
	if ( count( $vendor_items ) <= 1 ) {
		$vendor_id = array_key_first( $vendor_items );
		$packages[0]['tcg_vendor_id']     = $vendor_id;
		$packages[0]['tcg_vendor_name']   = TCG_Vendor_Profile::get_shop_name( $vendor_id );
		$packages[0]['tcg_delivery_mode'] = $delivery_mode;
		$packages[0]['tcg_pickup_store']  = $pickup_store;
		return $packages;
	}

	// Build one package per vendor.
	$new_packages = [];
	foreach ( $vendor_items as $vendor_id => $items ) {
		$pkg = $base_package;
		$pkg['contents']          = $items;
		$pkg['contents_cost']     = array_sum( wp_list_pluck( $items, 'line_total' ) );
		$pkg['tcg_vendor_id']     = $vendor_id;
		$pkg['tcg_vendor_name']   = TCG_Vendor_Profile::get_shop_name( $vendor_id );
		$pkg['tcg_delivery_mode'] = $delivery_mode;
		$pkg['tcg_pickup_store']  = $pickup_store;
		$new_packages[] = $pkg;
	}

	return $new_packages;
} );

/**
 * Customize the shipping package name shown at checkout.
 * Instead of "Shipping", show "Envío — Vendor Name".
 */
add_filter( 'woocommerce_shipping_package_name', function( $name, $index, $package ) {
	if ( ! empty( $package['tcg_vendor_name'] ) ) {
		return sprintf( __( 'Envío — %s', 'tcg-manager' ), $package['tcg_vendor_name'] );
	}
	return $name;
}, 10, 3 );
