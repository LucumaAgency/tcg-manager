<?php
defined( 'ABSPATH' ) || exit;

class TCG_Vendor_Profile {

	public function __construct() {
		$this->register_meta();
		$this->add_rewrite_rules();
		$this->maybe_flush_rewrites();
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'resolve_store_vendor' ], 5 );
		add_filter( 'template_include', [ $this, 'force_store_page_template' ], 999 );

		// Flush rewrites when store page option changes.
		add_action( 'update_option_tcg_store_page_id', function () {
			update_option( 'tcg_manager_flush_rewrite', 1 );
		} );

		// Shortcodes.
		add_shortcode( 'tcg_vendor_name', [ $this, 'sc_vendor_name' ] );
		add_shortcode( 'tcg_vendor_description', [ $this, 'sc_vendor_description' ] );
		add_shortcode( 'tcg_vendor_sales', [ $this, 'sc_vendor_sales' ] );
		add_shortcode( 'tcg_vendor_products_count', [ $this, 'sc_vendor_products_count' ] );
		add_shortcode( 'tcg_vendor_products_grid', [ $this, 'sc_vendor_products_grid' ] );
		add_shortcode( 'tcg_vendor_url', [ $this, 'sc_vendor_url' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_store_css' ] );

		// Bricks Query Loop integration.
		if ( defined( 'BRICKS_VERSION' ) ) {
			add_filter( 'bricks/setup/control_options', [ $this, 'bricks_add_query_type' ] );
			add_filter( 'bricks/query/run', [ $this, 'bricks_run_vendor_query' ], 10, 2 );
			add_filter( 'bricks/query/loop_object', [ $this, 'bricks_loop_object' ], 10, 3 );
		}
	}

	public function register_meta() {
		$fields = [
			'_tcg_shop_name'        => 'string',
			'_tcg_shop_slug'        => 'string',
			'_tcg_shop_description' => 'string',
			'_tcg_payment_info'     => 'string',
			'_tcg_commission_rate'  => 'string',
			'_tcg_shipping_lima_price'       => 'string',
			'_tcg_shipping_lima_days_min'    => 'string',
			'_tcg_shipping_lima_days_max'    => 'string',
			'_tcg_shipping_provincia_price'    => 'string',
			'_tcg_shipping_provincia_days_min' => 'string',
			'_tcg_shipping_provincia_days_max' => 'string',
		];

		foreach ( $fields as $key => $type ) {
			register_meta( 'user', $key, [
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
			] );
		}
	}

	public function add_rewrite_rules() {
		$page_id = (int) get_option( 'tcg_store_page_id', 0 );

		if ( $page_id ) {
			$slug = get_page_uri( $page_id );
			if ( $slug ) {
				// /tienda-vendedor/{vendor-slug}/ -> loads the store page with vendor context.
				add_rewrite_rule(
					'^' . preg_quote( $slug, '/' ) . '/([^/]+)/?$',
					'index.php?page_id=' . $page_id . '&tcg_vendor_store=$matches[1]',
					'top'
				);
			}
		}

		// Fallback: also keep the old pattern for the PHP template.
		add_rewrite_rule(
			'^tienda/vendor/([^/]+)/?$',
			'index.php?tcg_vendor_store=$matches[1]',
			'top'
		);
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'tcg_vendor_store';
		$vars[] = 'tcg_vendor_user';
		return $vars;
	}

	public function maybe_flush_rewrites() {
		if ( get_option( 'tcg_manager_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( 'tcg_manager_flush_rewrite' );
		}
	}

	/**
	 * Resolve the vendor from URL slug and set it as query var.
	 * If there's a store page configured, force WP to treat this as that page
	 * so Bricks (or any page builder) renders the template.
	 * Otherwise, fall back to the PHP template.
	 */
	public function resolve_store_vendor() {
		$store_slug = get_query_var( 'tcg_vendor_store' );
		if ( ! $store_slug ) {
			return;
		}

		$vendor = $this->find_vendor_by_slug( $store_slug );

		if ( ! $vendor ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		// Store vendor globally so shortcodes can access it.
		set_query_var( 'tcg_vendor_user', $vendor );
		$GLOBALS['tcg_current_vendor'] = $vendor;

		// If a store page is configured, force WP main query to behave as that page.
		$store_page_id = (int) get_option( 'tcg_store_page_id', 0 );
		if ( $store_page_id ) {
			global $wp_query, $post;

			// Force the main query to treat this as the store page.
			$wp_query->queried_object    = get_post( $store_page_id );
			$wp_query->queried_object_id = $store_page_id;
			$wp_query->is_page           = true;
			$wp_query->is_singular       = true;
			$wp_query->is_404            = false;
			$wp_query->is_home           = false;
			$wp_query->is_archive        = false;

			// Ensure the global $post is set to the store page.
			$post = get_post( $store_page_id );
			setup_postdata( $post );

			return; // Bricks or the page template will render with shortcodes.
		}

		// Fallback: load PHP template for /tienda/vendor/{slug}/.
		$template = locate_template( 'tcg-manager/store-page.php' );
		if ( ! $template ) {
			$template = TCG_MANAGER_PATH . 'templates/store/store-page.php';
		}
		if ( file_exists( $template ) ) {
			include $template;
			exit;
		}
	}

	/**
	 * Force Bricks/WP to use the store page template when on a vendor store URL.
	 */
	public function force_store_page_template( $template ) {
		if ( empty( $GLOBALS['tcg_current_vendor'] ) ) {
			return $template;
		}

		$store_page_id = (int) get_option( 'tcg_store_page_id', 0 );
		if ( ! $store_page_id ) {
			return $template;
		}

		// If Bricks is active and has content for this page, let Bricks handle it.
		if ( defined( 'BRICKS_VERSION' ) ) {
			$bricks_data = get_post_meta( $store_page_id, BRICKS_DB_PAGE_CONTENT, true );
			if ( ! empty( $bricks_data ) ) {
				// Force Bricks to render this page.
				return locate_template( 'singular.php' ) ?: $template;
			}
		}

		// Standard WordPress page template.
		$page_template = get_page_template_slug( $store_page_id );
		if ( $page_template && file_exists( get_stylesheet_directory() . '/' . $page_template ) ) {
			return get_stylesheet_directory() . '/' . $page_template;
		}

		return $template;
	}

	/**
	 * Find vendor by shop slug.
	 */
	private function find_vendor_by_slug( $slug ) {
		$users = get_users( [
			'meta_key'   => '_tcg_shop_slug',
			'meta_value' => sanitize_title( $slug ),
			'number'     => 1,
			'role'       => 'tcg_vendor',
		] );
		return ! empty( $users ) ? $users[0] : null;
	}

	/**
	 * Get current vendor from context (store page or shortcode attr).
	 */
	public static function get_current_vendor( $atts = [] ) {
		// From shortcode attribute.
		if ( ! empty( $atts['vendor_id'] ) ) {
			return get_userdata( absint( $atts['vendor_id'] ) );
		}
		if ( ! empty( $atts['slug'] ) ) {
			$users = get_users( [
				'meta_key'   => '_tcg_shop_slug',
				'meta_value' => sanitize_title( $atts['slug'] ),
				'number'     => 1,
				'role'       => 'tcg_vendor',
			] );
			return ! empty( $users ) ? $users[0] : null;
		}

		// From global (set by resolve_store_vendor).
		if ( ! empty( $GLOBALS['tcg_current_vendor'] ) ) {
			return $GLOBALS['tcg_current_vendor'];
		}

		// From query var.
		$vendor = get_query_var( 'tcg_vendor_user' );
		if ( $vendor instanceof WP_User ) {
			return $vendor;
		}

		return null;
	}

	/* ─── Shortcodes ─── */

	public function sc_vendor_name( $atts ) {
		$vendor = self::get_current_vendor( $atts ?? [] );
		if ( ! $vendor ) return '';
		return esc_html( self::get_shop_name( $vendor->ID ) );
	}

	public function sc_vendor_description( $atts ) {
		$vendor = self::get_current_vendor( $atts ?? [] );
		if ( ! $vendor ) return '';
		$desc = get_user_meta( $vendor->ID, '_tcg_shop_description', true );
		return $desc ? esc_html( $desc ) : '';
	}

	public function sc_vendor_sales( $atts ) {
		$vendor = self::get_current_vendor( $atts ?? [] );
		if ( ! $vendor ) return '0';
		global $wpdb;
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(pm.meta_value), 0)
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'total_sales'
			 WHERE p.post_type = 'product' AND p.post_status = 'publish' AND p.post_author = %d",
			$vendor->ID
		) );
		return esc_html( $total );
	}

	public function sc_vendor_products_count( $atts ) {
		$vendor = self::get_current_vendor( $atts ?? [] );
		if ( ! $vendor ) return '0';
		return esc_html( count_user_posts( $vendor->ID, 'product', true ) );
	}

	public function sc_vendor_url( $atts ) {
		$vendor = self::get_current_vendor( $atts ?? [] );
		if ( ! $vendor ) return '';
		return esc_url( self::get_store_url( $vendor->ID ) );
	}

	public function sc_vendor_products_grid( $atts ) {
		$atts = shortcode_atts( [
			'vendor_id' => '',
			'slug'      => '',
			'columns'   => 4,
			'limit'     => 24,
		], $atts );

		$vendor = self::get_current_vendor( $atts );
		if ( ! $vendor ) return '';

		$paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$limit = absint( $atts['limit'] );

		$query = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'author'         => $vendor->ID,
			'posts_per_page' => $limit,
			'paged'          => $paged,
			'meta_query'     => [ [ 'key' => '_linked_ygo_card', 'compare' => 'EXISTS' ] ],
		] );

		if ( ! $query->have_posts() ) {
			return '<p class="tcg-store-empty">' . esc_html__( 'Este vendedor no tiene productos disponibles.', 'tcg-manager' ) . '</p>';
		}

		$cols = absint( $atts['columns'] );

		ob_start();
		?>
		<div class="tcg-store-products" style="grid-template-columns:repeat(<?php echo $cols; ?>, 1fr);">
			<?php while ( $query->have_posts() ) : $query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( ! $product ) continue;
				$card_id  = get_post_meta( get_the_ID(), '_linked_ygo_card', true );
				$card_url = $card_id ? get_permalink( $card_id ) : '';
				$thumb    = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
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
							<?php echo esc_html( $product->get_stock_quantity()
								? sprintf( __( '%d disponible(s)', 'tcg-manager' ), $product->get_stock_quantity() )
								: __( 'En stock', 'tcg-manager' ) ); ?>
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
		<?php endif;

		return ob_get_clean();
	}

	/**
	 * Enqueue store CSS when on a vendor store page.
	 */
	public function maybe_enqueue_store_css() {
		if ( ! empty( $GLOBALS['tcg_current_vendor'] ) || get_query_var( 'tcg_vendor_store' ) ) {
			wp_enqueue_style( 'tcg-dashboard', TCG_MANAGER_URL . 'assets/css/dashboard.css', [], TCG_MANAGER_VERSION );
		}
	}

	/* ─── Bricks Query Loop ─── */

	/**
	 * Register "Vendor Products" query type in Bricks.
	 */
	public function bricks_add_query_type( $control_options ) {
		$control_options['queryTypes']['tcg_vendor_products'] = esc_html__( 'Vendor Products', 'tcg-manager' );
		return $control_options;
	}

	/**
	 * Run the custom "tcg_vendor_products" query for Bricks loop.
	 */
	public function bricks_run_vendor_query( $results, $query ) {
		if ( $query->object_type !== 'tcg_vendor_products' ) {
			return $results;
		}

		$vendor = self::get_current_vendor();
		if ( ! $vendor ) {
			return [];
		}

		$settings = $query->settings;
		$paged    = max( 1, absint( $_GET['paged'] ?? get_query_var( 'paged', 1 ) ) );

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'author'         => $vendor->ID,
			'posts_per_page' => ! empty( $settings['posts_per_page'] ) ? absint( $settings['posts_per_page'] ) : 24,
			'paged'          => $paged,
		];

		$wp_query = new \WP_Query( $args );

		// Store for pagination.
		$query->count        = $wp_query->found_posts;
		$query->max_num_pages = $wp_query->max_num_pages;

		return $wp_query->posts;
	}

	/**
	 * Set the loop object for each iteration (WP_Post → can use dynamic data).
	 */
	public function bricks_loop_object( $loop_object, $loop_key, $query ) {
		if ( $query->object_type !== 'tcg_vendor_products' ) {
			return $loop_object;
		}

		// Ensure global $post is set so Bricks dynamic tags work ({post_title}, {featured_image}, etc.).
		if ( $loop_object instanceof \WP_Post ) {
			global $post;
			$post = $loop_object;
			setup_postdata( $post );
		}

		return $loop_object;
	}

	/* ─── Static helpers ─── */

	/**
	 * Get store URL for a vendor.
	 */
	public static function get_store_url( $user_id ) {
		$slug = get_user_meta( $user_id, '_tcg_shop_slug', true );
		if ( ! $slug ) {
			return '';
		}

		// If a store page is configured, use clean URLs based on that page.
		$page_id = (int) get_option( 'tcg_store_page_id', 0 );
		if ( $page_id ) {
			return trailingslashit( get_permalink( $page_id ) ) . $slug . '/';
		}

		return home_url( '/tienda/vendor/' . $slug . '/' );
	}

	/**
	 * Get shop name with fallback.
	 */
	public static function get_shop_name( $user_id ) {
		$name = get_user_meta( $user_id, '_tcg_shop_name', true );
		if ( $name ) {
			return $name;
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : '';
	}
}
