{**
 * templates/controllers/tab/settings/form/newImageFileUploadForm.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Image file upload form.
 *}

<script type="text/javascript">
	// Attach the file upload form handler.
	$(function() {ldelim}
		$('#uploadForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				$uploader: $('#plupload'),
				uploaderOptions: {ldelim}
					uploadUrl: {plugin_url|json_encode path="uploadImage" escape=false},
					baseUrl: {$baseUrl|json_encode},
					filters: {ldelim}
						mime_types : [
							{ldelim} title : "Image files", extensions : "jpg,jpeg,png" {rdelim}
						]
					{rdelim},
					multipart_params: {ldelim}
						submissionId: {$submissionId|escape}
						//stageId: {$stageId|escape},
					{rdelim}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

<form id="uploadForm" class="pkp_form" action="{plugin_url path="saveFile"}" method="post">
	{csrf}
	<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
	{fbvFormArea id="file"}
		{fbvFormSection title="common.file"}
			{include file="controllers/fileUploadContainer.tpl" id="plupload"}
		{/fbvFormSection}
	{/fbvFormArea}
	{fbvFormArea id="extraFileData"}
		{fbvFormSection title="common.altText"}
			{fbvElement type="text" label="common.altTextInstructions" multilingual=true id="imageAltText" value=$imageAltText}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
