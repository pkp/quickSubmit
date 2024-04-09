<?php

/**
 * @file QuickSubmitForm.php
 *
 * Copyright (c) 2013-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class QuickSubmitForm
 *
 * @brief Form for QuickSubmit one-page submission plugin
 */

namespace APP\plugins\importexport\quickSubmit;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\quickSubmit\classes\form\SubmissionMetadataForm;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\submissionFile\SubmissionFile;

class QuickSubmitForm extends Form
{
    protected PKPRequest $_request;
    protected ?PKPSubmission $_submission = null;
    protected Journal $_context;
    protected SubmissionMetadataForm $form;

    /**
     * Constructor
     *
     * @param $plugin object
     * @param $request object
     */
    public function __construct(QuickSubmitPlugin $plugin, PKPRequest $request)
    {
        parent::__construct($plugin->getTemplateResource('index.tpl'));

        $this->_request = $request;
        $this->_context = $request->getContext();

        $this->form = new SubmissionMetadataForm($this);

        $locale = $request->getUserVar('locale');
        if ($locale && ($locale != Locale::getLocale())) {
            $this->setDefaultFormLocale($locale);
        }

        if ($submissionId = $request->getUserVar('submissionId')) {
            $this->_submission = Repo::submission()->get($submissionId);
            if ($this->_submission->getContextId() != $this->_context->getId()) {
                throw new \Exception('Submission not in context!');
            }

            $sectionId = $request->getUserVar('sectionId');
            if (!empty($sectionId)) {
                $this->_submission->setSectionId($sectionId);
            }

            $this->_submission->setLocale($this->getDefaultFormLocale());
            $publication = $this->_submission->getCurrentPublication();
            $publication->setData('locale', $this->getDefaultFormLocale());
            Repo::submission()->edit($this->_submission, []);
            Repo::publication()->edit($publication, []);

            $this->form->addChecks($this->_submission);
        }

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
        $contextId = $this->_context->getId();
        $this->addCheck(
            new \PKP\form\validation\FormValidatorCustom(
                $this,
                'sectionId',
                'required',
                'author.submit.form.sectionRequired',
                function ($sectionId) use ($contextId) {
                    return Repo::section()->exists((int) $sectionId, $contextId);
                }
            )
        );

        // Validation checks for this form
        $supportedSubmissionLocales = $this->_context->getSupportedSubmissionLocales();
        if (!is_array($supportedSubmissionLocales) || count($supportedSubmissionLocales) < 1) {
            $supportedSubmissionLocales = [$this->_context->getPrimaryLocale()];
        }
        $this->addCheck(new \PKP\form\validation\FormValidatorInSet($this, 'locale', 'required', 'submission.submit.form.localeRequired', $supportedSubmissionLocales));

        $this->addCheck(new \PKP\form\validation\FormValidatorUrl($this, 'licenseUrl', 'optional', 'form.url.invalid'));
    }

    /**
     * Get the submission associated with the form.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    /**
     * Get the names of fields for which data should be localized
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return $this->form->getLocaleFieldNames();
    }

    /**
     * Display the form.
     *
     * @param null|mixed $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign(
            'supportedSubmissionLocaleNames',
            $this->_context->getSupportedSubmissionLocaleNames()
        );

        // Tell the form what fields are enabled (and which of those are required)
        foreach (Application::getMetadataFields() as $field) {
            $templateMgr->assign([
                $field . 'Enabled' => in_array($this->_context->getData($field), [Context::METADATA_ENABLE, Context::METADATA_REQUEST, Context::METADATA_REQUIRE]),
                $field . 'Required' => $this->_context->getData($field) === Context::METADATA_REQUIRE,
            ]);
        }

        // Cover image delete link action
        $locale = Locale::getLocale();

        $router = $this->_request->getRouter();
        $publication = $this->_submission->getCurrentPublication();
        $templateMgr->assign('openCoverImageLinkAction', new LinkAction(
            'uploadFile',
            new AjaxModal(
                $router->url($this->_request, null, null, 'importexport', ['plugin', 'QuickSubmitPlugin', 'uploadCoverImage'], [
                    'coverImage' => $publication->getData('coverImage', $locale),
                    'submissionId' => $this->_submission->getId(),
                    'publicationId' => $publication->getId(),
                    // This action can be performed during any stage,
                    // but we have to provide a stage id to make calls
                    // to IssueEntryTabHandler
                    'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
                ]),
                __('common.upload'),
                'modal_add_file'
            ),
            __('common.upload'),
            'add'
        ));

        // Get section for this context
        $sectionTitles = Repo::section()
            ->getCollector()
            ->filterByContextIds([$this->_context->getId()])
            ->getMany()
            ->mapWithKeys(function ($section) {
                return [
                    $section->getId() => $section->getLocalizedTitle()
                ];
            })
            ->toArray();
        $sectionOptions = [0 => ''] + $sectionTitles;
        $templateMgr->assign('sectionOptions', $sectionOptions);

        // Get published Issues
        $issues = Repo::issue()->getCollector()
            ->filterByContextIds([$this->_context->getId()])
            ->orderBy(\APP\issue\Collector::ORDERBY_SHELF)
            ->getMany()
            ->toArray();

        $templateMgr->assign('hasIssues', count($issues) > 0);

        // Get Issues
        $templateMgr->assign([
            'issueOptions' => $this->getIssueOptions($this->_context),
            'submission' => $this->_submission,
            'locale' => $this->getDefaultFormLocale(),
            'publicationId' => $publication->getId(),
        ]);

        $sectionId = $this->getData('sectionId') ?: $this->_submission->getSectionId();
        $section = Repo::section()->get($sectionId, $this->_context->getId());
        $templateMgr->assign([
            'wordCount' => $section->getAbstractWordCount(),
            'abstractsRequired' => !$section->getAbstractsNotRequired(),
        ]);

        // Process entered tagit fields values for redisplay.
        // @see PKPSubmissionHandler::saveStep
        $tagitKeywords = $this->getData('keywords');
        if (is_array($tagitKeywords)) {
            $tagitFieldNames = $this->form->getTagitFieldNames();
            $locales = array_keys($this->supportedLocales);
            $formTagitData = [];
            foreach ($tagitFieldNames as $tagitFieldName) {
                foreach ($locales as $locale) {
                    $formTagitData[$locale] = array_key_exists($locale . "-{$tagitFieldName}", $tagitKeywords) ? $tagitKeywords[$locale . "-{$tagitFieldName}"] : [];
                }
                $this->setData($tagitFieldName, $formTagitData);
            }
        }

        $templateMgr->assign([
            'primaryLocale' => $this->_submission->getLocale(),
        ]);

        parent::display($request, $template);
    }

    /**
     * @copydoc Form::validate
     */
    public function validate($callHooks = true)
    {
        if (!parent::validate($callHooks)) {
            return false;
        }

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
    public function initData()
    {
        $this->_data = [];

        if (!$this->_submission) {
            $this->_data['locale'] = $this->getDefaultFormLocale();

            // Get Sections
            $sectionOptions = Repo::section()
                ->getCollector()
                ->filterByContextIds([$this->_context->getId()])
                ->getMany()
                ->map(function ($section) {
                    return [
                        $section->getId() => $section->getLocalizedTitle()
                    ];
                })
                ->toArray();

            // Create and insert a new submission and publication
            $this->_submission = Repo::submission()->dao->newDataObject();
            $this->_submission->setContextId($this->_context->getId());
            $this->_submission->setStatus(PKPSubmission::STATUS_QUEUED);
            $this->_submission->setSubmissionProgress('start');
            $this->_submission->stampStatusModified();
            $this->_submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
            $this->_submission->setData('sectionId', $sectionId = current(array_keys($sectionOptions)));
            $this->_submission->setLocale($this->getDefaultFormLocale());

            $publication = new Publication();
            $publication->setData('locale', $this->getDefaultFormLocale());
            $publication->setData('sectionId', $sectionId);
            $publication->setData('status', PKPSubmission::STATUS_QUEUED);
            $publication->setData('version', 1);

            Repo::submission()->add($this->_submission, $publication, $this->_context);
            $this->_submission = Repo::submission()->get($this->_submission->getId());
            $this->setData('submissionId', $this->_submission->getId());

            $this->form->initData($this->_submission);

            // Add the user manager group (first that is found) to the stage_assignment for that submission
            $user = $this->_request->getUser();

            $managerUserGroups = Repo::userGroup()->getCollector()
                ->filterByUserIds([$user->getId()])
                ->filterByContextIds([$this->_context->getId()])
                ->filterByRoleIds([Role::ROLE_ID_MANAGER])
                ->getMany();

            // $userGroupId is being used for $stageAssignmentDao->build
            // This build function needs the userGroupId
            // So here the first function should fail if no manager user group is found.
            $userGroupId = $managerUserGroups->firstOrFail()->getId();

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
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var \PKP\stageAssignment\StageAssignmentDAO $stageAssignmentDao */
            $stageAssignmentDao->build($this->_submission->getId(), $userGroupId, $user->getId());
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->form->readInputData();

        $this->readUserVars(
            [
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
            ]
        );
    }

    /**
     * cancel submit
     */
    public function cancel()
    {
        $submission = Repo::submission()->get((int) $this->getData('submissionId')); /** @var Submission $submission */

        if ($this->_submission->getContextId() != $this->_context->getId()) {
            throw new \Exception('Submission not in context!');
        }

        if ($submission) {
            Repo::submission()->delete($submission);
        }
    }

    /**
     * Save settings.
     */
    public function execute(...$functionParams)
    {
        // Execute submission metadata related operations.
        $this->form->execute($this->_submission, $this->_request);

        $publication = $this->_submission->getCurrentPublication();

        // Copy GalleyFiles to Submission Files
        // Get Galley Files by SubmissionId
        $galleyDao = Application::getRepresentationDAO();
        $galleys = $galleyDao->getByPublicationId($publication->getId());

        if (!is_null($galleys)) {
            foreach ($galleys as $galley) {
                $file = $galley->getFile();
                if ($file) {
                    $newSubmissionFile = clone $file;
                    $newSubmissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_SUBMISSION);
                    $newSubmissionFile->unsetData('assocType');
                    $newSubmissionFile->unsetData('assocId');
                    $newSubmissionFile->setData('viewable', true);
                    $newSubmissionFile->setData('sourceSubmissionFileId', $file->getId());
                    $newSubmissionFile = Repo::submissionFile()->add($newSubmissionFile);
                }
            }
        }

        $this->_submission->setLocale($this->getData('locale'));
        $this->_submission->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
        $this->_submission->setDateSubmitted(Core::getCurrentDate());
        $this->_submission->setSubmissionProgress('');

        parent::execute($this->_submission, ...$functionParams);

        Repo::submission()->edit($this->_submission, []);
        $this->_submission = Repo::submission()->get($this->_submission->getId());
        $publication = $this->_submission->getCurrentPublication();

        if ($publication->getData('sectionId') !== (int) $this->getData('sectionId')) {
            $publication = Repo::publication()->edit($publication, ['sectionId' => (int) $this->getData('sectionId')]);
        }

        if ($this->getData('articleStatus') == 1) {
            $publication->setData('copyrightYear', $this->getData('copyrightYear'));
            $publication->setData('copyrightHolder', $this->getData('copyrightHolder'), null);
            $publication->setData('licenseUrl', $this->getData('licenseUrl'));
            $publication->setData('pages', $this->getData('pages'));
            $publication->setData('datePublished', $this->getData('datePublished'));
            $publication->setData('accessStatus', Submission::ARTICLE_ACCESS_ISSUE_DEFAULT);
            $publication->setData('issueId', (int) $this->getData('issueId'));

            // If other articles in this issue have a custom sequence, put this at the end
            $otherSubmissionsInSection = Repo::submission()->getCollector()
                ->filterByContextIds([$this->_request->getContext()->getId()])
                ->filterByIssueIds([$publication->getData('issueId')])
                ->filterBySectionIds([$publication->getData('sectionId')])
                ->getMany()->toArray();
            if (count($otherSubmissionsInSection)) {
                $maxSequence = 0;
                foreach ($otherSubmissionsInSection as $submission) {
                    if ($publication->getData('seq')) {
                        $maxSequence = max($maxSequence, $publication->getData('seq'));
                    }
                }
                $publication->setData('seq', $maxSequence + 1);
            }

            Repo::publication()->publish($publication);
        }

        // Index article.
        $articleSearchIndex = Application::getSubmissionSearchIndex();
        $articleSearchIndex->submissionMetadataChanged($this->_submission);
        $articleSearchIndex->submissionFilesChanged($this->_submission);
        $articleSearchIndex->submissionChangesFinished();
    }

    /**
     * builds the issue options pulldown for published and unpublished issues
     *
     * @param $journal Journal
     *
     * @return array Associative list of options for pulldown
     */
    public function getIssueOptions($journal)
    {
        $issuesPublicationDates = [];
        $issueOptions = [];
        $journalId = $journal->getId();

        $issueOptions[-1] = '------    ' . __('editor.issues.futureIssues') . '    ------';
        $issues = Repo::issue()->getCollector()
            ->filterByContextIds([$journalId])
            ->filterByPublished(false)
            ->orderBy(\APP\issue\Collector::ORDERBY_SHELF)
            ->getMany();

        foreach ($issues as $issue) {
            $issueOptions[$issue->getId()] = $issue->getIssueIdentification();
            $issuesPublicationDates[$issue->getId()] = date(PKPString::convertStrftimeFormat(Config::getVar('general', 'date_format_short')), strtotime(Core::getCurrentDate()));
        }
        $issueOptions[-2] = '------    ' . __('editor.issues.currentIssue') . '    ------';
        $issues = array_values(
            Repo::issue()
                ->getCollector()
                ->filterByContextIds([$journalId])
                ->filterByPublished(true)
                ->orderBy(\APP\issue\Collector::ORDERBY_SHELF)
                ->getMany()
                ->toArray()
        );

        if (isset($issues[0]) && $issues[0]->getId() == $journal->getData('currentIssueId')) {
            $issueOptions[$issues[0]->getId()] = $issues[0]->getIssueIdentification();
            $issuesPublicationDates[$issues[0]->getId()] = date(PKPString::convertStrftimeFormat(Config::getVar('general', 'date_format_short')), strtotime($issues[0]->getDatePublished()));
            array_shift($issues);
        }
        $issueOptions[-3] = '------    ' . __('editor.issues.backIssues') . '    ------';
        foreach ($issues as $issue) {
            $issueOptions[$issue->getId()] = $issue->getIssueIdentification();
            $issuesPublicationDates[$issue->getId()] = date(PKPString::convertStrftimeFormat(Config::getVar('general', 'date_format_short')), strtotime($issues[0]->getDatePublished()));
        }

        $templateMgr = TemplateManager::getManager($this->_request);
        $templateMgr->assign('issuesPublicationDates', json_encode($issuesPublicationDates));

        return $issueOptions;
    }
}
