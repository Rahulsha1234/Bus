/**
 * SwiftBus Searchable Combobox
 * - Input placed DIRECTLY inside .input-group (no wrapper div) → icon stays aligned
 * - Pure custom dropdown (no Bootstrap .dropdown-menu class) → no CSS conflicts
 * - overflow-y: scroll forced → scrollbar always visible when items overflow
 * - _suppressSync flag → prevents double-set jerk on item click
 */
function convertToSearchableCombobox(selectId, placeholderText) {
    var $select = $('#' + selectId);
    if ($select.length === 0) return;

    // Prevent the 'change' listener from re-syncing the input
    // when the click handler already set it (avoids the visual jerk).
    var _suppressSync = false;

    // ── 1. Position anchor for the absolute dropdown ──────────────────
    var $group = $select.closest('.input-group');
    var $anchor;

    if ($group.length > 0) {
        // .input-group already has position:relative via Bootstrap
        $group.css('position', 'relative');
        $anchor = $group;
    } else {
        var $fb = $('<div>').css({ position: 'relative', display: 'block', width: '100%' });
        $select.before($fb);
        $fb.append($select);
        $anchor = $fb;
    }

    // ── 2. Visible text <input> ──────────────────────────────────────
    // Replace 'form-select' with 'form-control' to keep Bootstrap border classes
    var cls   = ($select.attr('class') || '').replace(/\bform-select\b/g, 'form-control');
    var style = $select.attr('style') || '';

    var $input = $('<input>', {
        type        : 'text',
        placeholder : placeholderText,
        autocomplete: 'off'
    })
    .addClass(cls + ' combobox-input')
    .attr('style', style)
    .css({ textTransform: 'uppercase', flex: '1 1 auto', minWidth: '0' });

    // Insert directly inside .input-group — keeps icon + input on one row
    $select.before($input);
    $select.hide();

    // ── 3. Dropdown <ul> — fully custom, no Bootstrap dropdown classes ─
    var $dropdown = $('<ul>')
        .addClass('cb-dropdown')
        .css({
            position    : 'absolute',
            top         : '100%',
            left        : '0',
            right       : '0',
            zIndex      : '2000',
            maxHeight   : '220px',
            overflowY   : 'scroll',   // always show scrollbar track
            display     : 'none',
            marginTop   : '4px',
            padding     : '4px',
            listStyle   : 'none',
            borderRadius: '10px'
        });

    $anchor.append($dropdown);

    // ── 4. Build / filter the dropdown items ─────────────────────────
    function buildDropdown(filter) {
        filter = (filter || '').trim().toUpperCase();
        $dropdown.empty();

        var added = 0;

        $select.find('option').each(function () {
            if ($(this).attr('data-custom') === 'true') return;   // skip manually-typed custom values
            var val  = $.trim($(this).val());
            var text = $.trim($(this).text());
            if (!val || /^select/i.test(text)) return;            // skip placeholders

            var upper = text.toUpperCase();
            if (filter && upper.indexOf(filter) === -1) return;   // filter match

            var $li = $('<li>').addClass('cb-item').text(upper).attr('data-value', val);
            $dropdown.append($li);
            added++;
        });

        if (added === 0) {
            var msg = filter ? 'No result for "' + filter + '"' : 'No options yet';
            $dropdown.append($('<li>').addClass('cb-empty').text(msg));
        }

        return added;
    }

    // ── 5. Pre-fill input from initial select value ───────────────────
    (function () {
        var val  = $select.val();
        var text = $.trim($select.find('option:selected').text());
        if (val && text && !/^select/i.test(text)) {
            $input.val(text.toUpperCase());
        }
    }());

    // ── 6. Events ─────────────────────────────────────────────────────

    // Open on focus or click
    $input.on('focus click', function (e) {
        e.stopPropagation();
        buildDropdown($input.val());
        // close all OTHER combobox dropdowns first
        $('.cb-dropdown').not($dropdown).hide();
        $dropdown.show();
    });

    // Live filter while typing
    $input.on('input', function () {
        buildDropdown($(this).val());
        $dropdown.show();
    });

    // Click an item — suppress sync to avoid jerk
    $dropdown.on('click', '.cb-item', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var val  = $(this).attr('data-value');
        var text = $(this).text();

        // 1. Update visible input immediately (no jerk, no re-set)
        $input.val(text);

        // 2. Ensure the option exists in the hidden select
        $select.find('option[data-custom="true"]').remove();
        if (!$select.find('option[value="' + val + '"]').length) {
            $select.append($('<option>', { value: val, text: text }));
        }

        // 3. Sync select + fire change for AJAX side effects
        //    _suppressSync keeps the change handler from overwriting $input
        _suppressSync = true;
        $select.val(val).trigger('change');
        _suppressSync = false;

        $dropdown.hide();
    });

    // Blur — hide + sync
    $input.on('blur', function () {
        setTimeout(function () {
            $dropdown.hide();

            var typed = $input.val().trim().toUpperCase();
            $select.find('option[data-custom="true"]').remove();

            if (!typed) {
                _suppressSync = true;
                $select.val('').trigger('change');
                _suppressSync = false;
                return;
            }

            var matched = false;
            $select.find('option').each(function () {
                if ($.trim($(this).text()).toUpperCase() === typed ||
                    $.trim($(this).val()).toUpperCase()  === typed) {
                    _suppressSync = true;
                    $select.val($(this).val()).trigger('change');
                    _suppressSync = false;
                    matched = true;
                    return false;
                }
            });

            if (!matched) {
                var $opt = $('<option>', { value: typed, text: typed }).attr('data-custom', 'true');
                $select.append($opt);
                _suppressSync = true;
                $select.val(typed).trigger('change');
                _suppressSync = false;
            }
        }, 220);
    });

    // External change (AJAX / swapper) → sync the visible input
    $select.on('change', function () {
        if (_suppressSync) return;
        var val  = $(this).val();
        var text = $.trim($(this).find('option:selected').text());
        if (val && text && !/^select/i.test(text)) {
            $input.val(text.toUpperCase());
        } else {
            $input.val('');
        }
    });

    // Called after AJAX reloads the <option> list; re-render dropdown if open
    $select.on('combobox:refresh', function () {
        if ($dropdown.is(':visible')) {
            buildDropdown($input.val());
        }
    });
}

// ── Close all dropdowns on outside click ──────────────────────────────
$(document).on('click', function (e) {
    if (!$(e.target).closest('.input-group, .cb-dropdown').length) {
        $('.cb-dropdown').hide();
    }
});

// ── Global styles + uppercase (skipped on login / register pages) ─────
$(document).ready(function () {
    var isAuth = /\/(login|register)\.php/i.test(window.location.pathname);

    /* Remove any previous style injection */
    $('#cb-global-styles').remove();

    $('<style id="cb-global-styles">')
        .html(
            /* Keep the combobox input stretching inside Bootstrap input-group */
            '.input-group > .combobox-input {\n' +
            '    flex: 1 1 auto;\n' +
            '    min-width: 0;\n' +
            '    width: 1%;\n' +
            '}\n' +

            /* ── Dropdown shell ── */
            '.cb-dropdown {\n' +
            '    background   : #ffffff;\n' +
            '    border       : 1px solid rgba(0,0,0,0.12);\n' +
            '    border-radius: 10px;\n' +
            '    box-shadow   : 0 8px 28px rgba(0,0,0,0.13);\n' +
            '}\n' +
            '[data-theme="dark"] .cb-dropdown {\n' +
            '    background   : #111111;\n' +
            '    border-color : rgba(255,255,255,0.1);\n' +
            '    box-shadow   : 0 8px 28px rgba(0,0,0,0.6);\n' +
            '}\n' +

            /* ── Items ── */
            '.cb-item {\n' +
            '    padding      : 8px 12px;\n' +
            '    cursor       : pointer;\n' +
            '    border-radius: 7px;\n' +
            '    font-size    : 0.875rem;\n' +
            '    font-weight  : 500;\n' +
            '    color        : #1e293b;\n' +
            '    letter-spacing: 0.03em;\n' +
            '    transition   : background 0.12s ease, color 0.12s ease;\n' +
            '}\n' +
            '.cb-item:hover {\n' +
            '    background   : rgba(25,135,84,0.1);\n' +
            '    color        : #198754;\n' +
            '}\n' +
            '[data-theme="dark"] .cb-item { color: #ffffff; }\n' +
            '[data-theme="dark"] .cb-item:hover { background: rgba(25,135,84,0.15); color: #198754; }\n' +

            /* ── Empty state ── */
            '.cb-empty {\n' +
            '    padding  : 10px 12px;\n' +
            '    font-size: 0.8rem;\n' +
            '    color    : #9ca3af;\n' +
            '}\n' +

            /* ── Thin scrollbar ── */
            '.cb-dropdown::-webkit-scrollbar { width: 5px; }\n' +
            '.cb-dropdown::-webkit-scrollbar-track { background: rgba(0,0,0,0.03); border-radius: 99px; }\n' +
            '.cb-dropdown::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.18); border-radius: 99px; }\n' +
            '[data-theme="dark"] .cb-dropdown::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); }\n' +

            /* ── Global uppercase for non-auth pages ── */
            (isAuth ? '' :
                'input:not([type="password"]):not([type="email"]) { text-transform: uppercase; }\n' +
                'textarea { text-transform: uppercase; }\n'
            )
        )
        .appendTo('head');

    /* Live uppercase as user types (preserve cursor position) */
    if (!isAuth) {
        $(document).on('input', 'input:not([type="password"]):not([type="email"]), textarea', function () {
            var s = this.selectionStart, e = this.selectionEnd;
            this.value = this.value.toUpperCase();
            if (s !== null) this.setSelectionRange(s, e);
        });
    }
});
