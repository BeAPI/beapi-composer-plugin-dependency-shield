<?php

namespace BEAPI\Composer\DependencyShieldPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use RuntimeException;

/**
 * Checks WordPress plugin header requirements against the project PHP/WP versions.
 */
class Checker
{
    private const WP_CORE_IMPLEMENTATION = 'wordpress/core-implementation';

    /** @var Composer */
    private $composer;

    /** @var IOInterface */
    private $io;

    /** @var PluginHeaderParser */
    private $parser;

    public function __construct(Composer $composer, IOInterface $io, ?PluginHeaderParser $parser = null)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->parser = $parser ?: new PluginHeaderParser();
    }

    /**
     * Run the dependency shield checks.
     *
     * Stays completely silent when the root project is not a WordPress Composer
     * project (no extra.installer-paths with type:wordpress-plugin). Safe for
     * global Composer installs.
     *
     * @throws RuntimeException When one or more packages violate PHP/WP requirements.
     */
    public function check(): void
    {
        if (!$this->isWordPressComposerProject()) {
            return;
        }

        $this->io->write('<info>Dependency Shield:</info> checking WordPress plugin requirements…');

        $phpVersion = $this->resolvePhpVersion();
        $wpVersion = $this->resolveWordpressVersion();

        if (null === $wpVersion) {
            $this->io->write(
                '<warning>Dependency Shield:</warning> no package provides <comment>'
                . self::WP_CORE_IMPLEMENTATION
                . '</comment>; WordPress requirement checks are skipped.'
            );
        } else {
            $this->io->write(
                sprintf(
                    '  Using PHP <comment>%s</comment>, WordPress <comment>%s</comment>',
                    $phpVersion,
                    $wpVersion
                )
            );
        }

        $ignore = $this->getIgnoredPackages();
        $violations = [];

        foreach ($this->getTargetPackages() as $package) {
            $name = $package->getName();
            if (isset($ignore[$name])) {
                $this->io->write(sprintf('  Skipping ignored package <info>%s</info>', $name), true, IOInterface::VERBOSE);
                continue;
            }

            $installPath = $this->composer->getInstallationManager()->getInstallPath($package);
            if (!$installPath || !is_dir($installPath)) {
                $this->io->write(
                    sprintf(
                        '<warning>Dependency Shield:</warning> install path not found for <info>%s</info>; skipped.',
                        $name
                    )
                );
                continue;
            }

            $plugins = $this->parser->findPlugins($installPath);
            if ($plugins === []) {
                $this->io->write(
                    sprintf(
                        '<warning>Dependency Shield:</warning> no plugin headers found in <info>%s</info>; skipped.',
                        $name
                    ),
                    true,
                    IOInterface::VERBOSE
                );
                continue;
            }

            foreach ($plugins as $relativeFile => $headers) {
                $pluginLabel = $name . ' (' . $relativeFile . ')';

                if (!Compatibility::isPhpVersionCompatible($headers['RequiresPHP'], $phpVersion)) {
                    $violations[] = sprintf(
                        '%s requires PHP >= %s (project: %s)',
                        $pluginLabel,
                        $headers['RequiresPHP'],
                        $phpVersion
                    );
                }

                if (null !== $wpVersion && !Compatibility::isWpVersionCompatible($headers['RequiresWP'], $wpVersion)) {
                    $violations[] = sprintf(
                        '%s requires WordPress >= %s (project: %s)',
                        $pluginLabel,
                        $headers['RequiresWP'],
                        $wpVersion
                    );
                }
            }
        }

        if ($violations === []) {
            $this->io->write('<info>Dependency Shield:</info> all checked plugins are compatible.');
            return;
        }

        $message = "Dependency Shield found incompatible WordPress plugin requirements:\n  - "
            . implode("\n  - ", $violations);

        $this->io->writeError('<error>' . $message . '</error>');

        throw new RuntimeException($message);
    }

    /**
     * Detect Bedrock-like projects via composer/installers installer-paths.
     */
    private function isWordPressComposerProject(): bool
    {
        $extra = $this->composer->getPackage()->getExtra();
        $installerPaths = $extra['installer-paths'] ?? null;

        if (!is_array($installerPaths) || $installerPaths === []) {
            return false;
        }

        foreach ($installerPaths as $rules) {
            if (!is_array($rules)) {
                $rules = [$rules];
            }

            foreach ($rules as $rule) {
                if (is_string($rule) && 'type:wordpress-plugin' === $rule) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<PackageInterface>
     */
    private function getTargetPackages(): array
    {
        $rootRequires = array_keys($this->composer->getPackage()->getRequires());
        $whitelist = array_fill_keys($rootRequires, true);
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        $packages = [];

        foreach ($localRepo->getPackages() as $package) {
            $type = $package->getType();
            if ('wordpress-plugin' !== $type && 'wordpress-muplugin' !== $type) {
                continue;
            }

            if (!isset($whitelist[$package->getName()])) {
                continue;
            }

            $packages[] = $package;
        }

        return $packages;
    }

    /**
     * @return array<string, true>
     */
    private function getIgnoredPackages(): array
    {
        $extra = $this->composer->getPackage()->getExtra();
        $ignore = $extra['dependency-shield']['ignore'] ?? [];

        if (!is_array($ignore)) {
            return [];
        }

        $map = [];
        foreach ($ignore as $name) {
            if (is_string($name) && $name !== '') {
                $map[strtolower($name)] = true;
            }
        }

        return $map;
    }

    private function resolvePhpVersion(): string
    {
        $platform = $this->composer->getConfig()->get('platform') ?: [];
        if (!empty($platform['php']) && is_string($platform['php'])) {
            return $platform['php'];
        }

        return PHP_VERSION;
    }

    private function resolveWordpressVersion(): ?string
    {
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

        foreach ($localRepo->getPackages() as $package) {
            foreach ($package->getProvides() as $link) {
                if (!$link instanceof Link) {
                    continue;
                }

                if (self::WP_CORE_IMPLEMENTATION !== $link->getTarget()) {
                    continue;
                }

                $constraint = $link->getPrettyConstraint();
                if (is_string($constraint) && $constraint !== '') {
                    return $constraint;
                }
            }
        }

        return null;
    }
}
