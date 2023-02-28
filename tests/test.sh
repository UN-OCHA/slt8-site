#!/usr/bin/env bash

# This is the equivalent of the tests in `.travis.yml` that can be run locally.

# Build local image.
echo "Build local image."
make

# Create the site, memcache and mysql containers.
echo "Create the site, memcache and mysql containers."
docker-compose -p slt-test -f tests/docker-compose.yml up -d

# Dump some information about the created containers.
echo "Dump some information about the created containers."
docker ps -a -fname=slt-test

# Wait a bit for memcache and mysql to be ready.
echo "Wait a bit for memcache and mysql to be ready."
sleep 10

# Ensure the file directories are writable.
echo "Ensure the file directories are writable."
docker exec -it slt-test-site chmod -R 777 /srv/www/html/sites/default/files /srv/www/html/sites/default/private

# Install the common design subtheme.
echo "Make sure the common design subtheme is installed"
docker exec -it -w /srv/www slt-test-site composer install

# Install the common design subtheme.
echo "Make sure the common design subtheme is installed"
docker exec -it -w /srv/www slt-test-site composer run sub-theme

# Check coding standards.
echo "Check coding standards."
docker exec -it -u appuser -w /srv/www slt-test-site ./vendor/bin/phpcs -p --report=full ./html/modules/custom ./html/themes/custom

# # Run unit tests.
# echo "Run unit tests."
docker exec -it -u root slt-test-site mkdir -m 777 -p /srv/www/html/sites/default/files/browser_output /srv/www/html/sites/simpletest
docker exec -it -u appuser -w /srv/www -e BROWSERTEST_OUTPUT_DIRECTORY=/srv/www/html/sites/default/files/browser_output slt-test-site php ./vendor/bin/phpunit --testsuite Unit --debug

# Install the site with the existing config.
echo "Install the site with the existing config."
docker exec -it slt-test-site drush -y si --existing-config minimal install_configure_form.enable_update_status_emails=NULL
docker exec -it slt-test-site drush -y en dblog

# Uninstall the social auth modules to allow normal user login for the tests.
echo "Uninstall the social auth modules to allow normal user login for the tests."
docker exec -it slt-test-site drush -y pm-uninstall social_auth

# Create the build logs directory and make sure it's writable.
echo "Create the build logs directory and make sure it's writable."
docker exec -it -u root slt-test-site mkdir -m 777 -p /srv/www/html/build/logs

# Run all tests and generate coverage report.
echo "Run all tests and generate coverage report."
docker exec -it -u appuser -w /srv/www -e XDEBUG_MODE=coverage -e BROWSERTEST_OUTPUT_DIRECTORY=/srv/www/html/sites/default/files/browser_output -e DTT_BASE_URL=http://127.0.0.1 -e SIMPLETEST_BASE_URL=http://127.0.0.1 slt-test-site php ./vendor/bin/phpunit --coverage-clover /srv/www/html/build/logs/clover.xml --debug --verbose

# Remove the image.
echo "Remove the test image"
docker-compose -p slt-test -f tests/docker-compose.yml down -v
