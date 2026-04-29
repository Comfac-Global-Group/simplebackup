(function($) {
    'use strict';

    $(document).ready(function() {
        // Store original settings for revert
        var originalSettings = {};
        $('#simplebackup-settings-form').find('input, select').each(function() {
            var name = $(this).attr('name');
            if (name) {
                if ($(this).attr('type') === 'checkbox') {
                    originalSettings[name] = $(this).prop('checked');
                } else {
                    originalSettings[name] = $(this).val();
                }
            }
        });

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

        // Test Settings
        $('#simplebackup-test-settings').on('click', function(e) {
            e.preventDefault();
            var $results = $('#simplebackup-settings-test-results');
            $results.html('<p>Testing settings...</p>');

            var settings = {};
            $('#simplebackup-settings-form').find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name && name.indexOf('simplebackup_settings[') === 0) {
                    var key = name.replace('simplebackup_settings[', '').replace(']', '');
                    if ($(this).attr('type') === 'checkbox') {
                        settings[key] = $(this).prop('checked') ? '1' : '';
                    } else {
                        settings[key] = $(this).val();
                    }
                }
            });

            $.ajax({
                url: simplebackup_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simplebackup_test_settings',
                    nonce: simplebackup_ajax.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '<div style="background:#f6f7f7;padding:10px;border:1px solid #c3c4c7;">';
                        html += '<h4>Test Results</h4>';
                        data.results.forEach(function(item) {
                            var color = item.status === 'success' ? 'green' : (item.status === 'error' ? 'red' : (item.status === 'warning' ? 'orange' : '#666'));
                            var icon = item.status === 'success' ? '✓' : (item.status === 'error' ? '✗' : (item.status === 'warning' ? '⚠' : 'ℹ'));
                            html += '<p style="color:' + color + ';margin:4px 0;">' + icon + ' ' + item.message + '</p>';
                        });
                        html += '<p><strong>Overall:</strong> ' + (data.pass ? '<span style="color:green">PASS — Settings look good!</span>' : '<span style="color:red">FAIL — Please fix errors before saving.</span>') + '</p>';
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

        // Revert Settings
        $('#simplebackup-revert-settings').on('click', function(e) {
            e.preventDefault();
            $('#simplebackup-settings-form').find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name && originalSettings.hasOwnProperty(name)) {
                    if ($(this).attr('type') === 'checkbox') {
                        $(this).prop('checked', originalSettings[name]);
                    } else {
                        $(this).val(originalSettings[name]);
                    }
                }
            });
            $('#simplebackup-settings-test-results').html('<p style="color:#666;">Settings reverted to last saved values.</p>');
        });

        // Test Backup (Dry Run)
        $('#simplebackup-test-backup').on('click', function(e) {
            e.preventDefault();
            var $results = $('#simplebackup-test-backup-results');
            var type = $('input[name="backup_type"]:checked').val() || 'full';
            $results.show().html('<p>Analyzing backup...</p>');

            $.ajax({
                url: simplebackup_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'simplebackup_test_backup',
                    nonce: simplebackup_ajax.nonce,
                    backup_type: type
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '<div style="background:#f6f7f7;padding:10px;border:1px solid #c3c4c7;margin-top:10px;">';
                        html += '<h4>Dry Run Results</h4>';
                        html += '<p><strong>Type:</strong> ' + data.type + (data.incremental ? ' (incremental)' : '') + '</p>';
                        if (data.database) {
                            html += '<p><strong>Database:</strong> Yes (~' + data.db_size_formatted + ')</p>';
                        }
                        html += '<p><strong>Files:</strong> ' + data.files.toLocaleString() + ' files, ~' + data.file_size_formatted + '</p>';
                        if (data.skipped_files > 0) {
                            html += '<p><strong>Skipped (unchanged):</strong> ' + data.skipped_files.toLocaleString() + ' files</p>';
                        }
                        html += '<p><strong>Destination:</strong> ' + data.dest_dir + ' (' + (data.dest_writable ? '<span style="color:green">writable</span>' : '<span style="color:red">not writable</span>') + ')</p>';
                        html += '<p style="color:#666;font-size:0.9em;"><em>This is an estimate. Actual backup size may vary due to compression.</em></p>';
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
                if (!confirm('WARNING: This will overwrite your current site with the selected backup. A safety backup will be created first. Are you sure?')) {
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
