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

use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Pcre\Preg;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;

final class PackageFilter
{
    private VersionParser $versionParser;

    public function __construct(
        private IOInterface $io,
        private Options $options,
        ?VersionParser $versionParser = null,
    ) {
        $this->versionParser = $versionParser ?? new VersionParser();
    }

    /**
     * @param BasePackage[] $packages
     *
     * @return BasePackage[]
     */
    public function enforceConstraints(RootPackageInterface $rootPackage, array $packages): array
    {
        $monolithPackages = $this->options->toArray();

        if (! $monolithPackages) {
            return $packages;
        }

        /** @var array<string, string> $resolvedPackages */
        $resolvedPackages = [];

        /** @var array<string, array<string, Link>> $monolithMetaPackages */
        $monolithMetaPackages = [];

        // Because all requirements are already resolved at this state we can't use
        // partial packages. So we need to add a package that sources the root-package.
        //
        // And targets the matched package of the monolith config.

        /** @var RootPackageInterface|null $resolvedRootPackage */
        $resolvedRootPackage = null;

        /** @var array<string, bool> $envOverwrites */
        $envOverwrites = [];

        $rootName = $rootPackage->getName();

        foreach ($packages as $package) {
            $name = $package->getName();

            if ($package instanceof AliasPackage) {
                $name = $package->getAliasOf()->getName();
            }

            if ($name === $rootName) {
                $resolvedRootPackage = $package;

                continue;
            }

            /**
             * @var string                                                                                               $configName
             * @var array{package: non-empty-string, constraint: string, constraint_obj: Constraint, exclude?: string[]} $monolithPackage
             */
            foreach ($monolithPackages as $configName => &$monolithPackage) {
                if (\in_array($name, $monolithPackage['exclude'] ?? [], true)) {
                    continue;
                }

                if (! Preg::isMatch($monolithPackage['package'], $name)) {
                    continue;
                }

                if (isset($resolvedPackages[$name])) {
                    if ($resolvedPackages[$name] !== $configName) {
                        $this->io->writeError(\sprintf('<warning>Monolith config "%s" conflicts with "%s" for package "%s" (ignored).</warning>', $configName, $resolvedPackages[$name], $name));
                    }

                    continue;
                }

                if (! isset($envOverwrites[$configName])) {
                    $envOverwrites[$configName] = true;

                    $monolithPackage['constraint_obj'] = $this->getOverwriteConstraintByEnv($configName, $monolithPackage);
                    $monolithPackage['constraint'] = $monolithPackage['constraint_obj']->getPrettyString();
                }

                $monolithMetaPackages[$configName][$name] = new Link($configName, $name, $monolithPackage['constraint_obj'], Link::TYPE_REQUIRE, $monolithPackage['constraint']);
                $resolvedPackages[$name] = $configName;

                // Continue with other monolith configs to see if there is any conflict.
            }

            unset($monolithPackage);
        }

        /** @var array<string, Package> $additionalPackages */
        $additionalPackages = [];

        /** @var array<string, Link> $rootPackageAddedRequirements */
        $rootPackageAddedRequirements = [];

        foreach ($resolvedPackages as $name => $configName) {
            $this->io->writeError(\sprintf('<info>Restricting package "%s" to "%s" by monolith config "%s"</>', $name, $monolithPackages[$configName]['constraint'], $configName));

            // Do this now to safe another iteration later.
            if (! isset($additionalPackages[$configName])) {
                $additionalPackages[$configName] = $p = new CompletePackage($configName, 'dev-main', 'dev-main');

                $p->setType('metapackage');
                $p->setRequires($monolithMetaPackages[$configName]);
                $p->setDistReference('dev-main');
                $p->setDistType('path');
                $p->setDistUrl('file://' . __DIR__ . '/dummy-package');

                $rootPackageAddedRequirements[$configName] = new Link($rootName, $configName, new Constraint('==', 'dev-main'), Link::TYPE_REQUIRE);
            }
        }

        if (! $resolvedPackages) {
            return $packages;
        }

        \assert($resolvedRootPackage instanceof RootPackageInterface);

        $resolvedRootPackage->setRequires(array_merge($rootPackage->getRequires(), $rootPackageAddedRequirements));

        return array_merge($packages, array_values($additionalPackages));
    }

    /** @param array{constraint_obj: ConstraintInterface} $monolithPackage */
    private function getOverwriteConstraintByEnv(string $configName, array $monolithPackage): ConstraintInterface
    {
        $envName = 'COMPOSER_MONOLITH_' . str_replace('-', '_', strtoupper($configName));

        if (! isset($_SERVER[$envName])) {
            return $monolithPackage['constraint_obj'];
        }

        $this->io->writeError(\sprintf('<info>Monolith config "%s" overwritten by ENV configuration to "%s".</>', $configName, $_SERVER[$envName]));

        try {
            return $this->versionParser->parseConstraints($_SERVER[$envName]);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(\sprintf('Monolith config "%s" constraint by ENV is not valid. Message: %s.', $configName, $e->getMessage()));
        }
    }
}
