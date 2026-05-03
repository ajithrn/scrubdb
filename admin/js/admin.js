/**
 * ScrubDB — Admin JavaScript
 */

/* global jQuery, scrubdb */

(function ($) {
    'use strict';

    window.scrubdbRun = function (task, mode) {
        if (mode === 'clean') {
            if (!confirm('Are you sure you want to run this cleanup?\n\nThis action cannot be undone. Make sure you have a database backup.')) {
                return;
            }
        }

        var $card   = $('#card-' + task);
        var $result = $('#result-' + task);

        $card.addClass('scrubdb-loading');
        $result.html('<span class="scrubdb-spinner"></span> Running ' + (mode === 'scan' ? 'scan' : 'cleanup') + '…');

        $.post(scrubdb.ajaxUrl, {
            action: 'scrubdb',
            nonce:  scrubdb.nonce,
            task:   task,
            mode:   mode,
        })
        .done(function (res) {
            if (res.success) {
                $result.html(render(task, res.data));
            } else {
                $result.html(badge('red', 'Error') + ' ' + esc(res.data));
            }
        })
        .fail(function (xhr) {
            $result.html(badge('red', 'Error') + ' Request failed: ' + xhr.statusText);
        })
        .always(function () {
            $card.removeClass('scrubdb-loading');
        });
    };

    // ── Renderers ───────────────────────────────────

    function render(task, d) {
        if (task === 'database_info')  return renderDbInfo(d);
        if (task === 'debug_log')      return renderDebugLog(d);
        if (task === 'autoload_audit') return renderAutoload(d);
        if (task === 'repair_tables')  return renderRepair(d);
        return renderStandard(task, d);
    }

    function renderStandard(task, d) {
        var h = '', count = d.count || 0;

        if (d.mode === 'scan') {
            h += count === 0
                ? badge('green', 'Clean') + ' No items found.'
                : badge('yellow', 'Found ' + count) + (d.size ? ' (~' + d.size + ' MB)' : '');
        } else {
            var del = d.deleted || d.optimized || 0;
            h += badge('green', 'Done') + ' ' + del + ' item(s) cleaned.';
        }

        if (d.note) h += '<br><em>' + esc(d.note) + '</em>';
        if (d.details && d.details.length) h += detailsTable(task, d.details);
        return h;
    }

    function renderDbInfo(d) {
        var h = '<div class="scrubdb-stats">';
        h += stat(d.table_count, 'Tables');
        h += stat(d.total_size + ' MB', 'Total Size');
        h += stat(d.total_overhead + ' MB', 'Overhead');
        h += stat(d.server, 'Server');
        h += stat(d.db_name, 'Database');
        h += '</div>';

        if (d.tables && d.tables.length) {
            h += '<table><tr><th>Table</th><th>Engine</th><th>Rows</th><th>Size (MB)</th><th>Overhead (MB)</th></tr>';
            d.tables.forEach(function (r) {
                h += '<tr><td>' + esc(r.name) + '</td><td>' + esc(r.engine) + '</td><td>' + (r.rows_count||0) + '</td><td>' + r.total_mb + '</td><td>' + r.overhead_mb + '</td></tr>';
            });
            h += '</table>';
        }
        return h;
    }

    function renderDebugLog(d) {
        if (d.mode === 'clean' && d.cleared) return badge('green', 'Cleared') + ' Debug log has been emptied.';

        var h = badge('blue', 'WP_DEBUG: ' + (d.debug_enabled ? 'ON' : 'OFF')) + ' ';
        h += badge('blue', 'WP_DEBUG_LOG: ' + (d.debug_log_enabled ? 'ON' : 'OFF')) + ' ';

        if (!d.exists) return h + '<br><br>No debug.log file found.';

        h += badge(d.size_mb > 10 ? 'red' : 'yellow', 'Size: ' + d.size_mb + ' MB');
        if (d.tail) h += '<div class="scrubdb-log-viewer">' + d.tail + '</div>';
        return h;
    }

    function renderAutoload(d) {
        var size = parseFloat(d.size) || 0;
        var h = badge(size > 1 ? 'red' : 'green', d.count + ' autoloaded options (' + d.size + ' MB)');

        if (size > 1) h += '<br><em style="color:#856404;">⚠️ Autoload size exceeds 1 MB — this slows every page load.</em>';

        if (d.top_options && d.top_options.length) {
            h += '<table><tr><th>Option Name</th><th>Size (KB)</th></tr>';
            d.top_options.forEach(function (r) { h += '<tr><td>' + esc(r.option_name) + '</td><td>' + r.size_kb + '</td></tr>'; });
            h += '</table>';
        }

        if (d.by_prefix && d.by_prefix.length) {
            h += '<h4 style="margin:16px 0 8px;font-size:13px;">By Plugin Prefix</h4>';
            h += '<table><tr><th>Prefix</th><th>Rows</th><th>Size (KB)</th></tr>';
            d.by_prefix.forEach(function (r) { h += '<tr><td>' + esc(r.prefix) + '</td><td>' + r.cnt + '</td><td>' + r.size_kb + '</td></tr>'; });
            h += '</table>';
        }
        return h;
    }

    function renderRepair(d) {
        if (d.mode === 'scan') return badge('blue', d.count + ' tables') + ' Ready to repair.';

        var h = badge('green', 'Done');
        if (d.results && d.results.length) {
            h += '<table><tr><th>Table</th><th>Status</th></tr>';
            d.results.forEach(function (r) {
                h += '<tr><td>' + esc(r.table) + '</td><td>' + badge(r.status === 'OK' ? 'green' : 'yellow', r.status) + '</td></tr>';
            });
            h += '</table>';
        }
        return h;
    }

    function detailsTable(task, details) {
        var h = '<table>';
        var cols = {
            'orphaned_postmeta':  [['Meta Key','meta_key'],['Count','cnt']],
            'trashed_posts':      [['Post Type','post_type'],['Count','cnt']],
            'duplicate_postmeta': [['Meta Key','meta_key'],['Duplicates','dup_count']],
            'optimize_tables':    [['Table','name'],['Engine','engine'],['Rows','rows_count'],['Size','data_mb','MB'],['Overhead','overhead_mb','MB']],
            'cron_cleanup':       [['Hook','hook'],['Next Run','next_run']],
        };

        var c = cols[task];
        if (!c) return '';

        h += '<tr>';
        c.forEach(function (col) { h += '<th>' + col[0] + '</th>'; });
        h += '</tr>';

        details.forEach(function (row) {
            h += '<tr>';
            c.forEach(function (col) {
                var val = row[col[1]] || '';
                h += '<td>' + esc(String(val)) + (col[2] ? ' ' + col[2] : '') + '</td>';
            });
            h += '</tr>';
        });

        h += '</table>';
        return h;
    }

    // ── Helpers ─────────────────────────────────────

    function badge(type, text) {
        return '<span class="scrubdb-badge scrubdb-badge-' + type + '">' + esc(text) + '</span>';
    }

    function stat(value, label) {
        return '<div class="scrubdb-stat"><span class="scrubdb-stat-value">' + esc(String(value)) + '</span><span class="scrubdb-stat-label">' + esc(label) + '</span></div>';
    }

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

})(jQuery);
