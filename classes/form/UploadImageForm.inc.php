<?php

/**
 * @file controllers/tab/settings/appearance/form/NewContextImageFileForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NewContextImageFileForm
 * @ingroup controllers_tab_settings_appearance_form
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

		//$this->setFileSettingName($imageSettingName);
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
	function initData($request) {
        $templateMgr = TemplateManager::getManager($this->request);
        $templateMgr->register_function('plugin_url', array($this->plugin, 'smartyPluginUrl'));
        $templateMgr->assign('submissionId', $this->submissionId);

		$context = $request->getContext();
		$fileSettingName = $this->getFileSettingName();

		$image = $context->getSetting($fileSettingName);
		$imageAltText = array();

		$supportedLocales = AppLocale::getSupportedLocales();
		foreach ($supportedLocales as $key => $locale) {
			if (!isset($image[$key]['altText'])) continue;
			$imageAltText[$key] = $image[$key]['altText'];
		}

		$this->setData('imageAltText', $imageAltText);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('imageAltText'));

		parent::readInputData();
	}

	/**
	 * Save the new image file.
	 * @param $request Request.
	 */
	function execute($request) {
        $submissionDao = Application::getSubmissionDAO();

		$temporaryFile = $this->fetchTemporaryFile($request);

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

			//$uploadName = $this->getFileSettingName() . '_' . $locale . $extension;
			if($publicFileManager->copyJournalFile($this->context->getId(), $temporaryFile->getFilePath(), $newFileName)) {

                $this->submission->setCoverImage($newFileName, $locale);

				// Get image dimensions
				//$filePath = $publicFileManager->getContextFilesPath($context->getAssocType(), $context->getId());
				//list($width, $height) = getimagesize($filePath . '/' . $uploadName);

				$imageAltText = $this->getData('imageAltText');


                $this->submission->setCoverImageAltText($this->getData('coverImageAltText'), $locale);

                $submissionDao->updateObject($this->submission);

				// Clean up the temporary file.
				$this->removeTemporaryFile($request);

				return true;
			}
		}
		return false;
	}
}

?>
