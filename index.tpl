{**
 * plugins/importexport/quickSubmit/index.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Template for one-page submission form
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.quickSubmit.displayName"}
{include file="common/header.tpl"}
{/strip}

{literal}
<script type="text/javascript">

	$(function(){
		$('input[type=radio][name=articleStatus]').change(function() {
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
	
		// Attach the JS form handler.
		$('#quickSubmitForm').pkpHandler(
			'$.pkp.controllers.form.FormHandler'
		);	
	});

</script>

{/literal}

<div id="quickSubmitPlugin" class="pkp_page_content pkp_pageQuickSubmit"> 
	<p>{translate key="plugins.importexport.quickSubmit.descriptionLong"}</p>

	<form class="pkp_form" id="quickSubmitForm" method="post" action="{plugin_url path="saveSubmit"}">
		{if $submissionId}<input type="hidden" name="submissionId" value="{$submissionId|escape}"/>{/if}

		{csrf}
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="quickSubmitFormNotification"}

		{fbvFormSection list="true"}
			{fbvElement type="radio" id="articleUnpublished" name="articleStatus" value=0 checked=$articleStatus_uncheched label='plugins.importexport.quickSubmit.unpublished' translate="true"}
			{fbvElement type="radio" id="articlePublished" name="articleStatus" value=1 checked=$articleStatus_cheched label='plugins.importexport.quickSubmit.published' translate="true"}

			{assign var=issueDescription value="editor.publishedIssues"}
			{fbvElement type="select" id="issueId" label=$issueDescription from=$issueOptions selected=$issueId translate=false disabled=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}


		{include file="submission/form/section.tpl" readOnly=$formParams.readOnly}

		{include file="core:submission/submissionMetadataFormTitleFields.tpl"}
		{include file="submission/submissionMetadataFormFields.tpl"}


		{fbvFormArea id="contributors"}
			<!--  Contributors -->
			{url|assign:authorGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.users.author.AuthorGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}
			{load_url_in_div id="authorsGridContainer" url=$authorGridUrl}

			{$additionalContributorsFields}
		{/fbvFormArea}

		{url|assign:submissionFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.SubmissionWizardFilesGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}
		{load_url_in_div id="submissionFilesGridDiv" url=$submissionFilesGridUrl}

		{capture assign="cancelUrl"}{plugin_url path="cancelSubmit" submissionId="$submissionId"}{/capture}

		{fbvFormButtons id="quickSubmit" submitText="common.save" cancelUrl=$cancelUrl cancelUrlTarget="_self"}

	</form>
</div>


{include file="common/footer.tpl"}
