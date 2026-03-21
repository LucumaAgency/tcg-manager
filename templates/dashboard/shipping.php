<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();

// Handle form submission.
if ( isset( $_POST['tcg_save_shipping'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_shipping_save' ) ) {
	$shipping_fields = [
		'_tcg_shipping_lima_price', '_tcg_shipping_lima_days_min', '_tcg_shipping_lima_days_max',
		'_tcg_shipping_provincia_price', '_tcg_shipping_provincia_days_min', '_tcg_shipping_provincia_days_max',
	];
	foreach ( $shipping_fields as $field ) {
		$key = str_replace( '_tcg_', '', $field );
		$val = sanitize_text_field( $_POST[ $key ] ?? '' );
		update_user_meta( $vendor_id, $field, $val );
	}

	wp_safe_redirect( add_query_arg( 'tcg_msg', 'shipping_saved', TCG_Dashboard::get_dashboard_url( 'shipping' ) ) );
	exit;
}

$ship_lima_price = get_user_meta( $vendor_id, '_tcg_shipping_lima_price', true );
$ship_lima_min   = get_user_meta( $vendor_id, '_tcg_shipping_lima_days_min', true );
$ship_lima_max   = get_user_meta( $vendor_id, '_tcg_shipping_lima_days_max', true );
$ship_prov_price = get_user_meta( $vendor_id, '_tcg_shipping_provincia_price', true );
$ship_prov_min   = get_user_meta( $vendor_id, '_tcg_shipping_provincia_days_min', true );
$ship_prov_max   = get_user_meta( $vendor_id, '_tcg_shipping_provincia_days_max', true );
?>

<h2><?php esc_html_e( 'Tarifas de envío', 'tcg-manager' ); ?></h2>

<form method="post" class="tcg-product-form">
	<?php wp_nonce_field( 'tcg_shipping_save' ); ?>

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
		<button type="submit" name="tcg_save_shipping" value="1" class="tcg-btn tcg-btn-primary">
			<?php esc_html_e( 'Guardar tarifas', 'tcg-manager' ); ?>
		</button>
	</div>
</form>
