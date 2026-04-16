<?php
/**
 * Email a vendedor: nuevo pedido (HTML).
 *
 * @var WC_Order $order
 * @var WP_User  $vendor
 * @var array    $vendor_items
 * @var float    $vendor_total
 * @var string   $email_heading
 * @var string   $additional_content
 * @var WC_Email $email
 */
defined( 'ABSPATH' ) || exit;

// Valores por defecto (vista previa desde WC admin puede llamar sin datos reales).
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

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( esc_html__( 'Hola %s,', 'tcg-manager' ), esc_html( $shop_name ) ); ?></p>

<p><?php esc_html_e( 'Has recibido un nuevo pedido. Estos son los productos que debes despachar:', 'tcg-manager' ); ?></p>

<?php if ( $order ) : ?>
<h2><?php printf( esc_html__( 'Pedido #%s', 'tcg-manager' ), esc_html( $order->get_order_number() ) ); ?>
	<span style="font-weight:normal;color:#777;">(<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>)</span>
</h2>
<?php endif; ?>

<div style="margin-bottom:40px;">
<table class="td" cellspacing="0" cellpadding="6" border="1" style="color:#636363;border:1px solid #e5e5e5;vertical-align:middle;width:100%;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;">
	<thead>
		<tr>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Producto', 'tcg-manager' ); ?></th>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Cantidad', 'tcg-manager' ); ?></th>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Total', 'tcg-manager' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $vendor_items as $item ) : ?>
			<tr>
				<td class="td" style="text-align:left;vertical-align:middle;"><?php echo esc_html( $item->get_name() ); ?></td>
				<td class="td" style="text-align:left;vertical-align:middle;"><?php echo esc_html( $item->get_quantity() ); ?></td>
				<td class="td" style="text-align:left;vertical-align:middle;"><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<tr>
			<th class="td" scope="row" colspan="2" style="text-align:right;"><?php esc_html_e( 'Tu total a despachar:', 'tcg-manager' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo wp_kses_post( wc_price( $vendor_total ) ); ?></td>
		</tr>
	</tfoot>
</table>
</div>

<?php if ( $order ) : ?>
<h2><?php esc_html_e( 'Datos de entrega', 'tcg-manager' ); ?></h2>
<?php if ( $order->get_meta( '_tcg_delivery_mode' ) === 'pickup' && class_exists( 'TCG_Pickup' ) ) : ?>
	<?php echo TCG_Pickup::render_block( $order, 'email-vendor' ); ?>
<?php else : ?>
	<p>
		<strong><?php echo esc_html( trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) ); ?></strong><br>
		<?php echo esc_html( $order->get_shipping_address_1() ); ?>
		<?php if ( $order->get_shipping_address_2() ) : ?><br><?php echo esc_html( $order->get_shipping_address_2() ); ?><?php endif; ?><br>
		<?php echo esc_html( trim( $order->get_shipping_city() . ', ' . $order->get_shipping_state() . ' ' . $order->get_shipping_postcode() ) ); ?>
	</p>
<?php endif; ?>

<h2><?php esc_html_e( 'Datos del cliente', 'tcg-manager' ); ?></h2>
<p>
	<?php echo esc_html( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ); ?><br>
	<?php if ( $order->get_billing_email() ) : ?><?php echo esc_html( $order->get_billing_email() ); ?><br><?php endif; ?>
	<?php if ( $order->get_billing_phone() ) : ?><?php echo esc_html( $order->get_billing_phone() ); ?><?php endif; ?>
</p>
<?php endif; ?>

<p style="text-align:center;margin:30px 0;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" style="background:#2271b1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">
		<?php esc_html_e( 'Ver en mi panel', 'tcg-manager' ); ?>
	</a>
</p>

<?php if ( ! empty( $additional_content ) ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
