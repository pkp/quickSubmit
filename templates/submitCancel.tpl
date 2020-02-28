{**
 * templates/submitCancel.tpl
 *
 * Copyright (c) 2013-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Display a message indicating that the submission was cancelled.
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
