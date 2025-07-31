/*
 * MLRichEditor plugin
 *
 * Data attributes:
 * - data-control="mlricheditor" - enables the plugin on an element
 * - data-textarea-element="textarea#id" - an option with a value
 *
 * JavaScript API:
 * $('a#someElement').mlRichEditor({ option: 'value' })
 *
 */

+function ($) { "use strict";

    var Base = $.wn.foundation.base,
        BaseProto = Base.prototype

    // MLRICHEDITOR CLASS DEFINITION
    // ============================

    var MLRichEditor = function(element, options) {
        this.options     = options
        this.$el         = $(element)
        this.$textarea   = $(options.textareaElement)
        this.$richeditor = $('[data-control=richeditor]:first', this.$el)
        this.editor      = this.$textarea.data('froala.editor')
        this.isFocused   = false

        $.wn.foundation.controlUtils.markDisposable(element)
        Base.call(this)

        // Init
        this.init()
    }

    MLRichEditor.prototype = Object.create(BaseProto)
    MLRichEditor.prototype.constructor = MLRichEditor

    MLRichEditor.DEFAULTS = {
        textareaElement: null,
        copyHandler: null,
        placeholderField: null,
        defaultLocale: 'en'
    }

    MLRichEditor.prototype.init = function() {
        this.$el.multiLingual()

        this.$el.on('setLocale.oc.multilingual', this.proxy(this.onSetLocale))
        this.$el.on('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))
        this.$textarea.on('syncContent.oc.richeditor', this.proxy(this.onSyncContent))

        this.editor.events.on('focus', this.proxy(this.toggleIsFocused));
        this.editor.events.on('blur', this.proxy(this.toggleIsFocused));

        this.updateLayout()

        $(window).on('resize', this.proxy(this.updateLayout))
        $(window).on('oc.updateUi', this.proxy(this.updateLayout))
        this.$el.one('dispose-control', this.proxy(this.dispose))
    }
    MLRichEditor.prototype.toggleIsFocused = function () {
        this.isFocused = !this.isFocused
        this.updateLayout()
    }

    MLRichEditor.prototype.dispose = function() {
        this.$el.off('setLocale.oc.multilingual', this.proxy(this.onSetLocale))
        this.$el.off('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))
        this.$textarea.off('syncContent.oc.richeditor', this.proxy(this.onSyncContent))
        $(window).off('resize', this.proxy(this.updateLayout))
        $(window).off('oc.updateUi', this.proxy(this.updateLayout))

        this.editor.events.off('focus', this.proxy(this.toggleIsFocused));
        this.editor.events.off('blur', this.proxy(this.toggleIsFocused));
        this.$el.off('dispose-control', this.proxy(this.dispose))

        this.$el.removeData('oc.mlRichEditor')

        this.$textarea = null
        this.$richeditor = null
        this.$el = null

        this.options = null

        BaseProto.dispose.call(this)
    }

    MLRichEditor.prototype.onSetLocale = function(e, locale, localeValue) {
        if (typeof localeValue === 'string' && this.$richeditor.data('oc.richEditor')) {
            this.$richeditor.richEditor('setContent', localeValue);
        }
    }
    MLRichEditor.prototype.onCopyLocale = function(e, locale, localeValue) {
        if (typeof localeValue === 'string' && this.$richeditor.data('oc.richEditor')) {
            this.$richeditor.richEditor('setContent', localeValue);
        }
    }

    MLRichEditor.prototype.onSyncContent = function(ev, richeditor, value) {
        this.$el.multiLingual('setLocaleValue', value.html)
    }

    MLRichEditor.prototype.updateLayout = function() {
        var $btn = $('.ml-btn[data-active-locale]:first', this.$el),
            $dropdown = $('.ml-dropdown-menu[data-locale-dropdown]:first', this.$el),
            $copyBtn = $('.ml-copy-btn:first', this.$el),
            $copyDropdown = $('.ml-copy-dropdown-menu:first', this.$el),
            $toolbar = $('.fr-toolbar', this.$el),
            $element = $('.fr-element', this.$el)

        if ($toolbar.length) {
            var height = $toolbar.outerHeight(true)
            if (height) {
                $btn.css('top', height + 1)
                $dropdown.css('top', height + 34)
                $copyBtn.css('top', height + 1)
                $copyDropdown.css('top', height + 34)
            }
        }

        // Hide locale buttons while editor is focused
        if (this.isFocused) {
            $btn.hide()
            $copyBtn.hide()
        } else {
            $btn.show()
            $copyBtn.show()
        }
        // set ML button position
        var hasScrollbar = false
        var scrollbarWidth = 0
        var elementHeight = $element.outerHeight()

        setMLButtonPosition()
        $element.on('keydown keyup', setMLButtonPosition)

        function setMLButtonPosition() {
            var scrollHeight = $element[0].scrollHeight
            var showScrollbar = scrollHeight > elementHeight

            if (!hasScrollbar && showScrollbar) {

                hasScrollbar = true
                if (!scrollbarWidth) scrollbarWidth = $element[0].offsetWidth - $element[0].clientWidth

                $element.css('padding-right', scrollbarWidth + 23)
                $btn.css('right', scrollbarWidth + 1)
                $dropdown.css('right', scrollbarWidth - 2)

            } else if (hasScrollbar && !showScrollbar) {
                hasScrollbar = false
                $element.css('padding-right', '')
                $btn.css('right', '')
                $dropdown.css('right', '')
            }
        }

    }

    // MLRICHEDITOR PLUGIN DEFINITION
    // ============================

    var old = $.fn.mlRichEditor

    $.fn.mlRichEditor = function (option) {
        var args = Array.prototype.slice.call(arguments, 1), result
        this.each(function () {
            var $this   = $(this)
            var data    = $this.data('oc.mlRichEditor')
            var options = $.extend({}, MLRichEditor.DEFAULTS, $this.data(), typeof option == 'object' && option)
            if (!data) $this.data('oc.mlRichEditor', (data = new MLRichEditor(this, options)))
            if (typeof option == 'string') result = data[option].apply(data, args)
            if (typeof result != 'undefined') return false
        })

        return result ? result : this
    }

    $.fn.mlRichEditor.Constructor = MLRichEditor

    // MLRICHEDITOR NO CONFLICT
    // =================

    $.fn.mlRichEditor.noConflict = function () {
        $.fn.mlRichEditor = old
        return this
    }

    // MLRICHEDITOR DATA-API
    // ===============

    $(document).render(function () {
        $('[data-control="mlricheditor"]').mlRichEditor()
    })

}(window.jQuery);
