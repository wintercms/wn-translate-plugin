/*
 * MLBlocks plugin
 *
 * Data attributes:
 * - data-control="mlblocks" - enables the plugin on an element
 *
 * JavaScript API:
 * $('a#someElement').mlBlocks({ option: 'value' })
 *
 */

+function ($) { "use strict";

    var Base = $.wn.foundation.base,
        BaseProto = Base.prototype

    // MLBLOCKS CLASS DEFINITION
    // ============================

    var MLBlocks = function(element, options) {
        this.options   = options
        this.$el       = $(element)
        this.$selector = $('[data-locale-dropdown]', this.$el)
        this.$locale   = $('[data-blocks-active-locale]', this.$el)
        this.locale    = options.defaultLocale

        $.wn.foundation.controlUtils.markDisposable(element)
        Base.call(this)

        // Init
        this.init()
    }

    MLBlocks.prototype = Object.create(BaseProto)
    MLBlocks.prototype.constructor = MLBlocks

    MLBlocks.DEFAULTS = {
        switchHandler: null,
        copyHandler: null,
        defaultLocale: 'en'
    }

    MLBlocks.prototype.init = function() {
        this.$el.multiLingual()

        this.checkEmptyItems()
        this.updateLayout()

        $(document).on('render', this.proxy(this.checkEmptyItems))

        this.$el.on('setLocale.oc.multilingual', this.proxy(this.onSetLocale))
        this.$el.on('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))

        this.$el.one('dispose-control', this.proxy(this.dispose))
    }

    MLBlocks.prototype.dispose = function() {

        $(document).off('render', this.proxy(this.checkEmptyItems))

        this.$el.off('setLocale.oc.multilingual', this.proxy(this.onSetLocale))
        this.$el.off('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))

        this.$el.off('dispose-control', this.proxy(this.dispose))

        this.$el.removeData('oc.mlBlocks')

        this.$selector = null
        this.$locale = null
        this.locale = null
        this.$el = null

        this.options = null

        BaseProto.dispose.call(this)
    }

    MLBlocks.prototype.checkEmptyItems = function() {
        var isEmpty = !$('ul.field-repeater-items > li', this.$el).length
        this.$el.toggleClass('is-empty', isEmpty)
    }

    MLBlocks.prototype.updateLayout = function() {
        // If this widget does NOT have a label and comment
        // then add margin for the locale buttons
        if (
            this.$el.siblings('label').length === 0 &&
            this.$el.siblings('p').length === 0
        ) {
            this.$el.css('margin-top','36px')
        }
    }
    MLBlocks.prototype.onCopyLocale = function(e, locale, localeValue) {
        var self = this,
            copyFromLocale = this.locale

        this.$el
            .addClass('loading-indicator-container size-form-field')
            .loadIndicator()

        this.$el.request(this.options.copyHandler, {
            data: {
                _blocks_copy_locale: copyFromLocale,
            },
            success: function(data) {
                self.$el.loadIndicator('hide')
                this.success(data)
            }
        })
    }

    MLBlocks.prototype.onSetLocale = function(e, locale, localeValue) {
        var self = this,
            previousLocale = this.locale

        this.$el
            .addClass('loading-indicator-container size-form-field')
            .loadIndicator()

        this.locale = locale
        this.$locale.val(locale)

        this.$el.request(this.options.switchHandler, {
            data: {
                _blocks_previous_locale: previousLocale,
                _blocks_locale: locale
            },
            success: function(data) {
                self.$el.multiLingual('setLocaleValue', data.updateValue, data.updateLocale)
                self.$el.loadIndicator('hide')
                this.success(data)
            }
        })
    }

    // MLBLOCKS PLUGIN DEFINITION
    // ============================

    var old = $.fn.mlBlocks

    $.fn.mlBlocks = function (option) {
        var args = Array.prototype.slice.call(arguments, 1), result
        this.each(function () {
            var $this   = $(this)
            var data    = $this.data('oc.mlBlocks')
            var options = $.extend({}, MLBlocks.DEFAULTS, $this.data(), typeof option == 'object' && option)
            if (!data) $this.data('oc.mlBlocks', (data = new MLBlocks(this, options)))
            if (typeof option == 'string') result = data[option].apply(data, args)
            if (typeof result != 'undefined') return false
        })

        return result ? result : this
    }

    $.fn.mlBlocks.Constructor = MLBlocks

    // MLBLOCKS NO CONFLICT
    // =================

    $.fn.mlBlocks.noConflict = function () {
        $.fn.mlBlocks = old
        return this
    }

    // MLBLOCKS DATA-API
    // ===============

    $(document).render(function () {
        $('[data-control="mlblocks"]').mlBlocks()
    })

}(window.jQuery);
