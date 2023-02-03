/**
 * @defgroup plugins_importexport_quickSubmit_js
 */
/**
 * @file js/QuickSubmitFileUploadFormHandler.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class QuickSubmitFileUploadFormHandler
 * @ingroup plugins_importexport_quickSubmit_js
 *
 * @brief QuickSubmit File upload form handler.
 */
(function($) {

	/** @type {Object} */
	$.pkp.plugins.importexport.quickSubmit =
			$.pkp.plugins.importexport.quickSubmit ||
			{ js: {} };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.FileUploadFormHandler
	 *
	 * @param {jQueryObject} $form The wrapped HTML form element.
	 * @param {{
	 *  resetUploader: boolean,
	 *  $uploader: jQueryObject,
	 *  $preview: jQueryObject,
	 *  $extraAltText: jQueryObject,
	 *  uploaderOptions: Object
	 *  }} options Form validation options.
	 */
	$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFileUploadFormHandler =
			function($form, options) {

		this.parent($form, options);
		this.$extraAltText_ = options.$extraAltText;

	};
	$.pkp.classes.Helper.inherits(
			$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFileUploadFormHandler,
			$.pkp.controllers.form.FileUploadFormHandler);


	/**
	 * The file preview DOM element. A jQuery object when available
	 * @private
	 * @type {boolean|jQueryObject}
	 */
	$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFileUploadFormHandler.
			prototype.extraAltText_ = false;


	/**
	 * Handle the response of a "file upload" request.
	 * @param {Object} caller The original context in which the callback was called.
	 * @param {Object} pluploader The pluploader object.
	 * @param {Object} file The data of the uploaded file.
	 * @param {{response: string}} ret The serialized JSON response.
	 */
	$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFileUploadFormHandler.
			prototype.handleUploadResponse = function(caller, pluploader, file, ret) {

		// Handle the server's JSON response.
		var jsonData = /** @type {boolean|{uploadedFile: Object,
				temporaryFileId: string, content: string}} */
				(this.handleJson($.parseJSON(ret.response))),
				$uploadForm, $temporaryFileId;
		if (jsonData !== false) {
			// Trigger the file uploaded event.
			this.trigger('fileUploaded', [jsonData.uploadedFile]);

			// Hide preview if one exists
			if (this.$preview) {
				this.$preview.empty();
				$('[id^="imageAltText"]').each(function() {
					$(this).val('');
				});
				this.$extraAltText_.show();
			}

			if (jsonData.content === '') {
				// Successful upload to temporary file; save to main form.
				$uploadForm = this.getHtmlElement();
				$temporaryFileId = $uploadForm.find('#temporaryFileId');
				$temporaryFileId.val(jsonData.temporaryFileId);
			} else {
				// Display the revision confirmation form.
				this.getHtmlElement().replaceWith(jsonData.content);
			}
		}
	};


	/**
	 * Fires when the file has been removed
	 */
	$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFileUploadFormHandler.
			prototype.fileDeleted = function() {

		if (this.$preview) {
			this.$preview.empty();
			$('[id^="imageAltText"]').each(function() {
				$(this).val('');
			});
			this.$extraAltText_.show();
		}
	};

	/** @param {jQuery} $ jQuery closure. */
}(jQuery));
