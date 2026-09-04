# Changelog

## 1.1.0 - 04 Sep 2026

* Scan only root-level `.php` files in each package (fix false positives from bundled libs) (#1)
* Always fail hard on `POST_INSTALL_CMD` / `POST_UPDATE_CMD` and on `composer dependency-shield` (#2)
* Avoid duplicating the violation list when throwing (#6)
* Accept `type:wordpress-muplugin` in the project guard; verbose skip message for non-WP projects (#5)
* Validate `wordpress/core-implementation` provide constraints (literal versions only) (#4)
* Resolve PHP from `config.platform.php`, then root `require.php`, then runtime; print the source (#11)
* Recommend per-project `--dev` install in the README (CI-reproducible) (#15)
* Document limits: assumes plugins and `composer.lock` are not versioned (#2)
* Sort target packages and violations for stable output (#7)
* Use `requireComposer()` when available in the command (#8)
* Normalize ignore-list lookup case-insensitively (#9)
* Add GitHub Actions CI, PHPStan (level 6), and missing test coverage (#14)

## 1.0.1 - 04 Sep 2026

* Declare package version in `composer.json`

## 1.0.0 - 04 Sep 2026

* Initial release
* Composer plugin + `dependency-shield` command
* Hooks on `POST_INSTALL_CMD` / `POST_UPDATE_CMD`
* Check direct `require` packages of type `wordpress-plugin` / `wordpress-muplugin`
* Parse plugin headers like WordPress (`get_plugins` / `get_file_data`)
* Compare PHP (`config.platform.php` or runtime) and WordPress (`wordpress/core-implementation`)
* Fail globally listing all incompatible packages
* Ignore list via `extra.dependency-shield.ignore`
* Stay silent unless `extra.installer-paths` contains `type:wordpress-plugin` (safe for global install)
* GPL-3.0-or-later
* PHPUnit test suite
