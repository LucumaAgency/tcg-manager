<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();
$paged     = max( 1, absint( get_query_var( 'paged', $_GET['paged'] ?? 1 ) ) );
$status    = sanitize_text_field( $_GET['status'] ?? '' );

$data = TCG_Commissions::get_vendor_commissions( $vendor_id, [
	'status'   => $status,
	'per_page' => 20,
	'page'     => $paged,
] );

$items = $data['items'];
$pages = $data['pages'];

$balance    = TCG_Commissions::get_vendor_balance( $vendor_id );
$total_paid = TCG_Commissions::get_vendor_total_paid( $vendor_id );
$monthly    = TCG_Commissions::get_vendor_monthly_sales( $vendor_id );
?>

<h2><?php esc_html_e( 'Mis Ganancias', 'tcg-manager' ); ?></h2>

<div class="tcg-dashboard-stats" style="margin-bottom:20px;">
	<div class="tcg-stat-box">
		<div class="tcg-stat-value"><?php echo wp_kses_post( wc_price( $balance ) ); ?></div>
		<div class="tcg-stat-label"><?php esc_html_e( 'Balance pendiente', 'tcg-manager' ); ?></div>
	</div>
	<div class="tcg-stat-box">
		<div class="tcg-stat-value"><?php echo wp_kses_post( wc_price( $total_paid ) ); ?></div>
		<div class="tcg-stat-label"><?php esc_html_e( 'Total pagado', 'tcg-manager' ); ?></div>
	</div>
	<div class="tcg-stat-box">
		<div class="tcg-stat-value"><?php echo wp_kses_post( wc_price( $monthly ) ); ?></div>
		<div class="tcg-stat-label"><?php esc_html_e( 'Ventas del mes', 'tcg-manager' ); ?></div>
	</div>
</div>

<!-- Filters -->
<form method="get" class="tcg-filters" style="margin-bottom:15px;">
	<select name="status">
		<option value=""><?php esc_html_e( 'Todos', 'tcg-manager' ); ?></option>
		<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pendiente', 'tcg-manager' ); ?></option>
		<option value="paid" <?php selected( $status, 'paid' ); ?>><?php esc_html_e( 'Pagado', 'tcg-manager' ); ?></option>
	</select>
	<button type="submit" class="tcg-btn tcg-btn-secondary" style="font-size:13px;padding:4px 10px;">
		<?php esc_html_e( 'Filtrar', 'tcg-manager' ); ?>
	</button>
</form>

<?php if ( empty( $items ) ) : ?>
	<p><?php esc_html_e( 'No tienes ganancias registradas aún.', 'tcg-manager' ); ?></p>
<?php else : ?>
<div class="tcg-table-responsive">
<table class="tcg-table tcg-table-earnings">
	<thead><tr>
		<th><?php esc_html_e( 'Pedido', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Producto', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Venta', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Comisión', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Neto', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Estado', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Fecha', 'tcg-manager' ); ?></th>
	</tr></thead>
	<tbody>
	<?php foreach ( $items as $item ) : ?>
		<tr>
			<td data-label="<?php esc_attr_e( 'Pedido', 'tcg-manager' ); ?>">#<?php echo esc_html( $item->order_id ); ?></td>
			<td data-label="<?php esc_attr_e( 'Producto', 'tcg-manager' ); ?>"><?php echo esc_html( get_the_title( $item->product_id ) ); ?></td>
			<td data-label="<?php esc_attr_e( 'Venta', 'tcg-manager' ); ?>"><?php echo wp_kses_post( wc_price( $item->sale_total ) ); ?></td>
			<td data-label="<?php esc_attr_e( 'Comisión', 'tcg-manager' ); ?>"><?php echo wp_kses_post( wc_price( $item->commission ) ); ?></td>
			<td data-label="<?php esc_attr_e( 'Neto', 'tcg-manager' ); ?>"><?php echo wp_kses_post( wc_price( $item->vendor_net ) ); ?></td>
			<td data-label="<?php esc_attr_e( 'Estado', 'tcg-manager' ); ?>">
				<?php if ( $item->status === 'pending' ) : ?>
					<span class="tcg-badge tcg-badge-pending"><?php esc_html_e( 'Pendiente', 'tcg-manager' ); ?></span>
				<?php else : ?>
					<span class="tcg-badge tcg-badge-paid"><?php esc_html_e( 'Pagado', 'tcg-manager' ); ?></span>
				<?php endif; ?>
			</td>
			<td data-label="<?php esc_attr_e( 'Fecha', 'tcg-manager' ); ?>"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $item->created_at ) ) ); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>

<?php if ( $pages > 1 ) : ?>
	<div class="tcg-pagination">
		<?php echo paginate_links( [
			'base'    => trailingslashit( TCG_Dashboard::get_dashboard_url( 'earnings' ) ) . 'page/%#%/',
			'format'  => '',
			'current' => $paged,
			'total'   => $pages,
		] ); ?>
	</div>
<?php endif; endif; ?>
