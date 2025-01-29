<?php

/**
 * @file classes/submission/SubmissionMetadataForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionMetadataForm
 *
 * @ingroup submission
 *
 * @deprecated 3.4
 *
 * @brief This can be used by other forms that want to
 * implement submission metadata data and form operations.
 */

namespace APP\plugins\importexport\quickSubmit\classes\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\log\event\SubmissionEventLogEntry;
use APP\submission\Submission;
use APP\plugins\importexport\quickSubmit\QuickSubmitForm;
use PKP\context\Context;
use PKP\core\Core;
use PKP\controlledVocab\ControlledVocab;
use PKP\security\Validation;

class SubmissionMetadataForm
{
    /** @var QuickSubmitForm Form that uses this implementation */
    public $_parentForm;

    /**
     * Constructor.
     *
     * @param QuickSubmitForm $parentForm A form that can use this form.
     */
    public function __construct($parentForm = null)
    {
        assert($parentForm instanceof \PKP\form\Form);
        $this->_parentForm = $parentForm;
    }

    /**
     * Retrieve whether abstracts are required
     */
    public function _getAbstractsRequired($submission)
    {
        $section = Repo::section()
            ->get(
                $submission->getCurrentPublication()->getData('sectionId'),
                $submission->getData('contextId')
            );

        return !$section->getAbstractsNotRequired();
    }

    /**
     * Add checks to form.
     *
     * @param Submission $submission
     */
    public function addChecks($submission)
    {
        // Validation checks.
        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorLocale($this->_parentForm, 'title', 'required', 'submission.submit.form.titleRequired', $submission->getCurrentPublication()->getData('locale')));
        if ($this->_getAbstractsRequired($submission)) {
            $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorLocale($this->_parentForm, 'abstract', 'required', 'submission.submit.form.abstractRequired', $submission->getCurrentPublication()->getData('locale')));
        }

        // Validates that at least one author has been added.
        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorCustom(
            $this->_parentForm,
            'authors',
            'required',
            'submission.submit.form.authorRequired',
            function () use ($submission) {
                return !empty($submission->getCurrentPublication()->getData('authors'));
            }
        ));

        $contextDao = Application::getContextDao();
        $context = $contextDao->getById($submission->getData('contextId'));
        $metadataFields = Application::getMetadataFields();
        foreach ($metadataFields as $field) {
            $requiredLocaleKey = 'submission.submit.form.' . $field . 'Required';
            if ($context->getData($field) === Context::METADATA_REQUIRE) {
                switch ($field) {
                    case in_array($field, $this->getLocaleFieldNames()):
                        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorLocale($this->_parentForm, $field, 'required', $requiredLocaleKey, $submission->getCurrentPublication()->getData('locale')));
                        break;
                    case in_array($field, $this->getTagitFieldNames()):
                        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorCustom($this->_parentForm, $field, 'required', $requiredLocaleKey, function ($field, $form, $name) {
                            $data = (array) $form->getData('keywords');
                            return array_key_exists($name, $data);
                        }, [$this->_parentForm, $submission->getCurrentPublication()->getData('locale') . '-' . $field]));
                        break;
                    case 'citations':
                        $form = $this->_parentForm;
                        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorCustom($this->_parentForm, 'citationsRaw', 'required', $requiredLocaleKey, function ($key) use ($form) {
                            return !empty($form->getData('citationsRaw'));
                        }));
                        break;
                    default:
                        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidator($this->_parentForm, $field, 'required', $requiredLocaleKey));
                }
            }
        }

        $section = Repo::section()->get($submission->getCurrentPublication()->getData('sectionId'), $submission->getData('contextId'));
        $wordCount = $section->getAbstractWordCount();
        if (isset($wordCount) && $wordCount > 0) {
            $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorCustom($this->_parentForm, 'abstract', 'required', 'submission.submit.form.wordCountAlert', function ($abstract) use ($wordCount) {
                foreach ($abstract as $localizedAbstract) {
                    if (count(preg_split('/\s+/', trim(str_replace('&nbsp;', ' ', strip_tags($localizedAbstract))))) > $wordCount) {
                        return false;
                    }
                }
                return true;
            }));
        }
    }

    /**
     * Initialize form data from current submission.
     *
     * @param Submission $submission
     */
    public function initData($submission)
    {
        if (isset($submission)) {
            $publication = $submission->getCurrentPublication();
            $formData = [
                'title' => $publication->getData('title'),
                'prefix' => $publication->getData('prefix'),
                'subtitle' => $publication->getData('subtitle'),
                'abstract' => $publication->getData('abstract'),
                'coverage' => $publication->getData('coverage'),
                'type' => $publication->getData('type'),
                'source' => $publication->getData('source'),
                'rights' => $publication->getData('rights'),
                'citationsRaw' => $publication->getData('citationsRaw'),
                'locale' => $publication->getData('locale'),
                'dataAvailability' => $publication->getData('dataAvailability'),
            ];

            foreach ($formData as $key => $data) {
                $this->_parentForm->setData($key, $data);
            }

            $this->_parentForm->setData('subjects', $publication->getData('subjects'));
            $this->_parentForm->setData('keywords', $publication->getData('keywords'));
            $this->_parentForm->setData('disciplines', $publication->getData('disciplines'));
            $this->_parentForm->setData('agencies', $publication->getData('supportingAgencies'));
            $this->_parentForm->setData('abstractsRequired', $this->_getAbstractsRequired($submission));
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        // 'keywords' is a tagit catchall that contains an array of values for each keyword/locale combination on the form.
        $userVars = [
            'title',
            'prefix',
            'subtitle',
            'abstract',
            'coverage',
            'type',
            'source',
            'rights',
            'keywords',
            'citationsRaw',
            'locale',
            'dataAvailability',
        ];

        $this->_parentForm->readUserVars($userVars);
    }

    /**
     * Get the names of fields for which data should be localized
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return [
            'title',
            'prefix',
            'subtitle',
            'abstract',
            'coverage',
            'type',
            'source',
            'rights',
            'dataAvailability',
        ];
    }

    /**
     * Get the names of fields for which tagit is used
     *
     * @return array
     */
    public function getTagitFieldNames()
    {
        return ['subjects', 'keywords', 'disciplines', 'agencies'];
    }

    /**
     * Save changes to submission.
     *
     * @param \APP\submission\Submission $submission
     * @param \PKP\core\PKPRequest $request
     */
    public function execute($submission, $request)
    {
        $publication = $submission->getCurrentPublication();
        $context = $request->getContext();

        // Get params to update
        $params = [
            'title' => $this->_parentForm->getData('title'),
            'prefix' => $this->_parentForm->getData('prefix'),
            'subtitle' => $this->_parentForm->getData('subtitle'),
            'abstract' => $this->_parentForm->getData('abstract'),
            'coverage' => $this->_parentForm->getData('coverage'),
            'type' => $this->_parentForm->getData('type'),
            'rights' => $this->_parentForm->getData('rights'),
            'source' => $this->_parentForm->getData('source'),
            'citationsRaw' => $this->_parentForm->getData('citationsRaw'),
            'dataAvailability' => $this->_parentForm->getData('dataAvailability'),
        ];

        // Save the publication
        Repo::publication()->edit($publication, $params);
        $publication = Repo::publication()->get($publication->getId());

        // get the supported locale keys
        $locales = array_keys($this->_parentForm->supportedLocales);

        $keywords = [];
        $agencies = [];
        $disciplines = [];
        $subjects = [];

        $tagitKeywords = $this->_parentForm->getData('keywords');

        if (is_array($tagitKeywords)) {
            foreach ($locales as $locale) {
                $keywords[$locale] = array_key_exists($locale . '-keywords', $tagitKeywords) ? $tagitKeywords[$locale . '-keywords'] : [];
                $agencies[$locale] = array_key_exists($locale . '-agencies', $tagitKeywords) ? $tagitKeywords[$locale . '-agencies'] : [];
                $disciplines[$locale] = array_key_exists($locale . '-disciplines', $tagitKeywords) ? $tagitKeywords[$locale . '-disciplines'] : [];
                $subjects[$locale] = array_key_exists($locale . '-subjects', $tagitKeywords) ? $tagitKeywords[$locale . '-subjects'] : [];
            }
        }

        $currentPublication = $submission->getCurrentPublication();
        Repo::controlledVocab()->insertBySymbolic(
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
            $keywords,
            Application::ASSOC_TYPE_PUBLICATION,
            $currentPublication->getId()
        );
        Repo::controlledVocab()->insertBySymbolic(
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_AGENCY,
            $agencies,
            Application::ASSOC_TYPE_PUBLICATION,
            $currentPublication->getId()
        );
        Repo::controlledVocab()->insertBySymbolic(
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE,
            $disciplines,
            Application::ASSOC_TYPE_PUBLICATION,
            $currentPublication->getId()
        );
        Repo::controlledVocab()->insertBySymbolic(
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
            $subjects,
            Application::ASSOC_TYPE_PUBLICATION,
            $currentPublication->getId()
        );

        // Only log modifications on completed submissions
        if (!$submission->getData('submissionProgress')) {
            // Log the metadata modification event.
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => Application::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
                'userId' => Validation::loggedInAs() ?? $request->getUser()?->getId(),
                'message' => 'submission.event.general.metadataUpdated',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
            ]);
            Repo::eventLog()->add($eventLog);
        }
    }
}
