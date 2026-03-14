<?php
defined( 'ABSPATH' ) || exit;

class TCG_Orders {

	public function __construct() {
		add_action( 'woocommerce_checkout_order_created', [ $this, 'split_order' ] );
	}

	/**
	 * Split order into sub-orders by vendor.
	 */
	public function split_order( $order ) {
		$items_by_vendor = [];

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$vendor_id  = (int) get_post_field( 'post_author', $product_id );

			if ( ! $vendor_id ) {
				continue;
			}

			$items_by_vendor[ $vendor_id ][] = $item;
		}

		// Single vendor — just tag the order.
		if ( count( $items_by_vendor ) <= 1 ) {
			$vendor_id = array_key_first( $items_by_vendor );
			if ( $vendor_id ) {
				$order->update_meta_data( '_tcg_vendor_id', $vendor_id );
				$order->save();
			}
			return;
		}

		// Multiple vendors — create sub-orders.
		$order->update_meta_data( '_tcg_has_sub_orders', 1 );
		$order->save();

		foreach ( $items_by_vendor as $vendor_id => $items ) {
			$this->create_sub_order( $order, $vendor_id, $items );
		}
	}

	/**
	 * Create a sub-order for a specific vendor.
	 */
	private function create_sub_order( $parent_order, $vendor_id, $items ) {
		$sub_order = wc_create_order( [
			'customer_id' => $parent_order->get_customer_id(),
			'parent'      => $parent_order->get_id(),
			'status'      => $parent_order->get_status(),
		] );

		if ( is_wp_error( $sub_order ) ) {
			return;
		}

		// Copy items.
		foreach ( $items as $item ) {
			$new_item = new WC_Order_Item_Product();
			$new_item->set_props( [
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity'     => $item->get_quantity(),
				'subtotal'     => $item->get_subtotal(),
				'total'        => $item->get_total(),
				'name'         => $item->get_name(),
			] );
			$sub_order->add_item( $new_item );
		}

		// Copy addresses.
		$sub_order->set_address( $parent_order->get_address( 'billing' ), 'billing' );
		$sub_order->set_address( $parent_order->get_address( 'shipping' ), 'shipping' );

		// Calculate totals.
		$sub_order->calculate_totals();

		// Store vendor reference.
		$sub_order->update_meta_data( '_tcg_vendor_id', $vendor_id );
		$sub_order->update_meta_data( '_tcg_parent_order', $parent_order->get_id() );
		$sub_order->save();
	}

	/**
	 * Get orders for a vendor.
	 */
	public static function get_vendor_orders( $vendor_id, $args = [] ) {
		$defaults = [
			'limit'    => 20,
			'page'     => 1,
			'status'   => [ 'processing', 'completed', 'on-hold' ],
			'orderby'  => 'date',
			'order'    => 'DESC',
		];
		$args = wp_parse_args( $args, $defaults );

		return wc_get_orders( [
			'limit'      => $args['limit'],
			'page'       => $args['page'],
			'status'     => $args['status'],
			'orderby'    => $args['orderby'],
			'order'      => $args['order'],
			'meta_key'   => '_tcg_vendor_id',
			'meta_value' => $vendor_id,
			'paginate'   => true,
		] );
	}

	/**
	 * Get sub-orders of a parent order.
	 */
	public static function get_sub_orders( $parent_order_id ) {
		return wc_get_orders( [
			'parent' => $parent_order_id,
			'limit'  => -1,
		] );
	}
}
