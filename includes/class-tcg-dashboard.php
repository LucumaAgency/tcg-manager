<?php
defined( 'ABSPATH' ) || exit;

class TCG_Dashboard {

	private static $sections = [
		'home', 'products', 'new-product', 'edit-product',
		'orders', 'order-view', 'earnings', 'profile',
	];

	public function __construct() {
		add_shortcode( 'tcg_dashboard', [ $this, 'render' ] );
		add_action( 'init', [ $this, 'add_rewrite_endpoints' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_rewrite_endpoints() {
		add_rewrite_endpoint( 'tcg-section', EP_PAGES );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'tcg-section';
		$vars[] = 'tcg-id';
		return $vars;
	}

	/**
	 * Get current dashboard section.
	 */
	public static function get_current_section() {
		$section = get_query_var( 'tcg-section', 'home' );
		return in_array( $section, self::$sections, true ) ? $section : 'home';
	}

	/**
	 * Get dashboard page URL.
	 */
	public static function get_dashboard_url( $section = '', $params = [] ) {
		$page_id = self::get_dashboard_page_id();
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/dashboard/' );

		if ( $section && $section !== 'home' ) {
			$url = trailingslashit( $url ) . $section . '/';
		}

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		return $url;
	}

	/**
	 * Find the page with [tcg_dashboard] shortcode.
	 */
	private static function get_dashboard_page_id() {
		static $page_id = null;
		if ( $page_id === null ) {
			global $wpdb;
			$page_id = (int) $wpdb->get_var(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[tcg_dashboard]%' LIMIT 1"
			);
		}
		return $page_id;
	}

	/**
	 * Check if we're on the dashboard page.
	 */
	public static function is_dashboard_page() {
		$page_id = self::get_dashboard_page_id();
		return $page_id && is_page( $page_id );
	}

	/**
	 * Render the dashboard shortcode.
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="tcg-alert tcg-alert-error">'
				. esc_html__( 'Debes iniciar sesión para acceder al dashboard.', 'tcg-manager' )
				. ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">'
				. esc_html__( 'Iniciar sesión', 'tcg-manager' ) . '</a></div>';
		}

		if ( ! TCG_Vendor_Role::is_vendor() ) {
			return '<div class="tcg-alert tcg-alert-error">'
				. esc_html__( 'No tienes permisos de vendedor.', 'tcg-manager' )
				. '</div>';
		}

		$section = self::get_current_section();

		ob_start();
		?>
		<div class="tcg-dashboard-wrap">
			<?php $this->render_sidebar( $section ); ?>
			<div class="tcg-dashboard-content">
				<?php $this->render_alerts(); ?>
				<?php $this->load_template( $section ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render sidebar navigation.
	 */
	private function render_sidebar( $current ) {
		$nav_items = [
			'home'     => [ 'label' => __( 'Inicio', 'tcg-manager' ),    'icon' => 'dashicons-admin-home' ],
			'products' => [ 'label' => __( 'Productos', 'tcg-manager' ), 'icon' => 'dashicons-products' ],
			'orders'   => [ 'label' => __( 'Pedidos', 'tcg-manager' ),   'icon' => 'dashicons-list-view' ],
			'earnings' => [ 'label' => __( 'Ganancias', 'tcg-manager' ), 'icon' => 'dashicons-chart-line' ],
			'profile'  => [ 'label' => __( 'Perfil', 'tcg-manager' ),    'icon' => 'dashicons-admin-users' ],
		];
		?>
		<div class="tcg-dashboard-sidebar">
			<div class="tcg-sidebar-header">
				<span class="tcg-shop-name"><?php echo esc_html( TCG_Vendor_Profile::get_shop_name( get_current_user_id() ) ); ?></span>
			</div>
			<ul class="tcg-nav-menu">
				<?php foreach ( $nav_items as $key => $item ) : ?>
					<li class="tcg-nav-item">
						<a href="<?php echo esc_url( self::get_dashboard_url( $key ) ); ?>"
						   class="tcg-nav-link <?php echo $current === $key ? 'active' : ''; ?>">
							<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?> tcg-nav-icon"></span>
							<?php echo esc_html( $item['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
				<li class="tcg-nav-item tcg-nav-logout">
					<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="tcg-nav-link">
						<span class="dashicons dashicons-exit tcg-nav-icon"></span>
						<?php esc_html_e( 'Cerrar sesión', 'tcg-manager' ); ?>
					</a>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render flash alerts from query params.
	 */
	private function render_alerts() {
		if ( isset( $_GET['tcg_msg'] ) ) {
			$messages = [
				'product_saved'   => __( 'Producto guardado correctamente.', 'tcg-manager' ),
				'product_deleted' => __( 'Producto eliminado.', 'tcg-manager' ),
				'profile_saved'   => __( 'Perfil actualizado.', 'tcg-manager' ),
			];
			$key = sanitize_text_field( wp_unslash( $_GET['tcg_msg'] ) );
			if ( isset( $messages[ $key ] ) ) {
				echo '<div class="tcg-alert tcg-alert-success">' . esc_html( $messages[ $key ] ) . '</div>';
			}
		}
		if ( isset( $_GET['tcg_error'] ) ) {
			echo '<div class="tcg-alert tcg-alert-error">' . esc_html( sanitize_text_field( wp_unslash( $_GET['tcg_error'] ) ) ) . '</div>';
		}
	}

	/**
	 * Load a dashboard template.
	 */
	private function load_template( $section ) {
		// Map sections to template files.
		$map = [
			'new-product'  => 'product-form',
			'edit-product' => 'product-form',
		];
		$file = isset( $map[ $section ] ) ? $map[ $section ] : $section;

		// Allow theme override.
		$template = locate_template( 'tcg-manager/dashboard/' . $file . '.php' );
		if ( ! $template ) {
			$template = TCG_MANAGER_PATH . 'templates/dashboard/' . $file . '.php';
		}

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<p>' . esc_html__( 'Sección no encontrada.', 'tcg-manager' ) . '</p>';
		}
	}

	/**
	 * Enqueue dashboard assets.
	 */
	public function enqueue_assets() {
		if ( ! self::is_dashboard_page() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'tcg-dashboard',
			TCG_MANAGER_URL . 'assets/css/dashboard.css',
			[],
			TCG_MANAGER_VERSION
		);

		wp_enqueue_script(
			'tcg-dashboard',
			TCG_MANAGER_URL . 'assets/js/dashboard.js',
			[ 'jquery' ],
			TCG_MANAGER_VERSION,
			true
		);

		wp_localize_script( 'tcg-dashboard', 'tcgDashboard', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'dashboardUrl'  => self::get_dashboard_url(),
			'i18n'          => [
				'confirmDelete' => __( '¿Seguro que quieres eliminar este producto?', 'tcg-manager' ),
			],
		] );
	}
}
