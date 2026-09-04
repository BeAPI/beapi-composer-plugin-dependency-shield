<?php

namespace BEAPI\Composer\DependencyShieldPlugin\Tests;

use BEAPI\Composer\DependencyShieldPlugin\PluginHeaderParser;
use PHPUnit\Framework\TestCase;

class PluginHeaderParserTest extends TestCase
{
    /** @var string */
    private $fixtureRoot;

    protected function setUp(): void
    {
        $this->fixtureRoot = sys_get_temp_dir() . '/dependency-shield-' . uniqid('', true);
        mkdir($this->fixtureRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->fixtureRoot);
    }

    public function testParsesRootPluginFileHeaders(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/sample-plugin.php',
            <<<'PHP'
<?php
/**
 * Plugin Name: Sample Plugin
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Version: 1.0.0
 */

PHP
        );

        $parser = new PluginHeaderParser();
        $plugins = $parser->findPlugins($this->fixtureRoot);

        self::assertArrayHasKey('sample-plugin.php', $plugins);
        self::assertSame('Sample Plugin', $plugins['sample-plugin.php']['Name']);
        self::assertSame('6.0', $plugins['sample-plugin.php']['RequiresWP']);
        self::assertSame('8.0', $plugins['sample-plugin.php']['RequiresPHP']);
    }

    public function testDiscoversPluginInOneLevelSubdirectory(): void
    {
        mkdir($this->fixtureRoot . '/my-plugin', 0777, true);
        $this->writeFile(
            $this->fixtureRoot . '/my-plugin/my-plugin.php',
            <<<'PHP'
<?php
/**
 * Plugin Name: Nested Plugin
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

PHP
        );

        $parser = new PluginHeaderParser();
        $plugins = $parser->findPlugins($this->fixtureRoot);

        self::assertArrayHasKey('my-plugin/my-plugin.php', $plugins);
        self::assertSame('Nested Plugin', $plugins['my-plugin/my-plugin.php']['Name']);
    }

    public function testIgnoresPhpFilesWithoutPluginName(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/helpers.php',
            <<<'PHP'
<?php
// Not a plugin.

PHP
        );

        $parser = new PluginHeaderParser();
        self::assertSame([], $parser->findPlugins($this->fixtureRoot));
    }

    public function testCleanupHeaderCommentStripsClosingComment(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/cleanup.php',
            <<<'PHP'
<?php
/**
 * Plugin Name: Cleanup Test
 * Requires PHP: 8.1 */
 */

PHP
        );

        $parser = new PluginHeaderParser();
        $plugins = $parser->findPlugins($this->fixtureRoot);

        self::assertSame('8.1', $plugins['cleanup.php']['RequiresPHP']);
    }

    public function testMissingRequirementHeadersAreEmptyStrings(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/minimal.php',
            <<<'PHP'
<?php
/**
 * Plugin Name: Minimal
 */

PHP
        );

        $parser = new PluginHeaderParser();
        $plugins = $parser->findPlugins($this->fixtureRoot);

        self::assertSame('', $plugins['minimal.php']['RequiresWP']);
        self::assertSame('', $plugins['minimal.php']['RequiresPHP']);
    }

    public function testReturnsEmptyForMissingDirectory(): void
    {
        $parser = new PluginHeaderParser();
        self::assertSame([], $parser->findPlugins($this->fixtureRoot . '/does-not-exist'));
    }

    private function writeFile(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
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
