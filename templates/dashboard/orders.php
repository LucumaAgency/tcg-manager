<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();
$paged     = max( 1, absint( get_query_var( 'paged', $_GET['paged'] ?? 1 ) ) );
$status    = sanitize_text_field( $_GET['status'] ?? '' );

$args = [
	'limit'  => 20,
	'page'   => $paged,
	'status' => $status ? [ $status ] : [ 'processing', 'completed', 'on-hold', 'pending' ],
];

$result = TCG_Orders::get_vendor_orders( $vendor_id, $args );
$orders = $result->orders ?? [];
$total  = $result->total ?? 0;
$pages  = ceil( $total / 20 );
?>

<h2><?php esc_html_e( 'Mis Pedidos', 'tcg-manager' ); ?></h2>

<!-- Filters -->
<form method="get" class="tcg-filters" style="margin-bottom:15px;">
	<?php
	// Preserve existing query params.
	$dashboard_url = parse_url( TCG_Dashboard::get_dashboard_url( 'orders' ) );
	if ( ! empty( $dashboard_url['query'] ) ) {
		parse_str( $dashboard_url['query'], $params );
		foreach ( $params as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
	}
	?>
	<select name="status">
		<option value=""><?php esc_html_e( 'Todos los estados', 'tcg-manager' ); ?></option>
		<option value="processing" <?php selected( $status, 'processing' ); ?>><?php esc_html_e( 'Procesando', 'tcg-manager' ); ?></option>
		<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completado', 'tcg-manager' ); ?></option>
		<option value="on-hold" <?php selected( $status, 'on-hold' ); ?>><?php esc_html_e( 'En espera', 'tcg-manager' ); ?></option>
	</select>
	<button type="submit" class="tcg-btn tcg-btn-secondary" style="font-size:13px;padding:4px 10px;">
		<?php esc_html_e( 'Filtrar', 'tcg-manager' ); ?>
	</button>
</form>

<?php if ( empty( $orders ) ) : ?>
	<p><?php esc_html_e( 'No tienes pedidos aún.', 'tcg-manager' ); ?></p>
<?php else : ?>
<div class="tcg-table-responsive">
<table class="tcg-table tcg-table-orders">
	<thead><tr>
		<th><?php esc_html_e( 'Pedido', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Fecha', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Cliente', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Total', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Estado', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Acciones', 'tcg-manager' ); ?></th>
	</tr></thead>
	<tbody>
	<?php foreach ( $orders as $order ) : ?>
		<tr>
			<td data-label="<?php esc_attr_e( 'Pedido', 'tcg-manager' ); ?>">#<?php echo esc_html( $order->get_id() ); ?></td>
			<td data-label="<?php esc_attr_e( 'Fecha', 'tcg-manager' ); ?>"><?php echo esc_html( $order->get_date_created()->date_i18n( 'd/m/Y H:i' ) ); ?></td>
			<td data-label="<?php esc_attr_e( 'Cliente', 'tcg-manager' ); ?>"><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></td>
			<td data-label="<?php esc_attr_e( 'Total', 'tcg-manager' ); ?>"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
			<td data-label="<?php esc_attr_e( 'Estado', 'tcg-manager' ); ?>"><span class="tcg-badge tcg-badge-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span></td>
			<td data-label="<?php esc_attr_e( 'Acciones', 'tcg-manager' ); ?>">
				<a href="<?php echo esc_url( TCG_Dashboard::get_dashboard_url( 'order-view', [ 'tcg-id' => $order->get_id() ] ) ); ?>" class="tcg-btn tcg-btn-secondary tcg-btn-sm">
					<?php esc_html_e( 'Ver', 'tcg-manager' ); ?>
				</a>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>

<?php if ( $pages > 1 ) : ?>
	<div class="tcg-pagination">
		<?php echo paginate_links( [
			'base'    => trailingslashit( TCG_Dashboard::get_dashboard_url( 'orders' ) ) . 'page/%#%/',
			'format'  => '',
			'current' => $paged,
			'total'   => $pages,
		] ); ?>
	</div>
<?php endif; endif; ?>
