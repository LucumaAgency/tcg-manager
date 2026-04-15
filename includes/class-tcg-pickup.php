<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lógica de modo de entrega (delivery / pickup) en checkout.
 *
 * - Inyecta el selector "Modo de entrega" dentro de la sección de envío.
 * - Persiste el modo y la tienda elegida en WC()->session.
 * - Filtra required de los campos de dirección cuando el modo es pickup.
 * - Valida que se haya elegido una tienda si el modo es pickup.
 * - Guarda la tienda (id + snapshot) en el shipping item del pedido.
 * - Muestra el bloque de recojo en thank-you, email, my-account, admin order y dashboard vendedor.
 */
class TCG_Pickup {

	const SESSION_MODE  = 'tcg_delivery_mode';
	const SESSION_STORE = 'tcg_pickup_store_id';

	public function __construct() {
		// UI en checkout.
		add_action( 'woocommerce_before_checkout_shipping_form', [ $this, 'render_selector' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Sincronizar sesión con lo posteado al refrescar checkout.
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'update_session_from_post' ] );

		// Campos no requeridos si es pickup.
		add_filter( 'woocommerce_checkout_fields', [ $this, 'maybe_unrequire_shipping_fields' ] );

		// Validación al enviar el checkout.
		add_action( 'woocommerce_checkout_process', [ $this, 'validate' ] );

		// Guardar en el pedido (a nivel order meta; y en shipping items).
		add_action( 'woocommerce_checkout_create_order', [ $this, 'save_to_order' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_shipping_item', [ $this, 'save_to_shipping_item' ], 10, 4 );

		// Limpiar sesión tras crear pedido.
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'clear_session' ] );

		// Display en los 5 puntos.
		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'display_on_order' ] );
		add_action( 'woocommerce_email_after_order_table',          [ $this, 'display_on_email' ], 10, 4 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $this, 'display_on_admin' ] );
	}

	/* --------------------------- Helpers de sesión --------------------------- */

	public static function get_mode() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) return '';
		$mode = WC()->session->get( self::SESSION_MODE );
		if ( $mode === 'pickup' || $mode === 'delivery' ) return $mode;
		return '';
	}

	public static function get_store_id() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) return 0;
		return (int) WC()->session->get( self::SESSION_STORE );
	}

	public function update_session_from_post( $posted_raw ) {
		parse_str( $posted_raw, $posted );

		$raw  = $posted['tcg_delivery_mode'] ?? '';
		$mode = in_array( $raw, [ 'delivery', 'pickup' ], true ) ? $raw : '';
		WC()->session->set( self::SESSION_MODE, $mode );

		$store_id = (int) ( $posted['tcg_pickup_store_id'] ?? 0 );
		WC()->session->set( self::SESSION_STORE, $store_id );
	}

	public function clear_session() {
		if ( ! WC()->session ) return;
		WC()->session->set( self::SESSION_MODE, null );
		WC()->session->set( self::SESSION_STORE, null );
	}

	/* --------------------------- UI en checkout --------------------------- */

	public function render_selector() {
		$stores      = TCG_Pickup_Store::get_all();
		$has_stores  = ! empty( $stores );
		$mode        = self::get_mode();
		$selected_id = self::get_store_id();
		?>
		<div id="tcg-delivery-mode-wrap" style="margin-bottom:16px;">
			<p class="form-row form-row-wide">
				<label for="tcg_delivery_mode"><strong><?php esc_html_e( 'Modo de entrega', 'tcg-manager' ); ?></strong></label>
				<select id="tcg_delivery_mode" name="tcg_delivery_mode" class="tcg-delivery-mode">
					<option value=""><?php esc_html_e( '— Selecciona una opción —', 'tcg-manager' ); ?></option>
					<option value="delivery" <?php selected( $mode, 'delivery' ); ?>><?php esc_html_e( 'Delivery a domicilio', 'tcg-manager' ); ?></option>
					<?php if ( $has_stores ) : ?>
						<option value="pickup" <?php selected( $mode, 'pickup' ); ?>><?php esc_html_e( 'Recojo en tienda', 'tcg-manager' ); ?></option>
					<?php endif; ?>
				</select>
			</p>

			<?php if ( $has_stores ) : ?>
			<div id="tcg-pickup-wrap" style="<?php echo $mode === 'pickup' ? '' : 'display:none;'; ?>">
				<p class="form-row form-row-wide">
					<label for="tcg_pickup_store_id"><?php esc_html_e( 'Selecciona una tienda', 'tcg-manager' ); ?> <abbr class="required" title="required">*</abbr></label>
					<select id="tcg_pickup_store_id" name="tcg_pickup_store_id" class="tcg-pickup-store">
						<option value=""><?php esc_html_e( '— Elegir tienda —', 'tcg-manager' ); ?></option>
						<?php foreach ( $stores as $s ) : ?>
							<option value="<?php echo esc_attr( $s['id'] ); ?>"
								data-address="<?php echo esc_attr( $s['address'] ); ?>"
								data-district="<?php echo esc_attr( $s['district'] ); ?>"
								data-hours="<?php echo esc_attr( $s['hours'] ); ?>"
								data-phone="<?php echo esc_attr( $s['phone'] ); ?>"
								<?php selected( $selected_id, $s['id'] ); ?>>
								<?php echo esc_html( $s['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<div id="tcg-pickup-details" class="tcg-pickup-details" style="display:none;background:#f7f7f7;padding:12px;border-radius:6px;margin-top:8px;"></div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function enqueue_assets() {
		if ( ! is_checkout() ) return;
		wp_enqueue_script(
			'tcg-checkout-pickup',
			TCG_MANAGER_URL . 'assets/js/checkout-pickup.js',
			[ 'jquery' ],
			TCG_MANAGER_VERSION,
			true
		);
	}

	/* --------------------------- Required fields --------------------------- */

	public function maybe_unrequire_shipping_fields( $fields ) {
		$mode = self::get_mode();
		// Solo para delivery mantenemos required; para pickup o sin selección, quitamos required.
		if ( $mode === 'delivery' ) return $fields;

		$shipping_keys = [ 'shipping_address_1', 'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_country', 'shipping_first_name', 'shipping_last_name' ];
		foreach ( $shipping_keys as $k ) {
			if ( isset( $fields['shipping'][ $k ] ) ) {
				$fields['shipping'][ $k ]['required'] = false;
			}
		}

		$billing_addr_keys = [ 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country' ];
		foreach ( $billing_addr_keys as $k ) {
			if ( isset( $fields['billing'][ $k ] ) ) {
				$fields['billing'][ $k ]['required'] = false;
			}
		}

		return $fields;
	}

	/* --------------------------- Validación --------------------------- */

	public function validate() {
		$mode = $_POST['tcg_delivery_mode'] ?? '';

		if ( ! in_array( $mode, [ 'delivery', 'pickup' ], true ) ) {
			wc_add_notice( __( 'Debes seleccionar un modo de entrega.', 'tcg-manager' ), 'error' );
			return;
		}

		if ( $mode !== 'pickup' ) return;

		$store_id = (int) ( $_POST['tcg_pickup_store_id'] ?? 0 );
		if ( ! $store_id || ! TCG_Pickup_Store::get( $store_id ) ) {
			wc_add_notice( __( 'Debes seleccionar una tienda de recojo.', 'tcg-manager' ), 'error' );
		}
	}

	/* --------------------------- Persistencia --------------------------- */

	public function save_to_order( $order, $data ) {
		$mode = isset( $_POST['tcg_delivery_mode'] ) && $_POST['tcg_delivery_mode'] === 'pickup' ? 'pickup' : 'delivery';
		$order->update_meta_data( '_tcg_delivery_mode', $mode );

		if ( $mode !== 'pickup' ) return;

		$store_id = (int) ( $_POST['tcg_pickup_store_id'] ?? 0 );
		$store    = TCG_Pickup_Store::get( $store_id );
		if ( ! $store ) return;

		$order->update_meta_data( '_tcg_pickup_store_id', $store_id );
		$order->update_meta_data( '_tcg_pickup_store_snapshot', $store );
	}

	public function save_to_shipping_item( $item, $package_key, $package, $order ) {
		if ( $order->get_meta( '_tcg_delivery_mode' ) !== 'pickup' ) return;
		$store = $order->get_meta( '_tcg_pickup_store_snapshot' );
		if ( ! $store ) return;

		$item->add_meta_data( __( 'Recojo en tienda', 'tcg-manager' ), $store['name'], true );
		$item->add_meta_data( '_tcg_pickup_store_id', $store['id'], true );
	}

	/* --------------------------- Display --------------------------- */

	public static function render_block( $order, $context = 'default' ) {
		if ( ! $order || $order->get_meta( '_tcg_delivery_mode' ) !== 'pickup' ) return '';
		$store = $order->get_meta( '_tcg_pickup_store_snapshot' );
		if ( ! $store ) return '';

		ob_start();
		?>
		<div class="tcg-pickup-block" style="background:#f7f7f7;padding:12px;border-radius:6px;margin:12px 0;">
			<h3 style="margin:0 0 8px;"><?php esc_html_e( 'Recojo en tienda', 'tcg-manager' ); ?></h3>
			<p style="margin:0;">
				<strong><?php echo esc_html( $store['name'] ); ?></strong><br>
				<?php if ( ! empty( $store['address'] ) ) : ?><?php echo esc_html( $store['address'] ); ?><?php endif; ?>
				<?php if ( ! empty( $store['district'] ) ) : ?>, <?php echo esc_html( $store['district'] ); ?><?php endif; ?><br>
				<?php if ( ! empty( $store['hours'] ) ) : ?><em><?php echo esc_html( $store['hours'] ); ?></em><br><?php endif; ?>
				<?php if ( ! empty( $store['phone'] ) ) : ?><?php esc_html_e( 'Tel:', 'tcg-manager' ); ?> <?php echo esc_html( $store['phone'] ); ?><?php endif; ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	public function display_on_order( $order ) {
		echo self::render_block( $order, 'order' );
	}

	public function display_on_email( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $plain_text ) {
			if ( $order->get_meta( '_tcg_delivery_mode' ) !== 'pickup' ) return;
			$store = $order->get_meta( '_tcg_pickup_store_snapshot' );
			if ( ! $store ) return;
			echo "\n" . __( 'RECOJO EN TIENDA', 'tcg-manager' ) . "\n";
			echo $store['name'] . "\n";
			if ( ! empty( $store['address'] ) ) echo $store['address'] . ', ' . ( $store['district'] ?? '' ) . "\n";
			if ( ! empty( $store['hours'] ) ) echo $store['hours'] . "\n";
			if ( ! empty( $store['phone'] ) ) echo 'Tel: ' . $store['phone'] . "\n";
			return;
		}
		echo self::render_block( $order, 'email' );
	}

	public function display_on_admin( $order ) {
		echo self::render_block( $order, 'admin' );
	}
}
