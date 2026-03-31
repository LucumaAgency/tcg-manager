<?php
defined( 'ABSPATH' ) || exit;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
	<h2 style="margin:0;"><?php esc_html_e( 'Importar desde CSV', 'tcg-manager' ); ?></h2>
</div>

<p class="tcg-form-help" style="margin-bottom:16px;font-size:14px;">
	<?php esc_html_e( 'Pega los datos de tu hoja de calculo (separados por tabulacion). Se crearan borradores con stock, condicion, rareza y printing. Luego asigna el precio a cada uno para publicar.', 'tcg-manager' ); ?>
</p>

<div style="margin-bottom:16px;padding:14px;background:#f8f9fa;border-radius:6px;font-size:13px;line-height:1.6;color:#555;">
	<strong><?php esc_html_e( 'Formato esperado (columnas separadas por Tab):', 'tcg-manager' ); ?></strong><br>
	<code>Set Name &nbsp; Product Name &nbsp; Number &nbsp; Rarity &nbsp; Condition &nbsp; Quantity &nbsp; Printing</code><br><br>
	<strong><?php esc_html_e( 'Ejemplo:', 'tcg-manager' ); ?></strong><br>
	<code>Legendary Modern Decks 2026 &nbsp; Pre-Preparation of Rites &nbsp; L26D-ENM11 &nbsp; Common / Short Print &nbsp; Near Mint &nbsp; 3 &nbsp; 1st Edition</code><br><br>
	<?php esc_html_e( 'La primera fila (encabezados) se ignora automaticamente. "Short Print" se filtra de la rareza.', 'tcg-manager' ); ?>
</div>

<form method="post" id="tcg-csv-import-form">
	<?php wp_nonce_field( 'tcg_csv_import', 'tcg_csv_nonce' ); ?>
	<input type="hidden" name="tcg_action" value="csv_import">

	<div class="tcg-form-group">
		<label for="tcg-csv-data" class="tcg-form-label">
			<?php esc_html_e( 'Datos (pegar desde hoja de calculo)', 'tcg-manager' ); ?> <span class="required">*</span>
		</label>
		<textarea
			name="csv_data"
			id="tcg-csv-data"
			class="tcg-form-control"
			rows="15"
			style="font-family:monospace;font-size:12px;"
			placeholder="<?php esc_attr_e( 'Pega aqui los datos de tu hoja de calculo...', 'tcg-manager' ); ?>"
			required
		></textarea>
	</div>

	<div id="tcg-csv-preview" style="display:none;margin-bottom:20px;">
		<h3 style="margin-bottom:10px;"><?php esc_html_e( 'Vista previa', 'tcg-manager' ); ?> (<span id="tcg-csv-count">0</span> <?php esc_html_e( 'filas', 'tcg-manager' ); ?>)</h3>
		<div class="tcg-table-responsive">
			<table class="tcg-table" id="tcg-csv-table">
				<thead><tr>
					<th>Set</th>
					<th><?php esc_html_e( 'Carta', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Codigo', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Rareza', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Condicion', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Stock', 'tcg-manager' ); ?></th>
					<th>Printing</th>
				</tr></thead>
				<tbody></tbody>
			</table>
		</div>
	</div>

	<div class="tcg-form-actions">
		<button type="submit" class="tcg-btn tcg-btn-primary" id="tcg-csv-submit" disabled>
			<?php esc_html_e( 'Importar como borradores', 'tcg-manager' ); ?>
		</button>
		<a href="<?php echo esc_url( TCG_Dashboard::get_dashboard_url( 'products' ) ); ?>" class="tcg-btn tcg-btn-secondary">
			<?php esc_html_e( 'Cancelar', 'tcg-manager' ); ?>
		</a>
	</div>
</form>

<script>
(function() {
	var textarea = document.getElementById('tcg-csv-data');
	var preview  = document.getElementById('tcg-csv-preview');
	var tbody    = document.querySelector('#tcg-csv-table tbody');
	var countEl  = document.getElementById('tcg-csv-count');
	var submitBtn = document.getElementById('tcg-csv-submit');
	var debounce = null;

	textarea.addEventListener('input', function() {
		clearTimeout(debounce);
		debounce = setTimeout(parsePreview, 300);
	});

	function parsePreview() {
		var raw = textarea.value.trim();
		if (!raw) {
			preview.style.display = 'none';
			submitBtn.disabled = true;
			return;
		}

		var lines = raw.split('\n').filter(function(l) { return l.trim(); });
		var rows = [];

		for (var i = 0; i < lines.length; i++) {
			var cols = lines[i].split('\t');
			if (cols.length < 3) continue;

			var setName = cols[0] ? cols[0].trim() : '';
			var name    = cols[1] ? cols[1].trim() : '';
			var code    = cols[2] ? cols[2].trim() : '';

			// Skip header.
			if (name.toLowerCase() === 'product name' || setName.toLowerCase() === 'set name') continue;
			if (!name || !code) continue;

			rows.push({
				set:       setName,
				name:      name,
				code:      code,
				rarity:    cols[3] ? cols[3].trim() : '',
				condition: cols[4] ? cols[4].trim() : '',
				qty:       cols[5] ? cols[5].trim() : '1',
				printing:  cols[6] ? cols[6].trim() : ''
			});
		}

		if (!rows.length) {
			preview.style.display = 'none';
			submitBtn.disabled = true;
			return;
		}

		var html = '';
		for (var j = 0; j < rows.length; j++) {
			var r = rows[j];
			html += '<tr>';
			html += '<td>' + esc(r.set) + '</td>';
			html += '<td>' + esc(r.name) + '</td>';
			html += '<td><code>' + esc(r.code) + '</code></td>';
			html += '<td>' + esc(r.rarity) + '</td>';
			html += '<td>' + esc(r.condition) + '</td>';
			html += '<td>' + esc(r.qty) + '</td>';
			html += '<td>' + esc(r.printing) + '</td>';
			html += '</tr>';
		}

		tbody.innerHTML = html;
		countEl.textContent = rows.length;
		preview.style.display = 'block';
		submitBtn.disabled = false;
	}

	function esc(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}
})();
</script>
