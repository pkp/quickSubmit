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

class QuickSubmitForm extends Form {
	/** @var $request object */
	var $request;

	/**
	 * Constructor
	 * @param $plugin object
     * @param $request object
	 */
	function QuickSubmitForm($plugin, $request) {
		parent::Form($plugin->getTemplatePath() . 'index.tpl');

		$this->request = $request;
		$journal =& $request->getJournal();

		$this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidator($this, 'title', 'required', 'admin.settings.form.titleRequired'));
        $this->addCheck(new FormValidator($this, 'abstract', 'required', 'admin.settings.form.abstractRequired'));
        $this->addCheck(new FormValidatorCustom($this, 'sectionId', 'required', 'author.submit.form.sectionRequired', array(DAORegistry::getDAO('SectionDAO'), 'sectionExists'), array($journal->getId())));
        // $this->addCheck(new FormValidatorCustom($this, 'authorsGridContainer', 'required', 'user.subscriptions.form.typeIdValid', create_function('$submissionId', '$authorDao = DAORegistry::getDAO(\'AuthorDAO\'); return ($authorDao->getAuthorCountBySubmissionId($submissionId) != 0);'), array($request->getUserVar('submissionId'))));
	}

	/**
	 * Get the names of fields for which data should be localized
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('tempFileId', 'title', 'abstract', 'discipline', 'subjectClass', 'subject', 'coverageGeo', 'coverageChron', 'coverageSample', 'type', 'sponsor');
	}

	/**
	 * Display the form.
	 */
	function display() {
        $request =& $this->request;
        $journal =& $request->getJournal();

        $templateMgr =& TemplateManager::getManager($request);
        $templateMgr->assign('abstractsRequired', true);

        // Get section for this context
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionOptions = array('0' => '') + $sectionDao->getSectionTitles($journal->getId());
		$templateMgr->assign('sectionOptions', $sectionOptions);

		parent::display();
	}

    /**
     * Perform additional validation checks
     * @copydoc Form::validate
     */
	function validate() {
		if (!parent::validate()) return false;

		// Validate that the section ID is attached to this journal.
		$request = Application::getRequest();
		$context = $request->getContext();
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$section = $sectionDao->getById($this->getData('sectionId'), $context->getId());
		if (!$section) return false;

        // Validate existance of authors
        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->getById($this->getData('submissionId'), $context->getId(), false);

        if (isset($submission)) {
            $authors = $submission->getAuthors();
            if (!(isset($authors) && is_array($authors) && count($authors) != 0)) {
                $this->addError('authorsGridContainer', 'user.subscriptions.form.typeIdValid');
                $this->errorFields['authorsGridContainer'] = 1;

                return false;
            }
        }
        else {
            
            return false;
        }

		return true;
	}

	/**
	 * Initialize form data for a new form.
	 */
	function initData() {
        $request =& $this->request;
        $journal =& $request->getJournal();
        $supportedSubmissionLocales = $journal->getSetting('supportedSubmissionLocales');

        $this->_data = array();

        if (!isset($this->submissionId)){
            $sectionDao = DAORegistry::getDAO('SectionDAO');
            $sectionOptions = $sectionDao->getSectionTitles($journal->getId());

            $submissionDao = Application::getSubmissionDAO();
            $this->submission = $submissionDao->newDataObject();
            $user = $request->getUser();
            $this->submission->setContextId($journal->getId());
            //$this->setSubmissionData($this->submission);
            $this->submission->stampStatusModified();
            $this->submission->setSubmissionProgress($this->step + 1);
            $this->submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
            $this->submission->setCopyrightNotice($journal->getLocalizedSetting('copyrightNotice'), $this->getData('locale'));
            $this->submission->setSectionId(current(array_keys($sectionOptions)));
            // Insert the submission
            $this->submissionId = $submissionDao->insertObject($this->submission);
            $this->setData('submissionId', $this->submissionId);
        }


	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(
			array(
				'tempFileId',
				'destination',
				'issueId',
				'pages',
				'sectionId',
				'authors',
				'primaryContact',
				'title',
				'abstract',
				'discipline',
				'subjectClass',
				'subject',
				'coverageGeo',
				'coverageChron',
				'coverageSample',
				'type',
				'language',
				'sponsor',
				'citations',
				'title',
				'abstract',
				'locale',
                'submissionId'
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
		$articleDao =& DAORegistry::getDAO('ArticleDAO');

		$application =& PKPApplication::getApplication();
		$request =& $this->request;
		$user =& $request->getUser();
		$router =& $request->getRouter();
		$journal =& $router->getContext($request);

        $article = $articleDao -> newDataObject();
		$article->setJournalId($journal->getId());
		$article->setSectionId($this->getData('sectionId'));
		$article->setTitle($this->getData('title'), null); // Localized
		$article->setAbstract($this->getData('abstract'), null); // Localized
		//$article->setDiscipline($this->getData('discipline'), null); // Localized
		//$article->setSubjectClass($this->getData('subjectClass'), null); // Localized
		//$article->setSubject($this->getData('subject'), null); // Localized
		//$article->setCoverageGeo($this->getData('coverageGeo'), null); // Localized
		//$article->setCoverageChron($this->getData('coverageChron'), null); // Localized
		//$article->setCoverageSample($this->getData('coverageSample'), null); // Localized
		//$article->setType($this->getData('type'), null); // Localized
		//$article->setSponsor($this->getData('sponsor'), null); // Localized
		//$article->setCitations($this->getData('citations'));
		//$article->setPages($this->getData('pages'));

		// Set some default values so the ArticleDAO doesn't complain when adding this article
		$article->setDateSubmitted(Core::getCurrentDate());
		$article->setStatus($this->getData('destination') == 'queue' ? STATUS_QUEUED : STATUS_PUBLISHED);
		$article->setSubmissionProgress(0);
		$article->stampStatusModified();
		$article->setCurrentRound(1);
		$article->setFastTracked(1);
		$article->setHideAuthor(0);
		//$article->setCommentsStatus(0);

		// Insert the article to get it's ID
		$articleDao->insertObject($article);
		$articleId = $article->getId();

		// Setup default copyright/license metadata after status is set and authors are attached.
		$article->initializePermissions();
		$articleDao->updateLocaleFields($article);

		// Index article.
		import('classes.search.ArticleSearchIndex');
		$articleSearchIndex = new ArticleSearchIndex();
		$articleSearchIndex->articleMetadataChanged($article);
		$articleSearchIndex->articleChangesFinished();
	}

	/**
	 * Schedule an article for publication in a given issue
	 */
	function scheduleForPublication($articleId, $issueId) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
		$sectionDao =& DAORegistry::getDAO('SectionDAO');
		$issueDao =& DAORegistry::getDAO('IssueDAO');

		$request =& $this->request;
		$journal =& $request->getJournal();
		$submission =& $sectionEditorSubmissionDao->getSectionEditorSubmission($articleId);
		$publishedArticle =& $publishedArticleDao->getPublishedArticleByArticleId($articleId);
		$issue =& $issueDao->getIssueById($issueId, $journal->getId());

		if ($issue) {
			// Schedule against an issue.
			if ($publishedArticle) {
				$publishedArticle->setIssueId($issueId);
				$publishedArticleDao->updatePublishedArticle($publishedArticle);
			} else {
				$publishedArticle = new PublishedArticle();
				$publishedArticle->setId($submission->getId());
				$publishedArticle->setIssueId($issueId);
				$publishedArticle->setDatePublished($this->getData('datePublished'));
				$publishedArticle->setSeq(REALLY_BIG_NUMBER);
				$publishedArticle->setAccessStatus(ARTICLE_ACCESS_ISSUE_DEFAULT);

				$publishedArticleDao->insertPublishedArticle($publishedArticle);

				// Resequence the articles.
				$publishedArticleDao->resequencePublishedArticles($submission->getSectionId(), $issueId);

				// If we're using custom section ordering, and if this is the first
				// article published in a section, make sure we enter a custom ordering
				// for it. (Default at the end of the list.)
				if ($sectionDao->customSectionOrderingExists($issueId)) {
					if ($sectionDao->getCustomSectionOrder($issueId, $submission->getSectionId()) === null) {
						$sectionDao->insertCustomSectionOrder($issueId, $submission->getSectionId(), REALLY_BIG_NUMBER);
						$sectionDao->resequenceCustomSectionOrders($issueId);
					}
				}
			}
		} else {
			if ($publishedArticle) {
				// This was published elsewhere; make sure we don't
				// mess up sequencing information.
				$publishedArticleDao->resequencePublishedArticles($submission->getSectionId(), $publishedArticle->getIssueId());
				$publishedArticleDao->deletePublishedArticleByArticleId($articleId);
			}
		}
		$submission->stampStatusModified();

		if ($issue && $issue->getPublished()) {
			$submission->setStatus(STATUS_PUBLISHED);
			if ($publishedArticle && !$publishedArticle->getDatePublished()) {
				$publishedArticle->setDatePublished($issue->getDatePublished());
			}
		} else {
			$submission->setStatus(STATUS_QUEUED);
		}

		$sectionEditorSubmissionDao->updateSectionEditorSubmission($submission);
		// Call initialize permissions again to check if copyright year needs to be initialized.
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$article = $articleDao->getArticle($articleId);
		$article->initializePermissions();
		$articleDao->updateLocaleFields($article);
	}
}

?>
