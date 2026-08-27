/*
 * Multi lingual control plugin
 *
 * Data attributes:
 * - data-control="multilingual" - enables the plugin on an element
 * - data-default-locale="en" - default locale code
 * - data-placeholder-field="#placeholderField" - an element that contains the placeholder value
 *
 * JavaScript API:
 * $('a#someElement').multiLingual({ option: 'value' })
 *
 * Dependences:
 * - Nil
 */

+function ($) { "use strict";

    // MULTILINGUAL CLASS DEFINITION
    // ============================

    var MultiLingual = function(element, options) {
        var self          = this
        this.options      = options
        this.$el          = $(element)

        this.$activeField  = null
        this.$activeButton = $('[data-active-locale]', this.$el)
        this.$dropdown     = $('ul.ml-dropdown-menu', this.$el)
        this.$placeholder  = $(this.options.placeholderField)

        /*
         * Init locale
         */
        this.activeLocale = this.options.defaultLocale
        this.$activeField = this.getLocaleElement(this.activeLocale)
        this.$activeButton.text(this.activeLocale)

        // Copy-from action: a trailing button on each locale row in the selector
        // copies that row's locale value into the currently-active locale. The
        // overwrite confirmation lives in copyLocale(), covering both paths below.
        this.$dropdown.on('click', '.ml-locale-copy[data-copy-locale]', function(event) {
            event.preventDefault()
            event.stopPropagation()

            var currentLocale = self.activeLocale
            var copyFromLocale = $(this).data('copy-locale')

            // Can't copy a locale onto itself.
            if (!copyFromLocale || currentLocale === copyFromLocale) return;

            // No usable translation provider configured: keep the plain one-click copy.
            var defaultProvider = $(this).data('default-provider')
            var copyOpenHandler = $(this).data('copy-open-handler')
            if (!copyOpenHandler) {
                self.copyLocale(copyFromLocale, '')
                return
            }

            if (defaultProvider && event.shiftKey) {
                self.copyLocale(copyFromLocale, defaultProvider)
                return
            }
            if (event.ctrlKey) {
                self.copyLocale(copyFromLocale, "")
                return
            }
            self.$el.on('complete.oc.popup', function (e, $source, $popup) {
                const $button = $popup.find(`[data-widget-id="${self.$el.attr('id')}"]`)
                $button.on('click', function(event) {
                    const provider = $popup.find('select[name^="translation_provider_"]').val() ?? ""
                    var copyFromLocale = $(this).attr('data-selected-locale')
                    self.copyLocale(copyFromLocale, provider)
                    // Blur the button before closing to prevent accessibility warning
                    $(this).blur();
                    $button.off('click');
                })
                self.$el.off('complete.oc.popup');
            });

            self.$el.popup({
                handler: copyOpenHandler,
                extraData: {
                    _copy_from_locale: copyFromLocale,
                    _current_locale: currentLocale,
                }
            })
        });

        this.$dropdown.on('click', '[data-switch-locale]', this.$activeButton, function(event){
            var currentLocale = event.data.text();
            var selectedLocale = $(this).data('switch-locale')

            // only call setLocale() if locale has changed
            if (selectedLocale != currentLocale) {
                self.setLocale(selectedLocale)
            }

            /*
             * If Ctrl/Cmd key is pressed, find other instances and switch
             */
            if (event.ctrlKey || event.metaKey) {
                event.preventDefault();
                $('[data-switch-locale="'+selectedLocale+'"]').click()
            }
        })

        this.$placeholder.on('input', function(){
            self.$activeField.val(this.value)
            self.updateLocaleIndicators()
        })

        /*
         * Keep the indicators in sync after a copy / auto-translate writes a value.
         */
        this.$el.on('copyLocale.oc.multilingual autoTranslateSuccess.oc.multilingual', function(){
            setTimeout(function(){ self.updateLocaleIndicators() }, 0)
        })

        this.updateLocaleIndicators()

        /*
         * Handle oc.inputPreset.beforeUpdate event
         */
        $('[data-input-preset]', this.$el).on('oc.inputPreset.beforeUpdate', function(event, src) {
            var sourceLocale = src.siblings('.ml-btn[data-active-locale]').text()
            var targetLocale = $(this).data('locale-value')
            var targetActiveLocale = $(this).siblings('.ml-btn[data-active-locale]').text()

            if (sourceLocale && targetLocale && targetActiveLocale) {
                if (targetActiveLocale !== sourceLocale)
                    self.setLocale(sourceLocale)
                $(this).data('update', sourceLocale === targetLocale)
            }
        })
    }

    MultiLingual.DEFAULTS = {
        defaultLocale: 'en',
        defaultField: null,
        placeholderField: null
    }

    MultiLingual.prototype.getLocaleElement = function(locale) {
        var el = this.$el.find('[data-locale-value="'+locale+'"]')
        return el.length ? el : null
    }

    MultiLingual.prototype.getLocaleValue = function(locale) {
        var value = this.getLocaleElement(locale)
        return value ? value.val() : null
    }

    MultiLingual.prototype.setLocaleValue = function(value, locale) {
        if (locale) {
            this.getLocaleElement(locale).val(value)
        }
        else {
            this.$activeField.val(value)
        }
    }

    MultiLingual.prototype.autoTranslate = function(copyFromLocale, provider) {
        var self = this
        if (provider === '') {
            return
        }
        var currentLocale = this.activeLocale
        var copyFromValue = this.getLocaleValue(copyFromLocale)

        if (!copyFromValue || copyFromLocale === currentLocale) {
            return
        }

        this.$el
            .addClass('loading-indicator-container size-form-field')
            .loadIndicator()

        this.$el.request(this.options.autoTranslateHandler, {
            data: {
                _copy_from_locale: copyFromLocale,
                _copy_from_value: copyFromValue,
                _current_locale: currentLocale,
                _provider: provider,
            },
            success: function(data) {
                self.$el.trigger('autoTranslateSuccess.oc.multilingual', [data])
                this.success(data)
            },
            complete: function() {
                self.$el.loadIndicator('hide')
            }
        })
    }

    MultiLingual.prototype.copyLocale = function(copyFromLocale, provider) {
        var self = this
        var currentLocale = this.activeLocale

        // Copying overwrites the active locale's value. When there's existing content
        // that would be discarded, confirm first so an accidental copy can't silently
        // destroy work; copying into an empty locale proceeds without a prompt.
        if (!this.localeHasContent(currentLocale)) {
            this.applyCopyLocale(copyFromLocale, provider)
            return
        }

        var message = 'This replaces the current "' + currentLocale + '" content with the ' +
            'value copied from "' + copyFromLocale + '". Continue?'

        // Prefer the backend's styled confirm; fall back to a native one.
        if ($.wn && typeof $.wn.confirm === 'function') {
            $.wn.confirm(message, function(isConfirm) {
                if (isConfirm) self.applyCopyLocale(copyFromLocale, provider)
            })
        }
        else if (window.confirm(message)) {
            this.applyCopyLocale(copyFromLocale, provider)
        }
    }

    /*
     * Performs the actual copy of a locale's value into the active locale, notifying
     * the widget so it can update / auto-translate.
     */
    MultiLingual.prototype.applyCopyLocale = function(copyFromLocale, provider) {
        var currentLocale = this.activeLocale
        var copyFromLocaleValue = this.getLocaleValue(copyFromLocale)
        this.$activeField.val(copyFromLocaleValue)
        this.$placeholder.val(copyFromLocaleValue)

        this.$el.trigger('copyLocale.oc.multilingual', [{
            copyFromLocale: copyFromLocale,
            copyFromValue: copyFromLocaleValue,
            currentLocale: currentLocale,
            provider: provider,
        }])
    }

    MultiLingual.prototype.setLocale = function(locale) {
        this.activeLocale = locale
        this.$activeField = this.getLocaleElement(locale)
        this.$activeButton.text(locale)

        this.$placeholder.val(this.getLocaleValue(locale))
        this.$el.trigger('setLocale.oc.multilingual', [locale, this.getLocaleValue(locale)])
        this.updateLocaleIndicators()
    }

    /*
     * Whether the field holds meaningful content for the given locale. Empty
     * strings and empty JSON containers (repeater/nestedform/blocks) count as
     * untranslated, i.e. the locale falls back to the default.
     */
    MultiLingual.prototype.localeHasContent = function(locale) {
        var $el = this.getLocaleElement(locale)
        if (!$el || !$el.length) return false
        var raw = $el.val()
        if (raw == null) return false
        var v = ('' + raw).trim()
        return !(v === '' || v === 'null' || v === '[]' || v === '{}' || v === '""')
    }

    /*
     * Marks each locale in the switcher as translated / empty and flags the
     * control when any non-default locale is still untranslated, so editors can
     * see at a glance what remains without opening every field.
     */
    MultiLingual.prototype.updateLocaleIndicators = function() {
        var self = this
        var total = 0, untranslated = 0

        $('[data-switch-locale]', this.$dropdown).each(function() {
            var code = '' + $(this).data('switch-locale')
            var isDefault = (code === self.options.defaultLocale)
            var filled = isDefault || self.localeHasContent(code)

            // Highlight the row of the locale currently being edited.
            $(this).toggleClass('is-current-locale', code === self.activeLocale)

            $('[data-locale-status="' + code + '"]', this)
                .toggleClass('is-default', isDefault)
                .toggleClass('is-filled', filled && !isDefault)
                .toggleClass('is-empty', !filled)

            if (!isDefault) {
                total++
                if (!filled) untranslated++
            }
        })

        // Disable a row's copy button when it's the active locale (copying onto
        // itself is a no-op) or when that locale has nothing to copy from.
        $('.ml-locale-copy', this.$dropdown).each(function() {
            var code = '' + $(this).data('copy-locale')
            var disabled = (code === self.activeLocale) || !self.localeHasContent(code)
            $(this).prop('disabled', disabled).attr('aria-disabled', disabled ? 'true' : 'false')
        })

        this.$el.toggleClass('ml-has-untranslated', untranslated > 0)
        this.$activeButton.attr('title', total === 0
            ? ''
            : (untranslated > 0
                ? (untranslated + '/' + total + ' locales untranslated')
                : 'All locales translated'))
    }

    // MULTILINGUAL PLUGIN DEFINITION
    // ============================

    var old = $.fn.multiLingual

    $.fn.multiLingual = function (option) {
        var args = Array.prototype.slice.call(arguments, 1), result
        this.each(function () {
            var $this   = $(this)
            var data    = $this.data('oc.multilingual')
            var options = $.extend({}, MultiLingual.DEFAULTS, $this.data(), typeof option == 'object' && option)
            if (!data) $this.data('oc.multilingual', (data = new MultiLingual(this, options)))
            if (typeof option == 'string') result = data[option].apply(data, args)
            if (typeof result != 'undefined') return false
        })

        return result ? result : this
    }

    $.fn.multiLingual.Constructor = MultiLingual

    // MULTILINGUAL NO CONFLICT
    // =================

    $.fn.multiLingual.noConflict = function () {
        $.fn.multiLingual = old
        return this
    }

    // SHARED ML HELPERS
    // =================
    // Centralises the auto-translate success handling that every ML widget repeats,
    // validating the response shape before handing the value + locale to the widget.
    $.wn = $.wn || {}
    $.wn.translate = $.wn.translate || {}
    $.wn.translate.applyAutoTranslateResponse = function (data, setValue) {
        if (data && Array.isArray(data.translatedValue) && data.translatedValue.length && data.translatedLocale) {
            setValue(data.translatedValue[0], data.translatedLocale)
        }
    }

    // MULTILINGUAL DATA-API
    // ===============
    $(document).render(function () {
        $('[data-control="multilingual"]').multiLingual()
    })

}(window.jQuery);
