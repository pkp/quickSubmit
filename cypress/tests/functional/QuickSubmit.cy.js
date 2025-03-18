/**
 * @file cypress/tests/functional/QuickSubmit.cy.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 */

describe('Quick Submit plugin tests', function() {
	it('Creates a published quick submission', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.get('nav').contains('Tools').click();
		cy.get('a:contains("QuickSubmit Plugin")').click();
		cy.get('select[id="sectionId"]').select('Articles');
		cy.waitJQuery(); // Wait for form resubmission hack on section change.
		cy.wait(2000); // FIXME: Detached element delay

		cy.get('input[id^="title-en-"]').type('QuickSubmit Published Test Submission', {delay: 0});
		cy.get('textarea[id^="abstract-en-"]').then(node => {
			cy.setTinyMceContent(node.attr('id'), 'This is a published QuickSubmit test submission.');
		});

		// Add an author
		cy.get('a[id^="component-grid-users-author-authorgrid-addAuthor-button-"]').click();
		cy.wait(1000); // Form init delay
		cy.get('input[id^="givenName-en-"]').type('Quincy', {delay: 0});
		cy.get('input[id^="familyName-en-"]').type('Submitter', {delay: 0});
		cy.get('select[id="country"]').select('Canada');
		cy.get('input[id^=email-]').type('qsubmitter@mailinator.com', {delay: 0});
		cy.get('input[id^="affiliation-en-"]').type('Queens University', {delay: 0});
		cy.get('label:contains("Author")').click();
		cy.get('form[id="editAuthor"] button:contains("Save")').click();
		cy.get('div:contains("Author added.")');

		// Schedule for publication
		cy.get('input#articlePublished').click();
		cy.get('select#issueId').select('Vol. 1 No. 2 (2014)');
		cy.get('input[id^="datePublished-"]:visible').type('2020-01-01', {delay: 0});
		cy.get('input[id^="licenseUrl"]').click(); // Take focus out of datepicker

		// Add a galley
		cy.get('a[id^="component-grid-articlegalleys-articlegalleygrid-addGalley-button-"]').click();
		cy.wait(1000); // Wait for the form to settle
		cy.get('input[id^=label-]').type('PDF', {delay: 0});
		cy.get('form#articleGalleyForm button:contains("Save")').click();
		cy.get('select[id=genreId]').select('Article Text');
		cy.wait(250);
		cy.fixture('dummy.pdf', 'base64').then(fileContent => {
			cy.get('div[id^="fileUploadWizard"] input[type=file]').attachFile(
				{fileContent, 'filePath': 'article.pdf', 'mimeType': 'application/pdf', 'encoding': 'base64'}
			);
		});
		cy.get('button').contains('Continue').click();
		cy.get('button').contains('Continue').click();
		cy.get('button').contains('Complete').click();

		// Complete the submission
		cy.get('form[id="quickSubmitForm"] button:contains("Save")').click();
		cy.waitJQuery();

		// Test the submission in the published front end

		cy.get('.app__contextTitle:contains("Journal of Public Knowledge")').click();
		cy.get('a:contains("Archives")').click();
		cy.get('a:contains("Vol. 1 No. 2 (2014")').click();
		cy.get('a:contains("QuickSubmit Published Test Submission")').click();
		cy.get('section.abstract p:contains("This is a published QuickSubmit test submission.")');
		cy.get('ul.galleys_links a:contains("PDF")').click();
		cy.get('iframe');
	});

	it('Creates an unpublished quick submission', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.get('nav').contains('Tools').click();
		cy.get('a:contains("QuickSubmit Plugin")').click();
		cy.get('select[id="sectionId"]').select('Articles');
		cy.waitJQuery(); // Wait for form resubmission hack on section change.
		cy.wait(2000); // FIXME: Detached element delay

		cy.get('input[id^="title-en-"]').type('QuickSubmit Unpublished Test Submission', {delay: 0});
		cy.get('textarea[id^="abstract-en-"]').then(node => {
			cy.setTinyMceContent(node.attr('id'), 'This is an unpublished QuickSubmit test submission.');
		});

		// Add an author
		cy.get('a[id^="component-grid-users-author-authorgrid-addAuthor-button-"]').click();
		cy.wait(1000); // Form init delay
		cy.get('input[id^="givenName-en-"]').type('Quincy', {delay: 0});
		cy.get('input[id^="familyName-en-"]').type('Submitter', {delay: 0});
		cy.get('select[id="country"]').select('Canada');
		cy.get('input[id^=email-]').type('qsubmitter@mailinator.com', {delay: 0});
		cy.get('input[id^="affiliation-en-"]').type('Queens University', {delay: 0});
		cy.get('label:contains("Author")').click();
		cy.get('form[id="editAuthor"] button:contains("Save")').click();
		cy.get('div:contains("Author added.")');

		// Complete the submission
		cy.get('form[id="quickSubmitForm"] button:contains("Save")').click();
		cy.get('a:contains("Go to Submission")').click();
		cy.get('button:contains("Schedule For Publication")');
	});
})
