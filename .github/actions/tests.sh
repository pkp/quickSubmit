#!/bin/bash

set -e

npx cypress run --headless --browser chrome --config '{"specPattern":["plugins/importexport/quickSubmit/cypress/tests/functional/*.cy.{js,jsx,ts,tsx}"]}'

