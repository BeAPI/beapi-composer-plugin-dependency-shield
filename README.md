# Dependency Shield

Composer plugin that verifies WordPress plugin header requirements (`Requires PHP`, `Requires at least`) against the project PHP version and the installed `wordpress/core-implementation` provider after `composer install` / `composer update`.

Also exposes a manual command: `composer dependency-shield`.

## Requirements

- PHP `>= 7.4`
- Composer 2 (`composer-plugin-api: ^2.0`)

## Installation

### Global (recommended)

```bash
composer global require beapi/composer-plugin-dependency-shield
composer global config allow-plugins.beapi/composer-plugin-dependency-shield true
```

On non-WordPress Composer projects the plugin stays **completely silent** (no output, no failure).
It only runs when the root `composer.json` has `extra.installer-paths` containing `type:wordpress-plugin`.

### Per-project (not recommended)

```bash
composer require beapi/composer-plugin-dependency-shield --dev
```

Allow the plugin in the root `composer.json`:

```json
{
  "config": {
    "allow-plugins": {
      "beapi/composer-plugin-dependency-shield": true
    }
  }
}
```

## Behaviour

1. **Guard**: if `extra.installer-paths` is missing or does not list `type:wordpress-plugin`, exit silently (global-install safe).
2. On `POST_INSTALL_CMD` / `POST_UPDATE_CMD` (and via the command), collect **direct `require`** packages with type `wordpress-plugin` or `wordpress-muplugin`.
3. Resolve PHP from `config.platform.php`, otherwise `PHP_VERSION`.
4. Resolve WordPress from an installed package that **provides** `wordpress/core-implementation` (e.g. `roots/wordpress-no-content`). If missing, WP checks are skipped with a warning.
5. Discover plugin files like WordPress `get_plugins()`, parse headers like `get_file_data()`.
6. Compare with `version_compare(..., '>=')` like WordPress core.
7. Accumulate all mismatches, then fail with a global error listing the packages.

`require-dev` packages are **not** checked.

### Ignore list

```json
{
  "extra": {
    "dependency-shield": {
      "ignore": [
        "wp-plugin/imagify",
        "plugin-planet/bbq-pro"
      ]
    }
  }
}
```

## Local testing on a Bedrock (or any Composer WP) project

### Option A — global + path repo

```bash
composer global config repositories.dependency-shield path /Users/njuen/Developer/beapi/beapi-composer-plugin-dependency-shield
composer global require beapi/composer-plugin-dependency-shield:@dev
composer global config allow-plugins.beapi/composer-plugin-dependency-shield true
```

Then in any Bedrock project:

```bash
composer dependency-shield
# or composer install / update
```

On a non-WP project (no `installer-paths` / no `type:wordpress-plugin`), the same commands stay silent.

### Option B — path repo in the project

```bash
cd /path/to/your-bedrock-project
composer config repositories.dependency-shield path ../beapi-composer-plugin-dependency-shield
composer require beapi/composer-plugin-dependency-shield:@dev --dev
```

Ensure `config.allow-plugins` includes `beapi/composer-plugin-dependency-shield: true`.

### Run the check

```bash
composer dependency-shield
```

Or trigger hooks:

```bash
composer install
# or
composer update
```

### Force a failure (sanity check)

Temporarily set a low platform PHP, then re-run:

```bash
composer config platform.php 7.0
composer dependency-shield
composer config --unset platform.php
```

Or put a package that requires a newer WP than your core in `require` and run the command again.

## Development / unit tests

```bash
composer install
composer test
```

## License

GPL-3.0-or-later — see [LICENSE.md](LICENSE.md).
