# Changelog

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
