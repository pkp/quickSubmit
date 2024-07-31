#!/bin/bash

set -e

npx cypress run  --headless --browser chrome  --config integrationFolder=plugins/importexport/quickSubmit/cypress/tests


