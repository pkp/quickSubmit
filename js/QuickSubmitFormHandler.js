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
		
		$('#publishedOption').hide();
		$('#publishedOption').find('input[name="published"]').attr('checked', false);

		$('#locale, #sectionId').on('change', function() {
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
		$.pkp.controllers.form.FormHandler
	);


	/**
	 * Callback to replace the element's content.
	 *
	 * @private
	 */
	$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFormHandler.prototype.
			updateSchedulePublicationDiv_ = function() {

		var self = this;

		$('input[type=radio][name=articleStatus]').on('change', function() {
			if ($(this).is(':checked') && this.value == '0') {
				$('#schedulePublicationDiv').hide();
			} else if ($(this).is(':checked') && this.value == '1') {
				$('#schedulePublicationDiv').show();
			} else {
				$('#schedulePublicationDiv').hide();
			}
		});

		$('input[type=radio][name=articleStatus]').trigger('change');

		$('input[name="published"]').on('change', function() {
			var val, array;
			val = /** @type {string} */ $('#issuesPublicationDates').val();
			array = JSON.parse(val);
			if ($(this).is(':checked')) {
				self.displayDatePublished_($('#issueId').val(), array);
			} else {
				$('input[name="datePublished"]').prop('required', false)
				$('#schedulingInformationDatePublished').hide();
			}
		});

		$('#issueId').on('change', function() {
			$('#publishedOption').find('input[name="published"]').attr('checked', false);
			$('#schedulingInformationDatePublished').find('input[name="datePublished"]').prop('required', false);

			var val, array, futureIssues, issueId;
			val = /** @type {string} */ $('#issuesPublicationDates').val();
			array = JSON.parse(val);
			futureIssues = JSON.parse($('#futureIssues').val());
			issueId = parseInt($('#issueId').val());

			if (issueId === 0) {
				self.displayDatePublished_(issueId, array);
			} else if (!array[issueId]) {
				$('input[name="datePublished"]').prop('required', false);
				$('#schedulingInformationDatePublished').hide();
			} else {
				if (futureIssues.includes(issueId)) {
					$('input[name="datePublished"]').prop('required', false);
					$('#schedulingInformationDatePublished').hide();
				} else {
					self.displayDatePublished_(issueId, array);
				}
			}
			
			futureIssues.includes(issueId)
				? $('#publishedOption').show()
				: $('#publishedOption').hide();
		});

		$('#issueId').trigger('change');
	};

	$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFormHandler.prototype.displayDatePublished_ = function(issueId, issuesPublicationDates) {
		$('input[name="datePublished"]').prop('required', true);
		$('input[name="datePublished"]').datepicker('setDate', issuesPublicationDates[issueId]);
		$('input[name="datePublished"]').val(issuesPublicationDates[issueId]);
		$('#ui-datepicker-div').hide();
		$('#schedulingInformationDatePublished').show();
	};

	/** @param {jQuery} $ jQuery closure. */
}(jQuery));
