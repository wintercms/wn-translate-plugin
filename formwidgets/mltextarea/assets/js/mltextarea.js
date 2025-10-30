/*
 * MLTextarea plugin
 *
 * Data attributes:
 * - data-control="mltextarea" - enables the plugin on an element
 * - data-auto-translate-handler - AJAX handler for auto translation
 *
 * JavaScript API:
 * $('div#someElement').mlTextarea({ option: 'value' })
 */

+function($) {
    var Base = $.wn.foundation.base,
        BaseProto = Base.prototype

    var MLTextarea = function(element, options) {
        this.options = options
        this.$el = $(element)
        $.wn.foundation.controlUtils.markDisposable(element)
        Base.call(this)
        this.init()
    }

    MLTextarea.prototype = Object.create(BaseProto)
    MLTextarea.prototype.constructor = MLTextarea

    MLTextarea.DEFAULTS = {
        autoTranslateHandler: null
    }

    MLTextarea.prototype.init = function() {
        this.$el.multiLingual()

        // Bind events
        this.$textarea = $('textarea.form-control', this.$el)
        this.$textarea.on('input', this.proxy(this.updateLayout))
        this.$el.on('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))
        this.$el.on('autoTranslateSuccess.oc.multilingual', this.proxy(this.onAutoTranslateSuccess))
        this.$el.one('dispose-control', this.proxy(this.dispose))

        // Initial layout update
        this.updateLayout()
    }

    MLTextarea.prototype.dispose = function() {
        if (this.$textarea) {
            this.$textarea.off('input', this.proxy(this.updateLayout))
        }
        if (this.$el) {
            this.$el.off('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))
            this.$el.off('autoTranslateSuccess.oc.multilingual', this.proxy(this.onAutoTranslateSuccess))
            this.$el.off('dispose-control', this.proxy(this.dispose))
            this.$el.removeData('oc.mlTextarea')
        }

        this.$textarea = null
        this.$el = null
        this.options = null
    }

    MLTextarea.prototype.updateLayout = function() {
        var $element = this.$textarea.get(0)
        if (!$element) return

        var $parent = $element.parentElement
        var $btn = $parent.querySelector('.ml-btn[data-active-locale]')
        var $dropdown = $parent.querySelector('.ml-dropdown-menu[data-locale-dropdown]')
        var $copyBtn = $parent.querySelector('.ml-copy-btn')
        var $copyDropdown = $parent.querySelector('.ml-copy-dropdown-menu')

        // set ML button position
        var elementHeight = $element.offsetHeight
        var scrollHeight = $element.scrollHeight
        var showScrollbar = (scrollHeight - elementHeight) > 0

        if (showScrollbar) {
            var scrollbarWidth = $element.offsetWidth - $element.clientWidth
            $element.style.paddingRight = (scrollbarWidth + 23) + 'px'
            $btn.style.right = (scrollbarWidth - 1) + 'px'
            $btn.style.borderTopRightRadius = '0px'
            $dropdown.style.right = (scrollbarWidth - 2) + 'px'
            $copyBtn.style.right = (scrollbarWidth - 1) + 'px'
            $copyBtn.style.borderTopRightRadius = '0px'
            $copyDropdown.style.right = (scrollbarWidth - 2) + 'px'
        } else {
            $element.style.paddingRight = ''
            $btn.style.right = ''
            $btn.style.borderTopRightRadius = ''
            $dropdown.style.right = ''
            $copyBtn.style.right = ''
            $copyBtn.style.borderTopRightRadius = ''
            $copyDropdown.style.right = ''
        }
    }

    MLTextarea.prototype.onCopyLocale = function(e, {copyFromLocale, copyFromValue, currentLocale, provider}) {
        this.$el.multiLingual('autoTranslate', copyFromLocale, provider)
    }

    MLTextarea.prototype.onAutoTranslateSuccess = function(e, data) {
        if (data.translatedValue && data.translatedLocale) {
            var translatedValue = data.translatedValue[0]
            this.$textarea.val(translatedValue).trigger('input')
            this.$el.multiLingual('setLocaleValue', translatedValue, data.translatedLocale)
        }
    }

    $.fn.mlTextarea = function(option) {
        return this.each(function() {
            var $this = $(this)
            var data = $this.data('oc.mlTextarea')
            var options = $.extend({}, MLTextarea.DEFAULTS, $this.data(), typeof option == 'object' && option)

            if (!data) $this.data('oc.mlTextarea', (data = new MLTextarea(this, options)))
            if (typeof option == 'string') data[option]()
        })
    }

    $.fn.mlTextarea.Constructor = MLTextarea

    $(document).render(function() {
        $('[data-control="mltextarea"]').mlTextarea()
    })

}(window.jQuery);
