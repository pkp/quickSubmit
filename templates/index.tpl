{**
 * plugins/importexport/quickSubmit/templates/index.tpl
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Template for one-page submission form
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.quickSubmit.displayName"}
{include file="common/header.tpl"}
{/strip}
<script src="{$baseUrl}/plugins/importexport/quickSubmit/js/QuickSubmitFormHandler.js"></script>

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#quickSubmitForm').pkpHandler('$.pkp.plugins.importexport.quickSubmit.js.QuickSubmitFormHandler');
	{rdelim});
	
</script>

<div id="quickSubmitPlugin" class="pkp_page_content pkp_pageQuickSubmit"> 
	<p>{translate key="plugins.importexport.quickSubmit.descriptionLong"}</p>

	<form class="pkp_form" id="quickSubmitForm" method="post" action="{plugin_url path="saveSubmit"}">
		<input type="hidden" name="reloadForm" id="reloadForm" value="0" />

		{if $submissionId}
		    <input type="hidden" name="submissionId" value="{$submissionId|escape}" />
		{/if}
		{if $issuesPublicationDates}
		    {fbvElement type="hidden" id="issuesPublicationDates" value=$issuesPublicationDates}
		{/if}

		{csrf}
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="quickSubmitFormNotification"}

		{fbvFormSection label="editor.issues.coverPage" class=$wizardClass}
			<div id="{$openCoverImageLinkAction->getId()}" class="pkp_linkActions">
				{include file="linkAction/linkAction.tpl" action=$openCoverImageLinkAction contextId="quickSubmitForm"}
			</div>
		{/fbvFormSection}

		{* There is only one supported submission locale; choose it invisibly *}
		{if count($supportedSubmissionLocaleNames) == 1}
		    {foreach from=$supportedSubmissionLocaleNames item=localeName key=locale}
		        {fbvElement type="hidden" id="locale" value=$locale}
		    {/foreach}

		{* There are several submission locales available; allow choice *}
		{else}
		    {fbvFormSection title="submission.submit.submissionLocale" size=$fbvStyles.size.MEDIUM for="locale"}
		        {fbvElement label="submission.submit.submissionLocaleDescription" required="true" type="select" id="locale" from=$supportedSubmissionLocaleNames selected=$locale translate=false}
		    {/fbvFormSection}
		{/if}

		{include file="submission/form/section.tpl" readOnly=$formParams.readOnly}

		{include file="core:submission/submissionMetadataFormTitleFields.tpl"}
		{include file="submission/submissionMetadataFormFields.tpl"}


		{fbvFormArea id="contributors"}
			<!--  Contributors -->
			{capture assign="authorGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.users.author.AuthorGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}{/capture}
			{load_url_in_div id="authorsGridContainer" url=$authorGridUrl}

			{$additionalContributorsFields}
		{/fbvFormArea}

		{capture assign="representationsGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.articleGalleys.ArticleGalleyGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}{/capture}
		{load_url_in_div id="formatsGridContainer"|uniqid url=$representationsGridUrl}

		{* Publishing article section *}
		{if $hasIssues}
			{fbvFormSection id='articlePublishingSection' list="false"}
				{fbvElement type="radio" id="articleUnpublished" name="articleStatus" value=0 checked=$articleStatus|compare:false label='plugins.importexport.quickSubmit.unpublished' translate="true"}
				{fbvElement type="radio" id="articlePublished" name="articleStatus" value=1 checked=$articleStatus|compare:true label='plugins.importexport.quickSubmit.published' translate="true"}

				{fbvFormSection id='schedulePublicationDiv' list="false"}
					{fbvFormArea id="schedulingInformation" title="editor.article.scheduleForPublication"}
						{fbvFormSection for="schedule"}
							{fbvElement type="select" required=true id="issueId" from=$issueOptions selected=$issueId translate=false label="editor.article.scheduleForPublication.toBeAssigned"}
						{/fbvFormSection}
					{/fbvFormArea}

					{fbvFormArea id="pagesInformation" title="editor.issues.pages"}
						{fbvFormSection for="customExtras"}
							{fbvElement type="text" id="pages" label="editor.issues.pages" value=$pages inline=true size=$fbvStyles.size.MEDIUM}
						{/fbvFormSection}
					{/fbvFormArea}

					{fbvFormArea id="schedulingInformationDatePublished" title="editor.issues.published"}
						{fbvFormSection for="publishedDate"}
							{fbvElement type="text" required=true id="datePublished" value=$datePublished|date_format:$dateFormatShort translate=false label="editor.issues.published" inline=true size=$fbvStyles.size.MEDIUM class="datepicker"}
						{/fbvFormSection}
					{/fbvFormArea}

					{fbvFormArea id="permissions" title="submission.permissions"}
						{fbvElement type="text" id="licenseURL" label="submission.licenseURL" value=$licenseURL}
						{fbvElement type="text" id="copyrightHolder" label="submission.copyrightHolder" value=$copyrightHolder multilingual=true size=$fbvStyles.size.MEDIUM inline=true}
						{fbvElement type="text" id="copyrightYear" label="submission.copyrightYear" value=$copyrightYear size=$fbvStyles.size.SMALL inline=true}
					{/fbvFormArea}
				{/fbvFormSection}

			{/fbvFormSection}
		{/if}

		{capture assign="cancelUrl"}{plugin_url path="cancelSubmit" submissionId="$submissionId"}{/capture}

		{fbvFormButtons id="quickSubmit" submitText="common.save" cancelUrl=$cancelUrl cancelUrlTarget="_self"}
	</form>
</div>

{include file="common/footer.tpl"}
