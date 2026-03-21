<?php
defined( 'ABSPATH' ) || exit;

/**
 * Custom WooCommerce shipping method that calculates per-vendor shipping
 * based on the customer's location (Lima Metropolitana vs Provincia).
 */
class TCG_Shipping extends WC_Shipping_Method {

	/** @var string Lima department code used in WooCommerce. */
	const LIMA_STATE = 'LIM';

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
	 * Calculate shipping — one rate per vendor in the cart.
	 */
	public function calculate_shipping( $package = [] ) {
		$destination_state = $package['destination']['state'] ?? '';
		$is_lima           = $this->is_lima( $destination_state );

		// Group items by vendor.
		$vendors = [];
		foreach ( $package['contents'] as $item ) {
			$product_id = $item['product_id'];
			$vendor_id  = (int) get_post_field( 'post_author', $product_id );
			if ( ! $vendor_id ) continue;

			if ( ! isset( $vendors[ $vendor_id ] ) ) {
				$vendors[ $vendor_id ] = [
					'name'  => TCG_Vendor_Profile::get_shop_name( $vendor_id ),
					'cost'  => $this->get_vendor_shipping_cost( $vendor_id, $is_lima ),
					'days'  => $this->get_vendor_shipping_days( $vendor_id, $is_lima ),
				];
			}
		}

		if ( empty( $vendors ) ) return;

		// If single vendor, add one clean rate.
		if ( count( $vendors ) === 1 ) {
			$vendor = reset( $vendors );
			$label  = $this->build_label( $vendor['name'], $vendor['days'] );
			$this->add_rate( [
				'id'    => $this->get_rate_id(),
				'label' => $label,
				'cost'  => $vendor['cost'],
			] );
			return;
		}

		// Multiple vendors: sum costs and combine labels.
		$total_cost = 0;
		$labels     = [];
		foreach ( $vendors as $vendor ) {
			$total_cost += $vendor['cost'];
			$labels[]    = $vendor['name'] . ': ' . wc_price( $vendor['cost'] )
				. ( $vendor['days'] ? ' (' . $vendor['days'] . ')' : '' );
		}

		$this->add_rate( [
			'id'    => $this->get_rate_id(),
			'label' => __( 'Envío por vendedor', 'tcg-manager' ),
			'cost'  => $total_cost,
			'meta_data' => [
				__( 'Detalle', 'tcg-manager' ) => implode( ' | ', $labels ),
			],
		] );
	}

	/**
	 * Determine if a state code is Lima Metropolitana.
	 */
	private function is_lima( $state ) {
		// WooCommerce Peru states: LIM = Lima (department), LMA = Lima Metropolitana (province).
		// Both should be treated as "Lima" for shipping purposes.
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

	/**
	 * Build label with vendor name and optional delivery time.
	 */
	private function build_label( $vendor_name, $days ) {
		$label = sprintf( __( 'Envío — %s', 'tcg-manager' ), $vendor_name );
		if ( $days ) {
			$label .= ' (' . $days . ')';
		}
		return $label;
	}
}

/**
 * Register the shipping method with WooCommerce.
 */
add_filter( 'woocommerce_shipping_methods', function( $methods ) {
	$methods['tcg_vendor_shipping'] = 'TCG_Shipping';
	return $methods;
} );
