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

	// Handle logo upload.
	if ( ! empty( $_FILES['shop_logo']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$attach_id = media_handle_upload( 'shop_logo', 0 );
		if ( ! is_wp_error( $attach_id ) ) {
			update_user_meta( $vendor_id, '_tcg_shop_logo_id', $attach_id );
		}
	}

	wp_safe_redirect( add_query_arg( 'tcg_msg', 'profile_saved', TCG_Dashboard::get_dashboard_url( 'profile' ) ) );
	exit;
}

$shop_name = get_user_meta( $vendor_id, '_tcg_shop_name', true );
$shop_desc = get_user_meta( $vendor_id, '_tcg_shop_description', true );
$payment   = get_user_meta( $vendor_id, '_tcg_payment_info', true );
$logo_id   = get_user_meta( $vendor_id, '_tcg_shop_logo_id', true );
$logo_url  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
?>

<h2><?php esc_html_e( 'Mi Perfil', 'tcg-manager' ); ?></h2>

<form method="post" enctype="multipart/form-data" class="tcg-product-form">
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
		<label for="tcg-shop-logo" class="tcg-form-label">
			<?php esc_html_e( 'Logo de tienda', 'tcg-manager' ); ?>
		</label>
		<?php if ( $logo_url ) : ?>
			<div style="margin-bottom:8px;">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
			</div>
		<?php endif; ?>
		<input type="file" name="shop_logo" id="tcg-shop-logo" accept="image/*">
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

	<div class="tcg-form-actions">
		<button type="submit" name="tcg_save_profile" value="1" class="tcg-btn tcg-btn-primary">
			<?php esc_html_e( 'Guardar perfil', 'tcg-manager' ); ?>
		</button>
	</div>
</form>
