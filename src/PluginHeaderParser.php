<?php

namespace BEAPI\Composer\DependencyShieldPlugin;

/**
 * Discovers WordPress plugin files and reads their headers the same way core does.
 *
 * Discovery mirrors get_plugins() (wp-admin/includes/plugin.php).
 * Header parsing mirrors get_file_data() / _cleanup_header_comment() (wp-includes/functions.php).
 * No filters, translations, markup or cache.
 */
class PluginHeaderParser
{
    private const HEADER_BYTES = 8192;

    /** @var array<string, string> */
    private const DEFAULT_HEADERS = [
        'Name' => 'Plugin Name',
        'RequiresWP' => 'Requires at least',
        'RequiresPHP' => 'Requires PHP',
    ];

    /**
     * Find plugin files under a package install path and return their headers.
     *
     * @return array<string, array{Name: string, RequiresWP: string, RequiresPHP: string}>
     *         Keyed by path relative to $pluginRoot.
     */
    public function findPlugins(string $pluginRoot): array
    {
        $pluginRoot = rtrim($pluginRoot, '/\\');
        if ($pluginRoot === '' || !is_dir($pluginRoot)) {
            return [];
        }

        $pluginFiles = $this->discoverPluginFiles($pluginRoot);
        if ($pluginFiles === []) {
            return [];
        }

        $plugins = [];
        foreach ($pluginFiles as $relativeFile) {
            $absolute = $pluginRoot . '/' . $relativeFile;
            if (!is_readable($absolute)) {
                continue;
            }

            $headers = $this->getFileData($absolute, self::DEFAULT_HEADERS);
            if ($headers['Name'] === '') {
                continue;
            }

            $plugins[$relativeFile] = $headers;
        }

        return $plugins;
    }

    /**
     * @see get_plugins() file discovery in WordPress core
     *
     * @return list<string>
     */
    private function discoverPluginFiles(string $pluginRoot): array
    {
        $pluginFiles = [];
        $pluginsDir = @opendir($pluginRoot);
        if (false === $pluginsDir) {
            return [];
        }

        while (false !== ($file = readdir($pluginsDir))) {
            if (strpos($file, '.') === 0) {
                continue;
            }

            $path = $pluginRoot . '/' . $file;
            if (is_dir($path)) {
                $subdir = @opendir($path);
                if (false === $subdir) {
                    continue;
                }

                while (false !== ($subfile = readdir($subdir))) {
                    if (strpos($subfile, '.') === 0) {
                        continue;
                    }

                    if (substr($subfile, -4) === '.php') {
                        $pluginFiles[] = $file . '/' . $subfile;
                    }
                }

                closedir($subdir);
            } elseif (substr($file, -4) === '.php') {
                $pluginFiles[] = $file;
            }
        }

        closedir($pluginsDir);

        return $pluginFiles;
    }

    /**
     * @see get_file_data() in WordPress core (without extra_*_headers filters)
     *
     * @param array<string, string> $defaultHeaders
     * @return array<string, string>
     */
    public function getFileData(string $file, array $defaultHeaders): array
    {
        $fileData = @file_get_contents($file, false, null, 0, self::HEADER_BYTES);
        if (false === $fileData) {
            $fileData = '';
        }

        // Make sure we catch CR-only line endings.
        $fileData = str_replace("\r", "\n", $fileData);

        $allHeaders = $defaultHeaders;
        foreach ($allHeaders as $field => $regex) {
            if (
                preg_match(
                    '/^(?:[ \t]*<\?(?:php)?)?[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi',
                    $fileData,
                    $match
                )
                && $match[1]
            ) {
                $allHeaders[$field] = $this->cleanupHeaderComment($match[1]);
            } else {
                $allHeaders[$field] = '';
            }
        }

        return $allHeaders;
    }

    /**
     * @see _cleanup_header_comment() in WordPress core
     */
    private function cleanupHeaderComment(string $str): string
    {
        return trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $str));
    }
}
