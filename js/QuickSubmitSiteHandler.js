/**
 * @defgroup plugins_importexport_quickSubmit_js
 */
/**
 * @file plugins/importexport/quickSubmit/js/QuickSubmitFormHandler.js
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QuickSubmitFormHandler.js
 * @ingroup plugins_importexport_quickSubmit_js
 *
 * @brief Handle the quickSubmit form.
 */
(function($) { // TODO defstat: Should I add that to registry/minifiedScripts.txt? No other plugin script there.

    $.pkp.plugins.importexport.quickSubmit =
        $.pkp.plugins.importexport.quickSubmit
        || { js: {} };

    /**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.SiteHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
    $.pkp.plugins.importexport.quickSubmit.js.QuickSubmitSiteHandler =
			function ($widgetWrapper, options) {

				this.parent($widgetWrapper, options);
				this.pageUnloadHandler_ = function () {
					return "blabla";
				};
			};

    $.pkp.classes.Helper.inherits(
			$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitSiteHandler,
			$.pkp.controllers.SiteHandler);


    $.pkp.plugins.importexport.quickSubmit.js.QuickSubmitSiteHandler.prototype.
		pageUnloadHandler_ = 
		function (object, event) {
			return "blabla";
		}

    /** @param {jQuery} $ jQuery closure. */
}(jQuery));
