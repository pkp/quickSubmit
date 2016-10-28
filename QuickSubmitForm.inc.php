<?php

/**
 * @file plugins/importexport/quickSubmit/QuickSubmitForm.inc.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $request object
	 */
	function QuickSubmitForm($plugin, $request) {
		parent::Form($plugin->getTemplatePath() . 'index.tpl');

		$this->request = $request;
		$this->context = $request->getContext();

		$this->_metadataFormImplem = new SubmissionMetadataFormImplementation($this);

		if ($request->getUserVar('submissionId')) {
			$this->submissionId  = $request->getUserVar('submissionId');
			$submissionDao = Application::getSubmissionDAO();
			$this->submission = $submissionDao->getById($request->getUserVar('submissionId'), $this->context->getId(), false);

			$this->_metadataFormImplem->addChecks($this->submission);
		}

		$this->addCheck(new FormValidatorPost($this));
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
		//$result = array_merge(array('title', 'abstract'), $this->_metadataFormImplem->getLocaleFieldNames());
		return $this->_metadataFormImplem->getLocaleFieldNames();
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr = TemplateManager::getManager($this->request);
		$templateMgr->assign('abstractsRequired', true);

		//$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		//$templateMgr->assign('userRoles', $userRoles);

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

		// manage post request
		$issueId = $this->getData('issueId');

		// Production
		$dispatcher = $this->request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$schedulePublicationLinkAction = new LinkAction(
			'schedulePublication',
			new AjaxModal(
				$dispatcher->url(
					$this->request, ROUTE_COMPONENT, null,
					'tab.issueEntry.IssueEntryTabHandler',
					'publicationMetadata', null,
					array('submissionId' => $this->submissionId, 'stageId' => WORKFLOW_STAGE_ID_PRODUCTION)
				),
				__('submission.issueEntry.publicationMetadata')
			),
			__('editor.article.schedulePublication')
		);
		$templateMgr->assign('schedulePublicationLinkAction', $schedulePublicationLinkAction);

		// Get section for this context
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionOptions = array('0' => '') + $sectionDao->getSectionTitles($this->context->getId());
		$templateMgr->assign('sectionOptions', $sectionOptions);

		// Get published Issues
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issuesIterator = $issueDao->getPublishedIssues($this->context->getId());
		$issues = $issuesIterator->toArray();
		foreach ($issues as $issue) {
			$issueOptions[$issue->getId()] = $issue->getIssueIdentification();
		}
		$issueOptions[0] = __('plugins.importexport.common.filter.issue');
		ksort($issueOptions);
		$templateMgr->assign('issueOptions', $issueOptions);

		parent::display();
	}

	/**
	 * Perform additional validation checks
	 * @copydoc Form::validate
	 */
	function validate() {
		if (!parent::validate()) return false;

		// Validate that the section ID is attached to this journal.
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$section = $sectionDao->getById($this->getData('sectionId'), $this->context->getId());
		if (!$section) return false;

		// Validate Issue if Published is selected
		// if articleStatus == 1 => should have issueId
		if ($this->getData('articleStatus') == 1) {
			if (!$this->getData('issueId') || $this->getData('issueId') == 0) {
				$this->addError('issueId', 'author.submit.form.sectionRequired');
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
			$supportedSubmissionLocales = $this->context->getSupportedSubmissionLocales();

			// Try these locales in order until we find one that's
			// supported to use as a default.
			$tryLocales = array(
				$this->getFormLocale(), // Current form locale
				AppLocale::getLocale(), // Current UI locale
				$this->context->getPrimaryLocale(), // Context locale
				$supportedSubmissionLocales[array_shift(array_keys($supportedSubmissionLocales))] // Fallback: first one on the list
			);

			foreach ($tryLocales as $locale) {
				if (in_array($locale, $supportedSubmissionLocales)) {
					// Found a default to use
					$this->_data['locale'] = $locale;
					break;
				}
			}

            // TODO: Manage no Sections exist
			$sectionDao = DAORegistry::getDAO('SectionDAO');
			$sectionOptions = $sectionDao->getSectionTitles($this->context->getId());

            // Create and insert a new submission
			$submissionDao = Application::getSubmissionDAO();
			$submission = $submissionDao->newDataObject();
			$submission->setContextId($this->context->getId());
            $submission->setStatus(STATUS_QUEUED);
            $submission->setSubmissionProgress(0);
			$submission->stampStatusModified();
			$submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
			//$submission->setCopyrightNotice($this->context->getLocalizedSetting('copyrightNotice'), $this->getData('locale'));
			$submission->setSectionId(current(array_keys($sectionOptions)));

			// Insert the submission
			$this->submissionId = $submissionDao->insertObject($submission);
			$this->setData('submissionId', $this->submissionId);

			$this->_metadataFormImplem->initData($submission);

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
				'sectionId',
				'submissionId',
				'articleStatus',
				'locale'
			)
		);

		$this->readUserDateVars(array('datePublished'));
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
		$application = PKPApplication::getApplication();

		// Execute submission metadata related operations.
		$this->_metadataFormImplem->execute($this->submission, $this->request);

        // $this->submission->setJournalId($this->context->getId());
        $this->submission->setSectionId($this->getData('sectionId'));

        // articleStatus == 1 -> Published and to an Issue
        if ($this->getData('articleStatus') == 1) {
            $publishedSubmissionDao = DAORegistry::getDAO('PublishedArticleDAO');

            // Get Issue
            $issueDao = DAORegistry::getDAO('IssueDAO');
            $issue = $issueDao->getById($this->getData('issueId'));

			$publishedSubmission = $publishedSubmissionDao->newDataObject();
			$publishedSubmission->setId($this->submission->getId());
			$publishedSubmission->setDatePublished(strtotime($issue->getDatePublished()));
			$publishedSubmission->setSequence(REALLY_BIG_NUMBER);
			$publishedSubmission->setAccessStatus(ARTICLE_ACCESS_ISSUE_DEFAULT);
			$publishedSubmission->setIssueId($issue->getId());
			$publishedSubmissionDao->insertObject($publishedSubmission);


            $this->submission->setStatus(STATUS_PUBLISHED);
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

				$revisionNumber = $submissionFileDao->getLatestRevisionNumber($newFile->getFileId());
				$submissionFileManager->copyFileToFileStage($newFile->getFileId(), $revisionNumber, SUBMISSION_FILE_SUBMISSION, null, true);
			}
		}

		$this->submission->setLocale($this->getData('locale'));
        $this->submission->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
        $this->submission->setDateSubmitted(Core::getCurrentDate());

		$submissionDao = Application::getSubmissionDAO();
        $submissionDao->updateObject($this->submission);

        //// Setup default copyright/license metadata after status is set and authors are attached.
        //$submission->initializePermissions();
        //$submissionDao->updateLocaleFields($submission);

        //// Index article.
        //import('classes.search.ArticleSearchIndex');
        //$articleSearchIndex = new ArticleSearchIndex();
        //$articleSearchIndex->articleMetadataChanged($submission);
        //$articleSearchIndex->articleChangesFinished();
	}
}

?>
