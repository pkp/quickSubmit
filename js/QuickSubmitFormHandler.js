/**
 * @defgroup plugins_importexport_quickSubmit_js
 */
/**
 * @file js/QuickSubmitFormHandler.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class QuickSubmitFormHandler.js
 * @ingroup plugins_importexport_quickSubmit_js
 *
 * @brief Handle the quickSubmit form.
 */
(function($) {

	/** @type {Object} */
	$.pkp.plugins.importexport.quickSubmit =
			$.pkp.plugins.importexport.quickSubmit ||
			{ js: {} };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.FormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFormHandler =
			function($form, options) {

		this.parent($form, options);
		this.callbackWrapper(this.updateSchedulePublicationDiv_());

		$('#locale, #sectionId').change(function() {
			// Trick the form not to validate missing data before submitting
			$('input,textarea,select').filter('[required]').each(function() {
				$(this).removeAttr('required');
				$(this).removeClass('required');
			});

			// This submit is for relocalisation of the form
			$('#reloadForm').val('1');

			// Submit the form
			$('#quickSubmitForm').submit();
		});

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
			updateSchedulePublicationDiv_ = function() {

		$('input[type=radio][name=articleStatus]').change(function() {
			if ($(this).is(':checked') && this.value == '0') {
				$('#schedulePublicationDiv').hide();
			} else if ($(this).is(':checked') && this.value == '1') {
				$('#schedulePublicationDiv').show();
			} else {
				$('#schedulePublicationDiv').hide();
			}
		});

		$('input[type=radio][name=articleStatus]').trigger('change');

		$('#issueId').change(function() {
			var val, array;
			val = /** @type {string} */ $('#issuesPublicationDates').val();
			array = JSON.parse(val);
			if (!array[$('#issueId').val()]) {
				$('#schedulingInformationDatePublished').hide();
			} else {
				$('input[name="datePublished"]').
						datepicker('setDate', array[$('#issueId').val()]);
				$('#ui-datepicker-div').hide();
				$('#schedulingInformationDatePublished').show();
			}
		});

		$('#issueId').trigger('change');
	};

	/** @param {jQuery} $ jQuery closure. */
}(jQuery));
