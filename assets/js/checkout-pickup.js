(function ($) {
	'use strict';

	function renderDetails() {
		var $sel = $('#tcg_pickup_store_id');
		var $box = $('#tcg-pickup-details');
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
		var mode = $('#tcg_delivery_mode').val() || 'delivery';
		var $pickup = $('#tcg-pickup-wrap');
		var $ship   = $('.woocommerce-shipping-fields, .shipping_address');

		if (mode === 'pickup') {
			$pickup.show();
			$ship.hide();
		} else {
			$pickup.hide();
			$ship.show();
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
