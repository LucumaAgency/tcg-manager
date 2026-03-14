(function ($) {
  'use strict';

  var $search = $('#tcg-card-search');
  var $cardId = $('#tcg-linked-card-id');
  var $preview = $('#tcg-card-preview');
  var $changeBtn = $('#tcg-change-card');

  /**
   * Initialize autocomplete with local card data (no AJAX for search).
   */
  function initAutocomplete() {
    var allCards = tcgManager.cards || [];

    $search.autocomplete({
      source: function (request, response) {
        var term = request.term.toLowerCase();
        var matches = [];
        for (var i = 0; i < allCards.length && matches.length < 15; i++) {
          if (allCards[i].label.toLowerCase().indexOf(term) !== -1) {
            matches.push(allCards[i]);
          }
        }

        if (!matches.length) {
          response([{ label: tcgManager.i18n.noResults, value: '', id: 0 }]);
          return;
        }
        response(matches);
      },
      minLength: 2,
      delay: 100,
      select: function (event, ui) {
        if (!ui.item.id) {
          event.preventDefault();
          return;
        }

        $search.val(ui.item.value).prop('readonly', true);
        $cardId.val(ui.item.id);
        loadCardPreview(ui.item.id);
        return false;
      },
    });

    // Custom render for autocomplete items.
    $search.autocomplete('instance')._renderItem = function (ul, item) {
      if (!item.id) {
        return $('<li>').append('<div class="tcg-ac-item tcg-ac-noresult">' + item.label + '</div>').appendTo(ul);
      }

      var info =
        '<div class="tcg-ac-info">' +
        '<strong>' + $('<span>').text(item.value).html() + '</strong>' +
        (item.set_code ? '<br><small>' + $('<span>').text(item.set_code).html() + '</small>' : '') +
        (item.set_rarity ? ' <small class="tcg-ac-rarity">' + $('<span>').text(item.set_rarity).html() + '</small>' : '') +
        '</div>';

      return $('<li>')
        .append('<div class="tcg-ac-item">' + info + '</div>')
        .appendTo(ul);
    };
  }

  /**
   * Load card preview via AJAX.
   */
  function loadCardPreview(cardId) {
    $.ajax({
      url: tcgManager.ajaxUrl,
      dataType: 'json',
      data: {
        action: 'tcg_get_ygo_card_data',
        nonce: tcgManager.nonce,
        card_id: cardId,
      },
      success: function (resp) {
        if (!resp.success) return;

        var card = resp.data;
        var html = '<div class="tcg-preview-inner">';

        if (card.thumbnail) {
          html += '<div class="tcg-preview-img"><img src="' + card.thumbnail + '" alt=""></div>';
        }

        html += '<div class="tcg-preview-info">';
        html += '<h4>' + $('<span>').text(card.title).html() + '</h4>';

        var meta = card.meta;
        if (meta._ygo_set_code) {
          html += '<p><strong>Set:</strong> ' + $('<span>').text(meta._ygo_set_code).html() + '</p>';
        }
        if (meta._ygo_set_rarity) {
          html += '<p><strong>Rarity:</strong> ' + $('<span>').text(meta._ygo_set_rarity).html() + '</p>';
        }
        if (meta._ygo_typeline) {
          html += '<p><strong>Type:</strong> ' + $('<span>').text(meta._ygo_typeline).html() + '</p>';
        }
        if (meta._ygo_atk) {
          var stat = 'ATK/' + meta._ygo_atk;
          if (meta._ygo_def) stat += ' DEF/' + meta._ygo_def;
          html += '<p><strong>Stats:</strong> ' + $('<span>').text(stat).html() + '</p>';
        }
        if (meta._ygo_level) {
          html += '<p><strong>Level:</strong> ' + meta._ygo_level + '</p>';
        }
        if (meta._ygo_rank) {
          html += '<p><strong>Rank:</strong> ' + meta._ygo_rank + '</p>';
        }
        if (meta._ygo_linkval) {
          html += '<p><strong>Link:</strong> ' + meta._ygo_linkval + '</p>';
        }

        var prices = meta._ygo_ref_prices;
        if (prices && typeof prices === 'object') {
          var priceLabels = { tcgplayer: 'TCGPlayer', cardmarket: 'Cardmarket', ebay: 'eBay', amazon: 'Amazon' };
          var priceHtml = '';
          $.each(priceLabels, function (key, label) {
            if (prices[key] && prices[key] !== '0' && prices[key] !== '0.00') {
              priceHtml += '<span class="tcg-ref-price">' + label + ': $' + $('<span>').text(prices[key]).html() + '</span> ';
            }
          });
          if (priceHtml) {
            html += '<p class="tcg-preview-prices"><strong>Ref:</strong> ' + priceHtml + '</p>';
          }
        }

        html += '</div></div>';
        $preview.html(html).show();
      },
    });
  }

  /**
   * "Change card" button handler.
   */
  function initChangeButton() {
    $(document).on('click', '#tcg-change-card', function () {
      $search.val('').prop('readonly', false).focus();
      $cardId.val('');
      $preview.hide().empty();
      $(this).remove();
    });
  }

  /**
   * On edit page: load preview if card is already linked.
   */
  function loadExistingPreview() {
    var existingId = $cardId.val();
    if (existingId && parseInt(existingId, 10) > 0) {
      loadCardPreview(existingId);
    }
  }

  // Init on DOM ready.
  $(function () {
    if (!$search.length) return;
    initAutocomplete();
    initChangeButton();
    loadExistingPreview();
  });
})(jQuery);
