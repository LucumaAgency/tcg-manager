<?php
defined( 'ABSPATH' ) || exit;

class TCG_Listings {

	public function __construct() {
		add_shortcode( 'tcg_card_listings', [ $this, 'render_shortcode_combined' ] );
		add_shortcode( 'tcg_buy_box', [ $this, 'render_shortcode_buy_box' ] );
		add_shortcode( 'tcg_other_vendors', [ $this, 'render_shortcode_other_vendors' ] );
		add_shortcode( 'tcg_card_price', [ $this, 'render_shortcode_card_price' ] );
		add_shortcode( 'tcg_add_to_cart_btn', [ $this, 'render_shortcode_add_to_cart_btn' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function render_shortcode_combined() {
		if ( ! is_singular( 'ygo_card' ) ) return '';
		$listings = $this->get_ranked_listings();
		ob_start();
		if ( empty( $listings ) ) {
			$this->render_out_of_stock();
		} elseif ( count( $listings ) === 1 ) {
			$this->render_buy_box( $listings[0] );
		} else {
			$this->render_buy_box( $listings[0] );
			$this->render_vendors_table( array_slice( $listings, 1 ) );
		}
		return ob_get_clean();
	}

	public function render_shortcode_buy_box() {
		if ( ! is_singular( 'ygo_card' ) ) return '';
		$listings = $this->get_ranked_listings();
		ob_start();
		empty( $listings ) ? $this->render_out_of_stock() : $this->render_buy_box( $listings[0] );
		return ob_get_clean();
	}

	public function render_shortcode_other_vendors() {
		if ( ! is_singular( 'ygo_card' ) ) return '';
		$listings = $this->get_ranked_listings();
		ob_start();
		if ( count( $listings ) >= 2 ) {
			$this->render_vendors_table( array_slice( $listings, 1 ) );
		}
		return ob_get_clean();
	}

	public function render_shortcode_card_price() {
		$card_id = get_the_ID();
		if ( ! $card_id || get_post_type( $card_id ) !== 'ygo_card' ) return '';
		$listings = $this->get_ranked_listings_for( $card_id );
		if ( empty( $listings ) ) {
			return '<span class="tcg-card-price tcg-card-price--empty">' . esc_html__( 'No disponible', 'tcg-manager' ) . '</span>';
		}
		return '<span class="tcg-card-price">' . $listings[0]['price_html'] . '</span>';
	}

	public function render_shortcode_add_to_cart_btn( $atts ) {
		$atts    = shortcode_atts( [ 'label' => __( 'Agregar al carrito', 'tcg-manager' ) ], $atts );
		$card_id = get_the_ID();
		if ( ! $card_id || get_post_type( $card_id ) !== 'ygo_card' ) return '';
		$listings = $this->get_ranked_listings_for( $card_id );
		if ( empty( $listings ) ) {
			return '<button type="button" class="tcg-add-to-cart button alt" disabled>' . esc_html__( 'No disponible', 'tcg-manager' ) . '</button>';
		}
		$best  = $listings[0];
		$nonce = wp_create_nonce( 'tcg_listings_nonce' );
		$this->enqueue_listings_js();
		return '<button type="button" class="tcg-add-to-cart button alt" data-product-id="' . esc_attr( $best['product_id'] ) . '" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html( $atts['label'] ) . '</button>';
	}

	/* ─── Data ─── */

	private function get_ranked_listings_for( $card_id ) {
		static $cache = [];
		if ( isset( $cache[ $card_id ] ) ) return $cache[ $card_id ];
		$listings = $this->get_vendor_listings( $card_id );
		$listings = $this->rank_listings( $listings );
		$cache[ $card_id ] = $listings;
		return $listings;
	}

	private function get_ranked_listings() {
		return $this->get_ranked_listings_for( get_the_ID() );
	}

	private function get_vendor_listings( $card_id ) {
		$query = new WP_Query( [
			'post_type'      => 'product',
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'meta_query'     => [ [ 'key' => '_linked_ygo_card', 'value' => $card_id, 'type' => 'NUMERIC' ] ],
		] );

		$listings = [];
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product || ! $product->is_in_stock() ) continue;
			$listing = $this->get_listing_data( $product );
			if ( $listing ) $listings[] = $listing;
		}
		wp_reset_postdata();
		return $listings;
	}

	private function get_listing_data( $product ) {
		$product_id = $product->get_id();
		$vendor_id  = (int) get_post_field( 'post_author', $product_id );

		$vendor_name = TCG_Vendor_Profile::get_shop_name( $vendor_id );
		$vendor_url  = TCG_Vendor_Profile::get_store_url( $vendor_id );

		$condition = $this->get_first_term( $product_id, 'ygo_condition' );
		$printing  = $this->get_first_term( $product_id, 'ygo_printing' );
		$language  = $this->get_first_term( $product_id, 'ygo_language' );

		return [
			'product_id'  => $product_id,
			'price'       => (float) $product->get_price(),
			'price_html'  => $product->get_price_html(),
			'stock_qty'   => $product->get_stock_quantity() ?: 0,
			'total_sales' => (int) get_post_meta( $product_id, 'total_sales', true ),
			'vendor_name' => $vendor_name,
			'vendor_url'  => $vendor_url,
			'condition'   => $condition,
			'printing'    => $printing,
			'language'    => $language,
		];
	}

	private function get_first_term( $product_id, $taxonomy ) {
		$terms = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'names' ] );
		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : '';
	}

	private function rank_listings( $listings ) {
		usort( $listings, function( $a, $b ) {
			if ( $a['total_sales'] !== $b['total_sales'] ) return $b['total_sales'] - $a['total_sales'];
			return $a['price'] <=> $b['price'];
		} );
		return $listings;
	}

	private function render_qty_select( $stock_qty, $css_class = 'tcg-qty-select' ) {
		$max = $stock_qty > 0 ? min( $stock_qty, 99 ) : 10;
		echo '<select class="' . esc_attr( $css_class ) . '">';
		for ( $i = 1; $i <= $max; $i++ ) {
			echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . '</option>';
		}
		echo '</select>';
	}

	/* ─── Render ─── */

	private function render_out_of_stock() {
		?>
		<div class="tcg-buy-box tcg-buy-box--empty">
			<p class="tcg-buy-box__unavailable"><?php esc_html_e( 'No disponible actualmente', 'tcg-manager' ); ?></p>
		</div>
		<?php
	}

	private function render_buy_box( $listing ) {
		$nonce = wp_create_nonce( 'tcg_listings_nonce' );
		?>
		<div class="tcg-buy-box" data-product-id="<?php echo esc_attr( $listing['product_id'] ); ?>">
			<div class="tcg-buy-box__price"><?php echo $listing['price_html']; ?></div>
			<div class="tcg-buy-box__badges">
				<?php if ( $listing['condition'] ) : ?><span class="tcg-badge tcg-badge--condition"><?php echo esc_html( $listing['condition'] ); ?></span><?php endif; ?>
				<?php if ( $listing['printing'] ) : ?><span class="tcg-badge tcg-badge--printing"><?php echo esc_html( $listing['printing'] ); ?></span><?php endif; ?>
				<?php if ( $listing['language'] ) : ?><span class="tcg-badge tcg-badge--language"><?php echo esc_html( $listing['language'] ); ?></span><?php endif; ?>
			</div>
			<?php if ( $listing['vendor_name'] ) : ?>
				<div class="tcg-buy-box__vendor">
					<?php esc_html_e( 'Vendido por', 'tcg-manager' ); ?>
					<a href="<?php echo esc_url( $listing['vendor_url'] ); ?>"><?php echo esc_html( $listing['vendor_name'] ); ?></a>
				</div>
			<?php endif; ?>
			<div class="tcg-buy-box__stock">
				<?php $listing['stock_qty'] > 0 ? printf( esc_html__( '%d disponible(s)', 'tcg-manager' ), $listing['stock_qty'] ) : esc_html_e( 'En stock', 'tcg-manager' ); ?>
			</div>
			<div class="tcg-buy-box__actions">
				<?php $this->render_qty_select( $listing['stock_qty'] ); ?>
				<button type="button" class="tcg-add-to-cart button alt" data-product-id="<?php echo esc_attr( $listing['product_id'] ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Agregar al carrito', 'tcg-manager' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	private function render_vendors_table( $listings ) {
		if ( empty( $listings ) ) return;
		$nonce = wp_create_nonce( 'tcg_listings_nonce' );
		?>
		<div class="tcg-vendors-section">
			<h3 class="tcg-vendors-section__title"><?php printf( esc_html__( 'Otros vendedores (%d)', 'tcg-manager' ), count( $listings ) ); ?></h3>
			<table class="tcg-vendors-table">
				<thead><tr>
					<th><?php esc_html_e( 'Vendedor', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Condición', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Printing', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Idioma', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Precio', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Stock', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Cantidad', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Comprar', 'tcg-manager' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $listings as $listing ) : ?>
					<tr data-product-id="<?php echo esc_attr( $listing['product_id'] ); ?>">
						<td data-label="<?php esc_attr_e( 'Vendedor', 'tcg-manager' ); ?>">
							<?php if ( $listing['vendor_url'] ) : ?><a href="<?php echo esc_url( $listing['vendor_url'] ); ?>"><?php echo esc_html( $listing['vendor_name'] ); ?></a>
							<?php else : echo esc_html( $listing['vendor_name'] ); endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Condición', 'tcg-manager' ); ?>"><?php if ( $listing['condition'] ) : ?><span class="tcg-badge tcg-badge--condition"><?php echo esc_html( $listing['condition'] ); ?></span><?php endif; ?></td>
						<td data-label="<?php esc_attr_e( 'Printing', 'tcg-manager' ); ?>"><?php if ( $listing['printing'] ) : ?><span class="tcg-badge tcg-badge--printing"><?php echo esc_html( $listing['printing'] ); ?></span><?php endif; ?></td>
						<td data-label="<?php esc_attr_e( 'Idioma', 'tcg-manager' ); ?>"><?php if ( $listing['language'] ) : ?><span class="tcg-badge tcg-badge--language"><?php echo esc_html( $listing['language'] ); ?></span><?php endif; ?></td>
						<td data-label="<?php esc_attr_e( 'Precio', 'tcg-manager' ); ?>"><?php echo $listing['price_html']; ?></td>
						<td data-label="<?php esc_attr_e( 'Stock', 'tcg-manager' ); ?>"><?php echo esc_html( $listing['stock_qty'] > 0 ? $listing['stock_qty'] : __( 'En stock', 'tcg-manager' ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Cantidad', 'tcg-manager' ); ?>"><?php $this->render_qty_select( $listing['stock_qty'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Comprar', 'tcg-manager' ); ?>">
							<button type="button" class="tcg-add-to-cart button alt" data-product-id="<?php echo esc_attr( $listing['product_id'] ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
								<?php esc_html_e( 'Comprar', 'tcg-manager' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/* ─── Assets ─── */

	public function enqueue_assets() {
		$is_ygo = is_singular( 'ygo_card' ) || is_post_type_archive( 'ygo_card' ) || is_tax( 'ygo_set' ) || is_tax( 'ygo_archetype' ) || is_tax( 'ygo_card_type' ) || is_tax( 'ygo_attribute' ) || is_tax( 'ygo_race' );
		if ( ! $is_ygo ) return;

		wp_enqueue_style( 'tcg-listings', TCG_MANAGER_URL . 'assets/css/listings.css', [], TCG_MANAGER_VERSION );

		if ( is_singular( 'ygo_card' ) ) {
			$this->enqueue_listings_js();
		}
	}

	public function enqueue_listings_js() {
		if ( wp_script_is( 'tcg-listings', 'enqueued' ) ) return;

		wp_enqueue_style( 'tcg-listings', TCG_MANAGER_URL . 'assets/css/listings.css', [], TCG_MANAGER_VERSION );

		wp_enqueue_script( 'tcg-listings', TCG_MANAGER_URL . 'assets/js/listings.js', [ 'jquery' ], TCG_MANAGER_VERSION, true );

		wp_localize_script( 'tcg-listings', 'tcgListings', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => [
				'adding' => __( 'Agregando…', 'tcg-manager' ),
				'added'  => __( '¡Agregado!', 'tcg-manager' ),
				'error'  => __( 'Error al agregar', 'tcg-manager' ),
				'buy'    => __( 'Comprar', 'tcg-manager' ),
				'add'    => __( 'Agregar al carrito', 'tcg-manager' ),
			],
		] );
	}
}
