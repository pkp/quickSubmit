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
	$(function() {ldelim}
		// Attach the form handler.
		$('#uploadForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				$uploader: $('#coverImageUploader'),
				$preview: $('#coverImagePreview'),
				uploaderOptions: {ldelim}
					uploadUrl: {plugin_url|json_encode path="uploadImage" escape=false},
					baseUrl: {$baseUrl|json_encode},
					filters: {ldelim}
						mime_types : [
							{ldelim} title : "Image files", extensions : "jpg,jpeg,png" {rdelim}
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
	{fbvFormArea id="coverImage" title="editor.article.coverImage"}
		{if !$formParams.readOnly}
			{fbvFormSection}
				{include file="controllers/fileUploadContainer.tpl" id="coverImageUploader"}
				<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />
			{/fbvFormSection}
			{fbvFormArea id="extraFileData"}
				{fbvFormSection title="common.altText"}
					{fbvElement type="text" label="common.altTextInstructions" multilingual=true id="imageAltText" value=$imageAltText}
				{/fbvFormSection}
			{/fbvFormArea}
		{/if}
		{fbvFormSection id="coverImagePreview"}
			{if $coverImage != ''}
				<div class="pkp_form_file_view pkp_form_image_view">
					<img src="{$publicFilesDir}/{$coverImage|escape:"url"}{'?'|uniqid}" {if $coverImageAlt !== ''} alt="{$coverImageAlt|escape}"{/if}>

					<div id="{$deleteCoverImageLinkAction->getId()}" class="actions">
						{include file="linkAction/linkAction.tpl" action=$deleteCoverImageLinkAction contextId="uploadForm"}
					</div>
				</div>
			{/if}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
