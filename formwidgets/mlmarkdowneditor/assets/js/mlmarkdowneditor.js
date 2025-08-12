/*
 * MLMarkdownEditor plugin
 *
 * Data attributes:
 * - data-control="mlmarkdowneditor" - enables the plugin on an element
 * - data-textarea-element="textarea#id" - an option with a value
 *
 * JavaScript API:
 * $('a#someElement').mlMarkdownEditor({ option: 'value' })
 *
 */

+function ($) { "use strict";

    var Base = $.wn.foundation.base,
        BaseProto = Base.prototype

    // MLMARKDOWNEDITOR CLASS DEFINITION
    // ============================

    var MLMarkdownEditor = function(element, options) {
        this.options         = options
        this.$el             = $(element)
        this.$textarea       = $(options.textareaElement)
        this.$markdownEditor = $('[data-control=markdowneditor]:first', this.$el)
        this.$code           = $('.editor-code', this.$el)
        this.codeEditor      = ace.edit(this.$code.attr('id'))
        this.isFocused       = false

        $.wn.foundation.controlUtils.markDisposable(element)
        Base.call(this)

        // Init
        this.init()
    }

    MLMarkdownEditor.prototype = Object.create(BaseProto)
    MLMarkdownEditor.prototype.constructor = MLMarkdownEditor

    MLMarkdownEditor.DEFAULTS = {
        textareaElement: null,
        placeholderField: null,
        copyHandler: null,
        defaultLocale: 'en'
    }

    MLMarkdownEditor.prototype.init = function() {
        this.$el.multiLingual()

        this.$el.on('setLocale.oc.multilingual', this.proxy(this.onSetLocale))
        this.$el.on('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))
        this.$textarea.on('changeContent.oc.markdowneditor', this.proxy(this.onChangeContent))

        this.codeEditor.on('blur', this.proxy(this.toggleIsFocused))
        this.codeEditor.on('focus', this.proxy(this.toggleIsFocused))

        this.updateLayout()

        $(window).on('resize', this.proxy(this.updateLayout))
        $(window).on('oc.updateUi', this.proxy(this.updateLayout))
        this.$el.one('dispose-control', this.proxy(this.dispose))
    }

    MLMarkdownEditor.prototype.dispose = function() {
        this.$el.off('setLocale.oc.multilingual', this.proxy(this.onSetLocale))
        this.$el.off('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))
        this.$textarea.off('changeContent.oc.markdowneditor', this.proxy(this.onChangeContent))
        this.$el.off('dispose-control', this.proxy(this.dispose))
        this.codeEditor.off('blur', this.proxy(this.toggleIsFocused))
        this.codeEditor.off('focus', this.proxy(this.toggleIsFocused))

        this.$el.removeData('oc.mlMarkdownEditor')

        this.$textarea = null
        this.$markdownEditor = null
        this.$el = null

        this.options = null

        BaseProto.dispose.call(this)
    }

    MLMarkdownEditor.prototype.onSetLocale = function(e, locale, localeValue) {
        if (typeof localeValue === 'string' && this.$markdownEditor.data('oc.markdownEditor')) {
            this.$markdownEditor.markdownEditor('setContent', localeValue);
        }
    }

    MLMarkdownEditor.prototype.onCopyLocale = function(e, locale, localeValue) {
        if (typeof localeValue === 'string' && this.$markdownEditor.data('oc.markdownEditor')) {
            this.$markdownEditor.markdownEditor('setContent', localeValue);
        }
    }

    MLMarkdownEditor.prototype.onChangeContent = function(ev, markdowneditor, value) {
        this.$el.multiLingual('setLocaleValue', value)
    }

    MLMarkdownEditor.prototype.toggleIsFocused = function () {
        this.isFocused = !this.isFocused
        this.updateLayout()
    }

    MLMarkdownEditor.prototype.updateLayout = function() {
        var $btn = $('.ml-btn[data-active-locale]:first', this.$el),
            $dropdown = $('.ml-dropdown-menu[data-locale-dropdown]:first', this.$el),
            $copyBtn = $('.ml-copy-btn:first', this.$el),
            $copyDropdown = $('.ml-copy-dropdown-menu:first', this.$el),
            $toolbar = $('.control-toolbar', this.$el),
            $container = $('.editor-write', this.$el),
            $scrollbar = $('.ace_scrollbar', this.$el),
            $input = $('.ace_text-input', this.$el)

        if ($toolbar.length) {
            var height = $toolbar.outerHeight(true)
            if (height) {
                $btn.css('top', height + 1)
                $dropdown.css('top', height + 34)
                $copyBtn.css('top', height + 1)
                $copyDropdown.css('top', height + 34)
            }
        }
        // set ML button position
        var $container = $('.editor-write', this.$el),
            $previewContainer = $('.editor-preview', this.$el),
            $scrollbar = $('.ace_scrollbar', this.$el),
            $input = $('.ace_text-input', this.$el)

        // Hide locale buttons while editor is focused
        if (this.isFocused) {
            $btn.hide()
            $copyBtn.hide()
        } else {
            $btn.show()
            $copyBtn.show()
        }

        // fix exit fullscreen
        setTimeout(function() {
            setMLButtonPosition()
        }, 0)

        // input listener
        $input.on('keydown keyup', setMLButtonPosition)

        function setMLButtonPosition() {

            // make sure container is displayed (fix previewmode)
            $container.css('display', 'initial')

            var scrollbarWidth = $scrollbar[0].offsetWidth - 5
            if (scrollbarWidth === -5) scrollbarWidth = $previewContainer[0].offsetWidth - $previewContainer[0].clientWidth

            if (scrollbarWidth >= 0) {
                $container.css('padding-right', scrollbarWidth + 23)
                $btn.css('right', scrollbarWidth + 1)
                $dropdown.css('right', scrollbarWidth - 1)
            } else {
                $container.css('padding-right', '')
                $btn.css('right', '')
                $dropdown.css('right', '')
            }

            // reset container
            $container.css('display', '')

        }

    }

    // MLMARKDOWNEDITOR PLUGIN DEFINITION
    // ============================

    var old = $.fn.mlMarkdownEditor

    $.fn.mlMarkdownEditor = function (option) {
        var args = Array.prototype.slice.call(arguments, 1), result

        this.each(function () {
            var $this   = $(this)
            var data    = $this.data('oc.mlMarkdownEditor')
            var options = $.extend({}, MLMarkdownEditor.DEFAULTS, $this.data(), typeof option == 'object' && option)
            if (!data) $this.data('oc.mlMarkdownEditor', (data = new MLMarkdownEditor(this, options)))
            if (typeof option == 'string') result = data[option].apply(data, args)
            if (typeof result != 'undefined') return false
        })

        return result ? result : this
    }

    $.fn.mlMarkdownEditor.Constructor = MLMarkdownEditor;

    // MLMARKDOWNEDITOR NO CONFLICT
    // =================

    $.fn.mlMarkdownEditor.noConflict = function () {
        $.fn.mlMarkdownEditor = old
        return this
    }

    // MLMARKDOWNEDITOR DATA-API
    // ===============

    $(document).render(function (){
        $('[data-control="mlmarkdowneditor"]').mlMarkdownEditor()
    })


}(window.jQuery);
