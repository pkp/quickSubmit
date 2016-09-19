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

		$this->request =& $request;
		$journal =& $request->getJournal();

		$this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidator($this, 'title', 'required', 'admin.settings.form.titleRequired'));
        $this->addCheck(new FormValidator($this, 'abstract', 'required', 'admin.settings.form.abstractRequired'));
        $this->addCheck(new FormValidatorCustom($this, 'sectionId', 'required', 'author.submit.form.sectionRequired', array(DAORegistry::getDAO('SectionDAO'), 'sectionExists'), array($journal->getId())));
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

		return true;
	}

	/**
	 * Initialize form data for a new form.
	 */
	function initData() {
        $request =& $this->request;
        $journal =& $request->getJournal();
        $supportedSubmissionLocales = $journal->getSetting('supportedSubmissionLocales');


        //// Try these locales in order until we find one that's
        //// supported to use as a default.
        //$fallbackLocales = array_keys($supportedSubmissionLocales);
        //$tryLocales = array(
        //    $this->getFormLocale(), // Current form locale
        //    AppLocale::getLocale(), // Current UI locale
        //    $journal->getPrimaryLocale(), // Journal locale
        //    $supportedSubmissionLocales[array_shift($fallbackLocales)] // Fallback: first one on the list
        //);
        $this->_data = array();
        //foreach ($tryLocales as $locale) {
        //    if (in_array($locale, $supportedSubmissionLocales)) {
        //        // Found a default to use
        //        $this->_data['locale'] = $locale;
        //        break;
        //    }
        //}



        if (!isset($this->submission)){
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
				'locale'
			)
		);

		$this->readUserDateVars(array('datePublished'));
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
		//$article->setLocale($this->getData('locale'));
		//$article->setUserId($user->getId());
		$article->setJournalId($journal->getId());
		$article->setSectionId($this->getData('sectionId'));
		// $article->setLanguage($this->getData('language'));
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

		// Add authors
        //$authorDao =& DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
        //$authors = $this->getData('authors');
        //for ($i=0, $count=count($authors); $i < $count; $i++) {
        //    if ($authors[$i]['authorId'] > 0) {
        //        // Update an existing author
        //        $author =& $authorDao->getAuthor($authors[$i]['authorId'], $articleId);
        //        $isExistingAuthor = true;
        //    } else {
        //        // Create a new author
        //        $author = new Author();
        //        $isExistingAuthor = false;
        //    }

        //    if ($author != null) {
        //        $author->setSubmissionId($articleId);
        //        $author->setFirstName($authors[$i]['firstName']);
        //        $author->setMiddleName($authors[$i]['middleName']);
        //        $author->setLastName($authors[$i]['lastName']);
        //        if (array_key_exists('affiliation', $authors[$i])) {
        //            $author->setAffiliation($authors[$i]['affiliation'], null);
        //        }
        //        $author->setCountry($authors[$i]['country']);
        //        $author->setEmail($authors[$i]['email']);
        //        $author->setData('orcid', $authors[$i]['orcid']);
        //        $author->setUrl($authors[$i]['url']);
        //        if (array_key_exists('competingInterests', $authors[$i])) {
        //            $author->setCompetingInterests($authors[$i]['competingInterests'], null);
        //        }
        //        $author->setBiography($authors[$i]['biography'], null);
        //        $author->setPrimaryContact($this->getData('primaryContact') == $i ? 1 : 0);
        //        $author->setSequence($authors[$i]['seq']);

        //        if ($isExistingAuthor == false) {
        //            $authorDao->insertAuthor($author);
        //        }
        //    }
        //}

		// Setup default copyright/license metadata after status is set and authors are attached.
		$article->initializePermissions();
		$articleDao->updateLocaleFields($article);

		// Add the submission files as galleys
        //import('classes.file.TemporaryFileManager');
        //import('classes.file.ArticleFileManager');
        //$tempFileIds = $this->getData('tempFileId');
        //$temporaryFileManager = new TemporaryFileManager();
        //$articleFileManager = new ArticleFileManager($articleId);
        //$designatedPrimary = false;
        //foreach (array_keys($tempFileIds) as $locale) {
        //    $temporaryFile = $temporaryFileManager->getFile($tempFileIds[$locale], $user->getId());
        //    $fileId = null;
        //    if ($temporaryFile) {
        //        $fileId = $articleFileManager->temporaryFileToArticleFile($temporaryFile, ARTICLE_FILE_SUBMISSION);
        //        $fileType = $temporaryFile->getFileType();

        //        if (strstr($fileType, 'html')) {
        //            import('classes.article.ArticleHTMLGalley');
        //            $galley = new ArticleHTMLGalley();
        //        } else {
        //            import('classes.article.ArticleGalley');
        //            $galley = new ArticleGalley();
        //        }
        //        $galley->setArticleId($articleId);
        //        $galley->setFileId($fileId);
        //        $galley->setLocale($locale);

        //        if ($galley->isHTMLGalley()) {
        //            $galley->setLabel('HTML');
        //        } else {
        //            if (strstr($fileType, 'pdf')) {
        //                $galley->setLabel('PDF');
        //            } else if (strstr($fileType, 'postscript')) {
        //                $galley->setLabel('Postscript');
        //            } else if (strstr($fileType, 'xml')) {
        //                $galley->setLabel('XML');
        //            } else {
        //                $galley->setLabel(__('common.untitled'));
        //            }
        //        }

        //        $galleyDao =& DAORegistry::getDAO('ArticleGalleyDAO');
        //        $galleyDao->insertGalley($galley);

        //        if (!$designatedPrimary) {
        //            $article->setSubmissionFileId($fileId);
        //            $article->setReviewFileId($fileId);
        //            if ($locale == $journal->getPrimaryLocale()) {
        //                // Used to make sure that *some* file
        //                // is designated Review Version, but
        //                // preferrably the primary locale.
        //                $designatedPrimary = true;
        //            }
        //        }
        //    }

        //    // Update file search index
        //    import('classes.search.ArticleSearchIndex');
        //    $articleSearchIndex = new ArticleSearchIndex();
        //    if (isset($galley)) {
        //        $articleSearchIndex->articleFileChanged(
        //            $galley->getArticleId(), ARTICLE_SEARCH_GALLEY_FILE, $galley->getFileId()
        //        );
        //    }
        //    $articleSearchIndex->articleChangesFinished();
        //}


        //// Designate this as the review version by default.
        //$authorSubmissionDao =& DAORegistry::getDAO('AuthorSubmissionDAO');
        //$authorSubmission =& $authorSubmissionDao->getAuthorSubmission($articleId);
        //import('classes.submission.author.AuthorAction');
        //AuthorAction::designateReviewVersion($authorSubmission, true);

        //// Accept the submission
        //$sectionEditorSubmission =& $sectionEditorSubmissionDao->getSectionEditorSubmission($articleId);
        //$articleFileManager = new ArticleFileManager($articleId);
        //$sectionEditorSubmission->setReviewFile($articleFileManager->getFile($article->getSubmissionFileId()));
        //import('classes.submission.sectionEditor.SectionEditorAction');
        //SectionEditorAction::recordDecision($sectionEditorSubmission, SUBMISSION_EDITOR_DECISION_ACCEPT, $this->request);

        //// Create signoff infrastructure
        //$copyeditInitialSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $articleId);
        //$copyeditAuthorSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_AUTHOR', ASSOC_TYPE_ARTICLE, $articleId);
        //$copyeditFinalSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_FINAL', ASSOC_TYPE_ARTICLE, $articleId);
        //$copyeditInitialSignoff->setUserId(0);
        //$copyeditAuthorSignoff->setUserId($user->getId());
        //$copyeditFinalSignoff->setUserId(0);
        //$signoffDao->updateObject($copyeditInitialSignoff);
        //$signoffDao->updateObject($copyeditAuthorSignoff);
        //$signoffDao->updateObject($copyeditFinalSignoff);

        //$layoutSignoff = $signoffDao->build('SIGNOFF_LAYOUT', ASSOC_TYPE_ARTICLE, $articleId);
        //$layoutSignoff->setUserId(0);
        //$signoffDao->updateObject($layoutSignoff);

        //$proofAuthorSignoff = $signoffDao->build('SIGNOFF_PROOFREADING_AUTHOR', ASSOC_TYPE_ARTICLE, $articleId);
        //$proofProofreaderSignoff = $signoffDao->build('SIGNOFF_PROOFREADING_PROOFREADER', ASSOC_TYPE_ARTICLE, $articleId);
        //$proofLayoutEditorSignoff = $signoffDao->build('SIGNOFF_PROOFREADING_LAYOUT', ASSOC_TYPE_ARTICLE, $articleId);
        //$proofAuthorSignoff->setUserId($user->getId());
        //$proofProofreaderSignoff->setUserId(0);
        //$proofLayoutEditorSignoff->setUserId(0);
        //$signoffDao->updateObject($proofAuthorSignoff);
        //$signoffDao->updateObject($proofProofreaderSignoff);
        //$signoffDao->updateObject($proofLayoutEditorSignoff);

        //import('classes.author.form.submit.AuthorSubmitForm');
        //AuthorSubmitForm::assignEditors($article);

        //$articleDao->updateArticle($article);

        //// Add to end of editing queue
        //import('classes.submission.editor.EditorAction');
        //if (isset($galley)) EditorAction::expediteSubmission($article, $this->request);

        //if ($this->getData('destination') == "issue") {
        //    // Add to an existing issue
        //    $issueId = $this->getData('issueId');
        //    $this->scheduleForPublication($articleId, $issueId);
        //}

        //// Import the references list.
        //$citationDao =& DAORegistry::getDAO('CitationDAO');
        //$rawCitationList = $article->getCitations();
        //$citationDao->importCitations($request, ASSOC_TYPE_ARTICLE, $articleId, $rawCitationList);

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
