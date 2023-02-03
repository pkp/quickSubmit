{**
 * templates/submitCancel.tpl
 *
 * Copyright (c) 2013-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Display a message indicating that the submission was cancelled.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.importexport.quickSubmit.cancel"}
	</h1>

	<div class="app__contentPanel">
		<p>
			{translate key="plugins.importexport.quickSubmit.cancelDescription"}
		</p>
		<p>
			<a href="{plugin_url}">
				{translate key="plugins.importexport.quickSubmit.successReturn"}
			</a>
		</p>
	</div>
{/block}
