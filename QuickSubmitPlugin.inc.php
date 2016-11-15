<?php

/**
 * @file plugins/importexport/quickSubmit/QuickSubmitPlugin.inc.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True iff plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
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
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'QuickSubmitPlugin';
	}

	function getDisplayName() {
		return __('plugins.importexport.quickSubmit.displayName');
	}

	function getDescription() {
		return __('plugins.importexport.quickSubmit.description');
	}

	function display($args, $request) {
		$templateMgr = TemplateManager::getManager();
		$templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));

		switch (array_shift($args)) {
            case 'saveSubmit':
                $this->saveSubmit($args, $request);
                break;
            case 'cancelSubmit':
                $this->cancelSubmit($args, $request);
                break;
			case 'uploadCoverImage':
				return $this->uploadImage($args, $request);
                //break;
            default:
                $this->import('QuickSubmitForm');
                $form = new QuickSubmitForm($this, $request);
                $form->initData();
                $form->display();
                break;
        }
	}

	function cancelSubmit($args, $request) {
		$templateMgr = TemplateManager::getManager($request);

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

	function uploadImage($args, $request) {
		$router = $request->getRouter();
		$context = $request->getContext();
		$user = $request->getUser();

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
		if ($temporaryFile) {
			$json = new JSONMessage(true);
			$json->setAdditionalAttributes(array(
				'temporaryFileId' => $temporaryFile->getId()
			));
			return $json;
		} else {
			return new JSONMessage(false, __('common.uploadFailed'));
		}
	}

	/**
	 * Save the submitted form
	 * @param $args array
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
}

?>
