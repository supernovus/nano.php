# TODO

## v5

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

My current proposal for v6 is what I call the Great Divide.

The idea behind Nano.php was to be an extremely small framework for building
PHP apps. The problem is, it's greatly expanded from that mandate into
a collection of libraries (similar to Nano.js, which may also get split up
going forward). It's become a behemoth of things that should probably be
their own standalone libraries.

I'm going to work on getting the libraries to work with Composer, and then
I will provide Composer package definitions for each of them.

Everything currently in the \Nano\Utils sub-namespace would be split off
into new packages.

With that in mind, my current list of proposed packages:

### nanophp-core

Depends on:

* nanophp-file

| File                  | Description                                       |
| --------------------- | ------------------------------------------------- |
| `init.php`            | The bootstrap library, registers the autoloader.  |
| `core.php`            | Provides \Nano\Core object (replaces Nano\Nano).  |
| `plugins/*.php`       | The core plugins.                                 |
| `loader/*.php`        | The loader plugins.                               |
| `pragmas/*.php`       | The pragma plugins.                               |
| `meta/*.php`          | Metadata Traits.                                  |
| `data/*.php`          | The Data object classes.                          |

### nanophp-framework

Depends on:

* nanophp-core
* nanophp-simpleauth
* nanophp-translation
* nanophp-html
* nanophp-language
* nanophp-mailer
* nanophp-notifications

Recommends:

* nanophp-db

| File                  | Description                                       |
| --------------------- | ------------------------------------------------- |
| `controllers/*.php`   | Base classes for controllers.                     |
| `models/*.php`        | Base classes for certain models.                  |

### nanophp-db

| File                  | Description                                       |
| --------------------- | ------------------------------------------------- |
| `db/*.php`            | The core DB classes.                              |
| `db/pdo/*.php`        | PDO specific DB classes.                          |
| `db/mongo/*.php`      | MongoDB specific classes.                         |
| `db/schemata/*.php`   | PDO extensions for managing DB schemata.          |

### nanophp-array
### nanophp-base91
### nanophp-browser
### nanophp-csv
### nanophp-curl
### nanophp-currency
### nanophp-expression
### nanophp-file
### nanophp-flags
### nanophp-hash
### nanophp-html
### nanophp-json-patch
### nanophp-json-rpc
### nanophp-language
### nanophp-mailer
### nanophp-notifications
### nanophp-opensrs
### nanophp-simpleauth
### nanophp-socket (provides both Socket and Socket\Daemon)
### nanophp-spjs
### nanophp-spreadsheet
### nanophp-text
### nanophp-translation
### nanophp-ubjson
### nanophp-units
### nanophp-uuid
### nanophp-webservice
### nanophp-xml (Provides both XML and XML\UTF8NCR)
### nanophp-zip 
### riml.php (Moved from \Nano\Utils to \RIML namespace, classes renamed.)

## Further thoughts

I may want to rename the Nano.php and Nano.js sets to something else, as
I've found the names NanoPHP and NanoJS are used for other projects (a few
each actually, seems a popular name these days.)

It would be a painful process renaming them which makes me hesitant, but at
the same time, having so many libraries out there with similar names is pretty
confusing.

