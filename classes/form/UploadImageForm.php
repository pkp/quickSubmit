<?php

/**
 * @file plugins/importexport/quickSubmit/classes/form/UploadImageForm.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class UploadImageForm
 *
 * @brief Form for upload an image.
 */

namespace APP\plugins\importexport\quickSubmit\classes\form;

use APP\facades\Repo;
use APP\plugins\importexport\quickSubmit\QuickSubmitPlugin;
use APP\file\PublicFileManager;
use APP\template\TemplateManager;
use Exception;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileDAO;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\publication\PKPPublication;
use PKP\submission\PKPSubmission;

class UploadImageForm extends Form
{
    public string $_fileSettingName;

    public PKPRequest $request;

    public int $submissionId;

    public PKPSubmission $submission;

    public PKPPublication $publication;

    public QuickSubmitPlugin $plugin;

    public Context $context;

    /**
     * Constructor.
     */
    public function __construct(QuickSubmitPlugin $plugin, PKPRequest $request)
    {
        parent::__construct($plugin->getTemplateResource('uploadImageForm.tpl'));

        $this->addCheck(new FormValidator($this, 'temporaryFileId', 'required', 'manager.website.imageFileRequired'));

        $this->plugin = $plugin;
        $this->request = $request;
        $this->context = $request->getContext();

        $this->submissionId = $request->getUserVar('submissionId');

        $this->submission = Repo::submission()->get($request->getUserVar('submissionId'));
        if ($this->submission->getData('contextId') != $this->context->getId()) {
            throw new Exception('Submission context ID does not match context!');
        }
        $this->publication = $this->submission->getCurrentPublication();
    }

    //
    // Extend methods from Form.
    //
    /**
     * @copydoc \PKP\form\Form::getLocaleFieldNames()
     */
    public function getLocaleFieldNames(): array
    {
        return ['imageAltText'];
    }

    /**
     * @copydoc \PKP\form\Form::initData()
     */
    public function initData()
    {
        $templateMgr = TemplateManager::getManager($this->request);
        $templateMgr->assign('submissionId', $this->submissionId);

        $locale = Locale::getLocale();
        $coverImage = $this->publication->getData('coverImage', $locale);

        if ($coverImage) {
            $router = $this->request->getRouter();
            $deleteCoverImageLinkAction = new LinkAction(
                'deleteCoverImage',
                new RemoteActionConfirmationModal(
                    $this->request->getSession(),
                    __('common.confirmDelete'),
                    null,
                    $router->url($this->request, null, null, 'importexport', ['plugin', 'QuickSubmitPlugin', 'deleteCoverImage'], [
                        'coverImage' => $coverImage['uploadName'],
                        'submissionId' => $this->submission->getId(),
                        'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
                    ]),
                    'modal_delete'
                ),
                __('common.delete'),
                null
            );
            $templateMgr->assign('deleteCoverImageLinkAction', $deleteCoverImageLinkAction);
        }

        $this->setData('coverImage', $coverImage);
        $this->setData('imageAltText', $coverImage['altText'] ?? '');
        $this->setData('coverImageName', $coverImage['uploadName'] ?? '');
    }

    /**
     * @copydoc \PKP\form\Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['imageAltText', 'temporaryFileId']);
    }

    /**
     * An action to delete an article cover image.
     */
    public function deleteCoverImage(PKPRequest $request): JSONMessage
    {
        assert($request->getUserVar('coverImage') != '' && $request->getUserVar('submissionId') != '');

        $file = $request->getUserVar('coverImage');

        // Remove cover image and alt text from article settings
        $this->publication->setData('coverImage', []);
        Repo::publication()->edit($this->publication, []);

        // Remove the file
        $publicFileManager = new PublicFileManager();
        if ($publicFileManager->removeContextFile($this->submission->getData('contextId'), $file)) {
            $json = new JSONMessage(true);
            $json->setEvent('fileDeleted');
            return $json;
        }

        return new JSONMessage(false, __('editor.article.removeCoverImageFileNotFound'));
    }

    /**
     * Save file image to Submission
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $temporaryFile = $this->fetchTemporaryFile($this->request);
        $locale = Locale::getLocale();
        $coverImage = $this->publication->getData('coverImage');

        if ($temporaryFile instanceof \PKP\file\TemporaryFile) {
            $type = $temporaryFile->getFileType();
            $fileManager = new FileManager();
            $extension = $fileManager->getImageExtension($type);
            if (!$extension) {
                return false;
            }
            $locale = Locale::getLocale();

            $newFileName = 'article_' . $this->submissionId . '_cover_' . $locale . $fileManager->getImageExtension($temporaryFile->getFileType());

            $publicFileManager = new PublicFileManager();
            if ($publicFileManager->copyContextFile($this->context->getId(), $temporaryFile->getFilePath(), $newFileName)) {
                $this->publication->setData('coverImage', [
                    'altText' => $this->getData('imageAltText'),
                    'uploadName' => $newFileName,
                ], $locale);
                Repo::publication()->edit($this->publication, []);

                // Clean up the temporary file.
                $this->removeTemporaryFile($this->request);
            }
        } elseif ($coverImage) {
            $coverImage = $this->publication->getData('coverImage');
            $coverImage[$locale]['altText'] = $this->getData('imageAltText');
            $this->publication->setData('coverImage', $coverImage);
            Repo::publication()->edit($this->publication, []);
        }

        return \PKP\db\DAO::getDataChangedEvent();
    }

    /**
     * Get the image that this form will upload a file to.
     *
     * @return string
     */
    public function getFileSettingName(): ?string
    {
        return isset($this->_fileSettingName) ? $this->_fileSettingName : null;
    }

    /**
     * Set the image that this form will upload a file to.
     */
    public function setFileSettingName(string $fileSettingName): void
    {
        $this->_fileSettingName = $fileSettingName;
    }


    //
    // Implement template methods from Form.
    //
    /**
     * @see Form::fetch()
     *
     * @param $params template parameters
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false, $params = null)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'fileSettingName' => $this->getFileSettingName(),
            'fileType' => 'image',
        ]);

        return parent::fetch($request, $template, $display);
    }


    //
    // Public methods
    //
    /**
     * Fetch the temporary file.
     */
    public function fetchTemporaryFile(PKPRequest $request): TemporaryFile
    {
        $user = $request->getUser();

        /** @var TemporaryFileDAO $temporaryFileDao */
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
        $temporaryFile = $temporaryFileDao->getTemporaryFile(
            $this->getData('temporaryFileId'),
            $user->getId()
        );

        return $temporaryFile;
    }

    /**
     * Clean temporary file.
     */
    public function removeTemporaryFile(PKPRequest $request): void
    {
        $user = $request->getUser();

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFileManager->deleteById($this->getData('temporaryFileId'), $user->getId());
    }

    /**
     * Upload a temporary file.
     */
    public function uploadFile(PKPRequest $request): int|bool
    {
        $user = $request->getUser();

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());

        if ($temporaryFile) {
            return $temporaryFile->getId();
        }

        return false;
    }
}
