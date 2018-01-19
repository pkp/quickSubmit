<?php

/**
 * @file plugins/importexport/quickSubmit/classes/form/UploadImageForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UploadImageForm
 * @ingroup plugins_importexport_quicksubmit_classes_form
 *
 * @brief Form for upload an image.
 */

import('lib.pkp.controllers.tab.settings.form.SettingsFileUploadForm');

class UploadImageForm extends SettingsFileUploadForm {

	/** @var $request object */
	var $request;

	/** @var $submissionId int */
	var $submissionId;

	/** @var $submission Submission */
	var $submission;

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
		parent::__construct($plugin->getTemplatePath() . 'uploadImageForm.tpl');

		$this->plugin = $plugin;
		$this->request = $request;
		$this->context = $request->getContext();

		$this->submissionId = $request->getUserVar('submissionId');

		$submissionDao = Application::getSubmissionDAO();
		$this->submission = $submissionDao->getById($request->getUserVar('submissionId'), $this->context->getId(), false);
	}


	//
	// Extend methods from SettingsFileUploadForm.
	//
	/**
	 * @copydoc SettingsFileUploadForm::fetch()
	 */
	function fetch($request) {
		$params = array('fileType' => 'image');
		return parent::fetch($request, $params);
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
		$templateMgr->register_function('plugin_url', array($this->plugin, 'smartyPluginUrl'));
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
		$this->readUserVars(array('imageAltText'));

		parent::readInputData();
	}

	/**
	 * An action to delete an article cover image.
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteCoverImage($request) {
		assert($request->getUserVar('coverImage') != '' && $request->getUserVar('submissionId') != '');

		$submissionDao = Application::getSubmissionDAO();
		$file = $request->getUserVar('coverImage');

		// Remove cover image and alt text from article settings
		$locale = AppLocale::getLocale();
		$this->submission->setCoverImage('', $locale);
		$this->submission->setCoverImageAltText('', $locale);

		$submissionDao->updateObject($this->submission);

		// Remove the file
		$publicFileManager = new PublicFileManager();
		if ($publicFileManager->removeJournalFile($this->submission->getJournalId(), $file)) {
			$json = new JSONMessage(true);
			$json->setEvent('fileDeleted');
			return $json;
		} else {
			return new JSONMessage(false, __('editor.article.removeCoverImageFileNotFound'));
		}
	}

	/**
	 * Save file image to Submission
	 * @param $request Request.
	 */
	function execute($request) {
		$submissionDao = Application::getSubmissionDAO();

		$temporaryFile = $this->fetchTemporaryFile($request);
		$locale = AppLocale::getLocale();
		$coverImage = $this->submission->getCoverImage($locale);

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

			if($publicFileManager->copyJournalFile($this->context->getId(), $temporaryFile->getFilePath(), $newFileName)) {

				$this->submission->setCoverImage($newFileName, $locale);

				$imageAltText = $this->getData('imageAltText');

				$this->submission->setCoverImageAltText($imageAltText, $locale);

				$submissionDao->updateObject($this->submission);

				// Clean up the temporary file.
				$this->removeTemporaryFile($request);

				return DAO::getDataChangedEvent();
			}
		} elseif ($coverImage) {
			$imageAltText = $this->getData('imageAltText');
			$this->submission->setCoverImageAltText($imageAltText, $locale);
			$submissionDao->updateObject($this->submission);
			return DAO::getDataChangedEvent();
		}
		return new JSONMessage(false, __('common.uploadFailed'));

	}
}

?>
