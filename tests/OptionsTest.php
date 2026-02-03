<?php

declare(strict_types=1);

/*
 * This file is part of the Rollerworks MonolithVersions package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\MonolithVersions\Tests;

use Composer\Package\Version\VersionParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rollerworks\MonolithVersions\Options;

/**
 * @internal
 */
final class OptionsTest extends TestCase
{
    #[Test]
    public function works_with_empty_options(): void
    {
        $options = new Options([]);
        $this->assertSame([], $options->toArray());

        $options = new Options(['monolith-versions' => []]);
        $this->assertSame([], $options->toArray());
    }

    #[Test]
    public function options_is_an_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "extra.monolith-versions" option must be an array.');

        new Options(['monolith-versions' => false]);
    }

    #[Test]
    #[DataProvider('requires_monolith_name_matches_regex_provider')]
    public function name_matches_regex(int | string $name): void
    {
        $this->expectConfigurationIsInvalid(\sprintf('"%s" is not a valid config name, must match "^[a-z]((-{1,2})?[a-z0-9]+)*$" (lowercase only).', $name));

        new Options(['monolith-versions' => [$name => []]]);
    }

    /** @return iterable<int, array{0: int|string}> */
    public static function requires_monolith_name_matches_regex_provider(): iterable
    {
        yield [0];
        yield [''];
        yield ['foo/bar'];
        yield ['foo_bar'];
    }

    #[Test]
    public function config_requires_array_structure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid('config "rollersearch" must be an object with keys: "require" and "package".');

        new Options(['monolith-versions' => ['rollersearch' => true]]);
    }

    #[Test]
    public function config_requires_package_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid('"rollersearch" must contain "constraint" and "package".');

        new Options(['monolith-versions' => ['rollersearch' => ['constraint' => '2.0']]]);
    }

    #[Test]
    public function config_requires_constraint_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid('"rollersearch" must contain "constraint" and "package".');

        new Options(['monolith-versions' => ['rollersearch' => ['package' => 'rollersearch/*']]]);
    }

    #[Test]
    public function config_requires_constraint_to_a_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid('"rollersearch": "constraint" must be a string.');

        new Options(['monolith-versions' => ['rollersearch' => ['package' => 'rollersearch/*', 'constraint' => ['2.0']]]]);
    }

    #[Test]
    public function config_requires_package_is_a_string_or_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid('"rollersearch": "package" must be a string or array of strings.');

        new Options(['monolith-versions' => ['rollersearch' => ['package' => 0, 'constraint' => '2.0']]]);
    }

    #[Test]
    public function config_requires_package_array_contains_only_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid('"rollersearch": package array only allows strings, "integer" given.');

        new Options(['monolith-versions' => ['rollersearch' => ['package' => ['rollersearch/*', 0], 'constraint' => '2.0']]]);
    }

    #[Test]
    #[DataProvider('provide_config_requires_package_is_valid')]
    public function config_requires_package_is_valid_string(string $package, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid(\sprintf('"rollersearch": package "%s" is not valid%s', $package, $message));

        new Options(['monolith-versions' => ['rollersearch' => ['package' => $package, 'constraint' => '2.0']]]);
    }

    #[Test]
    #[DataProvider('provide_config_requires_package_is_valid')]
    public function config_requires_package_is_valid_string_in_array(string $package, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid(\sprintf('"rollersearch": package "%s" is not valid%s', $package, $message));

        new Options(['monolith-versions' => ['rollersearch' => ['package' => [$package], 'constraint' => '2.0']]]);
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function provide_config_requires_package_is_valid(): iterable
    {
        yield 'no vendor wildcard' => ['*/search', '. Vendor name cannot contain expends or wildcard'];
        yield 'no package name' => ['rollersearch/', '. Name cannot be empty, use a wildcard "*" instead.'];
        yield 'no expands *and* wildcard' => ['rollersearch/he-*-{you,now}', '. Cannot contain both a wildcard and expands.'];
        yield 'no multiple expands' => ['rollersearch/{you, now}-{how}', '. Cannot contain more than one expands.'];
        yield 'no multiple wildcards' => ['rollersearch/he-*-*', '. Cannot contain more than one wildcard.'];
        yield 'invalid expand' => ['rollersearch/he-{now', '. Missing closing expands "}" character.'];
        yield 'no invalid characters' => ['rollersearch/he now', '.'];
    }

    #[Test]
    public function config_requires_constraint_is_valid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid('"rollersearch" constraint is not valid. Message: Could not parse version constraint >>2.0: Invalid version string ">2.0".');

        new Options([
            'monolith-versions' => [
                'rollersearch' => [
                    'package' => 'rollerworks/search-*',
                    'constraint' => '>>2.0',
                ],
            ],
        ]);
    }

    #[Test]
    public function config_requires_exclude_is_an_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid('"rollersearch": exclude-packages (when set) must contain an array of packages.');

        new Options([
            'monolith-versions' => [
                'rollersearch' => [
                    'package' => 'rollerworks/search-*',
                    'constraint' => '2.0',
                    'exclude' => false,
                ],
            ],
        ]);
    }

    #[Test]
    public function config_requires_exclude_is_valid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectConfigurationIsInvalid(
            '"rollersearch": exclude-package only allows strings, "integer" given.',
            '"rollersearch": exclude-package only allows strings, "boolean" given.',
            '"rollersearch": exclude-package "rollerworks/search-*" is not valid, must be a valid package name in the format "vendor/package".',
            '"rollersearch": exclude-package only allows strings, "array" given.',
        );

        new Options([
            'monolith-versions' => [
                'rollersearch' => [
                    'package' => 'rollerworks/search-*',
                    'constraint' => '2.0',
                    'exclude' => [0, false, 'rollerworks/search-*', [0]],
                ],
            ],
        ]);
    }

    #[Test]
    public function configuration_processed(): void
    {
        $versionParser = new VersionParser();

        $options = new Options([
            'monolith-versions' => [
                'rollersearch' => ['package' => 'rollerworks/search-*', 'constraint' => '^2.0'],
                'lifthill' => ['package' => ['lifthill/{common, core-bundle }'], 'constraint' => '^1.0'],
                'symfony' => [
                    'package' => ['symfony/*-bundle', 'symfony/*-bridge'],
                    'constraint' => '^6.4',
                    'exclude' => ['symfony/translation-contracts'],
                ],
            ],
        ]);

        $this->assertEquals([
            'rollersearch' => [
                'package' => '#^((rollerworks/search-[a-z0-9-.]+))$#i',
                'exclude' => [],
                'constraint' => '^2.0',
                'constraint_obj' => $versionParser->parseConstraints('^2.0'),
            ],
            'lifthill' => [
                'package' => '#^((lifthill/(common|core-bundle)))$#i',
                'exclude' => [],
                'constraint' => '^1.0',
                'constraint_obj' => $versionParser->parseConstraints('^1.0'),
            ],
            'symfony' => [
                'package' => '#^((symfony/[a-z0-9-.]+-bundle)|(symfony/[a-z0-9-.]+-bridge))$#i',
                'exclude' => ['symfony/translation-contracts'],
                'constraint' => '^6.4',
                'constraint_obj' => $versionParser->parseConstraints('^6.4'),
            ],
        ], $options->toArray());
    }

    private function expectConfigurationIsInvalid(string ...$errors): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid configuration detected in "extra.monolith-versions": ' . "\n - " . implode("\n - ", $errors)
        );
    }
}
