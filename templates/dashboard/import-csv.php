<?php
defined( 'ABSPATH' ) || exit;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
	<h2 style="margin:0;"><?php esc_html_e( 'Importar desde CSV', 'tcg-manager' ); ?></h2>
</div>

<p class="tcg-form-help" style="margin-bottom:16px;font-size:14px;">
	<?php esc_html_e( 'Sube un archivo CSV o TSV exportado desde tu hoja de calculo. Se crearan borradores con stock, condicion, rareza y printing. Luego asigna el precio a cada uno para publicar.', 'tcg-manager' ); ?>
</p>

<div style="margin-bottom:16px;padding:14px;background:#f8f9fa;border-radius:6px;font-size:13px;line-height:1.6;color:#555;">
	<strong><?php esc_html_e( 'Formato esperado (columnas):', 'tcg-manager' ); ?></strong><br>
	<code>Product Name | Number | Rarity | Condition | Price | Quantity | Printing | Language</code><br><br>
	<strong><?php esc_html_e( 'Ejemplo:', 'tcg-manager' ); ?></strong><br>
	<code>Pre-Preparation of Rites, L26D-ENM11, Common, Near Mint, 5.00, 3, 1st Edition, English</code><br><br>
	<?php esc_html_e( 'Si falta algun dato se crea como borrador, si esta completo se publica. "Short Print" se filtra de la rareza.', 'tcg-manager' ); ?>
</div>

<form method="post" id="tcg-csv-import-form" enctype="multipart/form-data">
	<?php wp_nonce_field( 'tcg_csv_import', 'tcg_csv_nonce' ); ?>
	<input type="hidden" name="tcg_action" value="csv_import">

	<div class="tcg-form-group">
		<label for="tcg-csv-file" class="tcg-form-label">
			<?php esc_html_e( 'Archivo CSV / TSV', 'tcg-manager' ); ?> <span class="required">*</span>
		</label>
		<input
			type="file"
			name="csv_file"
			id="tcg-csv-file"
			class="tcg-form-control"
			accept=".csv,.tsv,.txt"
			required
		>
	</div>

	<div id="tcg-csv-preview" style="display:none;margin-bottom:20px;">
		<h3 style="margin-bottom:10px;"><?php esc_html_e( 'Vista previa', 'tcg-manager' ); ?> (<span id="tcg-csv-count">0</span> <?php esc_html_e( 'filas', 'tcg-manager' ); ?>)</h3>
		<div class="tcg-table-responsive">
			<table class="tcg-table" id="tcg-csv-table">
				<thead><tr>
					<th><?php esc_html_e( 'Carta', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Codigo', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Rareza', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Condicion', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Precio', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Stock', 'tcg-manager' ); ?></th>
					<th>Printing</th>
					<th><?php esc_html_e( 'Idioma', 'tcg-manager' ); ?></th>
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
	var fileInput = document.getElementById('tcg-csv-file');
	var preview   = document.getElementById('tcg-csv-preview');
	var tbody     = document.querySelector('#tcg-csv-table tbody');
	var countEl   = document.getElementById('tcg-csv-count');
	var submitBtn = document.getElementById('tcg-csv-submit');

	fileInput.addEventListener('change', function() {
		var file = this.files[0];
		if (!file) {
			preview.style.display = 'none';
			submitBtn.disabled = true;
			return;
		}

		var reader = new FileReader();
		reader.onload = function(e) {
			parsePreview(e.target.result);
		};
		reader.readAsText(file);
	});

	function detectSeparator(line) {
		if (line.indexOf('\t') !== -1) return '\t';
		if (line.indexOf(';') !== -1) return ';';
		return ',';
	}

	function parsePreview(raw) {
		var lines = raw.split('\n').filter(function(l) { return l.trim(); });
		if (!lines.length) {
			preview.style.display = 'none';
			submitBtn.disabled = true;
			return;
		}

		var sep = detectSeparator(lines[0]);
		var rows = [];

		for (var i = 0; i < lines.length; i++) {
			var cols = lines[i].split(sep);
			if (cols.length < 2) continue;

			var name = cols[0] ? cols[0].trim() : '';
			var code = cols[1] ? cols[1].trim() : '';

			if (name.toLowerCase() === 'product name' || name.toLowerCase() === 'nombre') continue;
			if (!name && !code) continue;

			rows.push({
				name:      name,
				code:      code,
				rarity:    cols[2] ? cols[2].trim() : '',
				condition: cols[3] ? cols[3].trim() : '',
				price:     cols[4] ? cols[4].trim() : '',
				qty:       cols[5] ? cols[5].trim() : '',
				printing:  cols[6] ? cols[6].trim() : '',
				language:  cols[7] ? cols[7].trim() : ''
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
			html += '<td>' + esc(r.name) + '</td>';
			html += '<td><code>' + esc(r.code) + '</code></td>';
			html += '<td>' + esc(r.rarity) + '</td>';
			html += '<td>' + esc(r.condition) + '</td>';
			html += '<td>' + esc(r.price) + '</td>';
			html += '<td>' + esc(r.qty) + '</td>';
			html += '<td>' + esc(r.printing) + '</td>';
			html += '<td>' + esc(r.language) + '</td>';
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
