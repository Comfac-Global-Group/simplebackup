(function($) {
    'use strict';

    $(document).ready(function() {
        // Directory tester
        $('#simplebackup-test-dir').on('click', function(e) {
            e.preventDefault();
            var dir = $('#backup_dir').val();
            var $results = $('#simplebackup-dir-results');
            
            $results.html('<p>Testing...</p>');
            
            $.ajax({
                url: simplebackup_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simplebackup_test_dir',
                    nonce: simplebackup_ajax.nonce,
                    dir: dir
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '<div class="simplebackup-dir-test" style="background:#f6f7f7;padding:10px;border:1px solid #c3c4c7;">';
                        html += '<p><strong>Path:</strong> ' + data.realpath + '</p>';
                        html += '<p><strong>Exists:</strong> ' + (data.exists ? 'Yes' : 'No') + ' | ';
                        html += '<strong>Directory:</strong> ' + (data.is_dir ? 'Yes' : 'No') + ' | ';
                        html += '<strong>Readable:</strong> ' + (data.readable ? 'Yes' : 'No') + ' | ';
                        html += '<strong>Writable:</strong> ' + (data.writable ? '<span style="color:green">Yes</span>' : '<span style="color:red">No</span>') + ' | ';
                        html += '<strong>Permissions:</strong> ' + data.perms + ' | ';
                        html += '<strong>Free Space:</strong> ' + data.free_space + '</p>';
                        
                        if (data.contents.length > 0) {
                            html += '<p><strong>Contents:</strong></p><ul style="max-height:200px;overflow-y:auto;font-size:0.9em;">';
                            data.contents.forEach(function(item) {
                                var icon = item.type === 'dir' ? '📁' : (item.type === 'info' ? 'ℹ️' : '📄');
                                html += '<li>' + icon + ' ' + item.name + (item.size ? ' (' + item.size + ')' : '') + '</li>';
                            });
                            html += '</ul>';
                        }
                        html += '</div>';
                        $results.html(html);
                    } else {
                        $results.html('<p style="color:red">Error: ' + response.data + '</p>');
                    }
                },
                error: function() {
                    $results.html('<p style="color:red">AJAX request failed.</p>');
                }
            });
        });

        // Confirm destructive actions
        $('form').on('submit', function(e) {
            var action = $(this).find('input[name="simplebackup_action"]').val();
            if (action === 'restore') {
                if (!confirm('WARNING: This will overwrite your current site with the selected backup. Are you sure?')) {
                    e.preventDefault();
                }
            }
            if (action === 'delete_backup') {
                if (!confirm('Delete this backup permanently?')) {
                    e.preventDefault();
                }
            }
        });
    });
})(jQuery);
