<?php
defined( 'ABSPATH' ) || exit;
$vendor_id = get_current_user_id();
$user      = get_userdata( $vendor_id );

// Handle form submission.
if ( isset( $_POST['tcg_save_profile'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_profile_save' ) ) {
	$shop_name = sanitize_text_field( $_POST['shop_name'] ?? '' );
	$shop_slug = sanitize_title( $shop_name );
	$shop_desc = sanitize_textarea_field( $_POST['shop_description'] ?? '' );
	update_user_meta( $vendor_id, '_tcg_shop_name', $shop_name );
	update_user_meta( $vendor_id, '_tcg_shop_slug', $shop_slug );
	update_user_meta( $vendor_id, '_tcg_shop_description', $shop_desc );

	// Payment fields.
	$payment_fields = [
		'_tcg_pay_yape', '_tcg_pay_plin',
		'_tcg_pay_interbank', '_tcg_pay_interbank_cci',
		'_tcg_pay_bcp', '_tcg_pay_bcp_cci',
		'_tcg_pay_bbva', '_tcg_pay_bbva_cci',
	];
	foreach ( $payment_fields as $field ) {
		$key = str_replace( '_tcg_', '', $field );
		update_user_meta( $vendor_id, $field, sanitize_text_field( $_POST[ $key ] ?? '' ) );
	}

	wp_safe_redirect( add_query_arg( 'tcg_msg', 'profile_saved', TCG_Dashboard::get_dashboard_url( 'profile' ) ) );
	exit;
}

$shop_name = get_user_meta( $vendor_id, '_tcg_shop_name', true );
$shop_desc = get_user_meta( $vendor_id, '_tcg_shop_description', true );

// Payment.
$pay_yape          = get_user_meta( $vendor_id, '_tcg_pay_yape', true );
$pay_plin          = get_user_meta( $vendor_id, '_tcg_pay_plin', true );
$pay_interbank     = get_user_meta( $vendor_id, '_tcg_pay_interbank', true );
$pay_interbank_cci = get_user_meta( $vendor_id, '_tcg_pay_interbank_cci', true );
$pay_bcp           = get_user_meta( $vendor_id, '_tcg_pay_bcp', true );
$pay_bcp_cci       = get_user_meta( $vendor_id, '_tcg_pay_bcp_cci', true );
$pay_bbva          = get_user_meta( $vendor_id, '_tcg_pay_bbva', true );
$pay_bbva_cci      = get_user_meta( $vendor_id, '_tcg_pay_bbva_cci', true );
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

	<h3 style="margin-top:32px;"><?php esc_html_e( 'Información de pago', 'tcg-manager' ); ?></h3>
	<p class="tcg-form-help" style="margin-bottom:16px;"><?php esc_html_e( 'Esta información será visible solo para el administrador.', 'tcg-manager' ); ?></p>

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

	<div class="tcg-form-actions">
		<button type="submit" name="tcg_save_profile" value="1" class="tcg-btn tcg-btn-primary">
			<?php esc_html_e( 'Guardar perfil', 'tcg-manager' ); ?>
		</button>
	</div>
</form>
