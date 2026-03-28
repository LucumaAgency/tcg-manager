<?php
defined( 'ABSPATH' ) || exit;

$vendor_id = get_current_user_id();

// Get all sets that have cards.
$sets = get_terms( [
	'taxonomy'   => 'ygo_set',
	'hide_empty' => true,
	'orderby'    => 'name',
	'order'      => 'ASC',
	'fields'     => 'all',
] );

if ( is_wp_error( $sets ) ) {
	$sets = [];
}

// Get card IDs this vendor already has products for.
global $wpdb;
$existing_card_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT DISTINCT pm.meta_value
	 FROM {$wpdb->posts} p
	 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_linked_ygo_card'
	 WHERE p.post_type = 'product' AND p.post_status IN ('publish','draft','pending') AND p.post_author = %d",
	$vendor_id
) );
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
	<h2 style="margin:0;"><?php esc_html_e( 'Agregar por Set', 'tcg-manager' ); ?></h2>
</div>

<p class="tcg-form-help" style="margin-bottom:16px;">
	<?php esc_html_e( 'Selecciona un set y elige las cartas que quieres agregar. Se crearán como borradores para que les asignes precio, stock y condición antes de publicar.', 'tcg-manager' ); ?>
</p>

<form method="post" id="tcg-bulk-add-form">
	<?php wp_nonce_field( 'tcg_bulk_add', 'tcg_bulk_nonce' ); ?>
	<input type="hidden" name="tcg_action" value="bulk_add">

	<!-- Set selector -->
	<div class="tcg-form-group">
		<label for="tcg-set-select" class="tcg-form-label">
			<?php esc_html_e( 'Set', 'tcg-manager' ); ?>
		</label>
		<select id="tcg-set-select" class="tcg-form-control" style="max-width:400px;">
			<option value=""><?php esc_html_e( '— Seleccionar set —', 'tcg-manager' ); ?></option>
			<?php foreach ( $sets as $set ) : ?>
				<option value="<?php echo esc_attr( $set->term_id ); ?>">
					<?php echo esc_html( $set->name ); ?> (<?php echo esc_html( $set->count ); ?>)
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<!-- Loading -->
	<div id="tcg-bulk-loading" style="display:none;padding:20px;text-align:center;color:#666;">
		<?php esc_html_e( 'Cargando cartas...', 'tcg-manager' ); ?>
	</div>

	<!-- Card grid -->
	<div id="tcg-bulk-grid" class="tcg-bulk-grid" style="display:none;"></div>

	<!-- Actions bar -->
	<div id="tcg-bulk-actions" class="tcg-bulk-actions" style="display:none;">
		<label class="tcg-bulk-select-all">
			<input type="checkbox" id="tcg-bulk-select-all">
			<?php esc_html_e( 'Seleccionar todas', 'tcg-manager' ); ?>
		</label>
		<span id="tcg-bulk-count">0 <?php esc_html_e( 'seleccionadas', 'tcg-manager' ); ?></span>
		<button type="submit" class="tcg-btn tcg-btn-primary" id="tcg-bulk-submit" disabled>
			<?php esc_html_e( 'Crear borradores', 'tcg-manager' ); ?>
		</button>
	</div>
</form>

<script>
(function($) {
	var existingCardIds = <?php echo wp_json_encode( array_map( 'intval', $existing_card_ids ) ); ?>;
	var $grid     = $('#tcg-bulk-grid');
	var $loading  = $('#tcg-bulk-loading');
	var $actions  = $('#tcg-bulk-actions');
	var $count    = $('#tcg-bulk-count');
	var $submit   = $('#tcg-bulk-submit');
	var $selectAll = $('#tcg-bulk-select-all');

	$('#tcg-set-select').on('change', function() {
		var termId = $(this).val();
		$grid.hide().empty();
		$actions.hide();
		$selectAll.prop('checked', false);

		if (!termId) return;

		$loading.show();

		$.ajax({
			url: tcgDashboard.ajaxUrl,
			data: {
				action: 'tcg_get_cards_by_set',
				nonce: tcgDashboard.bulkNonce,
				term_id: termId
			},
			success: function(res) {
				$loading.hide();
				if (!res.success || !res.data.length) {
					$grid.html('<p style="color:#666;"><?php echo esc_js( __( 'No se encontraron cartas en este set.', 'tcg-manager' ) ); ?></p>').show();
					return;
				}

				var html = '';
				$.each(res.data, function(i, card) {
					var exists = existingCardIds.indexOf(card.id) !== -1;
					var cls = 'tcg-bulk-card' + (exists ? ' tcg-bulk-card--exists' : '');
					html += '<label class="' + cls + '">';
					if (!exists) {
						html += '<input type="checkbox" name="card_ids[]" value="' + card.id + '" class="tcg-bulk-checkbox">';
					}
					html += '<div class="tcg-bulk-card-img">';
					if (card.thumb) {
						html += '<img src="' + card.thumb + '" alt="" loading="lazy">';
					}
					html += '</div>';
					html += '<div class="tcg-bulk-card-info">';
					html += '<span class="tcg-bulk-card-title">' + card.title + '</span>';
					if (card.set_code) {
						html += '<span class="tcg-bulk-card-code">' + card.set_code + '</span>';
					}
					if (exists) {
						html += '<span class="tcg-badge tcg-badge-draft" style="font-size:11px;"><?php echo esc_js( __( 'Ya agregada', 'tcg-manager' ) ); ?></span>';
					}
					html += '</div></label>';
				});

				$grid.html(html).show();
				$actions.show();
				updateCount();
			},
			error: function() {
				$loading.hide();
				$grid.html('<p class="tcg-alert tcg-alert-error"><?php echo esc_js( __( 'Error al cargar cartas.', 'tcg-manager' ) ); ?></p>').show();
			}
		});
	});

	// Select all (only non-existing).
	$selectAll.on('change', function() {
		$grid.find('.tcg-bulk-checkbox').prop('checked', this.checked);
		updateCount();
	});

	// Individual checkbox.
	$grid.on('change', '.tcg-bulk-checkbox', function() {
		updateCount();
	});

	function updateCount() {
		var checked = $grid.find('.tcg-bulk-checkbox:checked').length;
		$count.text(checked + ' <?php echo esc_js( __( 'seleccionadas', 'tcg-manager' ) ); ?>');
		$submit.prop('disabled', checked === 0);
	}

	// Submit confirmation.
	$('#tcg-bulk-add-form').on('submit', function(e) {
		var checked = $grid.find('.tcg-bulk-checkbox:checked').length;
		if (checked === 0) {
			e.preventDefault();
			return;
		}
		if (!confirm('<?php echo esc_js( sprintf( __( 'Se crearán borradores para las cartas seleccionadas. ¿Continuar?', 'tcg-manager' ) ) ); ?>')) {
			e.preventDefault();
		}
	});
})(jQuery);
</script>
