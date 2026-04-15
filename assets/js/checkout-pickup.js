(function ($) {
	'use strict';

	// Selectores de los campos de dirección (tanto billing como shipping).
	var ADDRESS_SELECTORS = [
		'#billing_country_field',
		'#billing_address_1_field',
		'#billing_address_2_field',
		'#billing_city_field',
		'#billing_state_field',
		'#billing_postcode_field',
		'.woocommerce-shipping-fields',
		'.shipping_address'
	].join(',');

	function renderDetails() {
		var $sel = $('#tcg_pickup_store_id');
		var $box = $('#tcg-pickup-details');
		if (!$sel.length) return;
		var $opt = $sel.find('option:selected');

		if (!$sel.val()) {
			$box.hide().empty();
			return;
		}

		var address  = $opt.data('address')  || '';
		var district = $opt.data('district') || '';
		var hours    = $opt.data('hours')    || '';
		var phone    = $opt.data('phone')    || '';

		var html = '';
		if (address)  html += '<div>' + $('<div>').text(address).html() + (district ? ', ' + $('<div>').text(district).html() : '') + '</div>';
		if (hours)    html += '<div><em>' + $('<div>').text(hours).html() + '</em></div>';
		if (phone)    html += '<div>Tel: ' + $('<div>').text(phone).html() + '</div>';

		$box.html(html).show();
	}

	function applyMode() {
		var mode = $('#tcg_delivery_mode').val() || '';
		var $pickup  = $('#tcg-pickup-wrap');
		var $address = $(ADDRESS_SELECTORS);

		if (mode === 'pickup') {
			$pickup.show();
			$address.hide();
		} else if (mode === 'delivery') {
			$pickup.hide();
			$address.show();
		} else {
			// Nada seleccionado → ocultar todo.
			$pickup.hide();
			$address.hide();
		}
	}

	$(document.body).on('change', '#tcg_delivery_mode', function () {
		applyMode();
		$(document.body).trigger('update_checkout');
	});

	$(document.body).on('change', '#tcg_pickup_store_id', function () {
		renderDetails();
		$(document.body).trigger('update_checkout');
	});

	$(document.body).on('updated_checkout', function () {
		applyMode();
		renderDetails();
	});

	$(function () {
		applyMode();
		renderDetails();
	});
})(jQuery);
