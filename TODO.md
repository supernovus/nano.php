# TODO

## General

### UX Issues

* Finish creating ./skel/flexible.d with the new app template and modules.
* Make a new bin/user.php script for the new template(s).
* Finish and test the bin/initapp.php script.

* Fix the bugs in the JSONRPC library that are causing the tests to fail.

* Add nginx configuration examples.

### Library issues

* Write JSONRPC\Client\Socket library.
* Finish writing JSON\Patch library.

* Add proper PHPDoc documentation to all class files.

## v6

In v6, Nano.php would be renamed to Lum.php to coincide with Lum.js (which
is the new name of Nano.js). The namespace would change from \Nano to \Lum.

My current proposal for v6 is what I call the Great Divide.

The idea behind this was to be an extremely small framework for building
PHP apps. The problem is, it's greatly expanded from that mandate into
a collection of libraries. It's become a behemoth of things that should 
probably be their own standalone libraries.

I'm going to work on getting the libraries to work with Composer, and then
I will provide Composer package definitions for each of them.

Everything currently in the \Nano\Utils sub-namespace would be split off
into new packages, likely directly in the new \Lum namespace.

With that in mind, my current list of proposed packages, with the proposed
Composer package name first and the proposed Github package name in brackets.

Because the existing version depends on the `spl_autoload` autoloader, but
Composer uses it's own autoloader by default, support for both would have to
be considered and put into a new version of the init.php library (which would
be optional now, but kept to make using the autoloaders simple.) Because the
Composer support would be expected out of the box, the `pragmas/composer.php`
file would be removed entirely and it's code integrated with the new `init.php`.

All of these new packages would be in their own standalone repositories, and
this repository (which would be renamed to `lum.php`) would be changed to
contain only the README.md file, which would simply be a list of all of the
new packages with links to their standalone repositories.

### lum-core (lum.core.php)

#### Dependencies

* lum-file

#### Recommendations

* lum-base91
* lum-ubjson
* simpledom

#### Provides

| File                  | Description                                       |
| --------------------- | ------------------------------------------------- |
| `init.php`            | The bootstrap library, registers the autoloader.  |
| `core.php`            | Provides \Lum\Core object (replaces Nano\Nano).   |
| `plugins/*.php`       | The core plugins.                                 |
| `loader/*.php`        | The loader plugins.                               |
| `pragmas/*.php`       | The pragma plugins.                               |
| `meta/*.php`          | Metadata Traits.                                  |
| `data/*.php`          | The Data object classes.                          |

The `loaders` would have to be updated to support a few new features, as
they currently depend on the `spl_autoload` case-insensitivity. That would have
to be kept, but new support for PSR-4 style classes would have to be added.

### lum-framework (lum.framework.php)

#### Dependencies

* lum-core
* lum-simpleauth
* lum-translation
* lum-html
* lum-language
* lum-mailer
* lum-notifications

#### Recommendations

* lum-db

#### Provides

| File                  | Description                                       |
| --------------------- | ------------------------------------------------- |
| `controllers/*.php`   | Base classes for controllers.                     |
| `models/*.php`        | Base classes for certain models.                  |

### lum-db (lum.db.php)

#### Required Extensions

* PDO (if using PDO classes).
* MongoDB (if using MongoDB classes).

#### Provides

| File                  | Description                                       |
| --------------------- | ------------------------------------------------- |
| `db/*.php`            | The core DB classes.                              |
| `db/pdo/*.php`        | PDO specific DB classes.                          |
| `db/mongo/*.php`      | MongoDB specific classes.                         |
| `db/schemata/*.php`   | PDO extensions for managing DB schemata.          |

### lum-array (lum.array.php)
### lum-base91 (lum.base91.php)
### lum-browser (lum.browser.php)
### lum-csv (lum.csv.php)
### lum-curl (lum.curl.php)
### lum-currency (lum.currency.php)
### lum-expression (lum.expression.php)
### lum-file (lum.file.php)

#### Required Extensions

* mbstring
* zip (if using getZip() or getZipDir() functions).

#### Recommendations

* lum-csv (used in getDelimited() function).

### lum-flags (lum.flags.php)
### lum-hash (lum.hash.php)
### lum-html (lum.html.php)
### lum-json-patch (lum.json-patch.php)
### lum-json-rpc (lum.json-rpc.php)
### lum-language (lum.language.php)
### lum-mailer (lum.mailer.php)
### lum-notifications (lum.notifications.php)

#### Requirements

* lum-core

### lum-opensrs (lum.opensrs.php)
### lum-simpleauth (lum.simpleauth.php)
### lum-socket (lum.socket.php)

Provides both Socket and Socket\Daemon (replaces SocketDaemon).

### lum-spjs (lum.spjs.php)
### lum-spreadsheet (lum.spreadsheet.php)

#### Requirements

* phpoffice/phpspreadsheet

### lum-test (lum.test.php)

This will replace the `lib/test.php`, and will be redesigned to be more
like the JS version I wrote for Lum.js.

All of the tests in every other package will depend on this.

### lum-text (lum.text.php)
### lum-translation (lum.translation.php)
### lum-ubjson (lum.ubjson.php)
### lum-units (lum.units.php)
### lum-uuid (lum.uuid.php)
### lum-webservice (lum.webservice.php)

#### Dependencies

* lum-core
* guzzle

### lum-xml (lum.xml.php)

Provides both XML and XML\UTF8NCR (replaces UTF8XML).

### lum-zip (lum.zip.php)

#### Required Extensions

* zip

### riml (riml.php)

Moved from \Nano\Utils to \RIML namespace, classes renamed.

Basically all of the Riml prefixes of the current class names would be
removed, as they'd all be in the \RIML namespace now, so the class names
can be shortened.

The old \Nano\Utils\RIML namespace would be renamed \RIML\Compiler.

A couple examples of the renamed classes/traits/etc.

| Type  | Old Name                      | New Name                           |
| ----- | ----------------------------- | ---------------------------------- |
| trait | \Nano\Utils\RimlRouteInfo     | \RIML\RouteInfo                    |
| class | \Nano\Utils\RIML              | \RIML\Document                     |
| class | \Nano\Utils\RIML\HTML         | \RIML\Compiler\HTML                |

I'd probably still keep all of the RIML parser classes in one file since the
`RIML\Document` class would be the entry point for all parsing.

The compilers as before would be in their own files.

#### Required Extensions

* yaml

