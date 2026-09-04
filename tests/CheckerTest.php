<?php

namespace BEAPI\Composer\DependencyShieldPlugin\Tests;

use BEAPI\Composer\DependencyShieldPlugin\Checker;
use BEAPI\Composer\DependencyShieldPlugin\PluginHeaderParser;
use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\IO\BufferIO;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\Constraint;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CheckerTest extends TestCase
{
    /** @var string */
    private $fixtureRoot;

    protected function setUp(): void
    {
        $this->fixtureRoot = sys_get_temp_dir() . '/dependency-shield-checker-' . uniqid('', true);
        mkdir($this->fixtureRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->fixtureRoot);
    }

    public function testPassesWhenRequirementsAreMet(): void
    {
        $pluginDir = $this->fixtureRoot . '/ok-plugin';
        mkdir($pluginDir);
        file_put_contents(
            $pluginDir . '/ok-plugin.php',
            "<?php\n/**\n * Plugin Name: OK\n * Requires at least: 6.0\n * Requires PHP: 8.0\n */\n"
        );

        $io = new BufferIO();
        $checker = $this->createChecker(
            [
                $this->createPluginPackage('vendor/ok-plugin', $pluginDir),
                $this->createWpCorePackage('6.8.3'),
            ],
            ['vendor/ok-plugin' => true],
            [],
            '8.2.0',
            null,
            $io
        );

        $checker->check();
        self::assertStringContainsString('Dependency Shield', $io->getOutput());
    }

    public function testStaysSilentWithoutInstallerPaths(): void
    {
        $io = new BufferIO();
        $checker = $this->createChecker(
            [],
            [],
            [],
            '8.2.0',
            [],
            $io
        );

        $checker->check();
        self::assertSame('', $io->getOutput());
    }

    public function testStaysSilentWhenInstallerPathsHaveNoWordpressPluginType(): void
    {
        $io = new BufferIO();
        $checker = $this->createChecker(
            [],
            [],
            [],
            '8.2.0',
            [
                'web/app/themes/{$name}/' => ['type:wordpress-theme'],
            ],
            $io
        );

        $checker->check();
        self::assertSame('', $io->getOutput());
    }

    public function testRunsWhenOnlyMupluginInstallerPathIsPresent(): void
    {
        $pluginDir = $this->fixtureRoot . '/mu-plugin';
        mkdir($pluginDir);
        file_put_contents(
            $pluginDir . '/mu-plugin.php',
            "<?php\n/**\n * Plugin Name: MU\n * Requires PHP: 7.4\n */\n"
        );

        $io = new BufferIO();
        $package = $this->createPluginPackage('vendor/mu-plugin', $pluginDir);
        $package->setType('wordpress-muplugin');

        $checker = $this->createChecker(
            [
                $package,
                $this->createWpCorePackage('6.8.3'),
            ],
            ['vendor/mu-plugin' => true],
            [],
            '8.1.0',
            [
                'web/app/mu-plugins/{$name}/' => ['type:wordpress-muplugin'],
            ],
            $io
        );

        $checker->check();
        self::assertStringContainsString('all checked plugins are compatible', $io->getOutput());
    }

    public function testFailsOnPhpAndWpMismatchAndListsAllViolations(): void
    {
        $pluginDir = $this->fixtureRoot . '/bad-plugin';
        mkdir($pluginDir);
        file_put_contents(
            $pluginDir . '/bad-plugin.php',
            "<?php\n/**\n * Plugin Name: Bad\n * Requires at least: 6.9\n * Requires PHP: 8.3\n */\n"
        );

        $checker = $this->createChecker(
            [
                $this->createPluginPackage('vendor/bad-plugin', $pluginDir),
                $this->createWpCorePackage('6.8.3'),
            ],
            ['vendor/bad-plugin' => true],
            [],
            '8.1.0'
        );

        try {
            $checker->check();
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('vendor/bad-plugin', $e->getMessage());
            self::assertStringContainsString('PHP >= 8.3', $e->getMessage());
            self::assertStringContainsString('WordPress >= 6.9', $e->getMessage());
        }
    }

    public function testIgnoresPackagesListedInExtra(): void
    {
        $pluginDir = $this->fixtureRoot . '/ignored-plugin';
        mkdir($pluginDir);
        file_put_contents(
            $pluginDir . '/ignored-plugin.php',
            "<?php\n/**\n * Plugin Name: Ignored\n * Requires PHP: 99.0\n */\n"
        );

        $checker = $this->createChecker(
            [
                $this->createPluginPackage('vendor/ignored-plugin', $pluginDir),
                $this->createWpCorePackage('6.8.3'),
            ],
            ['vendor/ignored-plugin' => true],
            ['vendor/ignored-plugin'],
            '8.1.0'
        );

        $checker->check();
        $this->addToAssertionCount(1);
    }

    public function testSkipsWpCheckWhenCoreImplementationIsMissing(): void
    {
        $pluginDir = $this->fixtureRoot . '/wp-only';
        mkdir($pluginDir);
        file_put_contents(
            $pluginDir . '/wp-only.php',
            "<?php\n/**\n * Plugin Name: WP Only\n * Requires at least: 99.0\n * Requires PHP: 7.4\n */\n"
        );

        $checker = $this->createChecker(
            [
                $this->createPluginPackage('vendor/wp-only', $pluginDir),
            ],
            ['vendor/wp-only' => true],
            [],
            '8.1.0'
        );

        // WP requirement is ignored; PHP is fine.
        $checker->check();
        $this->addToAssertionCount(1);
    }

    public function testSkipsPackagesNotInRootRequire(): void
    {
        $pluginDir = $this->fixtureRoot . '/dev-only';
        mkdir($pluginDir);
        file_put_contents(
            $pluginDir . '/dev-only.php',
            "<?php\n/**\n * Plugin Name: Dev Only\n * Requires PHP: 99.0\n */\n"
        );

        $checker = $this->createChecker(
            [
                $this->createPluginPackage('vendor/dev-only', $pluginDir),
                $this->createWpCorePackage('6.8.3'),
            ],
            [], // not in root require
            [],
            '8.1.0'
        );

        $checker->check();
        $this->addToAssertionCount(1);
    }

    /**
     * @param list<Package>             $installedPackages
     * @param array<string,true>        $rootRequires
     * @param list<string>              $ignore
     * @param array<string,mixed>|null  $installerPaths null = default Bedrock-like paths
     */
    private function createChecker(
        array $installedPackages,
        array $rootRequires,
        array $ignore,
        string $platformPhp,
        ?array $installerPaths = null,
        ?BufferIO $io = null
    ): Checker {
        $pathMap = [];
        foreach ($installedPackages as $package) {
            $extra = $package->getExtra();
            if (isset($extra['_test_install_path'])) {
                $pathMap[$package->getName()] = $extra['_test_install_path'];
            }
        }

        if (null === $installerPaths) {
            $installerPaths = [
                'web/app/plugins/{$name}/' => ['type:wordpress-plugin'],
                'web/app/mu-plugins/{$name}/' => ['type:wordpress-muplugin'],
            ];
        }

        /** @var RootPackage&MockObject $root */
        $root = $this->createMock(RootPackage::class);
        $links = [];
        foreach (array_keys($rootRequires) as $name) {
            $links[$name] = new Link('__root__', $name, new Constraint('=', '1.0.0'));
        }
        $root->method('getRequires')->willReturn($links);
        $root->method('getExtra')->willReturn([
            'installer-paths' => $installerPaths,
            'dependency-shield' => [
                'ignore' => $ignore,
            ],
        ]);

        /** @var InstalledRepositoryInterface&MockObject $localRepo */
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('getPackages')->willReturn($installedPackages);

        /** @var RepositoryManager&MockObject $repoManager */
        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);

        /** @var InstallationManager&MockObject $installationManager */
        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager->method('getInstallPath')->willReturnCallback(
            static function (Package $package) use ($pathMap) {
                return $pathMap[$package->getName()] ?? null;
            }
        );

        /** @var Config&MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            static function ($key) use ($platformPhp) {
                if ($key === 'platform') {
                    return ['php' => $platformPhp];
                }

                return null;
            }
        );

        /** @var Composer&MockObject $composer */
        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($root);
        $composer->method('getRepositoryManager')->willReturn($repoManager);
        $composer->method('getInstallationManager')->willReturn($installationManager);
        $composer->method('getConfig')->willReturn($config);

        return new Checker($composer, $io ?: new BufferIO(), new PluginHeaderParser());
    }

    private function createPluginPackage(string $name, string $installPath): Package
    {
        $package = new Package($name, '1.0.0.0', '1.0.0');
        $package->setType('wordpress-plugin');
        $package->setExtra(['_test_install_path' => $installPath]);

        return $package;
    }

    private function createWpCorePackage(string $version): Package
    {
        $package = new Package('roots/wordpress-no-content', $version . '.0', $version);
        $package->setType('wordpress-core');
        $package->setProvides([
            'wordpress/core-implementation' => new Link(
                'roots/wordpress-no-content',
                'wordpress/core-implementation',
                new Constraint('=', $version),
                Link::TYPE_PROVIDE,
                $version
            ),
        ]);

        return $package;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
