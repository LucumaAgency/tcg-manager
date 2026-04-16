<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) return;
if ( class_exists( 'TCG_Email_Vendor_New_Order' ) ) return;

/**
 * Notifica al vendedor cuando recibe un pedido. Se dispara una vez por vendedor
 * por pedido — si el pedido contiene productos de varios vendedores, cada uno
 * recibe su propio email con solo sus items.
 */
class TCG_Email_Vendor_New_Order extends WC_Email {

	/** @var WP_User|null */
	public $vendor = null;
	/** @var WC_Order_Item_Product[] */
	public $vendor_items = [];
	/** @var float */
	public $vendor_total = 0;

	public function __construct() {
		$this->id             = 'tcg_vendor_new_order';
		$this->title          = __( 'Vendedor: nuevo pedido', 'tcg-manager' );
		$this->description    = __( 'Se envía al vendedor cuando uno de sus productos entra en un pedido procesable.', 'tcg-manager' );
		$this->customer_email = false;
		$this->template_base  = TCG_MANAGER_PATH . 'templates/';
		$this->template_html  = 'emails/vendor-new-order.php';
		$this->template_plain = 'emails/plain/vendor-new-order.php';
		$this->placeholders   = [
			'{site_title}'   => $this->get_blogname(),
			'{order_number}' => '',
			'{order_date}'   => '',
			'{vendor_name}'  => '',
		];

		// Triggers: transiciones típicas a processing / on-hold.
		add_action( 'woocommerce_order_status_pending_to_processing_notification',  [ $this, 'trigger' ], 10, 2 );
		add_action( 'woocommerce_order_status_failed_to_processing_notification',   [ $this, 'trigger' ], 10, 2 );
		add_action( 'woocommerce_order_status_on-hold_to_processing_notification',  [ $this, 'trigger' ], 10, 2 );
		add_action( 'woocommerce_order_status_pending_to_on-hold_notification',     [ $this, 'trigger' ], 10, 2 );

		parent::__construct();
	}

	public function get_default_subject() {
		return __( '[{site_title}] Nuevo pedido #{order_number} para {vendor_name}', 'tcg-manager' );
	}

	public function get_default_heading() {
		return __( 'Tienes un nuevo pedido', 'tcg-manager' );
	}

	public function get_default_additional_content() {
		return __( 'Ingresa a tu panel para ver los detalles y actualizar el envío.', 'tcg-manager' );
	}

	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();

		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			$this->restore_locale();
			return;
		}

		// Agrupar items por vendedor (post_author del producto).
		$items_by_vendor = [];
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$vendor_id  = (int) get_post_field( 'post_author', $product_id );
			if ( ! $vendor_id ) continue;
			$items_by_vendor[ $vendor_id ][] = $item;
		}

		// Evitar notificar dos veces al mismo vendedor por el mismo pedido.
		$notified = (array) $order->get_meta( '_tcg_vendor_notified' );

		foreach ( $items_by_vendor as $vendor_id => $items ) {
			if ( in_array( (int) $vendor_id, array_map( 'intval', $notified ), true ) ) continue;

			$vendor = get_user_by( 'id', $vendor_id );
			if ( ! $vendor || empty( $vendor->user_email ) ) continue;

			$this->object       = $order;
			$this->vendor       = $vendor;
			$this->vendor_items = $items;
			$this->vendor_total = array_sum( array_map( function( $i ) { return (float) $i->get_total(); }, $items ) );
			$this->recipient    = $vendor->user_email;

			$shop_name = class_exists( 'TCG_Vendor_Profile' )
				? TCG_Vendor_Profile::get_shop_name( $vendor_id )
				: $vendor->display_name;

			$this->placeholders['{order_number}'] = $order->get_order_number();
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{vendor_name}']  = $shop_name;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
				$notified[] = (int) $vendor_id;
			}
		}

		if ( ! empty( $notified ) ) {
			$order->update_meta_data( '_tcg_vendor_notified', array_values( array_unique( array_map( 'intval', $notified ) ) ) );
			$order->save();
		}

		$this->restore_locale();
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->get_template_args( false ), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->get_template_args( true ), '', $this->template_base );
	}

	private function get_template_args( $plain_text ) {
		return [
			'order'              => $this->object,
			'vendor'             => $this->vendor,
			'vendor_items'       => $this->vendor_items,
			'vendor_total'       => $this->vendor_total,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain_text,
			'email'              => $this,
		];
	}
}
