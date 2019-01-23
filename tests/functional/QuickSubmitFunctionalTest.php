<?php

/**
 * @file tests/functional/QuickSubmitFunctionalTest.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QuickSubmitFunctionalTest
 *
 * @brief Functional tests for the quick submit plugin.
 */

import('lib.pkp.tests.WebTestCase');

class QuickSubmitFunctionalTest extends WebTestCase {
	/**
	 * @copydoc WebTestCase::getAffectedTables
	 */
	protected function getAffectedTables() {
		return PKP_TEST_ENTIRE_DB;
	}

	/**
	 * Enable the plugin
	 */
	function testQuickSubmit() {
		$this->open(self::$baseUrl);

		$this->logIn('admin', 'admin');
		$this->waitForElementPresent($selector='link=Import/Export');
		$this->click($selector);
		$this->waitForElementPresent($selector='link=QuickSubmit Plugin');
		$this->click($selector);
		$this->logOut();
	}
}

