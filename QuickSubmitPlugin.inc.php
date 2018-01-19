<?php

/**
 * @file plugins/importexport/quickSubmit/QuickSubmitPlugin.inc.php
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QuickSubmitPlugin
 * @ingroup plugins_importexport_quickSubmit
 *
 * @brief Quick Submit one-page submission plugin
 */


import('lib.pkp.classes.plugins.ImportExportPlugin');

class QuickSubmitPlugin extends ImportExportPlugin {

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_APP_AUTHOR,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_SUBMISSION);

		$success = parent::register($category, $path);
		$this->addLocaleData();

		return $success;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'QuickSubmitPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.importexport.quickSubmit.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.importexport.quickSubmit.description');
	}

	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager();
		$templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));

		switch (array_shift($args)) {
			case 'saveSubmit':
				if ($request->getUserVar('reloadForm') == '1') {
					$this->reloadForm($args, $request);
				} else {
					$this->saveSubmit($args, $request);
				}
				break;
			case 'cancelSubmit':
				$this->cancelSubmit($args, $request);
				break;
			case 'uploadCoverImage':
				return $this->showFileUploadForm($args, $request);
			case 'uploadImage':
				return $this->uploadImage($args, $request);
			case 'saveUploadedImage':
				return $this->saveUploadedImage($request);
			case 'deleteCoverImage':
				return $this->deleteUploadedImage($request);
			default:
				$this->import('QuickSubmitForm');
				$form = new QuickSubmitForm($this, $request);
				$form->initData();
				$form->display(false);
				break;
		}
	}

	/**
	 * Cancels the submission
	 * @param $args array
	 * @param $request Request
	 */
	function cancelSubmit($args, $request) {
		$this->import('QuickSubmitForm');
		$form = new QuickSubmitForm($this, $request);
		$form->readInputData();

		$form->cancel();

		// Submission removal notification.
		$notificationContent = __('notification.removedSubmission');
		$currentUser = $request->getUser();
		$notificationMgr = new NotificationManager();
		$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => $notificationContent));

		$path = array('plugin', $this->getName());
		$request->redirect(null, null, null, $path, null, null);
	}

	/**
	 * Show the upload image form.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function showFileUploadForm($args, $request) {
		import('plugins.importexport.quickSubmit.classes.form.UploadImageForm');
		$imageUploadForm = new UploadImageForm($this, $request);
		$imageUploadForm->initData($request);
		return new JSONMessage(true, $imageUploadForm->fetch($request));
	}

	/**
	 * Upload the image to a temporary file
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function uploadImage($args, $request) {
		import('plugins.importexport.quickSubmit.classes.form.UploadImageForm');
		$imageUploadForm = new UploadImageForm($this, $request);
		$imageUploadForm->readInputData();

		$temporaryFileId = $imageUploadForm->uploadFile($request);
		if ($temporaryFileId) {
			$json = new JSONMessage(true);
			$json->setAdditionalAttributes(array(
				'temporaryFileId' => $temporaryFileId
			));
			return $json;
		} else {
			return new JSONMessage(false, __('common.uploadFailed'));
		}
	}

	/**
	 * Save the new image file.
	 * @param $request Request.
	 * @return JSONMessage JSON object
	 */
	function saveUploadedImage($request) {
		import('plugins.importexport.quickSubmit.classes.form.UploadImageForm');
		$imageUploadForm = new UploadImageForm($this, $request);
		$imageUploadForm->readInputData();
		return $imageUploadForm->execute($request);
	}

	/**
	 * Delete the uploaded image
	 * @param $request Request.
	 * @return JSONMessage JSON object
	 */
	function deleteUploadedImage($request) {
		import('plugins.importexport.quickSubmit.classes.form.UploadImageForm');
		$imageUploadForm = new UploadImageForm($this, $request);
		$imageUploadForm->readInputData();
		return $imageUploadForm->deleteCoverImage($request);
	}

	/**
	 * Save the submitted form
	 * @param $args array
	 * @param $request Request.
	 */
	function saveSubmit($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->import('QuickSubmitForm');
		$form = new QuickSubmitForm($this, $request);
		$form->readInputData();
		if($form->validate()){
			$form->execute();
			$templateMgr->assign('submissionId', $form->submissionId);
			$templateMgr->assign('stageId', WORKFLOW_STAGE_ID_PRODUCTION);
			$templateMgr->display($this->getTemplatePath() . 'submitSuccess.tpl');
		} else {
			$form->display();
		}
	}

	/**
	 * Reloads the form
	 * @param $args array
	 * @param $request Request.
	 */
	function reloadForm($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->import('QuickSubmitForm');
		$form = new QuickSubmitForm($this, $request);
		$form->readInputData();
		$form->display();
	}

	/**
	 * Extend the {url ...} for smarty to support this plugin.
	 */
	function smartyPluginUrl($params, &$smarty) {
		$path = array('plugin',$this->getName());
		if (is_array($params['path'])) {
			$params['path'] = array_merge($path, $params['path']);
		} elseif (!empty($params['path'])) {
			$params['path'] = array_merge($path, array($params['path']));
		} else {
			$params['path'] = $path;
		}

		if (!empty($params['id'])) {
			$params['path'] = array_merge($params['path'], array($params['id']));
			unset($params['id']);
		}
		return $smarty->smartyUrl($params, $smarty);
	}

	/**
	 * @copydoc PKPImportExportPlugin::usage
	 */
	function usage($scriptName) {
		fatalError('Not implemented');
	}

	/**
	 * @copydoc PKPImportExportPlugin::executeCLI()
	 */
	function executeCLI($scriptName, &$args) {
		fatalError('Not implemented');
	}

	/**
	 * @copydoc Plugin::getTemplatePath()
	 */
	function getTemplatePath($inCore = false) {
		return parent::getTemplatePath($inCore = false) . 'templates/';
	}
}
