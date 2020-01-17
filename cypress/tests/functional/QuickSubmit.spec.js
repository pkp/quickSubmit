/**
 * @file cypress/tests/functional/QuickSubmit.spec.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 */

describe('Quick Submit plugin tests', function() {
	it('Creates and exercises a quick submission', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.get('ul[id="navigationPrimary"] a:contains("Tools")').click();
		cy.get('ul[id="navigationPrimary"] a:contains("Import/Export")').click();
		cy.get('a:contains("QuickSubmit Plugin")').click();
		cy.get('select[id="sectionId"]').select('Articles');
		cy.waitJQuery(); // Wait for form resubmission hack on section change.

		cy.get('input[id^="title-en_US-"]').type('QuickSubmit Test Submission', {delay: 0});
		cy.get('textarea[id^="abstract-en_US-"]').then(node => {
			cy.setTinyMceContent(node.attr('id'), 'This is a QuickSubmit test submission.');
		});

		// Add an author
		cy.get('a[id^="component-grid-users-author-authorgrid-addAuthor-button-"]').click();
		cy.get('input[id^="givenName-en_US-"]').type('Quincy', {delay: 0});
		cy.get('input[id^="familyName-en_US-"]').type('Submitter', {delay: 0});
		cy.get('select[id="country"]').select('Canada');
		cy.get('input[id^=email-]').type('qsubmitter@mailinator.com', {delay: 0});
		cy.get('input[id^="affiliation-en_US-"]').type('Queens University', {delay: 0});
		cy.get('label:contains("Author")').click();
		cy.get('form[id="editAuthor"] button:contains("Save")').click();
		cy.get('div:contains("Author added.")');

		// Complete the submission
		cy.get('form[id="quickSubmitForm"] button:contains("Save")').click();
		cy.get('a:contains("Go to Submission")');
	});
})
