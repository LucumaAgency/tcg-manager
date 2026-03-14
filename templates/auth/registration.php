<?php
defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) {
	echo '<div class="tcg-alert tcg-alert-error">' . esc_html__( 'Ya tienes una cuenta.', 'tcg-manager' ) . '</div>';
	return;
}

// Collect validation errors for display (form was NOT processed by template_redirect if we're still here).
$errors = [];
if ( isset( $_POST['tcg_register_vendor'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'tcg_vendor_register' ) ) {
	$username  = sanitize_user( $_POST['username'] ?? '' );
	$email     = sanitize_email( $_POST['email'] ?? '' );
	$password  = $_POST['password'] ?? '';
	$shop_name = sanitize_text_field( $_POST['shop_name'] ?? '' );

	if ( ! $username ) $errors[] = __( 'El nombre de usuario es obligatorio.', 'tcg-manager' );
	if ( ! $email )    $errors[] = __( 'El email es obligatorio.', 'tcg-manager' );
	if ( ! $password ) $errors[] = __( 'La contraseña es obligatoria.', 'tcg-manager' );
	if ( ! $shop_name ) $errors[] = __( 'El nombre de tienda es obligatorio.', 'tcg-manager' );
	if ( $password && strlen( $password ) < 6 ) $errors[] = __( 'La contraseña debe tener al menos 6 caracteres.', 'tcg-manager' );
	if ( $username && username_exists( $username ) ) $errors[] = __( 'Este nombre de usuario ya existe.', 'tcg-manager' );
	if ( $email && email_exists( $email ) ) $errors[] = __( 'Este email ya está registrado.', 'tcg-manager' );
}
?>

<div class="tcg-registration-form">
	<h2><?php esc_html_e( 'Registrarse como vendedor', 'tcg-manager' ); ?></h2>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="tcg-alert tcg-alert-error">
			<?php foreach ( $errors as $error ) : ?>
				<p style="margin:0 0 4px;"><?php echo esc_html( $error ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<form method="post" class="tcg-product-form">
		<?php wp_nonce_field( 'tcg_vendor_register' ); ?>

		<div class="tcg-form-group">
			<label for="tcg-reg-username" class="tcg-form-label">
				<?php esc_html_e( 'Nombre de usuario', 'tcg-manager' ); ?> <span class="required">*</span>
			</label>
			<input type="text" name="username" id="tcg-reg-username" class="tcg-form-control"
				   value="<?php echo esc_attr( $_POST['username'] ?? '' ); ?>" required>
		</div>

		<div class="tcg-form-group">
			<label for="tcg-reg-email" class="tcg-form-label">
				<?php esc_html_e( 'Email', 'tcg-manager' ); ?> <span class="required">*</span>
			</label>
			<input type="email" name="email" id="tcg-reg-email" class="tcg-form-control"
				   value="<?php echo esc_attr( $_POST['email'] ?? '' ); ?>" required>
		</div>

		<div class="tcg-form-group">
			<label for="tcg-reg-password" class="tcg-form-label">
				<?php esc_html_e( 'Contraseña', 'tcg-manager' ); ?> <span class="required">*</span>
			</label>
			<input type="password" name="password" id="tcg-reg-password" class="tcg-form-control" required minlength="6">
		</div>

		<div class="tcg-form-group">
			<label for="tcg-reg-shop" class="tcg-form-label">
				<?php esc_html_e( 'Nombre de tu tienda', 'tcg-manager' ); ?> <span class="required">*</span>
			</label>
			<input type="text" name="shop_name" id="tcg-reg-shop" class="tcg-form-control"
				   value="<?php echo esc_attr( $_POST['shop_name'] ?? '' ); ?>" required>
		</div>

		<div class="tcg-form-actions">
			<button type="submit" name="tcg_register_vendor" value="1" class="tcg-btn tcg-btn-primary">
				<?php esc_html_e( 'Crear cuenta', 'tcg-manager' ); ?>
			</button>
		</div>

		<p style="margin-top:15px;text-align:center;">
			<?php printf(
				esc_html__( '¿Ya tienes cuenta? %s', 'tcg-manager' ),
				'<a href="' . esc_url( wp_login_url( TCG_Dashboard::get_dashboard_url() ) ) . '">' . esc_html__( 'Inicia sesión', 'tcg-manager' ) . '</a>'
			); ?>
		</p>
	</form>
</div>
