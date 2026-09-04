<?php

namespace BEAPI\Composer\DependencyShieldPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use RuntimeException;

class DependencyShield implements PluginInterface, Capable, CommandProvider, EventSubscriberInterface
{
    /** @var Composer */
    private $composer;

    /** @var IOInterface */
    private $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nothing to do.
    }

    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => self::class,
        ];
    }

    public function getCommands(): array
    {
        return [
            new Command\DependencyShieldCommand(),
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallOrUpdate',
            ScriptEvents::POST_UPDATE_CMD => 'onPostInstallOrUpdate',
        ];
    }

    /**
     * Always fail hard on violations after install/update.
     *
     * @throws RuntimeException
     */
    public function onPostInstallOrUpdate(): void
    {
        (new Checker($this->composer, $this->io))->check();
    }
}
