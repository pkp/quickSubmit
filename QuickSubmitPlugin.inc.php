<?php

/**
 * @file QuickSubmitPlugin.inc.php
 *
 * Copyright (c) 2013-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
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
	public function register($category, $path, $mainContextId = NULL) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_APP_AUTHOR,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_SUBMISSION);

		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();

		return $success;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	public function getName() {
		return 'QuickSubmitPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.importexport.quickSubmit.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.importexport.quickSubmit.description');
	}

	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	public function display($args, $request) {
		$templateMgr = TemplateManager::getManager();
		$templateMgr->registerPlugin('function', 'plugin_url', array($this, 'smartyPluginUrl'));

		switch (array_shift($args)) {
			case 'saveSubmit':
				if ($request->getUserVar('reloadForm') == '1') {
					$this->_reloadForm($request);
				} else {
					$this->_saveSubmit($request);
				}
				break;
			case 'cancelSubmit':
				$this->_cancelSubmit($request);
				break;
			case 'uploadCoverImage':
				return $this->_showFileUploadForm($request);
			case 'uploadImage':
				return $this->_uploadImage($request);
			case 'saveUploadedImage':
				return $this->_saveUploadedImage($request);
			case 'deleteCoverImage':
				return $this->_deleteUploadedImage($request);
			default:
				$this->import('QuickSubmitForm');
				$templateMgr->assign([
					'pageTitle' => $this->getDisplayName(),
				]);
				$form = new QuickSubmitForm($this, $request);
				$form->initData();
				$form->display($request);
				break;
		}
	}

	/**
	 * Cancels the submission
	 * @param $request Request
	 */
	protected function _cancelSubmit($request) {
		$this->import('QuickSubmitForm');
		$form = new QuickSubmitForm($this, $request);
		$form->readInputData();

		$form->cancel();

		// Submission removal notification.
		$notificationContent = __('notification.removedSubmission');
		$currentUser = $request->getUser();
		$notificationMgr = new NotificationManager();
		$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => $notificationContent));

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'pageTitle' => __('plugins.importexport.quickSubmit.cancel'),
		]);
		$templateMgr->display($this->getTemplateResource('submitCancel.tpl'));
	}

	/**
	 * Show the upload image form.
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	protected function _showFileUploadForm($request) {
		import('plugins.importexport.quickSubmit.classes.form.UploadImageForm');
		$imageUploadForm = new UploadImageForm($this, $request);
		$imageUploadForm->initData($request);
		return new JSONMessage(true, $imageUploadForm->fetch($request));
	}

	/**
	 * Upload the image to a temporary file
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	protected function _uploadImage($request) {
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
	protected function _saveUploadedImage($request) {
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
	protected function _deleteUploadedImage($request) {
		import('plugins.importexport.quickSubmit.classes.form.UploadImageForm');
		$imageUploadForm = new UploadImageForm($this, $request);
		$imageUploadForm->readInputData();
		return $imageUploadForm->deleteCoverImage($request);
	}

	/**
	 * Save the submitted form
	 * @param $request Request.
	 */
	protected function _saveSubmit($request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->import('QuickSubmitForm');
		$form = new QuickSubmitForm($this, $request);
		$form->readInputData();
		if($form->validate()){
			$form->execute();
			$templateMgr->assign(array(
				'pageTitle' => __('plugins.importexport.quickSubmit.success'),
				'submissionId' => $form->getSubmission()->getId(),
				'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
			));
			$templateMgr->display($this->getTemplateResource('submitSuccess.tpl'));
		} else {
			$form->display($request);
		}
	}

	/**
	 * Reloads the form
	 * @param $request Request.
	 */
	protected function _reloadForm($request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->import('QuickSubmitForm');
		$form = new QuickSubmitForm($this, $request);
		$form->readInputData();
		$form->display($request);
	}

	/**
	 * Extend the {url ...} for smarty to support this plugin.
	 */
	function smartyPluginUrl($params, $smarty) {
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
	public function usage($scriptName) {
		fatalError('Not implemented');
	}

	/**
	 * @copydoc PKPImportExportPlugin::executeCLI()
	 */
	public function executeCLI($scriptName, &$args) {
		fatalError('Not implemented');
	}
}

