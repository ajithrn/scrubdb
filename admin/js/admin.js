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
            $result.html(res.success ? render(task, res.data) : badge('red', 'Error') + ' ' + esc(res.data));
        })
        .fail(function (xhr) {
            $result.html(badge('red', 'Error') + ' Request failed: ' + xhr.statusText);
        })
        .always(function () {
            $card.removeClass('scrubdb-loading');
        });
    };

    // ── Router ──────────────────────────────────────

    function render(task, d) {
        if (task === 'database_info')  return renderDbInfo(d);
        if (task === 'debug_log')      return renderDebugLog(d);
        if (task === 'autoload_audit') return renderAutoload(d);
        if (task === 'repair_tables')  return renderRepair(d);
        return renderStandard(task, d);
    }

    // ── Standard Result ─────────────────────────────

    function renderStandard(task, d) {
        var h = '', count = d.count || 0;

        if (d.mode === 'scan') {
            if (count === 0) {
                h += badge('green', 'Clean') + ' Nothing found — no action needed.';
            } else {
                h += badge('yellow', 'Found ' + count + ' items');
                if (d.size) h += ' <span style="color:#64748b;">(' + d.size + ' MB)</span>';
            }
        } else {
            var del = d.deleted || d.optimized || 0;
            h += badge('green', 'Cleaned ' + del + ' items');
        }

        if (d.note) h += '<br><em style="color:#64748b;font-size:12px;">' + esc(d.note) + '</em>';

        // Render items table with collapsible wrapper.
        if (d.items && d.items.length) {
            h += itemsTable(task, d.items, d.items_columns);
        }

        // Legacy details support.
        if (d.details && d.details.length && !(d.items && d.items.length)) {
            h += detailsTable(task, d.details);
        }

        return h;
    }

    // ── Items Table (generic) ───────────────────────

    function itemsTable(task, items, columns) {
        if (!columns || !columns.length) return '';

        var total = items.length;
        var showToggle = total > 5;
        var id = 'items-' + task + '-' + Date.now();

        var h = '';
        if (showToggle) {
            h += '<button type="button" class="scrubdb-items-toggle" onclick="scrubdbToggle(\'' + id + '\', this)">Show ' + total + ' items ▾</button>';
            h += '<div id="' + id + '" class="scrubdb-items-wrap collapsed">';
        }

        h += '<table>';
        h += '<tr>';
        columns.forEach(function (col) { h += '<th>' + esc(col.label) + '</th>'; });
        h += '</tr>';

        items.forEach(function (row) {
            h += '<tr>';
            columns.forEach(function (col) {
                var val = row[col.key] !== undefined ? String(row[col.key]) : '';
                var cls = col.mono ? ' class="scrubdb-mono"' : '';
                h += '<td' + cls + '>' + esc(val) + (col.suffix ? ' ' + col.suffix : '') + '</td>';
            });
            h += '</tr>';
        });

        h += '</table>';
        if (showToggle) h += '</div>';

        return h;
    }

    window.scrubdbToggle = function (id, btn) {
        var $wrap = $('#' + id);
        if ($wrap.hasClass('collapsed')) {
            $wrap.removeClass('collapsed').css('max-height', $wrap[0].scrollHeight + 'px');
            btn.textContent = btn.textContent.replace('Show', 'Hide').replace('▾', '▴');
        } else {
            $wrap.addClass('collapsed').css('max-height', '0');
            btn.textContent = btn.textContent.replace('Hide', 'Show').replace('▴', '▾');
        }
    };

    // ── Legacy Details Table ────────────────────────

    function detailsTable(task, details) {
        var cols = {
            'orphaned_postmeta':  [['Meta Key','meta_key'],['Count','cnt']],
            'trashed_posts':      [['Post Type','post_type'],['Count','cnt']],
            'duplicate_postmeta': [['Meta Key','meta_key'],['Duplicates','dup_count']],
            'optimize_tables':    [['Table','name'],['Engine','engine'],['Rows','rows_count'],['Size','data_mb',' MB'],['Overhead','overhead_mb',' MB']],
            'cron_cleanup':       [['Hook','hook'],['Next Run','next_run']],
        };

        var c = cols[task];
        if (!c) return '';

        var h = '<table><tr>';
        c.forEach(function (col) { h += '<th>' + col[0] + '</th>'; });
        h += '</tr>';

        details.forEach(function (row) {
            h += '<tr>';
            c.forEach(function (col) {
                h += '<td>' + esc(String(row[col[1]] || '')) + (col[2] || '') + '</td>';
            });
            h += '</tr>';
        });

        return h + '</table>';
    }

    // ── Database Info ───────────────────────────────

    function renderDbInfo(d) {
        var h = '<div class="scrubdb-stats">';
        h += stat(d.table_count, 'Tables');
        h += stat(d.total_size, 'Total Size');
        h += stat(d.total_overhead, 'Overhead');
        h += stat(d.server, 'Server Version');
        h += stat(d.db_name, 'Database');
        h += '</div>';

        if (d.tables && d.tables.length) {
            h += '<table><tr><th>Table</th><th>Engine</th><th>Rows</th><th>Size</th><th>Overhead</th></tr>';
            d.tables.forEach(function (r) {
                var ohVal = parseFloat(r.overhead_size) || 0;
                var isMB = r.overhead_size && r.overhead_size.indexOf('MB') > -1;
                var ohClass = (isMB && ohVal > 1) ? ' style="color:#dc2626;font-weight:600;"' : '';
                h += '<tr>';
                h += '<td class="scrubdb-mono">' + esc(r.name) + '</td>';
                h += '<td>' + esc(r.engine) + '</td>';
                h += '<td>' + fmtNum(r.rows_count) + '</td>';
                h += '<td>' + esc(r.total_size) + '</td>';
                h += '<td' + ohClass + '>' + esc(r.overhead_size) + '</td>';
                h += '</tr>';
            });
            h += '</table>';
        }
        return h;
    }

    // ── Debug Log ───────────────────────────────────

    function renderDebugLog(d) {
        if (d.mode === 'clean' && d.cleared) return badge('green', 'Cleared') + ' Debug log has been emptied.';

        var h = badge('blue', 'WP_DEBUG: ' + (d.debug_enabled ? 'ON' : 'OFF')) + ' ';
        h += badge('blue', 'WP_DEBUG_LOG: ' + (d.debug_log_enabled ? 'ON' : 'OFF')) + ' ';

        if (!d.exists) return h + '<br><br>No debug.log file found.';

        h += badge(d.size_mb > 10 ? 'red' : 'yellow', 'Size: ' + d.size_mb + ' MB');
        if (d.tail) h += '<div class="scrubdb-log-viewer">' + d.tail + '</div>';
        return h;
    }

    // ── Autoload Audit ──────────────────────────────

    function renderAutoload(d) {
        var size = parseFloat(d.size) || 0;
        var h = badge(size > 1 ? 'red' : 'green', d.count + ' autoloaded options — ' + d.size + ' MB');

        if (size > 1) h += '<br><em style="color:#854d0e;font-size:12px;">Autoload exceeds 1 MB — this slows every single page load.</em>';

        if (d.top_options && d.top_options.length) {
            h += '<h4 style="margin:16px 0 8px;font-size:13px;font-weight:600;color:#1e293b;">Top Autoloaded Options</h4>';
            h += '<table><tr><th>Option Name</th><th>Size</th></tr>';
            d.top_options.forEach(function (r) {
                var cls = parseFloat(r.size_kb) > 100 ? ' style="color:#dc2626;font-weight:600;"' : '';
                h += '<tr><td class="scrubdb-mono">' + esc(r.option_name) + '</td><td' + cls + '>' + r.size_kb + ' KB</td></tr>';
            });
            h += '</table>';
        }

        if (d.by_prefix && d.by_prefix.length) {
            h += '<h4 style="margin:16px 0 8px;font-size:13px;font-weight:600;color:#1e293b;">By Plugin Prefix</h4>';
            h += '<table><tr><th>Prefix</th><th>Rows</th><th>Size</th></tr>';
            d.by_prefix.forEach(function (r) {
                h += '<tr><td class="scrubdb-mono">' + esc(r.prefix) + '</td><td>' + r.cnt + '</td><td>' + r.size_kb + ' KB</td></tr>';
            });
            h += '</table>';
        }
        return h;
    }

    // ── Repair Tables ───────────────────────────────

    function renderRepair(d) {
        if (d.mode === 'scan') return badge('blue', d.count + ' tables') + ' Ready to repair.';

        var h = badge('green', 'Repair complete');
        if (d.results && d.results.length) {
            h += '<table><tr><th>Table</th><th>Status</th></tr>';
            d.results.forEach(function (r) {
                h += '<tr><td class="scrubdb-mono">' + esc(r.table) + '</td><td>' + badge(r.status === 'OK' ? 'green' : 'yellow', r.status) + '</td></tr>';
            });
            h += '</table>';
        }
        return h;
    }

    // ── Helpers ─────────────────────────────────────

    function badge(type, text) {
        return '<span class="scrubdb-badge scrubdb-badge-' + type + '">' + esc(text) + '</span>';
    }

    function stat(value, label) {
        return '<div class="scrubdb-stat"><span class="scrubdb-stat-value">' + esc(String(value)) + '</span><span class="scrubdb-stat-label">' + esc(label) + '</span></div>';
    }

    function fmtNum(n) {
        return Number(n || 0).toLocaleString();
    }

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

})(jQuery);
