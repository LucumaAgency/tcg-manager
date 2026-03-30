<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();
$products  = count_user_posts( $vendor_id, 'product', true );
$balance   = TCG_Commissions::get_vendor_balance( $vendor_id );
$monthly   = TCG_Commissions::get_vendor_monthly_sales( $vendor_id );

// Orders this month.
$orders_result = TCG_Orders::get_vendor_orders( $vendor_id, [
	'limit' => 5,
	'status' => [ 'processing', 'completed', 'on-hold' ],
] );
$recent_orders = $orders_result->orders ?? [];
$orders_count  = $orders_result->total ?? 0;
?>

<h2><?php printf( esc_html__( 'Bienvenido, %s', 'tcg-manager' ), esc_html( TCG_Vendor_Profile::get_shop_name( $vendor_id ) ) ); ?></h2>

<div class="tcg-dashboard-stats">
	<div class="tcg-stat-box">
		<div class="tcg-stat-value"><?php echo esc_html( $products ); ?></div>
		<div class="tcg-stat-label"><?php esc_html_e( 'Productos activos', 'tcg-manager' ); ?></div>
	</div>
	<div class="tcg-stat-box">
		<div class="tcg-stat-value"><?php echo esc_html( $orders_count ); ?></div>
		<div class="tcg-stat-label"><?php esc_html_e( 'Pedidos', 'tcg-manager' ); ?></div>
	</div>
	<div class="tcg-stat-box">
		<div class="tcg-stat-value"><?php echo wp_kses_post( wc_price( $monthly ) ); ?></div>
		<div class="tcg-stat-label"><?php esc_html_e( 'Ventas del mes', 'tcg-manager' ); ?></div>
	</div>
	<div class="tcg-stat-box">
		<div class="tcg-stat-value"><?php echo wp_kses_post( wc_price( $balance ) ); ?></div>
		<div class="tcg-stat-label"><?php esc_html_e( 'Balance pendiente', 'tcg-manager' ); ?></div>
	</div>
</div>

<!-- Info panel -->
<div class="tcg-info-panel">
	<button type="button" class="tcg-info-toggle" id="tcg-info-toggle">
		<?php esc_html_e( 'Como funciona la plataforma', 'tcg-manager' ); ?>
		<span class="tcg-info-arrow">&#9660;</span>
	</button>
	<div class="tcg-info-content" id="tcg-info-content" style="display:none;">
		<div class="tcg-info-grid">
			<div class="tcg-info-card">
				<strong><?php esc_html_e( 'Tus ganancias', 'tcg-manager' ); ?></strong>
				<p><?php esc_html_e( 'Te quedas con el 100% del precio de venta. El fee de administracion lo paga el comprador al momento del checkout, no se descuenta de tus ganancias.', 'tcg-manager' ); ?></p>
			</div>
			<div class="tcg-info-card">
				<strong><?php esc_html_e( 'Como vender', 'tcg-manager' ); ?></strong>
				<p><?php esc_html_e( 'Ve a "Agregar por Set" para listar cartas en lote, o a "Nuevo Producto" para agregar una carta individual. Asigna rareza, condicion, precio y stock para publicar.', 'tcg-manager' ); ?></p>
			</div>
			<div class="tcg-info-card">
				<strong><?php esc_html_e( 'Envio', 'tcg-manager' ); ?></strong>
				<p><?php esc_html_e( 'Configura tus tarifas en la seccion "Envio". El comprador ve tu costo de envio por separado en el checkout. Puedes poner tarifas distintas para Lima y Provincia.', 'tcg-manager' ); ?></p>
			</div>
			<div class="tcg-info-card">
				<strong><?php esc_html_e( 'Pagos', 'tcg-manager' ); ?></strong>
				<p><?php esc_html_e( 'Configura al menos un metodo de pago en tu Perfil (Yape, Plin o cuenta bancaria). Cuando se complete un pedido, veras tu balance pendiente. El admin te pagara al metodo que configuraste.', 'tcg-manager' ); ?></p>
			</div>
		</div>
	</div>
</div>
<script>
document.getElementById('tcg-info-toggle').addEventListener('click', function() {
	var content = document.getElementById('tcg-info-content');
	var arrow = this.querySelector('.tcg-info-arrow');
	if (content.style.display === 'none') {
		content.style.display = 'block';
		arrow.innerHTML = '&#9650;';
	} else {
		content.style.display = 'none';
		arrow.innerHTML = '&#9660;';
	}
});
</script>

<?php if ( ! empty( $recent_orders ) ) : ?>
<h3><?php esc_html_e( 'Pedidos recientes', 'tcg-manager' ); ?></h3>
<div class="tcg-table-responsive">
<table class="tcg-table">
	<thead><tr>
		<th><?php esc_html_e( 'Pedido', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Fecha', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Total', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Estado', 'tcg-manager' ); ?></th>
	</tr></thead>
	<tbody>
	<?php foreach ( $recent_orders as $order ) : ?>
		<tr>
			<td data-label="<?php esc_attr_e( 'Pedido', 'tcg-manager' ); ?>">#<?php echo esc_html( $order->get_id() ); ?></td>
			<td data-label="<?php esc_attr_e( 'Fecha', 'tcg-manager' ); ?>"><?php echo esc_html( $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) ); ?></td>
			<td data-label="<?php esc_attr_e( 'Total', 'tcg-manager' ); ?>"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
			<td data-label="<?php esc_attr_e( 'Estado', 'tcg-manager' ); ?>"><span class="tcg-badge tcg-badge-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>
