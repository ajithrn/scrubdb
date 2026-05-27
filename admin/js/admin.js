/**
 * ScrubDB — Admin JavaScript v2.1
 * Tab navigation, AJAX task runner, sortable + paginated tables.
 *
 * @package ScrubDB
 */

/* global jQuery, scrubdb */

(function ($) {
    'use strict';

    var ITEMS_PER_PAGE = 20;
    var resultData = {};
    var tableState = {};

    // ─────────────────────────────────────────────────
    // TAB NAVIGATION
    // ─────────────────────────────────────────────────

    $(document).on('click', '.scrubdb-tab', function () {
        var tab = $(this).data('tab');
        $('.scrubdb-tab').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        $('.scrubdb-tab-panel').removeClass('active');
        $('#panel-' + tab).addClass('active');
        if (history.replaceState) {
            history.replaceState(null, null, '#' + tab);
        }
    });

    $(function () {
        var hash = window.location.hash.replace('#', '');
        if (hash && $('.scrubdb-tab[data-tab="' + hash + '"]').length) {
            $('.scrubdb-tab[data-tab="' + hash + '"]').trigger('click');
        }
    });

    // ─────────────────────────────────────────────────
    // AJAX TASK RUNNER
    // ─────────────────────────────────────────────────

    window.scrubdbRun = function (task, mode) {
        if (mode === 'clean' && !confirm(
            'Are you sure you want to run this cleanup?\n\nThis cannot be undone. Make sure you have a backup.'
        )) { return; }

        var $card = $('#card-' + task);
        var $result = $('#result-' + task);
        $card.addClass('scrubdb-loading');
        $result.html('<span class="scrubdb-spinner"></span> Running ' +
            (mode === 'scan' ? 'scan' : 'cleanup') + '…');

        $.post(scrubdb.ajaxUrl, {
            action: 'scrubdb', nonce: scrubdb.nonce, task: task, mode: mode
        }).done(function (res) {
            if (res.success) {
                resultData[task] = res.data;
                $result.html(render(task, res.data));
            } else {
                $result.html(badge('red', 'Error') + ' ' + esc(res.data));
            }
        }).fail(function (xhr) {
            $result.html(badge('red', 'Error') + ' Request failed: ' + xhr.statusText);
        }).always(function () {
            $card.removeClass('scrubdb-loading');
        });
    };

    window.scrubdbRunAll = function (group) {
        $('[data-group="' + group + '"]').each(function () {
            scrubdbRun(this.id.replace('card-', ''), 'scan');
        });
    };

    // ─────────────────────────────────────────────────
    // RENDER ROUTER
    // ─────────────────────────────────────────────────

    function render(task, d) {
        if (task === 'database_info')  return renderDbInfo(d);
        if (task === 'debug_log')      return renderDebugLog(d);
        if (task === 'options_debug')  return renderOptionsDebug(d);
        if (task === 'repair_tables')  return renderRepair(d);
        return renderStandard(task, d);
    }

    function renderStandard(task, d) {
        var h = '', count = d.count || 0;
        if (d.mode === 'scan') {
            if (count === 0) {
                h += badge('green', 'Clean') + ' Nothing found — no action needed.';
            } else {
                h += badge('yellow', 'Found ' + fmtNum(count) + ' items');
                if (d.size) h += ' <span style="color:#64748b;font-size:12px;">(' + d.size + ' MB)</span>';
            }
        } else {
            h += badge('green', 'Cleaned ' + fmtNum(d.deleted || d.optimized || 0) + ' items');
        }
        if (d.note) h += '<br><em style="color:#64748b;font-size:12px;">' + esc(d.note) + '</em>';
        if (d.items && d.items.length) {
            h += sortableTable(task, d.items, d.items_columns);
        }
        if (d.details && d.details.length && !(d.items && d.items.length)) {
            h += detailsTable(task, d.details);
        }
        return h;
    }

    // ─────────────────────────────────────────────────
    // SORTABLE + PAGINATED TABLE ENGINE
    // ─────────────────────────────────────────────────

    function sortableTable(id, items, columns) {
        if (!columns || !columns.length) return '';
        var stateId = 'tbl-' + id + '-' + Date.now();
        tableState[stateId] = {
            items: items.slice(),
            columns: columns,
            page: 1,
            sortKey: null,
            sortDir: 'asc'
        };
        return '<div id="' + stateId + '">' + renderTableHTML(stateId) + '</div>';
    }

    function renderTableHTML(stateId) {
        var s = tableState[stateId];
        if (!s) return '';

        var items = s.items;
        var columns = s.columns;
        var totalPages = Math.ceil(items.length / ITEMS_PER_PAGE) || 1;
        var page = Math.min(s.page, totalPages);
        s.page = page;

        var start = (page - 1) * ITEMS_PER_PAGE;
        var end = Math.min(start + ITEMS_PER_PAGE, items.length);
        var pageItems = items.slice(start, end);

        // Table header with sort indicators.
        var h = '<div class="scrubdb-table-wrap"><table><tr>';
        columns.forEach(function (col) {
            var cls = 'scrubdb-sortable';
            if (s.sortKey === col.key) {
                cls += s.sortDir === 'asc' ? ' scrubdb-sort-asc' : ' scrubdb-sort-desc';
            }
            h += '<th class="' + cls + '" onclick="scrubdbSort(\'' +
                stateId + '\',\'' + col.key + '\')">' + esc(col.label) + '</th>';
        });
        h += '</tr>';

        // Table rows.
        pageItems.forEach(function (row) {
            h += '<tr>';
            columns.forEach(function (col) {
                var val = row[col.key] != null ? String(row[col.key]) : '';
                var cls = col.mono ? ' class="scrubdb-mono"' : '';
                h += '<td' + cls + '>' + esc(val) + (col.suffix ? ' ' + col.suffix : '') + '</td>';
            });
            h += '</tr>';
        });
        h += '</table></div>';

        // Pagination.
        if (totalPages > 1) {
            h += renderPagination(stateId, page, totalPages, items.length, start, end);
        }
        return h;
    }

    function renderPagination(stateId, page, totalPages, total, start, end) {
        var h = '<div class="scrubdb-pagination">';
        h += '<span class="scrubdb-pagination-info">Showing ' + (start + 1) + '–' + end + ' of ' + total + '</span>';
        h += '<div class="scrubdb-pagination-btns">';
        h += '<button ' + (page <= 1 ? 'disabled' : '') +
            ' onclick="scrubdbPage(\'' + stateId + '\',' + (page - 1) + ')">← Prev</button>';

        var sp = Math.max(1, page - 2);
        var ep = Math.min(totalPages, sp + 4);
        if (ep - sp < 4) sp = Math.max(1, ep - 4);
        for (var i = sp; i <= ep; i++) {
            h += '<button class="' + (i === page ? 'active' : '') +
                '" onclick="scrubdbPage(\'' + stateId + '\',' + i + ')">' + i + '</button>';
        }

        h += '<button ' + (page >= totalPages ? 'disabled' : '') +
            ' onclick="scrubdbPage(\'' + stateId + '\',' + (page + 1) + ')">Next →</button>';
        h += '</div></div>';
        return h;
    }

    // Sort handler.
    window.scrubdbSort = function (stateId, key) {
        var s = tableState[stateId];
        if (!s) return;

        // Toggle direction if same key, otherwise default to asc.
        if (s.sortKey === key) {
            s.sortDir = s.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            s.sortKey = key;
            s.sortDir = 'asc';
        }
        s.page = 1; // Reset to first page on sort.

        // Sort items.
        s.items.sort(function (a, b) {
            var va = a[key] != null ? a[key] : '';
            var vb = b[key] != null ? b[key] : '';

            // Try numeric comparison.
            var na = parseFloat(va), nb = parseFloat(vb);
            if (!isNaN(na) && !isNaN(nb)) {
                return s.sortDir === 'asc' ? na - nb : nb - na;
            }

            // String comparison.
            va = String(va).toLowerCase();
            vb = String(vb).toLowerCase();
            if (va < vb) return s.sortDir === 'asc' ? -1 : 1;
            if (va > vb) return s.sortDir === 'asc' ? 1 : -1;
            return 0;
        });

        $('#' + stateId).html(s.custom ? renderXrayTable(stateId) : renderTableHTML(stateId));
    };

    // Page handler.
    window.scrubdbPage = function (stateId, page) {
        var s = tableState[stateId];
        if (!s) return;
        s.page = page;
        $('#' + stateId).html(s.custom ? renderXrayTable(stateId) : renderTableHTML(stateId));
    };

    // ─────────────────────────────────────────────────
    // LEGACY DETAILS TABLE
    // ─────────────────────────────────────────────────

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

        var h = '<div class="scrubdb-table-wrap"><table><tr>';
        c.forEach(function (col) { h += '<th>' + col[0] + '</th>'; });
        h += '</tr>';
        details.forEach(function (row) {
            h += '<tr>';
            c.forEach(function (col) {
                h += '<td>' + esc(String(row[col[1]] || '')) + (col[2] || '') + '</td>';
            });
            h += '</tr>';
        });
        return h + '</table></div>';
    }

    // ─────────────────────────────────────────────────
    // DATABASE INFO RENDERER
    // ─────────────────────────────────────────────────

    function renderDbInfo(d) {
        var h = '<div class="scrubdb-stats">';
        h += stat(d.table_count, 'Tables');
        h += stat(d.total_size, 'Total Size');
        h += stat(d.total_overhead, 'Overhead');
        h += stat(d.server, 'Server');
        h += stat(d.db_name, 'Database');
        h += '</div>';

        if (d.tables && d.tables.length) {
            h += '<div class="scrubdb-table-wrap"><table>';
            h += '<tr><th>Table</th><th>Engine</th><th>Rows</th><th>Size</th><th>Overhead</th></tr>';
            d.tables.forEach(function (r) {
                var ohVal = parseFloat(r.overhead_size) || 0;
                var isMB = r.overhead_size && r.overhead_size.indexOf('MB') > -1;
                var ohCls = (isMB && ohVal > 1) ? ' style="color:#dc2626;font-weight:600;"' : '';
                h += '<tr>';
                h += '<td class="scrubdb-mono">' + esc(r.name) + '</td>';
                h += '<td>' + esc(r.engine) + '</td>';
                h += '<td>' + fmtNum(r.rows_count) + '</td>';
                h += '<td>' + esc(r.total_size) + '</td>';
                h += '<td' + ohCls + '>' + esc(r.overhead_size) + '</td>';
                h += '</tr>';
            });
            h += '</table></div>';
        }
        return h;
    }

    // ─────────────────────────────────────────────────
    // DEBUG LOG RENDERER
    // ─────────────────────────────────────────────────

    function renderDebugLog(d) {
        if (d.mode === 'clean' && d.cleared) {
            return badge('green', 'Cleared') + ' Debug log has been emptied.';
        }
        var h = badge('blue', 'WP_DEBUG: ' + (d.debug_enabled ? 'ON' : 'OFF')) + ' ';
        h += badge('blue', 'WP_DEBUG_LOG: ' + (d.debug_log_enabled ? 'ON' : 'OFF')) + ' ';
        if (!d.exists) return h + '<br><br>No debug.log file found.';
        h += badge(d.size_mb > 10 ? 'red' : 'yellow', 'Size: ' + d.size_mb + ' MB');
        if (d.tail) h += '<div class="scrubdb-log-viewer">' + d.tail + '</div>';
        return h;
    }

    // ─────────────────────────────────────────────────
    // AUTOLOAD AUDIT RENDERER
    // ─────────────────────────────────────────────────

    // ─────────────────────────────────────────────────
    // REPAIR TABLES RENDERER
    // ─────────────────────────────────────────────────

    function renderRepair(d) {
        if (d.mode === 'scan') return badge('blue', d.count + ' tables') + ' Ready to repair.';
        var h = badge('green', 'Repair complete');
        if (d.results && d.results.length) {
            h += '<div class="scrubdb-table-wrap"><table><tr><th>Table</th><th>Status</th></tr>';
            d.results.forEach(function (r) {
                h += '<tr><td class="scrubdb-mono">' + esc(r.table) + '</td><td>' +
                    badge(r.status === 'OK' ? 'green' : 'yellow', r.status) + '</td></tr>';
            });
            h += '</table></div>';
        }
        return h;
    }

    // ─────────────────────────────────────────────────
    // OPTIONS X-RAY RENDERER (sortable + paginated)
    // ─────────────────────────────────────────────────

    var PROTECTED_OPTIONS = [
        'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email',
        'users_can_register', 'start_of_week', 'date_format', 'time_format',
        'active_plugins', 'template', 'stylesheet', 'db_version',
        'initial_db_version', 'wp_user_roles', 'permalink_structure',
        'current_theme', 'WPLANG', 'blog_charset', 'gmt_offset',
        'timezone_string', 'default_role', 'cron', 'rewrite_rules'
    ];

    function isProtected(name) {
        return PROTECTED_OPTIONS.indexOf(name) > -1;
    }

    function renderOptionsDebug(d) {
        var h = '<div class="scrubdb-stats">';
        h += stat(fmtNum(d.total_rows), 'Total Rows');
        h += stat(d.total_mb + ' MB', 'Total Size');
        h += stat(d.autoload_yes.count + ' (' + d.autoload_yes.size_mb + ' MB)', 'Autoloaded');
        h += stat(d.autoload_no.count + ' (' + d.autoload_no.size_mb + ' MB)', 'Non-Autoloaded');
        h += stat(d.transients.count + ' (' + d.transients.size_mb + ' MB)', 'Transients');
        h += '</div>';

        var alSize = parseFloat(d.autoload_yes.size_mb) || 0;
        if (alSize > 1) {
            h += '<div class="scrubdb-warning"><strong>Warning:</strong> Autoloaded options total ' +
                d.autoload_yes.size_mb + ' MB — loads on every page request. Aim for under 1 MB.</div>';
        }

        h += '<div id="scrubdb-option-feedback" style="margin-bottom:10px;"></div>';

        // X-Ray table with sort + pagination.
        if (d.top_options && d.top_options.length) {
            h += '<h4 style="margin:14px 0 6px;font-size:12px;font-weight:600;color:#1e293b;">Top ' +
                d.top_options.length + ' Largest Options</h4>';

            var stateId = 'xray-' + Date.now();
            tableState[stateId] = {
                items: d.top_options.slice(),
                columns: [
                    { label: 'Option Name', key: 'option_name', mono: true },
                    { label: 'Type', key: 'type' },
                    { label: 'Size (KB)', key: 'size_kb' },
                    { label: 'Autoload', key: 'autoload' }
                ],
                page: 1, sortKey: null, sortDir: 'asc',
                custom: true // Flag for custom row renderer.
            };
            h += '<div id="' + stateId + '">' + renderXrayTable(stateId) + '</div>';
        }

        // By prefix breakdown — sortable + paginated.
        if (d.by_prefix && d.by_prefix.length) {
            h += '<h4 style="margin:18px 0 6px;font-size:12px;font-weight:600;color:#1e293b;">By Plugin/Prefix</h4>';
            h += sortableTable('xray_prefix', d.by_prefix, [
                { label: 'Prefix', key: 'prefix', mono: true },
                { label: 'Rows', key: 'cnt' },
                { label: 'Size', key: 'size_kb', suffix: 'KB' },
                { label: 'Autoloaded', key: 'autoloaded' }
            ]);
        }
        return h;
    }

    function renderXrayTable(stateId) {
        var s = tableState[stateId];
        if (!s) return '';

        var items = s.items;
        var columns = s.columns;
        var totalPages = Math.ceil(items.length / ITEMS_PER_PAGE) || 1;
        var page = Math.min(s.page, totalPages);
        s.page = page;
        var start = (page - 1) * ITEMS_PER_PAGE;
        var end = Math.min(start + ITEMS_PER_PAGE, items.length);
        var pageItems = items.slice(start, end);

        // Header with sort.
        var h = '<div class="scrubdb-table-wrap"><table><tr>';
        columns.forEach(function (col) {
            var cls = 'scrubdb-sortable';
            if (s.sortKey === col.key) {
                cls += s.sortDir === 'asc' ? ' scrubdb-sort-asc' : ' scrubdb-sort-desc';
            }
            h += '<th class="' + cls + '" onclick="scrubdbSort(\'' +
                stateId + '\',\'' + col.key + '\')">' + esc(col.label) + '</th>';
        });
        h += '<th>Actions</th></tr>';

        // Rows with inline actions.
        pageItems.forEach(function (r) {
            var sizeVal = parseFloat(r.size_kb) || 0;
            var sizeCls = sizeVal > 100 ? ' style="color:#dc2626;font-weight:600;"' : '';
            var typeColors = { Transient:'#dbeafe', Core:'#dcfce7', WooCommerce:'#fae8ff', 'Plugin/Theme':'#fef9c3' };
            var typeBg = typeColors[r.type] || '#f1f5f9';
            var safe = esc(r.option_name).replace(/'/g, "\\'");
            var prot = isProtected(r.option_name);

            h += '<tr id="opt-row-' + r.option_id + '">';
            h += '<td class="scrubdb-mono" title="' + esc(r.option_name) + '">' + esc(r.option_name) + '</td>';
            h += '<td><span style="background:' + typeBg + ';padding:2px 7px;border-radius:10px;font-size:10px;font-weight:500;white-space:nowrap;">' + esc(r.type) + '</span></td>';
            h += '<td' + sizeCls + '>' + r.size_kb + ' KB</td>';

            // Autoload toggle.
            var isAutoloaded = (r.autoload === 'yes' || r.autoload === 'on' || r.autoload === 'auto-on' || r.autoload === 'auto');
            var alLabel = isAutoloaded
                ? '<span style="color:#dc2626;font-weight:600;">' + esc(r.autoload) + '</span>'
                : '<span style="color:#166534;">' + esc(r.autoload) + '</span>';
            h += '<td>' + alLabel;
            if (!prot) {
                var tgl = isAutoloaded ? 'Set Off' : 'Set On';
                h += ' <button type="button" class="scrubdb-inline-btn" onclick="scrubdbToggleAutoload(\'' + safe + '\',this)">' + tgl + '</button>';
            }
            h += '</td>';

            // Delete.
            h += '<td>';
            if (prot) {
                h += '<span style="color:#94a3b8;font-size:10px;">Protected</span>';
            } else {
                h += '<button type="button" class="scrubdb-inline-btn scrubdb-inline-btn-danger" onclick="scrubdbDeleteOption(' + r.option_id + ',\'' + safe + '\',this)">Delete</button>';
            }
            h += '</td></tr>';
        });
        h += '</table></div>';

        if (totalPages > 1) {
            h += renderPagination(stateId, page, totalPages, items.length, start, end);
        }
        return h;
    }

    // Sort/page for xray uses custom renderer, handled by the s.custom flag
    // in the main scrubdbSort and scrubdbPage handlers above.

    // ─────────────────────────────────────────────────
    // OPTION MANAGEMENT ACTIONS
    // ─────────────────────────────────────────────────

    window.scrubdbToggleAutoload = function (optionName, btn) {
        btn.disabled = true;
        btn.textContent = '…';
        $.post(scrubdb.ajaxUrl, {
            action: 'scrubdb', nonce: scrubdb.nonce,
            task: 'toggle_autoload', mode: 'clean', option_name: optionName
        }).done(function (res) {
            var fb = $('#scrubdb-option-feedback');
            if (res.success && !res.data.error) {
                fb.html(badge('green', 'Done') + ' ' + esc(res.data.message));
                var nv = res.data.new_value;
                var isOn = (nv === 'yes' || nv === 'on' || nv === 'auto-on' || nv === 'auto');
                var label = isOn
                    ? '<span style="color:#dc2626;font-weight:600;">' + esc(nv) + '</span>'
                    : '<span style="color:#166534;">' + esc(nv) + '</span>';
                btn.textContent = isOn ? 'Set Off' : 'Set On';
                btn.disabled = false;
                $(btn).parent().contents().first().replaceWith($(label));
            } else {
                fb.html(badge('red', 'Error') + ' ' + esc(res.data.error || 'Unknown error'));
                btn.textContent = 'Retry';
                btn.disabled = false;
            }
        }).fail(function () {
            btn.textContent = 'Failed';
            btn.disabled = false;
        });
    };

    window.scrubdbDeleteOption = function (optionId, optionName, btn) {
        if (!confirm('Delete option "' + optionName + '"?\n\nThis cannot be undone.')) return;
        btn.disabled = true;
        btn.textContent = '…';
        $.post(scrubdb.ajaxUrl, {
            action: 'scrubdb', nonce: scrubdb.nonce,
            task: 'delete_option', mode: 'clean',
            option_id: optionId, option_name: optionName
        }).done(function (res) {
            var fb = $('#scrubdb-option-feedback');
            if (res.success && !res.data.error) {
                fb.html(badge('green', 'Deleted') + ' ' + esc(res.data.message));
                $('#opt-row-' + optionId).fadeOut(300, function () { $(this).remove(); });
            } else {
                fb.html(badge('red', 'Error') + ' ' + esc(res.data.error || 'Unknown error'));
                btn.textContent = 'Delete';
                btn.disabled = false;
            }
        }).fail(function () {
            btn.textContent = 'Failed';
            btn.disabled = false;
        });
    };

    // ─────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────

    function badge(type, text) {
        return '<span class="scrubdb-badge scrubdb-badge-' + type + '">' + esc(text) + '</span>';
    }

    function stat(value, label) {
        return '<div class="scrubdb-stat"><span class="scrubdb-stat-value">' +
            esc(String(value)) + '</span><span class="scrubdb-stat-label">' +
            esc(label) + '</span></div>';
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

    // ─────────────────────────────────────────────────
    // CHECK FOR UPDATES
    // ─────────────────────────────────────────────────

    window.scrubdbCheckUpdate = function () {
        var $btn = $('#scrubdb-check-update-btn');
        var $result = $('#scrubdb-update-result');

        $btn.prop('disabled', true).html('<span class="scrubdb-spinner"></span> Checking…');

        $.post(scrubdb.ajaxUrl, {
            action: 'scrubdb_check_update',
            nonce: scrubdb.nonce
        }).done(function (res) {
            if (!res.success) {
                $result.html('<div class="scrubdb-update-status scrubdb-update-available"><span class="dashicons dashicons-warning"></span><div><strong>Check failed</strong></div></div>');
                return;
            }
            var d = res.data;
            var h = '';
            if (d.status === 'up_to_date') {
                h += '<div class="scrubdb-update-status scrubdb-update-ok">';
                h += '<span class="dashicons dashicons-yes-alt"></span>';
                h += '<div><strong>You\'re up to date</strong>';
                h += '<p style="margin:4px 0 0;font-size:12px;color:#64748b;">Running the latest version (v' + esc(d.current_version) + ').</p></div></div>';
            } else if (d.status === 'update_available') {
                h += '<div class="scrubdb-update-status scrubdb-update-available">';
                h += '<span class="dashicons dashicons-update"></span>';
                h += '<div><strong>Version ' + esc(d.remote_version) + ' is available</strong>';
                h += '<p style="margin:4px 0 0;font-size:12px;">You\'re running v' + esc(d.current_version) + '. ';
                h += '<a href="' + esc(d.plugins_url) + '">Go to Plugins page to update</a></p></div></div>';
            } else {
                h += '<div class="scrubdb-update-status scrubdb-update-available">';
                h += '<span class="dashicons dashicons-warning"></span>';
                h += '<div><strong>' + esc(d.message) + '</strong></div></div>';
            }
            $result.html(h);
        }).fail(function () {
            $result.html('<div class="scrubdb-update-status scrubdb-update-available"><span class="dashicons dashicons-warning"></span><div><strong>Request failed. Try again.</strong></div></div>');
        }).always(function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Check for Updates');
        });
    };

})(jQuery);
