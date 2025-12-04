/*
 * MLText plugin
 *
 * Data attributes:
 * - data-control="mltext" - enables the plugin on an element
 *
 * JavaScript API:
 * $('a#someElement').mlText({ option: 'value' })
 *
 */

+function($) {

    var Base = $.wn.foundation.base,
        BaseProto = Base.prototype

    "use strict";

    var MLText = function(element, options) {
        this.options = options
        this.$el = $(element)
        $.wn.foundation.controlUtils.markDisposable(element)
        Base.call(this)

        this.init()
    }

    MLText.prototype = Object.create(BaseProto)
    MLText.prototype.constructor = MLText

    MLText.DEFAULTS = {
        autoTranslateHandler: null,
        // switchHan
        // defaultLocale: 'en'
    }

    MLText.prototype.init = function() {
        this.$el.multiLingual()

        // NOTE: this.$el.on('setLocale.oc.multilingual', this.proxy(this.onSetLocale))
        this.$el.on('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))
        this.$el.on('autoTranslateSuccess.oc.multilingual', this.proxy(this.onAutoTranslateSuccess))
        this.$el.one('dispose-control', this.proxy(this.dispose))
    }

    MLText.prototype.dispose = function() {
        this.$el.off('copyLocale.oc.multilingual', this.proxy(this.onCopyLocale))
        this.$el.off('autoTranslateSuccess.oc.multilingual', this.proxy(this.onAutoTranslateSuccess))
        this.$el.off('dispose-control', this.proxy(this.dispose))

        this.$el.removeData('oc.mlText')
        this.$el = null
        this.options = null
    }

    MLText.prototype.onCopyLocale = function(e, {copyFromLocale, copyFromValue, currentLocale, provider}) {
        this.$el.multiLingual('autoTranslate', copyFromLocale, provider)
    }

    MLText.prototype.onAutoTranslateSuccess = function(e, data) {
        const translatedValue = data.translatedValue[0]
        if (data.translatedValue && data.translatedLocale) {
            const $visibleInput = $('input.form-control', this.$el)
            $visibleInput.val(translatedValue).trigger('input')
            this.$el.multiLingual('setLocaleValue', translatedValue, data.translatedLocale)
        }
    }

    $.fn.mlText = function(option) {
        return this.each(function() {
            var $this = $(this)
            var data = $this.data('oc.mlText')
            var options = $.extend({}, MLText.DEFAULTS, $this.data(), typeof option == 'object' && option)

            if (!data) $this.data('oc.mlText', (data = new MLText(this, options)))
            if (typeof option == 'string') data[option]()
        })
    }

    $.fn.mlText.noConflict = function() {
        $.fn.mlText = old
        return this
    }

    $(document).render(function() {
        $('[data-control="mltext"]').mlText()
    })

}(window.jQuery);
