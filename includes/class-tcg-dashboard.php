<?php
defined( 'ABSPATH' ) || exit;

class TCG_Dashboard {

	private static $sections = [
		'home', 'products', 'new-product', 'edit-product',
		'orders', 'order-view', 'earnings', 'profile',
	];

	public function __construct() {
		add_shortcode( 'tcg_dashboard', [ $this, 'render' ] );
		add_shortcode( 'tcg_register', [ $this, 'render_register' ] );
		$this->add_rewrite_rules(); // Run immediately — we're already in init.
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'process_registration' ] );
		add_action( 'template_redirect', [ $this, 'process_customer_registration' ] );
		add_action( 'template_redirect', [ $this, 'process_login' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add rewrite rules for clean dashboard URLs.
	 */
	public function add_rewrite_rules() {
		$page_id = (int) get_option( 'tcg_dashboard_page_id', 0 );
		if ( ! $page_id ) {
			return;
		}

		$page = get_post( $page_id );
		if ( ! $page || $page->post_status !== 'publish' ) {
			return;
		}

		$slug = get_page_uri( $page_id );
		if ( ! $slug ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/([^/]+)/?$',
			'index.php?page_id=' . $page_id . '&tcg-section=$matches[1]',
			'top'
		);
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
		// Try query var first (works with rewrite rules).
		$section = get_query_var( 'tcg-section', '' );

		// Fallback: check $_GET param.
		if ( ! $section && isset( $_GET['tcg-section'] ) ) {
			$section = sanitize_text_field( $_GET['tcg-section'] );
		}

		if ( ! $section ) {
			$section = 'home';
		}

		return in_array( $section, self::$sections, true ) ? $section : 'home';
	}

	/**
	 * Get dashboard page URL.
	 */
	public static function get_dashboard_url( $section = '', $params = [] ) {
		$page_id = self::get_dashboard_page_id();
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/dashboard/' );

		if ( $section && $section !== 'home' ) {
			// Use clean URL path (works with rewrite rules).
			$url = trailingslashit( $url ) . $section . '/';
		}

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		return $url;
	}

	/**
	 * Get dashboard page ID from option (set on first render).
	 */
	public static function get_dashboard_page_id() {
		static $page_id = null;
		if ( $page_id === null ) {
			$page_id = (int) get_option( 'tcg_dashboard_page_id', 0 );
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
		// Auto-detect and save the dashboard page ID on first render.
		$current_id = get_the_ID();
		$saved_id   = (int) get_option( 'tcg_dashboard_page_id', 0 );
		if ( $current_id && $current_id !== $saved_id ) {
			update_option( 'tcg_dashboard_page_id', $current_id, true );
			update_option( 'tcg_manager_flush_rewrite', 1 );
		}

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
		$map = [
			'new-product'  => 'product-form',
			'edit-product' => 'product-form',
		];
		$file = isset( $map[ $section ] ) ? $map[ $section ] : $section;

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

	/**
	 * Render auth shortcode: login + register (customer / vendor) tabs.
	 */
	public function render_register() {
		wp_enqueue_style( 'tcg-dashboard', TCG_MANAGER_URL . 'assets/css/dashboard.css', [], TCG_MANAGER_VERSION );

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( TCG_Vendor_Role::is_vendor() ) {
				return '<div class="tcg-alert tcg-alert-success">'
					. esc_html__( 'Ya tienes una cuenta de vendedor.', 'tcg-manager' )
					. ' <a href="' . esc_url( self::get_dashboard_url() ) . '">'
					. esc_html__( 'Ir al dashboard', 'tcg-manager' ) . '</a></div>';
			}
			return '<div class="tcg-alert tcg-alert-success">'
				. sprintf( esc_html__( 'Hola %s, ya tienes una cuenta.', 'tcg-manager' ), esc_html( $user->display_name ) )
				. ' <a href="' . esc_url( wc_get_account_endpoint_url( 'dashboard' ) ) . '">'
				. esc_html__( 'Mi cuenta', 'tcg-manager' ) . '</a>'
				. ' | <a href="' . esc_url( wp_logout_url( get_permalink() ) ) . '">'
				. esc_html__( 'Cerrar sesión', 'tcg-manager' ) . '</a></div>';
		}

		$valid_tabs = [ 'login', 'register', 'vendor' ];
		$active_tab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], $valid_tabs, true ) ? $_GET['tab'] : 'login';

		// Collect registration errors (vendor).
		$vendor_errors = [];
		if ( isset( $_POST['tcg_register_vendor'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_vendor_register' ) ) {
			$active_tab = 'vendor';
			$username  = sanitize_user( $_POST['username'] ?? '' );
			$email     = sanitize_email( $_POST['email'] ?? '' );
			$password  = $_POST['password'] ?? '';
			$shop_name = sanitize_text_field( $_POST['shop_name'] ?? '' );

			if ( ! $username ) $vendor_errors[] = __( 'El nombre de usuario es obligatorio.', 'tcg-manager' );
			if ( ! $email )    $vendor_errors[] = __( 'El email es obligatorio.', 'tcg-manager' );
			if ( ! $password ) $vendor_errors[] = __( 'La contraseña es obligatoria.', 'tcg-manager' );
			if ( ! $shop_name ) $vendor_errors[] = __( 'El nombre de tienda es obligatorio.', 'tcg-manager' );
			if ( $password && strlen( $password ) < 6 ) $vendor_errors[] = __( 'La contraseña debe tener al menos 6 caracteres.', 'tcg-manager' );
			if ( $username && username_exists( $username ) ) $vendor_errors[] = __( 'Este nombre de usuario ya existe.', 'tcg-manager' );
			if ( $email && email_exists( $email ) ) $vendor_errors[] = __( 'Este email ya está registrado.', 'tcg-manager' );
		}

		// Collect registration errors (customer).
		$customer_errors = [];
		if ( isset( $_POST['tcg_register_customer'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_customer_register' ) ) {
			$active_tab = 'register';
			$username = sanitize_user( $_POST['username'] ?? '' );
			$email    = sanitize_email( $_POST['email'] ?? '' );
			$password = $_POST['password'] ?? '';

			if ( ! $username ) $customer_errors[] = __( 'El nombre de usuario es obligatorio.', 'tcg-manager' );
			if ( ! $email )    $customer_errors[] = __( 'El email es obligatorio.', 'tcg-manager' );
			if ( ! $password ) $customer_errors[] = __( 'La contraseña es obligatoria.', 'tcg-manager' );
			if ( $password && strlen( $password ) < 6 ) $customer_errors[] = __( 'La contraseña debe tener al menos 6 caracteres.', 'tcg-manager' );
			if ( $username && username_exists( $username ) ) $customer_errors[] = __( 'Este nombre de usuario ya existe.', 'tcg-manager' );
			if ( $email && email_exists( $email ) ) $customer_errors[] = __( 'Este email ya está registrado.', 'tcg-manager' );
		}

		// Login error.
		$login_error = '';
		if ( isset( $_GET['tcg_login_error'] ) ) {
			$login_error = __( 'Usuario o contraseña incorrectos.', 'tcg-manager' );
		}

		$base_url = remove_query_arg( [ 'tab', 'tcg_login_error' ] );

		ob_start();
		?>
		<div class="tcg-auth-wrap">
			<div class="tcg-auth-tabs">
				<a href="<?php echo esc_url( $base_url ); ?>"
				   class="tcg-auth-tab <?php echo $active_tab === 'login' ? 'active' : ''; ?>">
					<?php esc_html_e( 'Iniciar sesión', 'tcg-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'register', $base_url ) ); ?>"
				   class="tcg-auth-tab <?php echo $active_tab === 'register' ? 'active' : ''; ?>">
					<?php esc_html_e( 'Comprador', 'tcg-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'vendor', $base_url ) ); ?>"
				   class="tcg-auth-tab <?php echo $active_tab === 'vendor' ? 'active' : ''; ?>">
					<?php esc_html_e( 'Vendedor', 'tcg-manager' ); ?>
				</a>
			</div>

			<?php if ( $active_tab === 'login' ) : ?>
				<!-- Login form -->
				<div class="tcg-auth-panel">
					<?php if ( $login_error ) : ?>
						<div class="tcg-alert tcg-alert-error"><?php echo esc_html( $login_error ); ?></div>
					<?php endif; ?>

					<form method="post" class="tcg-product-form">
						<?php wp_nonce_field( 'tcg_vendor_login' ); ?>
						<input type="hidden" name="tcg_login_vendor" value="1">

						<div class="tcg-form-group">
							<label for="tcg-login-user" class="tcg-form-label">
								<?php esc_html_e( 'Usuario o email', 'tcg-manager' ); ?>
							</label>
							<input type="text" name="log" id="tcg-login-user" class="tcg-form-control" required
								   value="<?php echo esc_attr( $_POST['log'] ?? '' ); ?>">
						</div>

						<div class="tcg-form-group">
							<label for="tcg-login-pass" class="tcg-form-label">
								<?php esc_html_e( 'Contraseña', 'tcg-manager' ); ?>
							</label>
							<div class="tcg-password-wrap">
								<input type="password" name="pwd" id="tcg-login-pass" class="tcg-form-control" required>
								<button type="button" class="tcg-password-toggle" aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'tcg-manager' ); ?>">&#128065;</button>
							</div>
						</div>

						<div class="tcg-form-actions">
							<button type="submit" class="tcg-btn tcg-btn-primary" style="width:100%;">
								<?php esc_html_e( 'Entrar', 'tcg-manager' ); ?>
							</button>
						</div>

						<p style="margin-top:12px;text-align:center;font-size:13px;">
							<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
								<?php esc_html_e( '¿Olvidaste tu contraseña?', 'tcg-manager' ); ?>
							</a>
						</p>
					</form>
				</div>

			<?php elseif ( $active_tab === 'register' ) : ?>
				<!-- Customer register form -->
				<div class="tcg-auth-panel">
					<p class="tcg-auth-description"><?php esc_html_e( 'Crea una cuenta para comprar cartas.', 'tcg-manager' ); ?></p>

					<?php if ( ! empty( $customer_errors ) ) : ?>
						<div class="tcg-alert tcg-alert-error">
							<?php foreach ( $customer_errors as $error ) : ?>
								<p style="margin:0 0 4px;"><?php echo esc_html( $error ); ?></p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<form method="post" class="tcg-product-form">
						<?php wp_nonce_field( 'tcg_customer_register' ); ?>

						<div class="tcg-form-group">
							<label for="tcg-creg-username" class="tcg-form-label">
								<?php esc_html_e( 'Nombre de usuario', 'tcg-manager' ); ?> <span class="required">*</span>
							</label>
							<input type="text" name="username" id="tcg-creg-username" class="tcg-form-control"
								   value="<?php echo esc_attr( $_POST['username'] ?? '' ); ?>" required>
						</div>

						<div class="tcg-form-group">
							<label for="tcg-creg-email" class="tcg-form-label">
								<?php esc_html_e( 'Email', 'tcg-manager' ); ?> <span class="required">*</span>
							</label>
							<input type="email" name="email" id="tcg-creg-email" class="tcg-form-control"
								   value="<?php echo esc_attr( $_POST['email'] ?? '' ); ?>" required>
						</div>

						<div class="tcg-form-group">
							<label for="tcg-creg-password" class="tcg-form-label">
								<?php esc_html_e( 'Contraseña', 'tcg-manager' ); ?> <span class="required">*</span>
							</label>
							<div class="tcg-password-wrap">
								<input type="password" name="password" id="tcg-creg-password" class="tcg-form-control" required minlength="6">
								<button type="button" class="tcg-password-toggle" aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'tcg-manager' ); ?>">&#128065;</button>
							</div>
						</div>

						<div class="tcg-form-actions">
							<button type="submit" name="tcg_register_customer" value="1" class="tcg-btn tcg-btn-primary" style="width:100%;">
								<?php esc_html_e( 'Crear cuenta', 'tcg-manager' ); ?>
							</button>
						</div>

						<p style="margin-top:12px;text-align:center;font-size:13px;">
							<?php esc_html_e( '¿Quieres vender cartas?', 'tcg-manager' ); ?>
							<a href="<?php echo esc_url( add_query_arg( 'tab', 'vendor', $base_url ) ); ?>">
								<?php esc_html_e( 'Regístrate como vendedor', 'tcg-manager' ); ?>
							</a>
						</p>
					</form>
				</div>

			<?php else : ?>
				<!-- Vendor register form -->
				<div class="tcg-auth-panel">
					<p class="tcg-auth-description"><?php esc_html_e( 'Crea una cuenta de vendedor para publicar tus cartas.', 'tcg-manager' ); ?></p>

					<?php if ( ! empty( $vendor_errors ) ) : ?>
						<div class="tcg-alert tcg-alert-error">
							<?php foreach ( $vendor_errors as $error ) : ?>
								<p style="margin:0 0 4px;"><?php echo esc_html( $error ); ?></p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<form method="post" class="tcg-product-form">
						<?php wp_nonce_field( 'tcg_vendor_register' ); ?>

						<div class="tcg-form-group">
							<label for="tcg-vreg-username" class="tcg-form-label">
								<?php esc_html_e( 'Nombre de usuario', 'tcg-manager' ); ?> <span class="required">*</span>
							</label>
							<input type="text" name="username" id="tcg-vreg-username" class="tcg-form-control"
								   value="<?php echo esc_attr( $_POST['username'] ?? '' ); ?>" required>
						</div>

						<div class="tcg-form-group">
							<label for="tcg-vreg-email" class="tcg-form-label">
								<?php esc_html_e( 'Email', 'tcg-manager' ); ?> <span class="required">*</span>
							</label>
							<input type="email" name="email" id="tcg-vreg-email" class="tcg-form-control"
								   value="<?php echo esc_attr( $_POST['email'] ?? '' ); ?>" required>
						</div>

						<div class="tcg-form-group">
							<label for="tcg-vreg-password" class="tcg-form-label">
								<?php esc_html_e( 'Contraseña', 'tcg-manager' ); ?> <span class="required">*</span>
							</label>
							<div class="tcg-password-wrap">
								<input type="password" name="password" id="tcg-vreg-password" class="tcg-form-control" required minlength="6">
								<button type="button" class="tcg-password-toggle" aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'tcg-manager' ); ?>">&#128065;</button>
							</div>
						</div>

						<div class="tcg-form-group">
							<label for="tcg-vreg-shop" class="tcg-form-label">
								<?php esc_html_e( 'Nombre de tu tienda', 'tcg-manager' ); ?> <span class="required">*</span>
							</label>
							<input type="text" name="shop_name" id="tcg-vreg-shop" class="tcg-form-control"
								   value="<?php echo esc_attr( $_POST['shop_name'] ?? '' ); ?>" required>
						</div>

						<div class="tcg-form-actions">
							<button type="submit" name="tcg_register_vendor" value="1" class="tcg-btn tcg-btn-primary" style="width:100%;">
								<?php esc_html_e( 'Crear cuenta de vendedor', 'tcg-manager' ); ?>
							</button>
						</div>

						<p style="margin-top:12px;text-align:center;font-size:13px;">
							<?php esc_html_e( '¿Solo quieres comprar?', 'tcg-manager' ); ?>
							<a href="<?php echo esc_url( add_query_arg( 'tab', 'register', $base_url ) ); ?>">
								<?php esc_html_e( 'Regístrate como comprador', 'tcg-manager' ); ?>
							</a>
						</p>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<script>
		document.querySelectorAll('.tcg-password-toggle').forEach(function(btn){
			btn.addEventListener('click',function(){
				var input=this.previousElementSibling;
				var show=input.type==='password';
				input.type=show?'text':'password';
				this.innerHTML=show?'&#128064;':'&#128065;';
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Process vendor registration before output.
	 */
	public function process_registration() {
		if ( ! isset( $_POST['tcg_register_vendor'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_vendor_register' ) ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}

		$username  = sanitize_user( $_POST['username'] ?? '' );
		$email     = sanitize_email( $_POST['email'] ?? '' );
		$password  = $_POST['password'] ?? '';
		$shop_name = sanitize_text_field( $_POST['shop_name'] ?? '' );

		if ( ! $username || ! $email || ! $password || ! $shop_name ) {
			return; // Let the template show errors.
		}
		if ( strlen( $password ) < 6 || username_exists( $username ) || email_exists( $email ) ) {
			return;
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return;
		}

		$user = new WP_User( $user_id );
		$user->set_role( 'tcg_vendor' );

		update_user_meta( $user_id, '_tcg_shop_name', $shop_name );
		update_user_meta( $user_id, '_tcg_shop_slug', sanitize_title( $shop_name ) );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		wp_safe_redirect( self::get_dashboard_url() );
		exit;
	}

	/**
	 * Process customer registration before output.
	 */
	public function process_customer_registration() {
		if ( ! isset( $_POST['tcg_register_customer'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_customer_register' ) ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}

		$username = sanitize_user( $_POST['username'] ?? '' );
		$email    = sanitize_email( $_POST['email'] ?? '' );
		$password = $_POST['password'] ?? '';

		if ( ! $username || ! $email || ! $password ) {
			return;
		}
		if ( strlen( $password ) < 6 || username_exists( $username ) || email_exists( $email ) ) {
			return;
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return;
		}

		// Set as WooCommerce customer.
		$user = new WP_User( $user_id );
		$user->set_role( 'customer' );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		// Redirect to WooCommerce my-account or shop.
		$redirect = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'myaccount' )
			: home_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process vendor login before output.
	 */
	public function process_login() {
		if ( ! isset( $_POST['tcg_login_vendor'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_vendor_login' ) ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}

		$creds = [
			'user_login'    => sanitize_text_field( $_POST['log'] ?? '' ),
			'user_password' => $_POST['pwd'] ?? '',
			'remember'      => true,
		];

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( 'tcg_login_error', '1', wp_get_referer() ?: home_url() ) );
			exit;
		}

		// Redirect vendors to dashboard, customers to my-account.
		if ( in_array( 'tcg_vendor', (array) $user->roles, true ) ) {
			wp_safe_redirect( self::get_dashboard_url() );
		} else {
			$redirect = function_exists( 'wc_get_page_permalink' )
				? wc_get_page_permalink( 'myaccount' )
				: home_url();
			wp_safe_redirect( $redirect );
		}
		exit;
	}
}
