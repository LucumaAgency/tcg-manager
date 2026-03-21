(function ($) {
  'use strict';

  // Confirm product deletion.
  $(document).on('click', '.tcg-delete-product', function (e) {
    if (!confirm(tcgDashboard.i18n.confirmDelete)) {
      e.preventDefault();
    }
  });

  // Select all checkbox for bulk actions.
  $(document).on('change', '#cb-select-all', function () {
    $('input[name="commission_ids[]"]').prop('checked', this.checked);
  });

  // Mobile sidebar toggle.
  $(document).on('click', '.tcg-sidebar-toggle', function () {
    $('.tcg-dashboard-sidebar').toggleClass('tcg-sidebar-open');
  });

  // Save tracking code.
  $(document).on('click', '#tcg-tracking-save', function () {
    var $btn = $(this);
    var $input = $('#tcg-tracking-input');
    var origText = $btn.text();

    $btn.prop('disabled', true).text('…');

    $.post(tcgDashboard.ajaxUrl, {
      action: 'tcg_save_tracking',
      order_id: $btn.data('order-id'),
      tracking: $input.val(),
      nonce: $btn.data('nonce')
    }).done(function () {
      $btn.text('✓');
      setTimeout(function () { $btn.text(origText).prop('disabled', false); }, 1500);
    }).fail(function () {
      $btn.text('Error').prop('disabled', false);
      setTimeout(function () { $btn.text(origText); }, 1500);
    });
  });
})(jQuery);
