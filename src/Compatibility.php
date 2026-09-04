<?php

namespace BEAPI\Composer\DependencyShieldPlugin;

/**
 * WordPress-compatible version checks (no WP bootstrap).
 *
 * @see is_php_version_compatible()
 * @see is_wp_version_compatible()
 */
class Compatibility
{
    /**
     * @see is_php_version_compatible() in WordPress core
     */
    public static function isPhpVersionCompatible(string $required, string $phpVersion): bool
    {
        return $required === '' || version_compare($phpVersion, $required, '>=');
    }

    /**
     * @see is_wp_version_compatible() in WordPress core
     */
    public static function isWpVersionCompatible(string $required, string $wpVersion): bool
    {
        // Strip off any -alpha, -RC, -beta, -src suffixes.
        $version = explode('-', $wpVersion, 2)[0];

        $trimmed = trim($required);
        if ($trimmed !== '' && substr_count($trimmed, '.') > 1 && substr($trimmed, -2) === '.0') {
            $required = substr($trimmed, 0, -2);
        }

        return $required === '' || version_compare($version, $required, '>=');
    }
}
