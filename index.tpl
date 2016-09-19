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
<!--
// Move author up/down
function moveAuthor(dir, authorIndex) {
	var form = document.getElementById('submit');
	form.moveAuthor.value = 1;
	form.moveAuthorDir.value = dir;
	form.moveAuthorIndex.value = authorIndex;
	form.submit();
}

// Update the required attribute of the abstract field
function updateAbstractRequired() {
	var a = {{/literal}{foreach from=$sectionAbstractsRequired key=rSectionId item=rAbstractRequired}{$rSectionId|escape}: {$rAbstractRequired|escape}, {/foreach}{literal}};
	var selectedIndex = document.getElementById('submit').sectionId.selectedIndex;
	var sectionId = document.getElementById('submit').sectionId.options[selectedIndex].value;
	var abstractRequired = a[sectionId];
	var e = document.getElementById("abstractRequiredAsterisk");
	e.style.visibility = abstractRequired?"visible":"hidden";
}
// -->

$(function(){
		// Attach the JS form handler.
	
		$('#quickSubmitForm').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler'
		);
	}
);

</script>

{/literal}


<p>{translate key="plugins.importexport.quickSubmit.descriptionLong"}</p>

<form class="pkp_form" id="quickSubmitForm" method="post" action="{plugin_url path="saveSubmit"}">
	{if $submissionId}<input type="hidden" name="submissionId" value="{$submissionId|escape}"/>{/if}

	{csrf}
	{include file="common/formErrors.tpl"}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="quickSubmitFormNotification"}

	{include file="submission/form/section.tpl" readOnly=$formParams.readOnly}

	{include file="core:submission/submissionMetadataFormTitleFields.tpl"}
	{include file="submission/submissionMetadataFormFields.tpl"}
	{include file="core:submission/submissionMetadataFormFields.tpl"}

	{fbvFormArea id="contributors"}
		<!--  Contributors -->
		{url|assign:authorGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.users.author.AuthorGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}
		{load_url_in_div id="authorsGridContainer" url=$authorGridUrl}

		{$additionalContributorsFields}
	{/fbvFormArea}
	 
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="submitStep2FormNotification"}

	{url|assign:submissionFilesGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.SubmissionWizardFilesGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}
	{load_url_in_div id="submissionFilesGridDiv" url=$submissionFilesGridUrl}

	{capture assign="cancelUrl"}{plugin_url path="cancelSubmit"}{/capture}

	{fbvFormButtons id="quickSubmit" submitText="common.save" cancelUrl=$cancelUrl cancelUrlTarget="_self"}
</form>

{include file="common/footer.tpl"}
