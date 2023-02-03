<?php

/**
 * @file classes/form/UploadImageForm.inc.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class UploadImageForm
 * @ingroup plugins_importexport_quicksubmit_classes_form
 *
 * @brief Form for upload an image.
 */

import('lib.pkp.classes.form.Form');

class UploadImageForm extends Form {
	/** string Setting key that will be associated with the uploaded file. */
	var $_fileSettingName;

	/** @var $request object */
	var $request;

	/** @var $submissionId int */
	var $submissionId;

	/** @var $submission Submission */
	var $submission;

	/** @var $publication Publication */
	var $publication;

	/** @var $plugin QuickSubmitPlugin */
	var $plugin;

	/** @var $context Journal */
	var $context;

	/**
	 * Constructor.
	 * @param $plugin object
	 * @param $request object
	 */
	function __construct($plugin, $request) {
		parent::__construct($plugin->getTemplateResource('uploadImageForm.tpl'));

		$this->addCheck(new FormValidator($this, 'temporaryFileId', 'required', 'manager.website.imageFileRequired'));

		$this->plugin = $plugin;
		$this->request = $request;
		$this->context = $request->getContext();

		$this->submissionId = $request->getUserVar('submissionId');

		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$this->submission = $submissionDao->getById($request->getUserVar('submissionId'), $this->context->getId(), false);
		$this->publication = $this->submission->getCurrentPublication();
	}

	//
	// Extend methods from Form.
	//
	/**
	 * @copydoc Form::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array('imageAltText');
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$templateMgr = TemplateManager::getManager($this->request);
		$templateMgr->assign('submissionId', $this->submissionId);

		$locale = AppLocale::getLocale();
		$coverImage = $this->submission->getCoverImage($locale);

		if ($coverImage) {
			import('lib.pkp.classes.linkAction.LinkAction');
			import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
			$router = $this->request->getRouter();
			$deleteCoverImageLinkAction = new LinkAction(
				'deleteCoverImage',
				new RemoteActionConfirmationModal(
					$this->request->getSession(),
					__('common.confirmDelete'), null,
					$router->url($this->request, null, null, 'importexport', array('plugin', 'QuickSubmitPlugin', 'deleteCoverImage'), array(
						'coverImage' => $coverImage,
						'submissionId' => $this->submission->getId(),
						'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
					)),
					'modal_delete'
				),
				__('common.delete'),
				null
			);
			$templateMgr->assign('deleteCoverImageLinkAction', $deleteCoverImageLinkAction);
		}

		$this->setData('coverImage', $coverImage);
		$this->setData('imageAltText', $this->submission->getCoverImageAltText($locale));
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('imageAltText', 'temporaryFileId'));
	}

	/**
	 * An action to delete an article cover image.
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteCoverImage($request) {
		assert($request->getUserVar('coverImage') != '' && $request->getUserVar('submissionId') != '');

		$publicationDao = DAORegistry::getDAO('PublicationDAO'); /* @var $publicationDao PublicationDAO */
		$file = $request->getUserVar('coverImage');

		// Remove cover image and alt text from article settings
		$locale = AppLocale::getLocale();
		$this->publication->setData('coverImage', []);
		$publicationDao->updateObject($this->publication);

		// Remove the file
		$publicFileManager = new PublicFileManager();
		if ($publicFileManager->removeContextFile($this->submission->getContextId(), $file)) {
			$json = new JSONMessage(true);
			$json->setEvent('fileDeleted');
			return $json;
		} else {
			return new JSONMessage(false, __('editor.article.removeCoverImageFileNotFound'));
		}
	}

	/**
	 * Save file image to Submission
	 * @param mixed $functionArgs,... Arguments from the caller to be passed to the hook consumer
	 */
	function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		$publicationDao = DAORegistry::getDAO('PublicationDAO'); /* @var $publicationDao PublicationDAO */

		$temporaryFile = $this->fetchTemporaryFile($request);
		$locale = AppLocale::getLocale();
		$coverImage = $this->publication->getData('coverImage');

		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();

		if (is_a($temporaryFile, 'TemporaryFile')) {
			$type = $temporaryFile->getFileType();
			$extension = $publicFileManager->getImageExtension($type);
			if (!$extension) {
				return false;
			}
			$locale = AppLocale::getLocale();

			$newFileName = 'article_' . $this->submissionId . '_cover_' . $locale . $publicFileManager->getImageExtension($temporaryFile->getFileType());

			if ($publicFileManager->copyContextFile($this->context->getId(), $temporaryFile->getFilePath(), $newFileName)) {

				$this->publication->setData('coverImage', [
					'altText' => $this->getData('imageAltText'),
					'uploadName' => $newFileName,
				], $locale);
				$publicationDao->updateObject($this->publication);

				// Clean up the temporary file.
				$this->removeTemporaryFile($request);

				return DAO::getDataChangedEvent();
			}
		} elseif ($coverImage) {
			$coverImage = $this->publication->getData('coverImage');
			$coverImage[$locale]['altText'] = $this->getData('imageAltText');
			$this->publication->setData('coverImage', $coverImage);
			$publicationDao->updateObject($this->publication);
			return DAO::getDataChangedEvent();
		}
		return new JSONMessage(false, __('common.uploadFailed'));

	}

	/**
	 * Get the image that this form will upload a file to.
	 * @return string
	 */
	function getFileSettingName() {
		return $this->_fileSettingName;
	}

	/**
	 * Set the image that this form will upload a file to.
	 * @param $image string
	 */
	function setFileSettingName($fileSettingName) {
		$this->_fileSettingName = $fileSettingName;
	}


	//
	// Implement template methods from Form.
	//
	/**
	 * @see Form::fetch()
	 * @param $params template parameters
	 */
	function fetch($request, $template = null, $display = false, $params = null) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'fileSettingName' => $this->getFileSettingName(),
			'fileType' => 'image',
		));

		return parent::fetch($request, $template, $display);
	}


	//
	// Public methods
	//
	/**
	 * Fecth the temporary file.
	 * @param $request Request
	 * @return TemporaryFile
	 */
	function fetchTemporaryFile($request) {
		$user = $request->getUser();

		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
		$temporaryFile = $temporaryFileDao->getTemporaryFile(
			$this->getData('temporaryFileId'),
			$user->getId()
		);
		return $temporaryFile;
	}

	/**
	 * Clean temporary file.
	 * @param $request Request
	 */
	function removeTemporaryFile($request) {
		$user = $request->getUser();

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFileManager->deleteById($this->getData('temporaryFileId'), $user->getId());
	}

	/**
	 * Upload a temporary file.
	 * @param $request Request
	 */
	function uploadFile($request) {
		$user = $request->getUser();

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());

		if ($temporaryFile) return $temporaryFile->getId();

		return false;
	}
}

