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

namespace Rollerworks\MonolithVersions;

use Composer\Package\Version\VersionParser;
use Composer\Pcre\Preg;
use Composer\Semver\Constraint\ConstraintInterface;

final class Options
{
    public const PACKAGE_NAME_FORMAT = '{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$}i';

    private VersionParser $versionParser;

    /** @var array<string, array{constraint: string, constraint_obj: ConstraintInterface, package: string, exclude: string[]}> */
    private array $packages;

    /** @var string[] */
    private array $errors;

    /** @param array<string, mixed> $options */
    public function __construct(array $options, ?VersionParser $versionParser = null)
    {
        $this->packages = [];
        $this->errors = [];

        if (! isset($options['monolith-versions'])) {
            return;
        }

        if (! \is_array($options['monolith-versions'])) {
            throw new \InvalidArgumentException('The "extra.monolith-versions" option must be an array.');
        }

        $this->versionParser = $versionParser ?? new VersionParser();

        foreach ($options['monolith-versions'] as $configName => $config) {
            // We use this convention to allow ENV variables to change the "require" version constraint.
            if (! Preg::isMatch('{^[a-z]((-{1,2})?[a-z0-9]+)*$}', (string) $configName)) {
                $this->errors[] = \sprintf('"%s" is not a valid config name, must match "^[a-z]((-{1,2})?[a-z0-9]+)*$" (lowercase only).', $configName);

                // We can safely continue validation, as this is not a hard error.
                continue;
            }

            if (! $this->parsePackageConfig($configName, $config)) {
                continue;
            }

            // Convert the package to a regex
            // - converts "vendor/*" to "vendor/([a-z0-9-.]+)"
            // - convers "vendor/{core, doctrine-orm}" to "vendor/(core|doctrine-orm)"

            $config['package'] = (array) $config['package'];

            foreach ($config['package'] as &$package) {
                $package = '(' . str_replace(['{', '*', ','], ['(', '[a-z0-9-.]+', '|'], Preg::replace(['/\h+/', '/,?\}/'], ['', ')'], $package)) . ')';
            }

            unset($package);
            $config['package'] = '#^(' . implode('|', $config['package']) . ')$#i';

            $config['constraint_obj'] = $this->versionParser->parseConstraints($config['constraint']);
            $config['exclude'] ??= [];

            $this->packages[$configName] = $config;
        }

        if ($this->errors) {
            throw new \InvalidArgumentException('Invalid configuration detected in "extra.monolith-versions": ' . "\n - " . implode("\n - ", $this->errors));
        }
    }

    /** @return array<string, array{constraint: string, constraint_obj: ConstraintInterface, package: string, exclude: string[]}> */
    public function toArray(): array
    {
        return $this->packages;
    }

    public function parsePackageConfig(string $configName, mixed $config): bool
    {
        if (! \is_array($config)) {
            $this->errors[] = \sprintf('config "%s" must be an object with keys: "require" and "package".', $configName);

            return false;
        }

        if (! isset($config['constraint'], $config['package'])) {
            $this->errors[] = \sprintf('"%s" must contain "constraint" and "package".', $configName);

            return false;
        }

        if (! \is_string($config['constraint'])) {
            $this->errors[] = \sprintf('"%s": "constraint" must be a string.', $configName);

            return false;
        }

        if (! \is_string($config['package']) && ! \is_array($config['package'])) {
            $this->errors[] = \sprintf('"%s": "package" must be a string or array of strings.', $configName);

            return false;
        }

        try {
            $this->versionParser->parseConstraints($config['constraint']);
        } catch (\Throwable $e) {
            $this->errors[] = \sprintf('"%s" constraint is not valid. Message: %s.', $configName, $e->getMessage());

            return false;
        }

        $config['exclude'] ??= [];

        if (! \is_array($config['exclude'])) {
            $this->errors[] = \sprintf('"%s": exclude-packages (when set) must contain an array of packages.', $configName);

            $config['exclude'] = [];
        }

        foreach ($config['exclude'] as $package) {
            if (! \is_string($package)) {
                $this->errors[] = \sprintf('"%s": exclude-package only allows strings, "%s" given.', $configName, \gettype($package));
            } elseif (! Preg::isMatch(self::PACKAGE_NAME_FORMAT, $package)) {
                $this->errors[] = \sprintf('"%s": exclude-package "%s" is not valid, must be a valid package name in the format "vendor/package".', $configName, $package);
            }
        }

        $package = $config['package'];
        $valid = true;

        if (\is_array($package)) {
            array_walk($package, function (mixed $package) use ($configName, &$valid) {
                if (! \is_string($package)) {
                    $this->errors[] = \sprintf('"%s": package array only allows strings, "%s" given.', $configName, \gettype($package));

                    $valid = false;
                } elseif (! $this->validatePackage($configName, $package)) {
                    $valid = false;
                }
            });

            return $valid;
        }

        return $this->validatePackage($configName, $package);
    }

    private function validatePackage(string $configName, string $package): bool
    {
        if (! Preg::isMatch('#^([a-z0-9](?:[_.-]?[a-z0-9]+)*/([^$]*))#i', $package, $matches)) {
            $this->errors[] = \sprintf('"%s": package "%s" is not valid. Vendor name cannot contain expends or wildcard.', $configName, $package);

            return false;
        }

        // Note the regex is not as strict as package-name constraints, but invalid packages
        // are rejected by Composer anyway. Most important is that we don't allow a wildcard
        // *and* expand at the same time, and we don't allow multiple expands and wildcards.
        //
        // rollerworks/search-{core, doctrine-orm, *} wouldn't make sense, as the wildcard would cover
        // all packages.

        if (str_contains($package, '*') && str_contains($package, '{')) {
            $this->errors[] = \sprintf('"%s": package "%s" is not valid. Cannot contain both a wildcard and expands.', $configName, $package);

            return false;
        }

        if (str_contains($package, '{')) {
            if (! str_contains($package, '}')) {
                $this->errors[] = \sprintf('"%s": package "%s" is not valid. Missing closing expands "}" character.', $configName, $package);

                return false;
            }

            if (strpos($package, '{') !== strrpos($package, '{')) {
                $this->errors[] = \sprintf('"%s": package "%s" is not valid. Cannot contain more than one expands.', $configName, $package);

                return false;
            }
        }

        if (str_contains($package, '*') && strpos($package, '*') !== strrpos($package, '*')) {
            $this->errors[] = \sprintf('"%s": package "%s" is not valid. Cannot contain more than one wildcard.', $configName, $package);

            return false;
        }

        if (! Preg::isMatch("#^((\*?[a-z0-9-.]*\*?)|([a-z0-9-.]*(\{\h*([a-z0-9-.]+\h*,?\h*)+})[a-z0-9-.]*))$#i", $matches[2] ?? '')) {
            $this->errors[] = \sprintf('"%s": package "%s" is not valid.', $configName, $package);

            return false;
        }

        if ($matches[2] === '') {
            $this->errors[] = \sprintf('"%s": package "%s" is not valid. Name cannot be empty, use a wildcard "*" instead.', $configName, $package);

            return false;
        }

        return true;
    }
}
