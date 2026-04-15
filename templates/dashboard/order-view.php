<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();
$order_id  = absint( $_GET['tcg-id'] ?? 0 );
$order     = $order_id ? wc_get_order( $order_id ) : null;

// Verify this order belongs to the vendor.
if ( ! $order || (int) $order->get_meta( '_tcg_vendor_id' ) !== $vendor_id ) {
	echo '<div class="tcg-alert tcg-alert-error">' . esc_html__( 'Pedido no encontrado.', 'tcg-manager' ) . '</div>';
	return;
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
	<h2 style="margin:0;"><?php printf( esc_html__( 'Pedido #%d', 'tcg-manager' ), $order->get_id() ); ?></h2>
	<a href="<?php echo esc_url( TCG_Dashboard::get_dashboard_url( 'orders' ) ); ?>" class="tcg-btn tcg-btn-secondary">
		&larr; <?php esc_html_e( 'Volver', 'tcg-manager' ); ?>
	</a>
</div>

<div class="tcg-order-details">
	<div class="tcg-dashboard-stats" style="margin-bottom:20px;">
		<div class="tcg-stat-box">
			<div class="tcg-stat-value"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></div>
			<div class="tcg-stat-label"><?php esc_html_e( 'Estado', 'tcg-manager' ); ?></div>
		</div>
		<div class="tcg-stat-box">
			<div class="tcg-stat-value"><?php echo esc_html( $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) ); ?></div>
			<div class="tcg-stat-label"><?php esc_html_e( 'Fecha', 'tcg-manager' ); ?></div>
		</div>
		<div class="tcg-stat-box">
			<div class="tcg-stat-value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></div>
			<div class="tcg-stat-label"><?php esc_html_e( 'Total', 'tcg-manager' ); ?></div>
		</div>
	</div>

	<!-- Items -->
	<h3><?php esc_html_e( 'Productos', 'tcg-manager' ); ?></h3>
	<table class="tcg-table">
		<thead><tr>
			<th><?php esc_html_e( 'Producto', 'tcg-manager' ); ?></th>
			<th><?php esc_html_e( 'Cantidad', 'tcg-manager' ); ?></th>
			<th><?php esc_html_e( 'Total', 'tcg-manager' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $order->get_items() as $item ) : ?>
			<tr>
				<td><?php echo esc_html( $item->get_name() ); ?></td>
				<td><?php echo esc_html( $item->get_quantity() ); ?></td>
				<td><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Tracking -->
	<h3><?php esc_html_e( 'Seguimiento', 'tcg-manager' ); ?></h3>
	<?php $tracking = $order->get_meta( '_tcg_tracking' ); ?>
	<div class="tcg-tracking-wrap">
		<div class="tcg-shipping-fields">
			<input type="text" id="tcg-tracking-input" class="tcg-form-control" style="flex:1;"
				   value="<?php echo esc_attr( $tracking ); ?>"
				   placeholder="<?php esc_attr_e( 'Código o link de rastreo', 'tcg-manager' ); ?>">
			<button type="button" id="tcg-tracking-save" class="tcg-btn tcg-btn-primary"
					data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'tcg_save_tracking' ) ); ?>">
				<?php esc_html_e( 'Guardar', 'tcg-manager' ); ?>
			</button>
		</div>
		<?php if ( $tracking && filter_var( $tracking, FILTER_VALIDATE_URL ) ) : ?>
			<p style="margin-top:6px;"><a href="<?php echo esc_url( $tracking ); ?>" target="_blank"><?php esc_html_e( 'Ver seguimiento', 'tcg-manager' ); ?> &rarr;</a></p>
		<?php endif; ?>
	</div>

	<!-- Shipping info -->
	<h3><?php esc_html_e( 'Datos de envío', 'tcg-manager' ); ?></h3>
	<?php if ( $order->get_meta( '_tcg_delivery_mode' ) === 'pickup' ) : ?>
		<?php echo TCG_Pickup::render_block( $order, 'vendor' ); ?>
	<?php else : ?>
	<div class="tcg-order-address">
		<p>
			<strong><?php echo esc_html( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ); ?></strong><br>
			<?php echo esc_html( $order->get_shipping_address_1() ); ?>
			<?php if ( $order->get_shipping_address_2() ) : ?><br><?php echo esc_html( $order->get_shipping_address_2() ); ?><?php endif; ?><br>
			<?php echo esc_html( $order->get_shipping_city() . ', ' . $order->get_shipping_state() . ' ' . $order->get_shipping_postcode() ); ?><br>
			<?php echo esc_html( WC()->countries->countries[ $order->get_shipping_country() ] ?? $order->get_shipping_country() ); ?>
		</p>
	</div>
	<?php endif; ?>
</div>
