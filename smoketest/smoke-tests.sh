#!/bin/bash

# When executed from the smoketest directectory it will
# fail with exit 0
cd smoketest || exit 0

echo "Clean-up"
echo "========"

rm composer.lock
rm -rf vendor

echo "First round"
echo "==========="

composer install

echo "Second round"
echo "============"

# Check if everything still works
composer update

echo "Check with ENV"
echo "=============="

# Check with env
export COMPOSER_MONOLITH_ROLLERWORKS_SEARCH="<=2.0-BETA8"
composer update

echo " Should have shown 'Monolith config \"rollerworks-search\" overwritten by ENV configuration to \"<=2.0-BETA8\"'"

exit 0
