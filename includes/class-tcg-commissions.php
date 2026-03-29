<?php
defined( 'ABSPATH' ) || exit;

class TCG_Commissions {

	public function __construct() {
		add_action( 'woocommerce_order_status_completed', [ $this, 'calculate_commission' ] );
		add_action( 'woocommerce_order_status_processing', [ $this, 'calculate_commission' ] );
		add_filter( 'woocommerce_get_settings_pages', [ $this, 'add_settings_page' ] );
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_admin_fee' ] );
	}

	/**
	 * Get commission config for a vendor: [ 'rate' => float, 'fixed' => float ].
	 */
	public static function get_commission_config( $vendor_id ) {
		$rate  = get_user_meta( $vendor_id, '_tcg_commission_rate', true );
		$fixed = get_user_meta( $vendor_id, '_tcg_commission_fixed', true );

		return [
			'rate'  => ( $rate !== '' && $rate !== false ) ? (float) $rate : (float) get_option( 'tcg_manager_commission_rate', 10 ),
			'fixed' => ( $fixed !== '' && $fixed !== false ) ? (float) $fixed : (float) get_option( 'tcg_manager_commission_fixed', 0 ),
		];
	}

	/**
	 * Get commission rate for a vendor (percentage only — kept for backward compat).
	 */
	public static function get_commission_rate( $vendor_id ) {
		$config = self::get_commission_config( $vendor_id );
		return $config['rate'];
	}

	/**
	 * Calculate commission amount for a sale total.
	 */
	public static function calculate_commission_amount( $sale_total, $vendor_id, $quantity = 1 ) {
		$config     = self::get_commission_config( $vendor_id );
		$percentage = round( $sale_total * $config['rate'] / 100, 2 );
		$fixed      = round( $config['fixed'] * $quantity, 2 );
		return $percentage + $fixed;
	}

	/**
	 * Format commission config for display (e.g. "10% + S/ 1.00").
	 */
	public static function format_commission( $vendor_id ) {
		$config = self::get_commission_config( $vendor_id );
		$parts  = [];
		if ( $config['rate'] > 0 ) {
			$parts[] = $config['rate'] . '%';
		}
		if ( $config['fixed'] > 0 ) {
			$parts[] = wc_price( $config['fixed'] );
		}
		return $parts ? implode( ' + ', $parts ) : '0%';
	}

	/**
	 * Add admin fee at checkout based on commission config.
	 */
	public function add_admin_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Collect unique vendors in the cart.
		$vendors = [];
		foreach ( $cart->get_cart() as $item ) {
			$vendor_id = (int) get_post_field( 'post_author', $item['product_id'] );
			if ( $vendor_id ) {
				$vendors[ $vendor_id ] = true;
			}
		}

		$vendor_count = count( $vendors );
		if ( $vendor_count > 0 ) {
			$fee_per_vendor = (float) get_option( 'tcg_manager_commission_fixed', 1 );
			$total_fee      = $fee_per_vendor * $vendor_count;
			$cart->add_fee( __( 'Fee de administración', 'tcg-manager' ), $total_fee, false );
		}
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
			$quantity   = (int) $item->get_quantity();
			$commission = self::calculate_commission_amount( $sale_total, $vendor_id, $quantity );
			// Fee is paid by the customer at checkout, so vendor keeps the full sale total.
			$vendor_net = $sale_total;

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
			$currency = get_woocommerce_currency_symbol();
			return [
				[
					'title' => __( 'Comisiones', 'tcg-manager' ),
					'type'  => 'title',
					'desc'  => __( 'La comisión se calcula por item: (venta × porcentaje / 100) + (fija × cantidad). Se pueden usar ambas o solo una.', 'tcg-manager' ),
					'id'    => 'tcg_commission_options',
				],
				[
					'title'    => __( 'Comisión porcentual (%)', 'tcg-manager' ),
					'desc'     => __( 'Porcentaje de comisión por defecto.', 'tcg-manager' ),
					'id'       => 'tcg_manager_commission_rate',
					'type'     => 'number',
					'default'  => '10',
					'css'      => 'width:80px;',
					'custom_attributes' => [ 'min' => '0', 'max' => '100', 'step' => '0.1' ],
				],
				[
					/* translators: %s = currency symbol */
					'title'    => sprintf( __( 'Comisión fija (%s)', 'tcg-manager' ), $currency ),
					'desc'     => __( 'Monto fijo de comisión por unidad vendida. Se suma al porcentaje.', 'tcg-manager' ),
					'id'       => 'tcg_manager_commission_fixed',
					'type'     => 'number',
					'default'  => '0',
					'css'      => 'width:80px;',
					'custom_attributes' => [ 'min' => '0', 'step' => '0.01' ],
				],
				[
					'type' => 'sectionend',
					'id'   => 'tcg_commission_options',
				],
				[
					'title' => __( 'Páginas', 'tcg-manager' ),
					'type'  => 'title',
					'desc'  => __( 'Configura qué páginas usa el plugin.', 'tcg-manager' ),
					'id'    => 'tcg_pages_options',
				],
				[
					'title'    => __( 'Productos por página (tienda vendedor)', 'tcg-manager' ),
					'desc'     => __( 'Cantidad de productos a mostrar por página en la tienda del vendedor.', 'tcg-manager' ),
					'id'       => 'tcg_vendor_products_per_page',
					'type'     => 'number',
					'default'  => '25',
					'css'      => 'width:80px;',
					'custom_attributes' => [ 'min' => '1', 'max' => '100', 'step' => '1' ],
				],
				[
					'title'    => __( 'Página de tienda de vendedor', 'tcg-manager' ),
					'desc'     => __( 'Página con la plantilla Bricks para la tienda del vendedor. Usa los shortcodes [tcg_vendor_name], [tcg_vendor_products_grid], etc.', 'tcg-manager' ),
					'id'       => 'tcg_store_page_id',
					'type'     => 'single_select_page',
					'default'  => '',
					'css'      => 'min-width:300px;',
					'class'    => 'wc-enhanced-select',
					'desc_tip' => true,
				],
				[
					'type' => 'sectionend',
					'id'   => 'tcg_pages_options',
				],
			];
		}
	}

	return new TCG_Manager_Settings();
}
