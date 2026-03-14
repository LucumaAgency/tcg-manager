<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();
$paged     = max( 1, absint( $_GET['paged'] ?? 1 ) );

$query = new WP_Query( [
	'post_type'      => 'product',
	'post_status'    => [ 'publish', 'draft', 'pending' ],
	'author'         => $vendor_id,
	'posts_per_page' => 20,
	'paged'          => $paged,
	'meta_query'     => [ [ 'key' => '_linked_ygo_card', 'compare' => 'EXISTS' ] ],
] );
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
	<h2 style="margin:0;"><?php esc_html_e( 'Mis Productos', 'tcg-manager' ); ?></h2>
	<a href="<?php echo esc_url( TCG_Dashboard::get_dashboard_url( 'new-product' ) ); ?>" class="tcg-btn tcg-btn-primary">
		+ <?php esc_html_e( 'Nuevo Producto', 'tcg-manager' ); ?>
	</a>
</div>

<?php if ( ! $query->have_posts() ) : ?>
	<p><?php esc_html_e( 'No tienes productos aún.', 'tcg-manager' ); ?></p>
<?php else : ?>
<table class="tcg-table">
	<thead><tr>
		<th><?php esc_html_e( 'Imagen', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Nombre', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Precio', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Stock', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Estado', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Acciones', 'tcg-manager' ); ?></th>
	</tr></thead>
	<tbody>
	<?php while ( $query->have_posts() ) : $query->the_post();
		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) continue;
		$thumb = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' );
		?>
		<tr>
			<td><?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:40px;height:56px;object-fit:cover;border-radius:4px;"><?php endif; ?></td>
			<td><?php echo esc_html( get_the_title() ); ?></td>
			<td><?php echo wp_kses_post( $product->get_price_html() ); ?></td>
			<td><?php echo esc_html( $product->get_stock_quantity() ?? '—' ); ?></td>
			<td><span class="tcg-badge tcg-badge-<?php echo esc_attr( get_post_status() ); ?>"><?php echo esc_html( ucfirst( get_post_status() ) ); ?></span></td>
			<td>
				<a href="<?php echo esc_url( TCG_Dashboard::get_dashboard_url( 'edit-product', [ 'tcg-id' => get_the_ID() ] ) ); ?>" class="tcg-btn tcg-btn-secondary" style="font-size:13px;padding:4px 10px;">
					<?php esc_html_e( 'Editar', 'tcg-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'tcg_action' => 'delete_product', 'product_id' => get_the_ID() ] ), 'tcg_delete_' . get_the_ID() ) ); ?>"
				   class="tcg-btn tcg-btn-danger tcg-delete-product" style="font-size:13px;padding:4px 10px;">
					<?php esc_html_e( 'Eliminar', 'tcg-manager' ); ?>
				</a>
			</td>
		</tr>
	<?php endwhile; wp_reset_postdata(); ?>
	</tbody>
</table>

<?php if ( $query->max_num_pages > 1 ) : ?>
	<div class="tcg-pagination">
		<?php echo paginate_links( [
			'base'    => add_query_arg( 'paged', '%#%' ),
			'format'  => '',
			'current' => $paged,
			'total'   => $query->max_num_pages,
		] ); ?>
	</div>
<?php endif; endif; ?>
