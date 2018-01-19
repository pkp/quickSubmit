<?php

/**
 * @file plugins/importexport/quickSubmit/QuickSubmitForm.inc.php
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QuickSubmitForm
 * @ingroup plugins_importexport_quickSubmit
 *
 * @brief Form for QuickSubmit one-page submission plugin
 */


import('lib.pkp.classes.form.Form');

// This class contains a static method to describe metadata field settings
import('lib.pkp.controllers.grid.settings.metadata.MetadataGridHandler');
import('classes.submission.SubmissionMetadataFormImplementation');

class QuickSubmitForm extends Form {
	/** @var $request object */
	var $request;

	/** @var $submission Submission */
	var $submission;

	/** @var $context Journal */
	var $context;

	/** @var $submissionId int */
	var $submissionId;

	/** @var SubmissionMetadataFormImplementation */
	var $_metadataFormImplem;

	/** @var $publishedSubmission PublishedArticle */
	var $publishedSubmission;

	/** @var $plugin QuickSubmitPlugin */
	var $plugin;

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $request object
	 */
	function __construct($plugin, $request) {
		parent::__construct($plugin->getTemplatePath() . 'index.tpl');

		$this->request = $request;
		$this->context = $request->getContext();
		$this->plugin = $plugin;

		$this->_metadataFormImplem = new SubmissionMetadataFormImplementation($this);

		$locale = $request->getUserVar('locale');
		if ($locale && ($locale != AppLocale::getLocale())) {
			$this->setDefaultFormLocale($locale);
		}

		if ($request->getUserVar('submissionId')) {
			$this->submissionId  = $request->getUserVar('submissionId');
			$submissionDao = Application::getSubmissionDAO();
			$this->submission = $submissionDao->getById($request->getUserVar('submissionId'), $this->context->getId(), false);
			$this->submission->setLocale($this->getDefaultFormLocale());
			$submissionDao->updateObject($this->submission);

			$this->_metadataFormImplem->addChecks($this->submission);
		}

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		$this->addCheck(new FormValidatorCustom($this, 'sectionId', 'required', 'author.submit.form.sectionRequired', array(DAORegistry::getDAO('SectionDAO'), 'sectionExists'), array($this->context->getId())));

		// Validation checks for this form
		$supportedSubmissionLocales = $this->context->getSupportedSubmissionLocales();
		if (!is_array($supportedSubmissionLocales) || count($supportedSubmissionLocales) < 1)
			$supportedSubmissionLocales = array($this->context->getPrimaryLocale());
		$this->addCheck(new FormValidatorInSet($this, 'locale', 'required', 'submission.submit.form.localeRequired', $supportedSubmissionLocales));
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
	function display() {
		$templateMgr = TemplateManager::getManager($this->request);

		$templateMgr->assign(
			'supportedSubmissionLocaleNames',
			$this->context->getSupportedSubmissionLocaleNames()
		);

		// Tell the form what fields are enabled (and which of those are required)
		foreach (array_keys(MetadataGridHandler::getNames()) as $field) {
			$templateMgr->assign($a = array(
				$field . 'Enabled' => $this->context->getSetting($field . 'EnabledWorkflow'),
				$field . 'Required' => $this->context->getSetting($field . 'Required')
			));
		}

		// Cover image delete link action
		$locale = AppLocale::getLocale();
		$coverImage = $this->submission->getCoverImage($locale);

		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$router = $this->request->getRouter();
		$openCoverImageLinkAction = new LinkAction(
			'uploadFile',
			new AjaxModal(
				$router->url($this->request, null, null, 'importexport', array('plugin', 'QuickSubmitPlugin', 'uploadCoverImage'), array('coverImage' => $coverImage,
						'submissionId' => $this->submission->getId(),
						// This action can be performed during any stage,
						// but we have to provide a stage id to make calls
						// to IssueEntryTabHandler
						'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,)
				),
				__('common.upload'),
				'modal_add_file'
			),
			__('common.upload'),
			'add'
		);
		$templateMgr->assign('openCoverImageLinkAction', $openCoverImageLinkAction);
		// Get section for this context
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionOptions = array('0' => '') + $sectionDao->getTitles($this->context->getId());
		$templateMgr->assign('sectionOptions', $sectionOptions);

		// Get published Issues
		$templateMgr->assign('hasIssues', true);

		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issuesIterator = $issueDao->getIssues($this->context->getId());
		$issues = $issuesIterator->toArray();
		if (count($issues) == 0) {
			$templateMgr->assign('hasIssues', false);
		}

		// Get Issues
		$templateMgr->assign('issueOptions', $this->getIssueOptions($this->context));

		$templateMgr->assign('submission', $this->submission);

		$templateMgr->assign('locale', $this->getDefaultFormLocale());

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionId = $this->submission->getSectionId();
		if ($this->getData('sectionId') > 0) {
			$sectionId = $this->getData('sectionId');
		}
		$section = $sectionDao->getById($sectionId);
		$wordCount = $section->getAbstractWordCount();
		$templateMgr->assign('wordCount', $wordCount);
		$templateMgr->assign('abstractsRequired', !$section->getAbstractsNotRequired());

		parent::display();
	}

	/**
	 * Perform additional validation checks
	 * @copydoc Form::validate
	 */
	function validate() {
		if (!parent::validate()) return false;

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

		if (!isset($this->submissionId)){
			$this->_data['locale'] = $this->getDefaultFormLocale();

			// Get Sections
			$sectionDao = DAORegistry::getDAO('SectionDAO');
			$sectionOptions = $sectionDao->getTitles($this->context->getId());

			// Create and insert a new submission
			$submissionDao = Application::getSubmissionDAO();
			$submission = $submissionDao->newDataObject();
			$submission->setContextId($this->context->getId());
			$submission->setStatus(STATUS_QUEUED);
			$submission->setSubmissionProgress(1);
			$submission->stampStatusModified();
			$submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
			$submission->setSectionId(current(array_keys($sectionOptions)));
			$submission->setLocale($this->getDefaultFormLocale());

			// Insert the submission
			$this->submissionId = $submissionDao->insertObject($submission);
			$this->setData('submissionId', $this->submissionId);

			$this->_metadataFormImplem->initData($submission);

			// Add the user manager group (first that is found) to the stage_assignment for that submission
			$user = Request::getUser();

			$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

			$userGroupId = null;
			$managerUserGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $this->context->getId(), ROLE_ID_MANAGER);
			if($managerUserGroupAssignments) {
				while($managerUserGroupAssignment = $managerUserGroupAssignments->next()) {
					$managerUserGroup = $userGroupDao->getById($managerUserGroupAssignment->getUserGroupId());
					$userGroupId = $managerUserGroup->getId();
					break;
				}
			}

			// Assign the user author to the stage
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$stageAssignmentDao->build($this->submissionId, $userGroupId, $user->getId());

			$this->submission = $submission;
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
				'licenseURL',
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
		$submissionDao = Application::getSubmissionDAO();
		$submissionDao->deleteById($this->getData('submissionId'));
	}

	/**
	 * Save settings.
	 */
	function execute() {
		// Execute submission metadata related operations.
		$this->_metadataFormImplem->execute($this->submission, $this->request);

		$this->submission->setSectionId($this->getData('sectionId'));

		// articleStatus == 1 -> Published and to an Issue
		if ($this->getData('articleStatus') == 1) {
			$this->submission->setStatus(STATUS_PUBLISHED);
			$this->submission->setCopyrightYear($this->getData('copyrightYear'));
			$this->submission->setCopyrightHolder($this->getData('copyrightHolder'), null);
			$this->submission->setLicenseURL($this->getData('licenseURL'));
			$this->submission->setPages($this->getData('pages'));

			// Insert new publishedArticle
			$publishedSubmissionDao = DAORegistry::getDAO('PublishedArticleDAO');
			$publishedSubmission = $publishedSubmissionDao->newDataObject();
			$publishedSubmission->setId($this->submission->getId());
			$publishedSubmission->setDatePublished($this->getData('datePublished'));
			$publishedSubmission->setSequence(REALLY_BIG_NUMBER);
			$publishedSubmission->setAccessStatus(ARTICLE_ACCESS_ISSUE_DEFAULT);
			$publishedSubmission->setIssueId($this->getData('issueId'));
			$publishedSubmissionDao->insertObject($publishedSubmission);

			$this->publishedSubmission = $publishedSubmission;
		}

		// Copy GalleyFiles to Submission Files
		// Get Galley Files by SubmissionId
		$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$galleyFilesRes = $galleyDao->getBySubmissionId($this->submissionId);

		if (!is_null($galleyFilesRes)) {
			$galleyFiles = $galleyFilesRes->toAssociativeArray();

			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			import('lib.pkp.classes.file.SubmissionFileManager');
			$submissionFileManager = new SubmissionFileManager($this->context->getId(), $this->submissionId);

			foreach($galleyFiles as $galleyFile) {
				$newFile = $galleyFile->getFile();
				if ($newFile) {
					$revisionNumber = $submissionFileDao->getLatestRevisionNumber($newFile->getFileId());
					$submissionFileManager->copyFileToFileStage($newFile->getFileId(), $revisionNumber, SUBMISSION_FILE_SUBMISSION, null, true);
				}
			}
		}

		$this->submission->setLocale($this->getData('locale'));
		$this->submission->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
		$this->submission->setDateSubmitted(Core::getCurrentDate());
		$this->submission->setSubmissionProgress(0);

		parent::execute($this->submission);

		$submissionDao = Application::getSubmissionDAO();
		$submissionDao->updateObject($this->submission);

		if ($this->getData('articleStatus') == 1) {
			$publishedSubmissionDao = DAORegistry::getDAO('PublishedArticleDAO');
			$publishedSubmissionDao->resequencePublishedArticles($this->submission->getSectionId(), $this->publishedSubmission->getIssueId());

			// If we're using custom section ordering, and if this is the first
			// article published in a section, make sure we enter a custom ordering
			// for it. (Default at the end of the list.)
			$sectionDao = DAORegistry::getDAO('SectionDAO');
			if ($sectionDao->customSectionOrderingExists($this->publishedSubmission->getIssueId())) {
				if ($sectionDao->getCustomSectionOrder($this->publishedSubmission->getIssueId(), $this->submission->getSectionId()) === null) {
					$sectionDao->insertCustomSectionOrder($this->publishedSubmission->getIssueId(), $this->submission->getSectionId(), REALLY_BIG_NUMBER);
					$sectionDao->resequenceCustomSectionOrders($this->publishedSubmission->getIssueId());
				}
			}
		}

		// Index article.
		import('classes.search.ArticleSearchIndex');
		ArticleSearchIndex::articleMetadataChanged($this->submission);
		ArticleSearchIndex::submissionFilesChanged($this->submission);
		ArticleSearchIndex::articleChangesFinished();

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

		$templateMgr = TemplateManager::getManager($this->request);
		$templateMgr->assign('issuesPublicationDates', json_encode($issuesPublicationDates));

		return $issueOptions;
	}
}
