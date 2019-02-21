{**
 * plugins/importexport/quickSubmit/templates/submitCancel.tpl
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a message indicating that the submission was cancelled.
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.quickSubmit.cancel"}
{include file="common/header.tpl"}
{/strip}

<div class="pkp_page_content pkp_cancelQuickSubmit">
	<p>
		{translate key="plugins.importexport.quickSubmit.cancelDescription"}  
	</p>
	<p> 
		<a href="{plugin_url}">
			{translate key="plugins.importexport.quickSubmit.successReturn"}
		</a>
	</p>
</div>

{include file="common/footer.tpl"}
