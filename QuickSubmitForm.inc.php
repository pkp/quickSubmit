<?php

/**
 * @file QuickSubmitForm.inc.php
 *
 * Copyright (c) 2013-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class QuickSubmitForm
 * @ingroup plugins_importexport_quickSubmit
 *
 * @brief Form for QuickSubmit one-page submission plugin
 */


import('lib.pkp.classes.form.Form');
import('classes.submission.SubmissionMetadataFormImplementation');
import('classes.publication.Publication');

class QuickSubmitForm extends Form {
	/** @var Request */
	protected $_request;

	/** @var Submission */
	protected $_submission;

	/** @var Journal */
	protected $context;

	/** @var SubmissionMetadataFormImplementation */
	protected $_metadataFormImplem;

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $request object
	 */
	function __construct($plugin, $request) {
		parent::__construct($plugin->getTemplateResource('index.tpl'));

		$this->_request = $request;
		$this->_context = $request->getContext();

		$this->_metadataFormImplem = new SubmissionMetadataFormImplementation($this);

		$locale = $request->getUserVar('locale');
		if ($locale && ($locale != AppLocale::getLocale())) {
			$this->setDefaultFormLocale($locale);
		}

		if ($submissionId = $request->getUserVar('submissionId')) {
			$submissionDao = DAORegistry::getDAO('SubmissionDAO');
			$publicationDao = DAORegistry::getDAO('PublicationDAO'); /* @var $publicationDao PublicationDAO */

			$this->_submission = $submissionDao->getById($submissionId);
			if ($this->_submission->getContextId() != $this->_context->getId()) throw new Exeption('Submission not in context!');

			$this->_submission->setLocale($this->getDefaultFormLocale());
			$publication = $this->_submission->getCurrentPublication();
			$publication->setData('locale', $this->getDefaultFormLocale());
			$publication->setData('language', PKPString::substr($this->getDefaultFormLocale(), 0, 2));

			$sectionId = $request->getUserVar('sectionId');
			if (!empty($sectionId)) {
				$this->_submission->setSectionId($sectionId);
			}

			$submissionDao->updateObject($this->_submission);
			$publicationDao->updateObject($publication);

			$this->_metadataFormImplem->addChecks($this->_submission);
		}

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		$this->addCheck(new FormValidatorCustom($this, 'sectionId', 'required', 'author.submit.form.sectionRequired', array(DAORegistry::getDAO('SectionDAO'), 'sectionExists'), array($this->_context->getId())));

		// Validation checks for this form
		$supportedSubmissionLocales = $this->_context->getSupportedSubmissionLocales();
		if (!is_array($supportedSubmissionLocales) || count($supportedSubmissionLocales) < 1)
			$supportedSubmissionLocales = array($this->_context->getPrimaryLocale());
		$this->addCheck(new FormValidatorInSet($this, 'locale', 'required', 'submission.submit.form.localeRequired', $supportedSubmissionLocales));

		$this->addCheck(new FormValidatorURL($this, 'licenseUrl', 'optional', 'form.url.invalid'));
	}

	/**
	 * Get the submission associated with the form.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the names of fields for which data should be localized
	 * @return array
	 */
	function getLocaleFieldNames() {
		return $this->_metadataFormImplem->getLocaleFieldNames();
	}

	/**
	 * Display the form.
	 */
	function display($request = null, $template = null) {
		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign(
			'supportedSubmissionLocaleNames',
			$this->_context->getSupportedSubmissionLocaleNames()
		);

		// Tell the form what fields are enabled (and which of those are required)
		foreach (Application::getMetadataFields() as $field) {
			$templateMgr->assign(array(
				$field . 'Enabled' => in_array($this->_context->getData($field), array(METADATA_ENABLE, METADATA_REQUEST, METADATA_REQUIRE)),
				$field . 'Required' => $this->_context->getData($field) === METADATA_REQUIRE,
			));
		}

		// Cover image delete link action
		$locale = AppLocale::getLocale();

		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$router = $this->_request->getRouter();
		$templateMgr->assign('openCoverImageLinkAction', new LinkAction(
			'uploadFile',
			new AjaxModal(
				$router->url($this->_request, null, null, 'importexport', array('plugin', 'QuickSubmitPlugin', 'uploadCoverImage'), array(
					'coverImage' => $this->_submission->getCoverImage($locale),
					'submissionId' => $this->_submission->getId(),
					'publicationId' => $this->_submission->getCurrentPublication()->getId(),
					// This action can be performed during any stage,
					// but we have to provide a stage id to make calls
					// to IssueEntryTabHandler
					'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
				)),
				__('common.upload'),
				'modal_add_file'
			),
			__('common.upload'),
			'add'
		));

		// Get section for this context
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionOptions = array('0' => '') + $sectionDao->getTitlesByContextId($this->_context->getId());
		$templateMgr->assign('sectionOptions', $sectionOptions);

		// Get published Issues
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issuesIterator = $issueDao->getIssues($this->_context->getId());
		$issues = $issuesIterator->toArray();
		$templateMgr->assign('hasIssues', count($issues) > 0);

		// Get Issues
		$templateMgr->assign(array(
			'issueOptions' => $this->getIssueOptions($this->_context),
			'submission' => $this->_submission,
			'locale' => $this->getDefaultFormLocale(),
			'publicationId' => $this->_submission->getCurrentPublication()->getId(),
		));

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionId = $this->getData('sectionId') ?: $this->_submission->getSectionId();
		$section = $sectionDao->getById($sectionId, $this->_context->getId());
		$templateMgr->assign(array(
			'wordCount' => $section->getAbstractWordCount(),
			'abstractsRequired' => !$section->getAbstractsNotRequired(),
		));

		// Process entered tagit fields values for redisplay.
		// @see PKPSubmissionHandler::saveStep
		$tagitKeywords = $this->getData('keywords');
		if (is_array($tagitKeywords)) {
			$tagitFieldNames = $this->_metadataFormImplem->getTagitFieldNames();
			$locales = array_keys($this->supportedLocales);
			$formTagitData = array();
			foreach ($tagitFieldNames as $tagitFieldName) {
				foreach ($locales as $locale) {
					$formTagitData[$locale] = array_key_exists($locale . "-$tagitFieldName", $tagitKeywords) ? $tagitKeywords[$locale . "-$tagitFieldName"] : array();
				}
				$this->setData($tagitFieldName, $formTagitData);
			}
		}

		parent::display($request, $template);
	}

	/**
	 * @copydoc Form::validate
	 */
	function validate($callHooks = true) {
		if (!parent::validate($callHooks)) return false;

		// Validate Issue if Published is selected
		// if articleStatus == 1 => should have issueId
		if ($this->getData('articleStatus') == 1) {
			if ($this->getData('issueId') <= 0) {
				$this->addError('issueId', __('plugins.importexport.quickSubmit.selectIssue'));
				$this->errorFields['issueId'] = 1;

				return false;
			}
		}

		return true;

	}

	/**
	 * Initialize form data for a new form.
	 */
	function initData() {
		$this->_data = array();

		if (!$this->_submission) {
			$this->_data['locale'] = $this->getDefaultFormLocale();

			// Get Sections
			$sectionDao = DAORegistry::getDAO('SectionDAO');
			$sectionOptions = $sectionDao->getTitlesByContextId($this->_context->getId());

			// Create and insert a new submission
			$submissionDao = DAORegistry::getDAO('SubmissionDAO');
			$this->_submission = $submissionDao->newDataObject();
			$this->_submission->setContextId($this->_context->getId());
			$this->_submission->setStatus(STATUS_QUEUED);
			$this->_submission->setSubmissionProgress(1);
			$this->_submission->stampStatusModified();
			$this->_submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
			$this->_submission->setData('sectionId', $sectionId = current(array_keys($sectionOptions)));
			$this->_submission->setLocale($this->getDefaultFormLocale());

			// Insert the submission
			$this->_submission = Services::get('submission')->add($this->_submission, $this->_request);
			$this->setData('submissionId', $this->_submission->getId());

			$publication = new Publication();
			$publication->setData('submissionId', $this->_submission->getId());
			$publication->setData('locale', $this->getDefaultFormLocale());
			$publication->setData('language', PKPString::substr($this->getDefaultFormLocale(), 0, 2));
			$publication->setData('sectionId', $sectionId);
			$publication->setData('status', STATUS_QUEUED);
			$publication->setData('version', 1);
			$publication = Services::get('publication')->add($publication, $this->_request);
			$this->_submission = Services::get('submission')->edit($this->_submission, ['currentPublicationId' => $publication->getId()], $this->_request);

			$this->_metadataFormImplem->initData($this->_submission);

			// Add the user manager group (first that is found) to the stage_assignment for that submission
			$user = $this->_request->getUser();

			$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

			$userGroupId = null;
			$managerUserGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $this->_context->getId(), ROLE_ID_MANAGER);
			if($managerUserGroupAssignments) {
				while($managerUserGroupAssignment = $managerUserGroupAssignments->next()) {
					$managerUserGroup = $userGroupDao->getById($managerUserGroupAssignment->getUserGroupId());
					$userGroupId = $managerUserGroup->getId();
					break;
				}
			}

			// Pre-fill the copyright information fields from setup (#7236)
			$this->_data['licenseUrl'] = $this->_context->getData('licenseUrl');
			switch ($this->_context->getData('copyrightHolderType')) {
			case 'author':
				// The author has not been entered yet; let the user fill it in.
				break;
			case 'context':
				$this->_data['copyrightHolder'] = $this->_context->getData('name');
				break;
			case 'other':
				$this->_data['copyrightHolder'] = $this->_context->getData('copyrightHolderOther');
				break;
			}
			$this->_data['copyrightYear'] = date('Y');

			// Assign the user author to the stage
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$stageAssignmentDao->build($this->_submission->getId(), $userGroupId, $user->getId());
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->_metadataFormImplem->readInputData();

		$this->readUserVars(
			array(
				'issueId',
				'pages',
				'datePublished',
				'licenseUrl',
				'copyrightHolder',
				'copyrightYear',
				'sectionId',
				'submissionId',
				'articleStatus',
				'locale'
			)
		);
	}

	/**
	 * cancel submit
	 */
	function cancel() {
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($this->getData('submissionId'));
		if ($this->_submission->getContextId() != $this->_context->getId()) throw new Exeption('Submission not in context!');
		if ($submission) $submissionDao->deleteById($submission->getId());
	}

	/**
	 * Save settings.
	 */
	function execute(...$functionParams) {
		// Execute submission metadata related operations.
		$this->_metadataFormImplem->execute($this->_submission, $this->_request);

		// Copy GalleyFiles to Submission Files
		// Get Galley Files by SubmissionId
		$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$galleysResponse = $galleyDao->getByPublicationId($this->_submission->getCurrentPublication()->getId());

		if (!is_null($galleysResponse)) {
			$galleys = $galleysResponse->toAssociativeArray();

			foreach($galleys as $galley) {
				$file = $galley->getFile();
				if ($file) {
					$newSubmissionFile = clone $file;
					$newSubmissionFile->setData('fileStage', SUBMISSION_FILE_SUBMISSION);
					$newSubmissionFile->unsetData('assocType');
					$newSubmissionFile->unsetData('assocId');
					$newSubmissionFile->setData('viewable', true);
					$newSubmissionFile->setData('sourceSubmissionFileId', $file->getId());
					$newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, Application::get()->getRequest());
				}
			}
		}

		$this->_submission->setLocale($this->getData('locale'));
		$this->_submission->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
		$this->_submission->setDateSubmitted(Core::getCurrentDate());
		$this->_submission->setSubmissionProgress(0);

		parent::execute($this->_submission, ...$functionParams);

		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submissionDao->updateObject($this->_submission);
		$this->_submission = $submissionDao->getById($this->_submission->getId());

		$publication = $this->_submission->getCurrentPublication();
		if ($publication->getData('sectionId') !== (int) $this->getData('sectionId')) {
			$publication = Services::get('publication')->edit($publication, ['sectionId' => (int) $this->getData('sectionId')], $this->_request);
		}

		if ($this->getData('articleStatus') == 1) {
			$publication->setData('copyrightYear', $this->getData('copyrightYear'));
			$publication->setData('copyrightHolder', $this->getData('copyrightHolder'), null);
			$publication->setData('licenseUrl', $this->getData('licenseUrl'));
			$publication->setData('pages', $this->getData('pages'));
			$publication->setData('datePublished', $this->getData('datePublished'));
			$publication->setData('accessStatus', ARTICLE_ACCESS_ISSUE_DEFAULT);
			$publication->setData('issueId', (int) $this->getData('issueId'));

			// If other articles in this issue have a custom sequence, put this at the end
			$otherSubmissionsInSection = Services::get('submission')->getMany([
				'contextId' => $this->_request->getContext()->getId(),
				'issueIds' => [$publication->getData('issueId')],
				'sectionIds' => [$publication->getData('sectionId')],
			]);
			if (count($otherSubmissionsInSection)) {
				$maxSequence = 0;
				foreach ($otherSubmissionsInSection as $submission) {
					if ($submission->getCurrentPublication()->getData('seq')) {
						$maxSequence = max($maxSequence, $submission->getCurrentPublication()->getData('seq'));
					}
				}
				$publication->setData('seq', $maxSequence + 1);
			}

			$publication = Services::get('publication')->publish($publication);

			// If this submission's issue uses custom section ordering and this is the first
			// article published in a section, make sure we enter a custom ordering
			// for that section to place it at the end.
			if (DAORegistry::getDAO('SectionDAO')->customSectionOrderingExists($publication->getData('issueId'))) {
				$sectionOrder = DAORegistry::getDAO('SectionDAO')->getCustomSectionOrder($publication->getData('issueId'), $publication->getData('sectionId'));
				if  ($sectionOrder === null) {
					DAORegistry::getDAO('SectionDAO')->insertCustomSectionOrder($publication->getData('issueId'), $publication->getData('sectionId'), REALLY_BIG_NUMBER);
					DAORegistry::getDAO('SectionDAO')->resequenceCustomSectionOrders($publication->getData('issueId'));
				}
			}
		}

		// Index article.
		$articleSearchIndex = Application::getSubmissionSearchIndex();
		$articleSearchIndex->submissionMetadataChanged($this->_submission);
		$articleSearchIndex->submissionFilesChanged($this->_submission);
		$articleSearchIndex->submissionChangesFinished();

	}

	/**
	 * builds the issue options pulldown for published and unpublished issues
	 * @param $journal Journal
	 * @return array Associative list of options for pulldown
	 */
	function getIssueOptions($journal) {
		$issuesPublicationDates = array();
		$issueOptions = array();
		$journalId = $journal->getId();

		$issueDao = DAORegistry::getDAO('IssueDAO');

		$issueOptions[-1] =  '------    ' . __('editor.issues.futureIssues') . '    ------';
		$issueIterator = $issueDao->getUnpublishedIssues($journalId);
		while ($issue = $issueIterator->next()) {
			$issueOptions[$issue->getId()] = $issue->getIssueIdentification();
			$issuesPublicationDates[$issue->getId()] = strftime(Config::getVar('general', 'date_format_short'), strtotime(Core::getCurrentDate()));
		}
		$issueOptions[-2] = '------    ' . __('editor.issues.currentIssue') . '    ------';
		$issuesIterator = $issueDao->getPublishedIssues($journalId);
		$issues = $issuesIterator->toArray();
		if (isset($issues[0]) && $issues[0]->getCurrent()) {
			$issueOptions[$issues[0]->getId()] = $issues[0]->getIssueIdentification();
			$issuesPublicationDates[$issues[0]->getId()] = strftime(Config::getVar('general', 'date_format_short'), strtotime($issues[0]->getDatePublished()));
			array_shift($issues);
		}
		$issueOptions[-3] = '------    ' . __('editor.issues.backIssues') . '    ------';
		foreach ($issues as $issue) {
			$issueOptions[$issue->getId()] = $issue->getIssueIdentification();
			$issuesPublicationDates[$issue->getId()] = strftime(Config::getVar('general', 'date_format_short'), strtotime($issues[0]->getDatePublished()));
		}

		$templateMgr = TemplateManager::getManager($this->_request);
		$templateMgr->assign('issuesPublicationDates', json_encode($issuesPublicationDates));

		return $issueOptions;
	}
}

