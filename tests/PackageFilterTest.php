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

use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Package\Version\VersionParser;
use Composer\Semver\Constraint\Constraint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rollerworks\MonolithVersions\Options;
use Rollerworks\MonolithVersions\PackageFilter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @internal
 */
final class PackageFilterTest extends TestCase
{
    #[Test]
    public function nothing_when_empty(): void
    {
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->never())->method('writeError');

        $filter = new PackageFilter($io, new Options([]));

        $rootPackage = $this->createMock(RootPackage::class);
        $rootPackage->expects($this->never())->method('getName');
        $rootPackage->expects($this->never())->method('getRequires');
        $rootPackage->expects($this->never())->method('setRequires');

        $this->assertSame([], $filter->enforceConstraints($rootPackage, []));
    }

    #[Test]
    public function no_matching_packages(): void
    {
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->never())->method('writeError');

        $packages = [
            new CompletePackage('rollerworks/search', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-dbal', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-orm', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.0.0', '2.0.0'),
            new CompletePackage('symfony/translations', '8.0.0', '8.0.0'),
        ];

        $rootPackage1 = new RootPackage('park-manager/park-manager', '1.0.0', '1.0.0');
        $rootPackage1->setRequires(self::resolveRequires('park-manager/park-manager', $packages));
        $packages[] = $rootPackage1;

        $filter = new PackageFilter($io, new Options([
            'monolith-versions' => [
                'rollersearch' => [
                    'package' => 'rollersearch/*',
                    'constraint' => '^3.0',
                ],
            ],
        ]));

        $rootPackage = $this->createMock(RootPackage::class);
        $rootPackage->expects($this->once())->method('getName')->willReturn('park-manager/park-manager');
        $rootPackage
            ->expects($this->never())
            ->method('getRequires');

        $rootPackage
            ->expects($this->never())
            ->method('setRequires');

        $this->assertSame($packages, $filter->enforceConstraints($rootPackage, $packages));
    }

    #[Test]
    public function matching_package(): void
    {
        $packages = $origPackages = [
            new CompletePackage('rollerworks/search', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-dbal', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-orm', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.1.0', '2.1.0'),
            new CompletePackage('symfony/translations', '8.0.0', '8.0.0'),
        ];

        $rootPackage = new RootPackage('park-manager/park-manager', '1.0.0', '1.0.0');
        $rootPackage->setRequires(self::resolveRequires('park-manager/park-manager', $origPackages));
        $packages[] = $rootPackage;

        $io = new BufferIO('', StreamOutput::VERBOSITY_NORMAL, new OutputFormatter(false));
        $filter = new PackageFilter($io, new Options([
            'monolith-versions' => [
                'rollersearch' => [
                    'package' => 'rollerworks/search-*',
                    'constraint' => '^2.0',
                ],
            ],
        ]));

        $this->assertFilteredContainsAdditional(
            $filter,
            $rootPackage,
            $packages,
            [
                'rollersearch' => [
                    'constraint' => '^2.0',
                    'packages' => [
                        'rollerworks/search-doctrine-dbal',
                        'rollerworks/search-doctrine-orm',
                        'rollerworks/search-elasticsearch',
                    ],
                ],
            ],
        );

        $this->assertOutputEquals(
            <<<'OUTPUT'
                Restricting package "rollerworks/search-doctrine-dbal" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollerworks/search-doctrine-orm" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollerworks/search-elasticsearch" to "^2.0" by monolith config "rollersearch"
                OUTPUT,
            $io,
        );
    }

    #[Test]
    public function matching_package_with_multiple_configurations(): void
    {
        $packages = $origPackages = [
            new CompletePackage('rollerworks/search', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-dbal', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-orm', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.1.0', '2.1.0'),

            new CompletePackage('symfony/translations', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/framework-bundle', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/framework-bundle', '8.0.0', '8.1.0'),
            new CompletePackage('symfony/http-kernel', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/translation-contract', '6.4', '6.4'),
        ];

        $rootPackage = new RootPackage('park-manager/park-manager', '1.0.0', '1.0.0');
        $rootPackage->setRequires(self::resolveRequires('park-manager/park-manager', $origPackages));
        $packages[] = $rootPackage;

        $io = new BufferIO('', StreamOutput::VERBOSITY_NORMAL, new OutputFormatter(false));
        $filter = new PackageFilter($io, new Options([
            'monolith-versions' => [
                'rollersearch' => [
                    'package' => 'rollerworks/search-*',
                    'constraint' => '^2.0',
                ],
                'symfony' => [
                    'package' => ['symfony/*'],
                    'exclude' => ['symfony/translation-contract'],
                    'constraint' => '^8.1',
                ],
            ],
        ]));

        $this->assertFilteredContainsAdditional(
            $filter,
            $rootPackage,
            $packages,
            [
                'rollersearch' => [
                    'constraint' => '^2.0',
                    'packages' => [
                        'rollerworks/search-doctrine-dbal',
                        'rollerworks/search-doctrine-orm',
                        'rollerworks/search-elasticsearch',
                    ],
                ],
                'symfony' => [
                    'constraint' => '^8.1',
                    'packages' => [
                        'symfony/framework-bundle',
                        'symfony/http-kernel',
                        'symfony/translations',
                    ],
                ],
            ],
        );

        $this->assertOutputEquals(
            <<<'OUTPUT'
                Restricting package "rollerworks/search-doctrine-dbal" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollerworks/search-doctrine-orm" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollerworks/search-elasticsearch" to "^2.0" by monolith config "rollersearch"
                Restricting package "symfony/translations" to "^8.1" by monolith config "symfony"
                Restricting package "symfony/framework-bundle" to "^8.1" by monolith config "symfony"
                Restricting package "symfony/http-kernel" to "^8.1" by monolith config "symfony"
                OUTPUT,
            $io,
        );
    }

    #[Test]
    public function matching_package_with_other_vendor(): void
    {
        $packages = $origPackages = [
            new CompletePackage('rollerworks/search', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-dbal', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-orm', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.1.0', '2.1.0'),

            new CompletePackage('rollersearch/core', '3.0.0', '3.0.0'),
            new CompletePackage('rollersearch/doctrine-dbal', '3.0.0', '3.0.0'),
            new CompletePackage('rollersearch/doctrine-orm', '3.0.0', '3.0.0'),
            new CompletePackage('rollersearch/elastica', '3.0.0', '3.0.0'),

            new CompletePackage('symfony/translations', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/framework-bundle', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/framework-bundle', '8.0.0', '8.1.0'),
            new CompletePackage('symfony/http-kernel', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/translation-contract', '6.4', '6.4'),
        ];

        $rootPackage = new RootPackage('park-manager/park-manager', '1.0.0', '1.0.0');
        $rootPackage->setRequires(self::resolveRequires('park-manager/park-manager', $origPackages));
        $packages[] = $rootPackage;

        $io = new BufferIO('', StreamOutput::VERBOSITY_NORMAL, new OutputFormatter(false));
        $filter = new PackageFilter($io, new Options([
            'monolith-versions' => [
                'rollersearch' => [
                    'package' => ['rollerworks/search-*', 'rollersearch/*'],
                    'constraint' => '^2.0',
                ],
                'symfony' => [
                    'package' => ['symfony/*'],
                    'exclude' => ['symfony/translation-contract'],
                    'constraint' => '^8.1',
                ],
            ],
        ]));

        $this->assertFilteredContainsAdditional(
            $filter,
            $rootPackage,
            $packages,
            [
                'rollersearch' => [
                    'constraint' => '^2.0',
                    'packages' => [
                        'rollerworks/search-doctrine-dbal',
                        'rollerworks/search-doctrine-orm',
                        'rollerworks/search-elasticsearch',

                        'rollersearch/core',
                        'rollersearch/doctrine-dbal',
                        'rollersearch/doctrine-orm',
                        'rollersearch/elastica',
                    ],
                ],
                'symfony' => [
                    'constraint' => '^8.1',
                    'packages' => [
                        'symfony/framework-bundle',
                        'symfony/http-kernel',
                        'symfony/translations',
                    ],
                ],
            ],
        );

        $this->assertOutputEquals(
            <<<'OUTPUT'
                Restricting package "rollerworks/search-doctrine-dbal" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollerworks/search-doctrine-orm" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollerworks/search-elasticsearch" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollersearch/core" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollersearch/doctrine-dbal" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollersearch/doctrine-orm" to "^2.0" by monolith config "rollersearch"
                Restricting package "rollersearch/elastica" to "^2.0" by monolith config "rollersearch"
                Restricting package "symfony/translations" to "^8.1" by monolith config "symfony"
                Restricting package "symfony/framework-bundle" to "^8.1" by monolith config "symfony"
                Restricting package "symfony/http-kernel" to "^8.1" by monolith config "symfony"
                OUTPUT,
            $io,
        );
    }

    #[Test]
    public function reports_conflicts(): void
    {
        $packages = $origPackages = [
            new CompletePackage('rollerworks/search', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-dbal', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-orm', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.1.0', '2.1.0'),

            new CompletePackage('rollersearch/core', '3.0.0', '3.0.0'),
            new CompletePackage('rollersearch/doctrine-dbal', '3.0.0', '3.0.0'),
            new CompletePackage('rollersearch/doctrine-orm', '3.0.0', '3.0.0'),
            new CompletePackage('rollersearch/elastica', '3.0.0', '3.0.0'),

            new CompletePackage('symfony/translations', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/framework-bundle', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/framework-bundle', '8.0.0', '8.1.0'),
            new CompletePackage('symfony/http-kernel', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/translation-contract', '6.4', '6.4'),
        ];

        $rootPackage = new RootPackage('park-manager/park-manager', '1.0.0', '1.0.0');
        $rootPackage->setRequires(self::resolveRequires('park-manager/park-manager', $origPackages));
        $packages[] = $rootPackage;

        $io = new BufferIO('', StreamOutput::VERBOSITY_NORMAL, new OutputFormatter(false));
        $filter = new PackageFilter($io, new Options([
            'monolith-versions' => [
                'rollerworks-search' => [
                    'package' => ['rollerworks/search-*', 'rollersearch/*'],
                    'constraint' => '^2.0',
                ],
                'rollersearch' => [
                    'package' => ['rollersearch/core'],
                    'constraint' => '^3.0',
                ],
                'symfony' => [
                    'package' => ['symfony/*'],
                    'exclude' => ['symfony/translation-contract'],
                    'constraint' => '^8.1',
                ],
            ],
        ]));

        $this->assertFilteredContainsAdditional(
            $filter,
            $rootPackage,
            $packages,
            [
                'rollerworks-search' => [
                    'constraint' => '^2.0',
                    'packages' => [
                        'rollerworks/search-doctrine-dbal',
                        'rollerworks/search-doctrine-orm',
                        'rollerworks/search-elasticsearch',

                        'rollersearch/core',
                        'rollersearch/doctrine-dbal',
                        'rollersearch/doctrine-orm',
                        'rollersearch/elastica',
                    ],
                ],
                'symfony' => [
                    'constraint' => '^8.1',
                    'packages' => [
                        'symfony/framework-bundle',
                        'symfony/http-kernel',
                        'symfony/translations',
                    ],
                ],
            ],
        );

        $this->assertOutputEquals(
            <<<'OUTPUT'
                <warning>Monolith config "rollersearch" conflicts with "rollerworks-search" for package "rollersearch/core" (ignored).</warning>
                Restricting package "rollerworks/search-doctrine-dbal" to "^2.0" by monolith config "rollerworks-search"
                Restricting package "rollerworks/search-doctrine-orm" to "^2.0" by monolith config "rollerworks-search"
                Restricting package "rollerworks/search-elasticsearch" to "^2.0" by monolith config "rollerworks-search"
                Restricting package "rollersearch/core" to "^2.0" by monolith config "rollerworks-search"
                Restricting package "rollersearch/doctrine-dbal" to "^2.0" by monolith config "rollerworks-search"
                Restricting package "rollersearch/doctrine-orm" to "^2.0" by monolith config "rollerworks-search"
                Restricting package "rollersearch/elastica" to "^2.0" by monolith config "rollerworks-search"
                Restricting package "symfony/translations" to "^8.1" by monolith config "symfony"
                Restricting package "symfony/framework-bundle" to "^8.1" by monolith config "symfony"
                Restricting package "symfony/http-kernel" to "^8.1" by monolith config "symfony"
                OUTPUT,
            $io,
        );
    }

    #[Test]
    public function overwrite_using_env_configuration(): void
    {
        $_SERVER['COMPOSER_MONOLITH_ROLLERSEARCH'] = '^4.0';

        $packages = $origPackages = [
            new CompletePackage('rollerworks/search', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-dbal', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-doctrine-orm', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.0.0', '2.0.0'),
            new CompletePackage('rollerworks/search-elasticsearch', '2.1.0', '2.1.0'),

            new CompletePackage('symfony/translations', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/framework-bundle', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/framework-bundle', '8.0.0', '8.1.0'),
            new CompletePackage('symfony/http-kernel', '8.0.0', '8.0.0'),
            new CompletePackage('symfony/translation-contract', '6.4', '6.4'),
        ];

        $rootPackage = new RootPackage('park-manager/park-manager', '1.0.0', '1.0.0');
        $rootPackage->setRequires(self::resolveRequires('park-manager/park-manager', $origPackages));
        $packages[] = $rootPackage;

        $io = new BufferIO('', StreamOutput::VERBOSITY_NORMAL, new OutputFormatter(false));
        $filter = new PackageFilter($io, new Options([
            'monolith-versions' => [
                'rollersearch' => [
                    'package' => 'rollerworks/search-*',
                    'constraint' => '^2.0',
                ],
                'symfony' => [
                    'package' => ['symfony/*'],
                    'exclude' => ['symfony/translation-contract'],
                    'constraint' => '^8.1',
                ],
            ],
        ]));

        $this->assertFilteredContainsAdditional(
            $filter,
            $rootPackage,
            $packages,
            [
                'rollersearch' => [
                    'constraint' => '^4.0',
                    'packages' => [
                        'rollerworks/search-doctrine-dbal',
                        'rollerworks/search-doctrine-orm',
                        'rollerworks/search-elasticsearch',
                    ],
                ],
                'symfony' => [
                    'constraint' => '^8.1',
                    'packages' => [
                        'symfony/framework-bundle',
                        'symfony/http-kernel',
                        'symfony/translations',
                    ],
                ],
            ],
        );

        $this->assertOutputEquals(
            <<<'OUTPUT'
                Monolith config "rollersearch" overwritten by ENV configuration to "^4.0".
                Restricting package "rollerworks/search-doctrine-dbal" to "^4.0" by monolith config "rollersearch"
                Restricting package "rollerworks/search-doctrine-orm" to "^4.0" by monolith config "rollersearch"
                Restricting package "rollerworks/search-elasticsearch" to "^4.0" by monolith config "rollersearch"
                Restricting package "symfony/translations" to "^8.1" by monolith config "symfony"
                Restricting package "symfony/framework-bundle" to "^8.1" by monolith config "symfony"
                Restricting package "symfony/http-kernel" to "^8.1" by monolith config "symfony"
                OUTPUT,
            $io,
        );
    }

    /**
     * @param CompletePackage[] $packages
     *
     * @return array<string, Link>
     */
    private static function resolveRequires(string $source, array $packages): array
    {
        $resolved = [];

        foreach ($packages as $package) {
            $name = $package->getName();

            $resolved[$name] = new Link($source, $name, new Constraint('>=', 'dev-main'), Link::TYPE_REQUIRE);
        }

        return $resolved;
    }

    /**
     * @param array<int, CompletePackage>                                      $originalPackages
     * @param array<string, array{'constraint': string, 'packages': string[]}> $additions        ['name' => ['constraint' => 'constraint', 'packages' => ['package']]
     */
    private function assertFilteredContainsAdditional(
        PackageFilter $filter,
        RootPackage $rootPackage,
        array $originalPackages,
        array $additions,
    ): void {
        $originalRootPackage = clone $rootPackage;
        $origReqs = $originalRootPackage->getRequires();

        $resolvedAdditions = [];
        $resolvedReqs = [];

        foreach ($additions as $addition => $additionConfig) {
            $resolvedAdditions[] = $this->createAdditionalPackage($addition, $additionConfig['constraint'], $additionConfig['packages']);
            $resolvedReqs[$addition] = new Link($rootPackage->getName(), $addition, new Constraint('==', 'dev-main'), Link::TYPE_REQUIRE);
        }

        $filtered = $filter->enforceConstraints($rootPackage, $originalPackages);

        $this->assertEquals(array_merge($originalPackages, $resolvedAdditions), $filtered);
        $this->assertEquals(array_merge($origReqs, $resolvedReqs), $rootPackage->getRequires());
    }

    /** @param string[] $packages */
    private function createAdditionalPackage(string $name, string $constraint, array $packages): CompletePackage
    {
        $versionParser = new VersionParser();
        $constraintObj = $versionParser->parseConstraints($constraint);
        $reqs = [];

        foreach ($packages as $package) {
            $reqs[$package] = new Link($name, $package, $constraintObj, Link::TYPE_REQUIRE, $constraint);
        }

        $p = new CompletePackage($name, 'dev-main', 'dev-main');
        $p->setType('metapackage');
        $p->setDistReference('dev-main');
        $p->setDistType('path');
        $p->setDistUrl('file://' . \dirname(__DIR__) . '/src/dummy-package');
        $p->setRequires($reqs);

        return $p;
    }

    private function assertOutputEquals(string $expectedOutput, BufferIO $io): void
    {
        $this->assertSame($expectedOutput, rtrim(preg_replace('/\h/', ' ', $io->getOutput())));
    }
}
