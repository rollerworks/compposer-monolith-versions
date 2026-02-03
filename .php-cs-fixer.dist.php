<?php

$header = <<<EOF
This file is part of the Rollerworks MonolithVersions package.

(c) Sebastiaan Stok <s.stok@rollerscapes.net>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

/** @var \Symfony\Component\Finder\Finder $finder */
$finder = PhpCsFixer\Finder::create();
$finder
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules(
        array_merge(
            require __DIR__ . '/vendor/rollerscapes/standards/php-cs-fixer-rules.php',
            [
                'header_comment' => ['header' => $header],
                'mb_str_functions' => false, // Cannot be done as we don't polyfill mbstring available
            ],
        )
    )
    ->setFinder($finder);

return $config;
