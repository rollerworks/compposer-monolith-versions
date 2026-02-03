Composer MonolithVersion
========================

This Composer plugin enforces all to-be-installed packages of a monolith versioned 
distribution to have the same version.

**This library is in early development, use at your own risk!**

> [!NOTE]
> This will only work with monolith versioned distributions.
> 
> Only packages that would be normally installed are enforced to have the same version.
> Packages that are not installed are not enforced.

For example, if you have a monolith versioned distribution with the following packages:

* `rollerworks/search`
* `rollerworks/search-doctrine-dbal`
* `rollerworks/search-doctrine-orm`
* `rollerworks/search-elasticsearch`
* `rollerworks/search-bundle`

All the `rollerworks/search-*` packages should have the same version to ensure proper
compatibility between them. While Composer always installs the latest possible version 
of a package, you might want to ensure all packages are the same version.

Possible scenarios where this is useful:

## You want to test that all packages are compatible

The `rollerworks-search-doctrine-orm` package also installs the `rollerworks/search-doctrine-dbal` 
package, which depends on `doctrine/dbal`. When the `doctrine/dbal` is not compatible with the 
latest version of the `rollerworks/search-doctrine-dbal` package, an older version of the
`rollerworks/search-doctrine-dbal` package will be installed. Which would go unnoticed.

## Testing with `--prefer-lowest`

When testing with `--prefer-lowest` you don't want to add all sub-packages to the `require` section,
yet you want to ensure the packages are the same version.

## Usage

Before installing the plugin, make sure you have the latest version of Composer installed.

### Enabling the plugin

Because plugins are disabled by default, you need to enable this plugin before usage.

```bash
composer config --no-plugins allow-plugins.rollerworks/monolith-versions true
```

Install the plugin:

```bash
composer require lifthill/composer-monolith-version
```

### Configuring the plugin

Add the following to your `composer.json` file (in the extra section):

```json
{
    "extra": {
        "monolith-versions": {
            "monolith-package-name": {
                "package": "vendor/package-pattern",
                "constraint": "^2.1"
            }
        }
    }
}
```

For example:

```json
{
    "extra": {
        "monolith-versions": {
            "rollersearch": {
                "pattern": "rollerworks/search-*",
                "constraint": "^2.1",
                "exclude": [
                    "rollerworks/search-testing"
                ]
            }
        }
    }
}
```

Each entry in the `monolith-versions` array is a monolith configuration,
where the key is the name of the monolith and the value is an array with the following keys:

1. `package` (required) – The pattern to match the package names against (multiple patterns are allowed using an array).
2. `constraint` (required) – The constraint to enforce on the matched packages (same as in `require`).
3. `exclude` (optional) – An array of package names to exclude.

A package name can contain a wildcard (`search-*`, `*search`, `*`),
or a expand pattern (`{core, doctrine-dbal}`).

**Please take note of the following limitations:**

* A monolith name can only consist of `[a-z]` characters, numbers and dashes,
  and must start an `[a-z]` character, and contain no consecutive dashes (`--`).

* Conflicting patterns are ignored with a warning (only the first matching pattern is used).

* A vendor name cannot contain a wildcard (`*` or expands pattern `{rollerworks,symfony}`).

  Use an array for multiple patterns instead.

* Only a single wildcard (`*`) _or_ expands pattern is allowed in the package name.

* A package name (not the vendor) can contain a wildcard (`search-*`, `*search`, `*`),
  or a expand pattern (`{core, doctrine-dbal}`).

* The `exclude` key currently doesn't support patterns, only full names.

## Override constraints using environment variables

If you want to override the constraint for a specific monolith (for example to test a specific version), 
you can use an environment variable (per configuration) to override the constraint.

| Environment Variable                                 | Effect                                                                        |
|------------------------------------------------------|-------------------------------------------------------------------------------|
| `COMPOSER_MONOLITH_ROLLERWORKS_SEARCH`               | Overrides the constraint for the `rollerworks-search` monolith.               |
| `COMPOSER_MONOLITH_ROLLERWORKS_SEARCH_DOCTRINE_DBAL` | Overrides the constraint for the `rollerworks-search-doctrine-dbal` monolith. |

This is best used for testing purposes.

```bash
COMPOSER_MONOLITH_ROLLERWORKS_SEARCH="^2.1" composer update
```

Or in a CI environment:

```bash
export COMPOSER_MONOLITH_ROLLERWORKS_SEARCH="^2.1"

composer update
```

## Conflicts when using `--with`

Using the `--with` option of the `composer update` command might not work as expected
when a lower version is required than the monolith constraint allows.

Use an environment variable to override the constraint with a more permissive value
to resolve this issue. 

Like `COMPOSER_MONOLITH_ROLLERWORKS_SEARCH="^2.0" composer update --with rollerworks/search:2.1`.

## Contributing

This is an open source project. If you'd like to contribute,
please read the [Contributing Guidelines][contributing]. If you're submitting
a pull request, please follow the guidelines in the [Submitting a Patch][patches] section.

About Us
--------

This library is brought to you by [Sebastiaan Stok (@sstok)][sstok] and supported by [contributors][9].

[composer]: https://getcomposer.org/doc/00-intro.md
[contributing]: https://contributing.rollerscapes.net/
[patches]: https://contributing.rollerscapes.net/latest/patches.html
[sstok]: https://github.com/sstok
