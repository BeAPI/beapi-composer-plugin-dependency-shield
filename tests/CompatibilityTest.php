<?php

namespace BEAPI\Composer\DependencyShieldPlugin\Tests;

use BEAPI\Composer\DependencyShieldPlugin\Compatibility;
use PHPUnit\Framework\TestCase;

class CompatibilityTest extends TestCase
{
    /**
     * @dataProvider phpProvider
     */
    public function testIsPhpVersionCompatible(string $required, string $phpVersion, bool $expected): void
    {
        self::assertSame($expected, Compatibility::isPhpVersionCompatible($required, $phpVersion));
    }

    public function phpProvider(): array
    {
        return [
            'empty required' => ['', '7.4.0', true],
            'equal' => ['8.0', '8.0.0', true],
            'higher' => ['8.0', '8.2.1', true],
            'lower' => ['8.2', '8.0.0', false],
            'patch required' => ['8.1.0', '8.1.5', true],
        ];
    }

    /**
     * @dataProvider wpProvider
     */
    public function testIsWpVersionCompatible(string $required, string $wpVersion, bool $expected): void
    {
        self::assertSame($expected, Compatibility::isWpVersionCompatible($required, $wpVersion));
    }

    public function wpProvider(): array
    {
        return [
            'empty required' => ['', '6.4.2', true],
            'equal' => ['6.4', '6.4.2', true],
            'higher' => ['5.8', '6.8.3', true],
            'lower' => ['6.9', '6.8.3', false],
            'strip prerelease suffix' => ['6.4', '6.4.2-beta1', true],
            'strip trailing .0 on required' => ['6.4.0', '6.4', true],
        ];
    }
}
