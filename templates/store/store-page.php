<?php
defined( 'ABSPATH' ) || exit;

$vendor = get_query_var( 'tcg_vendor_user' );
if ( ! $vendor ) {
	return;
}

$vendor_id = $vendor->ID;
$shop_name = TCG_Vendor_Profile::get_shop_name( $vendor_id );
$shop_desc = get_user_meta( $vendor_id, '_tcg_shop_description', true );

// Get total sales count.
global $wpdb;
$total_sales = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COALESCE(SUM(pm.meta_value), 0)
	 FROM {$wpdb->posts} p
	 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'total_sales'
	 WHERE p.post_type = 'product' AND p.post_status = 'publish' AND p.post_author = %d",
	$vendor_id
) );

// Get vendor products.
$paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
$query = new WP_Query( [
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'author'         => $vendor_id,
	'posts_per_page' => 24,
	'paged'          => $paged,
	'meta_query'     => [ [ 'key' => '_linked_ygo_card', 'compare' => 'EXISTS' ] ],
] );

get_header();
?>

<div class="tcg-store-page">
	<div class="tcg-store-header">
		<div class="tcg-store-info">
			<h1 class="tcg-store-name"><?php echo esc_html( $shop_name ); ?></h1>
			<?php if ( $shop_desc ) : ?>
				<p class="tcg-store-desc"><?php echo esc_html( $shop_desc ); ?></p>
			<?php endif; ?>
			<p class="tcg-store-meta">
				<?php printf( esc_html__( '%d productos', 'tcg-manager' ), $query->found_posts ); ?>
				&middot;
				<?php printf( esc_html__( '%d ventas', 'tcg-manager' ), $total_sales ); ?>
			</p>
		</div>
	</div>

	<?php if ( $query->have_posts() ) : ?>
		<div class="tcg-store-products">
			<?php while ( $query->have_posts() ) : $query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( ! $product ) continue;
				$card_id = get_post_meta( get_the_ID(), '_linked_ygo_card', true );
				$card_url = $card_id ? get_permalink( $card_id ) : '';
				$thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
			?>
				<div class="tcg-store-product-card">
					<?php if ( $thumb ) : ?>
						<a href="<?php echo esc_url( $card_url ?: '#' ); ?>" class="tcg-store-product-img">
							<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
						</a>
					<?php endif; ?>
					<div class="tcg-store-product-info">
						<h3 class="tcg-store-product-title">
							<a href="<?php echo esc_url( $card_url ?: '#' ); ?>"><?php the_title(); ?></a>
						</h3>
						<div class="tcg-store-product-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
						<div class="tcg-store-product-stock">
							<?php echo esc_html( $product->get_stock_quantity() ? sprintf( __( '%d disponible(s)', 'tcg-manager' ), $product->get_stock_quantity() ) : __( 'En stock', 'tcg-manager' ) ); ?>
						</div>
					</div>
				</div>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>

		<?php if ( $query->max_num_pages > 1 ) : ?>
			<div class="tcg-pagination">
				<?php echo paginate_links( [
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $query->max_num_pages,
				] ); ?>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<p class="tcg-store-empty"><?php esc_html_e( 'Este vendedor no tiene productos disponibles.', 'tcg-manager' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
