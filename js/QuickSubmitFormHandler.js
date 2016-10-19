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
(function($) { // TODO defstat: According to https://pkp.sfu.ca/wiki/index.php?title=JavaScript_coding_conventions maybe should be added to templates/common/minifiedScripts.tpl. Where is that file?
    /** @type {Object} */
    $.pkp.plugins.importexport = // TODO defstat: Maybe should be added to the lib\pkp\js\classes\Helper.js file along the other declarations. 
        $.pkp.plugins.importexport
        || {};

    $.pkp.plugins.importexport.quickSubmit =
        $.pkp.plugins.importexport.quickSubmit
        || { js: {} };



    /**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
    $.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFormHandler =
			function ($form, options) {

			    this.parent($form, options);
			    this.callbackWrapper(this.updatePatternFormElementStatus_());
			};
    $.pkp.classes.Helper.inherits(
			$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFormHandler,
			$.pkp.controllers.form.FormHandler);


    /**
	 * Callback to replace the element's content.
	 *
	 * @private
	 */
    $.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFormHandler.prototype.
			updatePatternFormElementStatus_ =
			function () {
			    $('input[type=radio][name=articleStatus]').change(function () {
			        if ($(this).is(':checked') && this.value == '0') {
			            $('#issueId').prop('disabled', 'disabled');
			        }
			        else if ($(this).is(':checked') && this.value == '1') {
			            $('#issueId').attr('disabled', false);
			        }
			        else {
			            $('#issueId').prop('disabled', 'disabled');
			        }
			    });

			    $('input[type=radio][name=articleStatus]').trigger('change');
			};

    /** @param {jQuery} $ jQuery closure. */
}(jQuery));
