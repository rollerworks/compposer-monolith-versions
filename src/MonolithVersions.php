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

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;

final class MonolithVersions implements PluginInterface, EventSubscriberInterface
{
    private static bool $activated = true;

    private Composer $composer;
    private Options $options;
    private PackageFilter $filter;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->options = $this->initOptions();
        $this->filter = new PackageFilter($io, $this->options);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Noop
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        self::$activated = false;
    }

    public function enforceVersionsPackages(PrePoolCreateEvent $event): void
    {
        $rootPackage = $this->composer->getPackage();

        $event->setPackages($this->filter->enforceConstraints($rootPackage, $event->getPackages()));
    }

    public static function getSubscribedEvents(): array
    {
        if (! self::$activated) {
            return [];
        }

        $events = [
            PluginEvents::PRE_POOL_CREATE => 'enforceVersionsPackages',
        ];

        return $events;
    }

    private function initOptions(): Options
    {
        $extra = $this->composer->getPackage()->getExtra();

        if (empty($extra['monolith-versions'])) {
            self::$activated = false;
        }

        return new Options($extra);
    }
}
