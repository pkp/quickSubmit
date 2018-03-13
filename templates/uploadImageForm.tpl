{**
 * plugins/importexport/quickSubmit/templates/uploadImageForm.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Image file upload form.
 *}
<script src="{$baseUrl}/plugins/importexport/quickSubmit/js/QuickSubmitFileUploadFormHandler.js"></script>
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#uploadForm').pkpHandler(
			'$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFileUploadFormHandler',
			{ldelim}
				$uploader: $('#coverImageUploader'),
				$preview: $('#coverImagePreview'),
				$extraAltText: $('#extraAltText'),
				uploaderOptions: {ldelim}
					uploadUrl: {plugin_url|json_encode path="uploadImage" escape=false},
					baseUrl: {$baseUrl|json_encode},
					filters: {ldelim}
						mime_types : [
							{ldelim} title : "Image files", extensions : "jpg,jpeg,png,svg" {rdelim}
						]
					{rdelim},
					multipart_params: {ldelim}
						submissionId: {$submissionId|escape},
						{if $stageId}stageId: {$stageId|escape},{/if}
					{rdelim}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

<form id="uploadForm" class="pkp_form" action="{plugin_url path="saveUploadedImage"}" method="post">
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />

	{* Cover Image *}
	{fbvFormArea id="coverImage" title="editor.issues.coverPage"}
		{fbvFormSection}
			{include file="controllers/fileUploadContainer.tpl" id="coverImageUploader"}
			<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
		{/fbvFormSection}
		<div id="extraAltText"{if $coverImage} style="display:none"{/if}>
			{fbvFormSection title="common.altText"}
				{fbvElement type="text" label="common.altTextInstructions" id="imageAltText" value=$imageAltText}
			{/fbvFormSection}
		</div>

		{fbvFormSection id="coverImagePreview"}
			{if $coverImage != ''}
				<div class="pkp_form_file_view pkp_form_image_view">
					<div class="img">
						<img src="{$publicFilesDir}/{$coverImage|escape:"url"}{'?'|uniqid}" {if $coverImageAlt !== ''} alt="{$coverImageAlt|escape}"{/if}>
					</div>

					<div class="data">
						<span class="title">
							{translate key="common.altText"}
						</span>
						<span class="value">
							{fbvElement type="text" id="imageAltText" label="common.altTextInstructions" value=$imageAltText}
						</span>
						<div id="{$deleteCoverImageLinkAction->getId()}" class="actions">
							{include file="linkAction/linkAction.tpl" action=$deleteCoverImageLinkAction contextId="issueForm"}
						</div>
					</div>
				</div>
			{/if}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
