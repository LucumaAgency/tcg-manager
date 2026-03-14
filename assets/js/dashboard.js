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
})(jQuery);
