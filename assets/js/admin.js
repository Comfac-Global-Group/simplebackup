(function($) {
    'use strict';

    $(document).ready(function() {
        // Confirm destructive actions
        $('form').on('submit', function(e) {
            var action = $(this).find('input[name="simplebackup_action"]').val();
            if (action === 'restore') {
                if (!confirm(simplebackup_i18n.restore_confirm)) {
                    e.preventDefault();
                }
            }
        });
    });
})(jQuery);
