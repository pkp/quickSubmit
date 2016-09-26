{**
 * plugins/importexport/quickSubmit/index.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Template for issue select list
 *
 *}
{assign var=issueDescription value=""}
{if !$readOnly}
	{assign var=issueDescription value="editor.publishedIssues"}
{/if}
{fbvFormSection title="section.section" required=true}
	{fbvElement type="select" id="issueId" label=$issueDescription from=$issueOptions selected=$issueId translate=false disabled=false size=$fbvStyles.size.MEDIUM}
{/fbvFormSection}