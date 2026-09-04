# Dependency Shield

Composer plugin that verifies WordPress plugin header requirements (`Requires PHP`, `Requires at least`) against the project PHP version and the installed `wordpress/core-implementation` provider after `composer install` / `composer update`.

Also exposes a manual command: `composer dependency-shield` (exit code `1` on violations — useful in CI).

## Requirements

- PHP `>= 7.4`
- Composer 2 (`composer-plugin-api: ^2.0`)

## Installation

### Per-project (recommended)

Reproducible, present in CI, and the only mode that reliably guards merges and deploys:

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

In CI:

```bash
composer dependency-shield
```

### Global (optional)

Useful for immediate local feedback, not a replacement for the per-project install:

```bash
composer global require beapi/composer-plugin-dependency-shield
composer global config allow-plugins.beapi/composer-plugin-dependency-shield true
```

On non-WordPress Composer projects the plugin stays **completely silent** (no output, no failure).
It only runs when the root `composer.json` has `extra.installer-paths` containing `type:wordpress-plugin` or `type:wordpress-muplugin`.

## Behaviour

1. **Guard**: if `extra.installer-paths` is missing or does not list `type:wordpress-plugin` / `type:wordpress-muplugin`, exit silently (global-install safe). Verbose (`-v`) explains the skip.
2. On `POST_INSTALL_CMD` / `POST_UPDATE_CMD` **and** via `composer dependency-shield`, collect **direct `require`** packages with type `wordpress-plugin` or `wordpress-muplugin`.
3. Resolve PHP from `config.platform.php`, else the lower bound of root `require.php`, else `PHP_VERSION`. The chosen source is printed.
4. Resolve WordPress from an installed package that **provides** a **literal** `wordpress/core-implementation` version (e.g. `roots/wordpress-no-content`). Non-literal constraints (`*`, `>=6.0`, …) skip WP checks with a warning. If missing, WP checks are skipped with a warning.
5. Discover **root-level** `.php` files in each package install path and parse headers like `get_file_data()` (no subdirectory scan — avoids bundled shims).
6. Compare with `version_compare(..., '>=')` like WordPress core.
7. Accumulate all mismatches, then **fail hard** (exit ≠ 0) with a global listing.

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

## Limits / assumptions

The check runs on `POST_INSTALL_CMD` / `POST_UPDATE_CMD`, so packages are already downloaded when a violation fails the command. That is intentional: an incompatible plugin must break `composer install` / `composer update` / `composer require`, CI or not.

This model assumes a BeAPI-style Bedrock layout where:

- **Plugins are not versioned** (install paths such as `web/app/plugins/` stay out of Git);
- **`composer.lock` is not versioned** either.

Under those assumptions, a failed `composer require` may leave local leftovers (vendor / lock / plugin files) while Composer reverts `composer.json` — that local dirt is disposable and does not pollute the repository. If you *do* commit `composer.lock` or plugin trees, clean up after a failed require before committing.

Headers are only readable after download: the shield cannot prevent the network fetch, it fails the Composer command once the incompatibility is known.

## Local testing on a Bedrock (or any Composer WP) project

### Option A — path repo in the project (recommended)

```bash
cd /path/to/your-bedrock-project
composer config repositories.dependency-shield path ../composer-plugin-dependency-shield
composer require beapi/composer-plugin-dependency-shield --dev
```

Ensure `config.allow-plugins` includes `beapi/composer-plugin-dependency-shield: true`.

### Option B — global + path repo

```bash
composer global config repositories.dependency-shield path /path-to-composer-file/composer-plugin-dependency-shield
composer global require beapi/composer-plugin-dependency-shield:@dev
composer global config allow-plugins.beapi/composer-plugin-dependency-shield true
```

Then in any Bedrock project:

```bash
composer dependency-shield
# or composer install / update (fails on violations)
```

On a non-WP project (no matching `installer-paths`), the same commands stay silent.

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
