{**
 * plugins/importexport/quickSubmit/templates/submitSuccess.tpl
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a message indicating that the article was successfuly submitted.
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.quickSubmit.success"}
{include file="common/header.tpl"}
{/strip}

{capture assign="submissionUrl"}{url router=$smarty.const.ROUTE_PAGE page="workflow" op="access" stageId=$stageId submissionId=$submissionId contextId="submission" escape=false}{/capture}

<div class="pkp_page_content pkp_successQuickSubmit">
	<p>
		{translate key="plugins.importexport.quickSubmit.successDescription"}  
	</p>
	<p> 
		<a href="{plugin_url}">
			{translate key="plugins.importexport.quickSubmit.successReturn"}
		</a>
	</p>
	<p> 
		<a href="{ $submissionUrl }">
			{translate key="plugins.importexport.quickSubmit.goToSubmission"}
		</a>
	</p>
</div>

{include file="common/footer.tpl"}
