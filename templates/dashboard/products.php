<?php
defined( 'ABSPATH' ) || exit;
$vendor_id    = get_current_user_id();
$paged        = max( 1, absint( get_query_var( 'paged', $_GET['paged'] ?? 1 ) ) );
$search_term  = isset( $_GET['tcg_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tcg_search'] ) ) : '';

$query_args = [
	'post_type'      => 'product',
	'post_status'    => [ 'publish', 'draft', 'pending' ],
	'author'         => $vendor_id,
	'posts_per_page' => 20,
	'paged'          => $paged,
	'meta_query'     => [ [ 'key' => '_linked_ygo_card', 'compare' => 'EXISTS' ] ],
];

if ( $search_term ) {
	$query_args['s'] = $search_term;
}

$query = new WP_Query( $query_args );
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
	<h2 style="margin:0;"><?php esc_html_e( 'Mis Productos', 'tcg-manager' ); ?></h2>
	<a href="<?php echo esc_url( TCG_Dashboard::get_dashboard_url( 'new-product' ) ); ?>" class="tcg-btn tcg-btn-primary">
		+ <?php esc_html_e( 'Nuevo Producto', 'tcg-manager' ); ?>
	</a>
</div>

<!-- Product search -->
<div class="tcg-product-search" style="position:relative;margin-bottom:20px;">
	<input
		type="text"
		id="tcg-product-search-input"
		class="tcg-form-control"
		placeholder="<?php esc_attr_e( 'Buscar en mis productos...', 'tcg-manager' ); ?>"
		value="<?php echo esc_attr( $search_term ); ?>"
		autocomplete="off"
	>
	<div id="tcg-product-search-dropdown" class="tcg-live-search-dropdown" style="display:none;"></div>
</div>

<?php if ( ! $query->have_posts() ) : ?>
	<p><?php esc_html_e( 'No tienes productos aún.', 'tcg-manager' ); ?></p>
<?php else : ?>
<table class="tcg-table tcg-table-products">
	<thead><tr>
		<th><?php esc_html_e( 'Imagen', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Nombre', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Precio', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Stock', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Estado', 'tcg-manager' ); ?></th>
		<th><?php esc_html_e( 'Acciones', 'tcg-manager' ); ?></th>
	</tr></thead>
	<tbody>
	<?php while ( $query->have_posts() ) : $query->the_post();
		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) continue;
		$thumb = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' );
		?>
		<tr>
			<td data-label="<?php esc_attr_e( 'Imagen', 'tcg-manager' ); ?>"><?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" class="tcg-product-thumb"><?php endif; ?></td>
			<td data-label="<?php esc_attr_e( 'Nombre', 'tcg-manager' ); ?>"><?php echo esc_html( get_the_title() ); ?></td>
			<td data-label="<?php esc_attr_e( 'Precio', 'tcg-manager' ); ?>"><?php echo wp_kses_post( $product->get_price_html() ); ?></td>
			<td data-label="<?php esc_attr_e( 'Stock', 'tcg-manager' ); ?>"><?php echo esc_html( $product->get_stock_quantity() ?? '—' ); ?></td>
			<td data-label="<?php esc_attr_e( 'Estado', 'tcg-manager' ); ?>"><span class="tcg-badge tcg-badge-<?php echo esc_attr( get_post_status() ); ?>"><?php echo esc_html( ucfirst( get_post_status() ) ); ?></span></td>
			<td data-label="<?php esc_attr_e( 'Acciones', 'tcg-manager' ); ?>">
				<div class="tcg-actions">
					<a href="<?php echo esc_url( TCG_Dashboard::get_dashboard_url( 'edit-product', [ 'tcg-id' => get_the_ID() ] ) ); ?>" class="tcg-btn tcg-btn-secondary tcg-btn-sm">
						<?php esc_html_e( 'Editar', 'tcg-manager' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'tcg_action' => 'delete_product', 'product_id' => get_the_ID() ] ), 'tcg_delete_' . get_the_ID() ) ); ?>"
					   class="tcg-btn tcg-btn-danger tcg-btn-sm tcg-delete-product">
						<?php esc_html_e( 'Eliminar', 'tcg-manager' ); ?>
					</a>
				</div>
			</td>
		</tr>
	<?php endwhile; wp_reset_postdata(); ?>
	</tbody>
</table>

<?php if ( $query->max_num_pages > 1 ) : ?>
	<div class="tcg-pagination">
		<?php
		$pagination_base = trailingslashit( TCG_Dashboard::get_dashboard_url( 'products' ) ) . 'page/%#%/';
		if ( $search_term ) {
			$pagination_base = add_query_arg( 'tcg_search', rawurlencode( $search_term ), $pagination_base );
		}
		echo paginate_links( [
			'base'    => $pagination_base,
			'format'  => '',
			'current' => $paged,
			'total'   => $query->max_num_pages,
		] ); ?>
	</div>
<?php endif; endif; ?>

<?php
// Preload all vendor products for client-side search.
$all_products = new WP_Query( [
	'post_type'      => 'product',
	'post_status'    => [ 'publish', 'draft', 'pending' ],
	'author'         => $vendor_id,
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'meta_query'     => [ [ 'key' => '_linked_ygo_card', 'compare' => 'EXISTS' ] ],
] );

$products_data = [];
foreach ( $all_products->posts as $pid ) {
	$product = wc_get_product( $pid );
	if ( ! $product ) continue;
	$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' );
	$products_data[] = [
		'id'     => $pid,
		'title'  => get_the_title( $pid ),
		'thumb'  => $thumb ?: '',
		'price'  => $product->get_price() ? html_entity_decode( strip_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ) : '',
		'status' => get_post_status( $pid ),
		'url'    => TCG_Dashboard::get_dashboard_url( 'edit-product', [ 'tcg-id' => $pid ] ),
	];
}
wp_reset_postdata();
?>
<script>
(function() {
	'use strict';

	var products = <?php echo wp_json_encode( $products_data ); ?>;
	var input    = document.getElementById('tcg-product-search-input');
	var dropdown = document.getElementById('tcg-product-search-dropdown');
	if (!input || !dropdown || !products.length) return;

	var minChars     = 2;
	var maxResults   = 8;
	var debounceMs   = 200;
	var debounceTimer = null;
	var activeIndex  = -1;

	var statusLabels = {
		publish: 'Publicado',
		draft:   'Borrador',
		pending: 'Pendiente'
	};

	input.addEventListener('input', function() {
		clearTimeout(debounceTimer);
		activeIndex = -1;
		var term = input.value.trim().toLowerCase();
		if (term.length < minChars) {
			hideDropdown();
			return;
		}
		debounceTimer = setTimeout(function() {
			render(search(term), term);
		}, debounceMs);
	});

	input.addEventListener('keydown', function(e) {
		var items = dropdown.querySelectorAll('.tcg-live-search-item');
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			activeIndex = Math.min(activeIndex + 1, items.length - 1);
			updateActive(items);
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			activeIndex = Math.max(activeIndex - 1, 0);
			updateActive(items);
		} else if (e.key === 'Enter') {
			e.preventDefault();
			if (activeIndex >= 0 && items[activeIndex]) {
				var link = items[activeIndex].querySelector('a');
				if (link) window.location.href = link.href;
			}
		} else if (e.key === 'Escape') {
			hideDropdown();
			input.blur();
		}
	});

	document.addEventListener('click', function(e) {
		if (!input.parentNode.contains(e.target)) {
			hideDropdown();
		}
	});

	function search(term) {
		var matches = [];
		for (var i = 0; i < products.length && matches.length < maxResults; i++) {
			if (products[i].title.toLowerCase().indexOf(term) !== -1) {
				matches.push(products[i]);
			}
		}
		return matches;
	}

	function render(results, term) {
		if (!results.length) {
			dropdown.innerHTML = '<div class="tcg-live-search-empty">No se encontraron productos</div>';
			dropdown.style.display = 'block';
			return;
		}

		var html = '';
		for (var i = 0; i < results.length; i++) {
			var p = results[i];
			var highlighted = highlightMatch(p.title, term);
			var badge = statusLabels[p.status] || p.status;

			html += '<div class="tcg-live-search-item" data-index="' + i + '">';
			html += '<a href="' + escHtml(p.url) + '">';
			if (p.thumb) {
				html += '<img src="' + escHtml(p.thumb) + '" alt="" class="tcg-live-search-thumb" loading="lazy">';
			}
			html += '<div class="tcg-live-search-info">';
			html += '<span class="tcg-live-search-title">' + highlighted + '</span>';
			html += '<span class="tcg-live-search-meta">';
			if (p.price) html += escHtml(p.price) + ' · ';
			html += escHtml(badge);
			html += '</span>';
			html += '</div>';
			html += '</a></div>';
		}

		dropdown.innerHTML = html;
		dropdown.style.display = 'block';
		activeIndex = -1;
	}

	function highlightMatch(text, term) {
		var idx = text.toLowerCase().indexOf(term);
		if (idx === -1) return escHtml(text);
		return escHtml(text.substring(0, idx))
			+ '<mark>' + escHtml(text.substring(idx, idx + term.length)) + '</mark>'
			+ escHtml(text.substring(idx + term.length));
	}

	function updateActive(items) {
		for (var i = 0; i < items.length; i++) {
			items[i].classList.toggle('tcg-live-search-active', i === activeIndex);
		}
		if (items[activeIndex]) {
			items[activeIndex].scrollIntoView({ block: 'nearest' });
		}
	}

	function hideDropdown() {
		dropdown.style.display = 'none';
		dropdown.innerHTML = '';
		activeIndex = -1;
	}

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}
})();
</script>
