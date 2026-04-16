<?php
/**
 * Email a vendedor: nuevo pedido (texto plano).
 *
 * @var WC_Order $order
 * @var WP_User  $vendor
 * @var array    $vendor_items
 * @var float    $vendor_total
 * @var string   $email_heading
 * @var string   $additional_content
 */
defined( 'ABSPATH' ) || exit;

$vendor       = $vendor       ?? null;
$order        = $order        ?? null;
$vendor_items = $vendor_items ?? [];
$vendor_total = $vendor_total ?? 0;

if ( $vendor && ! empty( $vendor->ID ) ) {
	$shop_name = class_exists( 'TCG_Vendor_Profile' )
		? TCG_Vendor_Profile::get_shop_name( $vendor->ID )
		: $vendor->display_name;
} else {
	$shop_name = __( 'Vendedor', 'tcg-manager' );
}

$dashboard_url = class_exists( 'TCG_Dashboard' )
	? TCG_Dashboard::get_dashboard_url( 'orders' )
	: home_url();

echo wp_strip_all_tags( $email_heading ) . "\n\n";

printf( __( 'Hola %s,', 'tcg-manager' ), $shop_name );
echo "\n\n";

if ( $order ) {
	printf( __( 'Nuevo pedido #%s (%s)', 'tcg-manager' ),
		$order->get_order_number(),
		wc_format_datetime( $order->get_date_created() )
	);
	echo "\n";
	echo str_repeat( '-', 40 ) . "\n";
}

foreach ( $vendor_items as $item ) {
	echo '- ' . $item->get_name()
		. ' x' . $item->get_quantity()
		. ' — ' . html_entity_decode( wp_strip_all_tags( wc_price( $item->get_total() ) ) )
		. "\n";
}
echo "\n";
echo __( 'Tu total a despachar: ', 'tcg-manager' ) . html_entity_decode( wp_strip_all_tags( wc_price( $vendor_total ) ) ) . "\n\n";

if ( $order ) {

echo str_repeat( '=', 40 ) . "\n";
echo __( 'DATOS DE ENTREGA', 'tcg-manager' ) . "\n";
echo str_repeat( '=', 40 ) . "\n";

if ( $order->get_meta( '_tcg_delivery_mode' ) === 'pickup' ) {
	$store = $order->get_meta( '_tcg_pickup_store_snapshot' );
	if ( $store ) {
		echo __( 'Recojo en tienda: ', 'tcg-manager' ) . ( $store['name'] ?? '' ) . "\n";
		if ( ! empty( $store['address'] ) ) {
			echo $store['address'];
			if ( ! empty( $store['district'] ) ) echo ', ' . $store['district'];
			echo "\n";
		}
		if ( ! empty( $store['hours'] ) ) echo $store['hours'] . "\n";
		if ( ! empty( $store['phone'] ) ) echo 'Tel: ' . $store['phone'] . "\n";
	}
} else {
	echo trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) . "\n";
	echo $order->get_shipping_address_1() . "\n";
	if ( $order->get_shipping_address_2() ) echo $order->get_shipping_address_2() . "\n";
	echo trim( $order->get_shipping_city() . ', ' . $order->get_shipping_state() . ' ' . $order->get_shipping_postcode() ) . "\n";
}
echo "\n";

echo str_repeat( '=', 40 ) . "\n";
echo __( 'CLIENTE', 'tcg-manager' ) . "\n";
echo str_repeat( '=', 40 ) . "\n";
echo trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . "\n";
if ( $order->get_billing_email() ) echo $order->get_billing_email() . "\n";
if ( $order->get_billing_phone() ) echo $order->get_billing_phone() . "\n";
echo "\n";

} // end if ( $order )

echo __( 'Ver pedido:', 'tcg-manager' ) . ' ' . $dashboard_url . "\n\n";

if ( ! empty( $additional_content ) ) {
	echo wp_strip_all_tags( wptexturize( $additional_content ) ) . "\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
