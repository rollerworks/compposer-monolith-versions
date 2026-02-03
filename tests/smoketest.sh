#!/bin/bash

cd smoketest

rm composer.lock
rm -rf vendor

composer install

# Check if everything still works
composer update

# Check with env
export COMPOSER_MONOLITH_ROLLERWORKS_SEARCH="<=2.0-BETA8"
composer update
