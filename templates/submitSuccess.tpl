{**
 * templates/submitSuccess.tpl
 *
 * Copyright (c) 2013-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Display a message indicating that the article was successfuly submitted.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.importexport.quickSubmit.success"}
	</h1>

	{capture assign="submissionUrl"}{url router=$smarty.const.ROUTE_PAGE page="workflow" op="access" stageId=$stageId submissionId=$submissionId contextId="submission" escape=false}{/capture}

	<div class="app__contentPanel">
		<p>
			{translate key="plugins.importexport.quickSubmit.successDescription"}
		</p>
		<p>
			<a href="{plugin_url}">
				{translate key="plugins.importexport.quickSubmit.successReturn"}
			</a>
		</p>
		<p>
			<a href="{url router=$smarty.const.ROUTE_PAGE page="workflow" op="access" path=$submissionId escape=false}">
				{translate key="plugins.importexport.quickSubmit.goToSubmission"}
			</a>
		</p>
	</div>
{/block}
