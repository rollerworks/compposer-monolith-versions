include vendor/rollerscapes/standards/Makefile

phpunit:
	./vendor/bin/phpunit

test: phpunit
	./smoketest/smoke-tests.sh
