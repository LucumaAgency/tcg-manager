<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();
$step      = absint( $_GET['step'] ?? 1 );
if ( $step < 1 || $step > 3 ) $step = 1;

$steps = [
	1 => __( 'Primer producto', 'tcg-manager' ),
	2 => __( 'Información de pago', 'tcg-manager' ),
	3 => __( 'Tarifas de envío', 'tcg-manager' ),
];

// ─── Step 2: Save payment ───
if ( $step === 2 && isset( $_POST['tcg_onboarding_payment'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_onboarding_payment' ) ) {
	$payment_fields = [
		'_tcg_pay_yape', '_tcg_pay_plin',
		'_tcg_pay_interbank', '_tcg_pay_interbank_cci',
		'_tcg_pay_bcp', '_tcg_pay_bcp_cci',
		'_tcg_pay_bbva', '_tcg_pay_bbva_cci',
	];

	// Validate: at least one payment method must be filled.
	$primary_payment_keys = [ 'pay_yape', 'pay_plin', 'pay_interbank', 'pay_bcp', 'pay_bbva' ];
	$has_payment = false;
	foreach ( $primary_payment_keys as $pk ) {
		if ( ! empty( trim( $_POST[ $pk ] ?? '' ) ) ) {
			$has_payment = true;
			break;
		}
	}
	if ( ! $has_payment ) {
		wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( __( 'Debes completar al menos un método de pago.', 'tcg-manager' ) ), TCG_Dashboard::get_dashboard_url( 'onboarding', [ 'step' => 2 ] ) ) );
		exit;
	}

	foreach ( $payment_fields as $field ) {
		$key = str_replace( '_tcg_', '', $field );
		update_user_meta( $vendor_id, $field, sanitize_text_field( $_POST[ $key ] ?? '' ) );
	}
	wp_safe_redirect( TCG_Dashboard::get_dashboard_url( 'onboarding', [ 'step' => 3 ] ) );
	exit;
}

// ─── Step 3: Save shipping & complete ───
if ( $step === 3 && isset( $_POST['tcg_onboarding_shipping'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_onboarding_shipping' ) ) {
	$shipping_fields = [
		'_tcg_shipping_lima_price', '_tcg_shipping_lima_days_min', '_tcg_shipping_lima_days_max',
		'_tcg_shipping_provincia_price', '_tcg_shipping_provincia_days_min', '_tcg_shipping_provincia_days_max',
	];
	foreach ( $shipping_fields as $field ) {
		$key = str_replace( '_tcg_', '', $field );
		update_user_meta( $vendor_id, $field, sanitize_text_field( $_POST[ $key ] ?? '' ) );
	}
	update_user_meta( $vendor_id, '_tcg_onboarding_complete', 1 );
	wp_safe_redirect( add_query_arg( 'tcg_msg', 'onboarding_done', TCG_Dashboard::get_dashboard_url() ) );
	exit;
}
?>

<div class="tcg-onboarding">
	<h2 style="text-align:center;margin-bottom:8px;"><?php esc_html_e( 'Configura tu tienda', 'tcg-manager' ); ?></h2>
	<p style="text-align:center;color:#666;margin-bottom:24px;">
		<?php printf( esc_html__( 'Paso %d de %d — %s', 'tcg-manager' ), $step, count( $steps ), $steps[ $step ] ); ?>
	</p>

	<!-- Step indicator -->
	<div class="tcg-steps">
		<?php foreach ( $steps as $num => $label ) : ?>
			<div class="tcg-step <?php echo $num < $step ? 'completed' : ( $num === $step ? 'active' : '' ); ?>">
				<div class="tcg-step-number"><?php echo $num < $step ? '&#10003;' : esc_html( $num ); ?></div>
				<div class="tcg-step-label"><?php echo esc_html( $label ); ?></div>
			</div>
			<?php if ( $num < count( $steps ) ) : ?>
				<div class="tcg-step-line <?php echo $num < $step ? 'completed' : ''; ?>"></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<?php if ( $step === 1 ) : ?>
		<!-- ─── Step 1: Add first product ─── -->
		<form method="post" class="tcg-product-form">
			<?php wp_nonce_field( 'tcg_save_product', 'tcg_product_nonce' ); ?>
			<input type="hidden" name="tcg_action" value="save_product">
			<input type="hidden" name="product_id" value="0">
			<input type="hidden" name="tcg_onboarding" value="1">

			<div class="tcg-form-group tcg-card-selector">
				<label for="tcg-card-search" class="tcg-form-label">
					<?php esc_html_e( 'Carta vinculada', 'tcg-manager' ); ?> <span class="required">*</span>
				</label>
				<input type="text" id="tcg-card-search" class="tcg-form-control"
					   placeholder="<?php esc_attr_e( 'Buscar carta por nombre...', 'tcg-manager' ); ?>" autocomplete="off">
				<input type="hidden" name="_linked_ygo_card" id="tcg-linked-card-id" value="">
				<div id="tcg-card-preview" class="tcg-card-preview" style="display:none;"></div>
			</div>

			<?php
			$taxonomies = [
				'ygo_condition' => __( 'Condición', 'tcg-manager' ),
				'ygo_printing'  => __( 'Printing', 'tcg-manager' ),
				'ygo_language'  => __( 'Idioma', 'tcg-manager' ),
			];
			foreach ( $taxonomies as $tax => $label ) :
				$terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name' ] );
				if ( is_wp_error( $terms ) || empty( $terms ) ) continue;
			?>
				<div class="tcg-form-group">
					<label for="tcg-<?php echo esc_attr( $tax ); ?>" class="tcg-form-label">
						<?php echo esc_html( $label ); ?> <span class="required">*</span>
					</label>
					<select name="<?php echo esc_attr( $tax ); ?>" id="tcg-<?php echo esc_attr( $tax ); ?>" class="tcg-form-control">
						<option value=""><?php esc_html_e( '— Seleccionar —', 'tcg-manager' ); ?></option>
						<?php foreach ( $terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>

			<div class="tcg-form-group">
				<label for="tcg-price" class="tcg-form-label">
					<?php esc_html_e( 'Precio', 'tcg-manager' ); ?> (<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>) <span class="required">*</span>
				</label>
				<input type="number" name="price" id="tcg-price" class="tcg-form-control" step="0.01" min="0.01" required>
			</div>

			<div class="tcg-form-group">
				<label for="tcg-stock" class="tcg-form-label">
					<?php esc_html_e( 'Stock', 'tcg-manager' ); ?> <span class="required">*</span>
				</label>
				<input type="number" name="stock" id="tcg-stock" class="tcg-form-control" step="1" min="0" required>
			</div>

			<div class="tcg-form-actions">
				<button type="submit" class="tcg-btn tcg-btn-primary"><?php esc_html_e( 'Continuar', 'tcg-manager' ); ?> &rarr;</button>
			</div>
		</form>

	<?php elseif ( $step === 2 ) : ?>
		<!-- ─── Step 2: Payment info ─── -->
		<?php
		$pay_yape          = get_user_meta( $vendor_id, '_tcg_pay_yape', true );
		$pay_plin          = get_user_meta( $vendor_id, '_tcg_pay_plin', true );
		$pay_interbank     = get_user_meta( $vendor_id, '_tcg_pay_interbank', true );
		$pay_interbank_cci = get_user_meta( $vendor_id, '_tcg_pay_interbank_cci', true );
		$pay_bcp           = get_user_meta( $vendor_id, '_tcg_pay_bcp', true );
		$pay_bcp_cci       = get_user_meta( $vendor_id, '_tcg_pay_bcp_cci', true );
		$pay_bbva          = get_user_meta( $vendor_id, '_tcg_pay_bbva', true );
		$pay_bbva_cci      = get_user_meta( $vendor_id, '_tcg_pay_bbva_cci', true );
		?>
		<form method="post" class="tcg-product-form">
			<?php wp_nonce_field( 'tcg_onboarding_payment' ); ?>

			<p class="tcg-form-help"><?php esc_html_e( 'Esta información será visible solo para el administrador para realizarte los pagos.', 'tcg-manager' ); ?></p>

			<div class="tcg-payment-grid">
				<div class="tcg-form-group">
					<label class="tcg-form-label">Yape</label>
					<input type="text" name="pay_yape" class="tcg-form-control" value="<?php echo esc_attr( $pay_yape ); ?>" placeholder="<?php esc_attr_e( 'Número', 'tcg-manager' ); ?>">
				</div>
				<div class="tcg-form-group">
					<label class="tcg-form-label">Plin</label>
					<input type="text" name="pay_plin" class="tcg-form-control" value="<?php echo esc_attr( $pay_plin ); ?>" placeholder="<?php esc_attr_e( 'Número', 'tcg-manager' ); ?>">
				</div>
			</div>

			<div class="tcg-payment-bank">
				<label class="tcg-form-label">Interbank</label>
				<div class="tcg-payment-bank-fields">
					<input type="text" name="pay_interbank" class="tcg-form-control" value="<?php echo esc_attr( $pay_interbank ); ?>" placeholder="<?php esc_attr_e( 'N° de cuenta', 'tcg-manager' ); ?>">
					<input type="text" name="pay_interbank_cci" class="tcg-form-control" value="<?php echo esc_attr( $pay_interbank_cci ); ?>" placeholder="CCI">
				</div>
			</div>

			<div class="tcg-payment-bank">
				<label class="tcg-form-label">BCP</label>
				<div class="tcg-payment-bank-fields">
					<input type="text" name="pay_bcp" class="tcg-form-control" value="<?php echo esc_attr( $pay_bcp ); ?>" placeholder="<?php esc_attr_e( 'N° de cuenta', 'tcg-manager' ); ?>">
					<input type="text" name="pay_bcp_cci" class="tcg-form-control" value="<?php echo esc_attr( $pay_bcp_cci ); ?>" placeholder="CCI">
				</div>
			</div>

			<div class="tcg-payment-bank">
				<label class="tcg-form-label">BBVA</label>
				<div class="tcg-payment-bank-fields">
					<input type="text" name="pay_bbva" class="tcg-form-control" value="<?php echo esc_attr( $pay_bbva ); ?>" placeholder="<?php esc_attr_e( 'N° de cuenta', 'tcg-manager' ); ?>">
					<input type="text" name="pay_bbva_cci" class="tcg-form-control" value="<?php echo esc_attr( $pay_bbva_cci ); ?>" placeholder="CCI">
				</div>
			</div>

			<div class="tcg-form-actions">
				<button type="submit" name="tcg_onboarding_payment" value="1" class="tcg-btn tcg-btn-primary"><?php esc_html_e( 'Continuar', 'tcg-manager' ); ?> &rarr;</button>
			</div>
		</form>

	<?php else : ?>
		<!-- ─── Step 3: Shipping ─── -->
		<?php
		$ship_lima_price = get_user_meta( $vendor_id, '_tcg_shipping_lima_price', true );
		$ship_lima_min   = get_user_meta( $vendor_id, '_tcg_shipping_lima_days_min', true );
		$ship_lima_max   = get_user_meta( $vendor_id, '_tcg_shipping_lima_days_max', true );
		$ship_prov_price = get_user_meta( $vendor_id, '_tcg_shipping_provincia_price', true );
		$ship_prov_min   = get_user_meta( $vendor_id, '_tcg_shipping_provincia_days_min', true );
		$ship_prov_max   = get_user_meta( $vendor_id, '_tcg_shipping_provincia_days_max', true );
		?>
		<form method="post" class="tcg-product-form">
			<?php wp_nonce_field( 'tcg_onboarding_shipping' ); ?>

			<div class="tcg-shipping-zone">
				<label class="tcg-form-label"><?php esc_html_e( 'Lima Metropolitana', 'tcg-manager' ); ?></label>
				<div class="tcg-shipping-fields">
					<div class="tcg-shipping-field">
						<span class="tcg-shipping-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
						<input type="number" name="shipping_lima_price" class="tcg-form-control" step="0.50" min="0"
							   value="<?php echo esc_attr( $ship_lima_price ); ?>" placeholder="0.00">
					</div>
					<div class="tcg-shipping-field tcg-shipping-days">
						<input type="number" name="shipping_lima_days_min" class="tcg-form-control" min="1" max="30"
							   value="<?php echo esc_attr( $ship_lima_min ); ?>" placeholder="1">
						<span>a</span>
						<input type="number" name="shipping_lima_days_max" class="tcg-form-control" min="1" max="30"
							   value="<?php echo esc_attr( $ship_lima_max ); ?>" placeholder="3">
						<span><?php esc_html_e( 'días', 'tcg-manager' ); ?></span>
					</div>
				</div>
			</div>

			<div class="tcg-shipping-zone">
				<label class="tcg-form-label"><?php esc_html_e( 'Provincia', 'tcg-manager' ); ?></label>
				<div class="tcg-shipping-fields">
					<div class="tcg-shipping-field">
						<span class="tcg-shipping-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
						<input type="number" name="shipping_provincia_price" class="tcg-form-control" step="0.50" min="0"
							   value="<?php echo esc_attr( $ship_prov_price ); ?>" placeholder="0.00">
					</div>
					<div class="tcg-shipping-field tcg-shipping-days">
						<input type="number" name="shipping_provincia_days_min" class="tcg-form-control" min="1" max="30"
							   value="<?php echo esc_attr( $ship_prov_min ); ?>" placeholder="3">
						<span>a</span>
						<input type="number" name="shipping_provincia_days_max" class="tcg-form-control" min="1" max="30"
							   value="<?php echo esc_attr( $ship_prov_max ); ?>" placeholder="7">
						<span><?php esc_html_e( 'días', 'tcg-manager' ); ?></span>
					</div>
				</div>
			</div>

			<div class="tcg-form-actions">
				<button type="submit" name="tcg_onboarding_shipping" value="1" class="tcg-btn tcg-btn-primary"><?php esc_html_e( 'Finalizar', 'tcg-manager' ); ?> &#10003;</button>
			</div>
		</form>
	<?php endif; ?>
</div>
