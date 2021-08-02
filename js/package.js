(function($) {
  'use strict';

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.package = {
    attach: function (context, settings) {
      $('.package-select-all').once('package-select-all-init').each(function (idx, item) {
        var $chk = $('<input type="checkbox">').addClass('package-select-chk');
        $(this).append($chk);
        $chk.on('click', function() {
          var value = this.checked;
          $('input[type="checkbox"].package-select-item').prop('checked', value);
        });
      });
    }
  };
})(jQuery);
