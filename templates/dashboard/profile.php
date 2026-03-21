<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();
$user      = get_userdata( $vendor_id );

// Handle form submission.
if ( isset( $_POST['tcg_save_profile'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_profile_save' ) ) {
	$shop_name = sanitize_text_field( $_POST['shop_name'] ?? '' );
	$shop_slug = sanitize_title( $shop_name );
	$shop_desc = sanitize_textarea_field( $_POST['shop_description'] ?? '' );
	$payment   = sanitize_textarea_field( $_POST['payment_info'] ?? '' );

	update_user_meta( $vendor_id, '_tcg_shop_name', $shop_name );
	update_user_meta( $vendor_id, '_tcg_shop_slug', $shop_slug );
	update_user_meta( $vendor_id, '_tcg_shop_description', $shop_desc );
	update_user_meta( $vendor_id, '_tcg_payment_info', $payment );

	// Shipping.
	$shipping_fields = [
		'_tcg_shipping_lima_price', '_tcg_shipping_lima_days_min', '_tcg_shipping_lima_days_max',
		'_tcg_shipping_provincia_price', '_tcg_shipping_provincia_days_min', '_tcg_shipping_provincia_days_max',
	];
	foreach ( $shipping_fields as $field ) {
		$key = str_replace( '_tcg_', '', $field );
		$val = sanitize_text_field( $_POST[ $key ] ?? '' );
		update_user_meta( $vendor_id, $field, $val );
	}

	wp_safe_redirect( add_query_arg( 'tcg_msg', 'profile_saved', TCG_Dashboard::get_dashboard_url( 'profile' ) ) );
	exit;
}

$shop_name = get_user_meta( $vendor_id, '_tcg_shop_name', true );
$shop_desc = get_user_meta( $vendor_id, '_tcg_shop_description', true );
$payment   = get_user_meta( $vendor_id, '_tcg_payment_info', true );

// Shipping.
$ship_lima_price    = get_user_meta( $vendor_id, '_tcg_shipping_lima_price', true );
$ship_lima_min      = get_user_meta( $vendor_id, '_tcg_shipping_lima_days_min', true );
$ship_lima_max      = get_user_meta( $vendor_id, '_tcg_shipping_lima_days_max', true );
$ship_prov_price    = get_user_meta( $vendor_id, '_tcg_shipping_provincia_price', true );
$ship_prov_min      = get_user_meta( $vendor_id, '_tcg_shipping_provincia_days_min', true );
$ship_prov_max      = get_user_meta( $vendor_id, '_tcg_shipping_provincia_days_max', true );
?>

<h2><?php esc_html_e( 'Mi Perfil', 'tcg-manager' ); ?></h2>

<form method="post" class="tcg-product-form">
	<?php wp_nonce_field( 'tcg_profile_save' ); ?>

	<div class="tcg-form-group">
		<label for="tcg-shop-name" class="tcg-form-label">
			<?php esc_html_e( 'Nombre de tienda', 'tcg-manager' ); ?> <span class="required">*</span>
		</label>
		<input type="text" name="shop_name" id="tcg-shop-name" class="tcg-form-control"
			   value="<?php echo esc_attr( $shop_name ); ?>" required>
	</div>

	<div class="tcg-form-group">
		<label for="tcg-shop-desc" class="tcg-form-label">
			<?php esc_html_e( 'Descripción', 'tcg-manager' ); ?>
		</label>
		<textarea name="shop_description" id="tcg-shop-desc" class="tcg-form-control" rows="4"><?php echo esc_textarea( $shop_desc ); ?></textarea>
	</div>

	<div class="tcg-form-group">
		<label for="tcg-payment" class="tcg-form-label">
			<?php esc_html_e( 'Información de pago', 'tcg-manager' ); ?>
		</label>
		<textarea name="payment_info" id="tcg-payment" class="tcg-form-control" rows="3"
				  placeholder="<?php esc_attr_e( 'Ej: PayPal, transferencia bancaria, etc.', 'tcg-manager' ); ?>"><?php echo esc_textarea( $payment ); ?></textarea>
		<p class="tcg-form-help"><?php esc_html_e( 'Esta información será visible solo para el administrador.', 'tcg-manager' ); ?></p>
	</div>

	<div class="tcg-form-group">
		<label class="tcg-form-label"><?php esc_html_e( 'Email', 'tcg-manager' ); ?></label>
		<p style="margin:0;padding:8px 0;color:#666;"><?php echo esc_html( $user->user_email ); ?></p>
	</div>

	<div class="tcg-form-group">
		<label class="tcg-form-label"><?php esc_html_e( 'URL de tienda', 'tcg-manager' ); ?></label>
		<p style="margin:0;padding:8px 0;">
			<?php
			$store_url = TCG_Vendor_Profile::get_store_url( $vendor_id );
			if ( $store_url ) {
				echo '<a href="' . esc_url( $store_url ) . '" target="_blank">' . esc_html( $store_url ) . '</a>';
			} else {
				esc_html_e( 'Configura tu nombre de tienda para generar la URL.', 'tcg-manager' );
			}
			?>
		</p>
	</div>

	<h3 style="margin-top:32px;"><?php esc_html_e( 'Tarifas de envío', 'tcg-manager' ); ?></h3>

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
		<button type="submit" name="tcg_save_profile" value="1" class="tcg-btn tcg-btn-primary">
			<?php esc_html_e( 'Guardar perfil', 'tcg-manager' ); ?>
		</button>
	</div>
</form>
