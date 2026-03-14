<?php
defined( 'ABSPATH' ) || exit;

class TCG_Commissions {

	public function __construct() {
		add_action( 'woocommerce_order_status_completed', [ $this, 'calculate_commission' ] );
		add_action( 'woocommerce_order_status_processing', [ $this, 'calculate_commission' ] );
		add_filter( 'woocommerce_get_settings_pages', [ $this, 'add_settings_page' ] );
	}

	/**
	 * Get commission rate for a vendor.
	 */
	public static function get_commission_rate( $vendor_id ) {
		$per_vendor = get_user_meta( $vendor_id, '_tcg_commission_rate', true );
		if ( $per_vendor !== '' && $per_vendor !== false ) {
			return (float) $per_vendor;
		}
		return (float) get_option( 'tcg_manager_commission_rate', 10 );
	}

	/**
	 * Calculate commissions for an order.
	 */
	public function calculate_commission( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tcg_commissions';

		// Check if already calculated.
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(1) FROM {$table} WHERE order_id = %d",
			$order_id
		) );
		if ( $exists ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// If order has sub-orders, calculate from sub-orders.
		$has_sub = $order->get_meta( '_tcg_has_sub_orders' );
		if ( $has_sub ) {
			$sub_orders = TCG_Orders::get_sub_orders( $order_id );
			foreach ( $sub_orders as $sub ) {
				$this->insert_commissions_for_order( $sub, $order_id );
			}
		} else {
			$this->insert_commissions_for_order( $order, $order_id );
		}
	}

	/**
	 * Insert commission rows for an order's items.
	 */
	private function insert_commissions_for_order( $order, $parent_order_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tcg_commissions';

		$vendor_id = (int) $order->get_meta( '_tcg_vendor_id' );
		$sub_order_id = ( $order->get_id() !== $parent_order_id ) ? $order->get_id() : null;

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			if ( ! $vendor_id ) {
				$vendor_id = (int) get_post_field( 'post_author', $product_id );
			}

			if ( ! $vendor_id ) {
				continue;
			}

			$sale_total = (float) $item->get_total();
			$rate       = self::get_commission_rate( $vendor_id );
			$commission = round( $sale_total * $rate / 100, 2 );
			$vendor_net = round( $sale_total - $commission, 2 );

			$wpdb->insert( $table, [
				'order_id'     => $parent_order_id ?: $order->get_id(),
				'sub_order_id' => $sub_order_id,
				'vendor_id'    => $vendor_id,
				'product_id'   => $product_id,
				'sale_total'   => $sale_total,
				'commission'   => $commission,
				'vendor_net'   => $vendor_net,
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql' ),
			], [ '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s' ] );
		}
	}

	/**
	 * Get commissions for a vendor.
	 */
	public static function get_vendor_commissions( $vendor_id, $args = [] ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tcg_commissions';

		$defaults = [
			'status'   => '',
			'per_page' => 20,
			'page'     => 1,
		];
		$args = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( "WHERE vendor_id = %d", $vendor_id );
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		$results = $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT {$args['per_page']} OFFSET {$offset}"
		);

		$total = (int) $wpdb->get_var( "SELECT COUNT(1) FROM {$table} {$where}" );

		return [
			'items' => $results,
			'total' => $total,
			'pages' => ceil( $total / $args['per_page'] ),
		];
	}

	/**
	 * Get vendor pending balance.
	 */
	public static function get_vendor_balance( $vendor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tcg_commissions';

		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(vendor_net), 0) FROM {$table} WHERE vendor_id = %d AND status = 'pending'",
			$vendor_id
		) );
	}

	/**
	 * Get vendor total paid.
	 */
	public static function get_vendor_total_paid( $vendor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tcg_commissions';

		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(vendor_net), 0) FROM {$table} WHERE vendor_id = %d AND status = 'paid'",
			$vendor_id
		) );
	}

	/**
	 * Get vendor sales this month.
	 */
	public static function get_vendor_monthly_sales( $vendor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tcg_commissions';

		$start = date( 'Y-m-01 00:00:00' );

		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(sale_total), 0) FROM {$table} WHERE vendor_id = %d AND created_at >= %s",
			$vendor_id,
			$start
		) );
	}

	/**
	 * Mark commissions as paid.
	 */
	public static function mark_as_paid( $commission_ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tcg_commissions';

		if ( ! is_array( $commission_ids ) ) {
			$commission_ids = [ $commission_ids ];
		}

		$ids = array_map( 'absint', $commission_ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = 'paid', paid_date = %s WHERE id IN ({$placeholders})",
			array_merge( [ current_time( 'mysql' ) ], $ids )
		) );
	}

	/**
	 * Add WooCommerce settings page.
	 */
	public function add_settings_page( $settings ) {
		$instance = tcg_manager_get_settings_class();
		if ( $instance ) {
			$settings[] = $instance;
		}
		return $settings;
	}
}

/**
 * WooCommerce settings tab — loaded lazily to avoid class-not-found errors.
 */
function tcg_manager_get_settings_class() {
	if ( ! class_exists( 'WC_Settings_Page' ) ) {
		return null;
	}

	if ( class_exists( 'TCG_Manager_Settings' ) ) {
		return new TCG_Manager_Settings();
	}

	class TCG_Manager_Settings extends WC_Settings_Page {

		public function __construct() {
			$this->id    = 'tcg-manager';
			$this->label = __( 'TCG Manager', 'tcg-manager' );
			parent::__construct();
		}

		public function get_settings() {
			return [
				[
					'title' => __( 'Comisiones', 'tcg-manager' ),
					'type'  => 'title',
					'id'    => 'tcg_commission_options',
				],
				[
					'title'    => __( 'Comisión global (%)', 'tcg-manager' ),
					'desc'     => __( 'Porcentaje de comisión por defecto para todos los vendedores.', 'tcg-manager' ),
					'id'       => 'tcg_manager_commission_rate',
					'type'     => 'number',
					'default'  => '10',
					'css'      => 'width:80px;',
					'custom_attributes' => [ 'min' => '0', 'max' => '100', 'step' => '0.1' ],
				],
				[
					'type' => 'sectionend',
					'id'   => 'tcg_commission_options',
				],
			];
		}
	}

	return new TCG_Manager_Settings();
}
