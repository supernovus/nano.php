# Nano.php v6

Nano.php has been deprecated. It's replaced by the Lum.php set of libraries.
These are available in Composer, and are handled differently from Nano.php.

If you want to migrate your code, you will need to find the new class name
and update any code refering to the old class. Otherwise, Nano.php v5 still
exists in the `nano5` branch of this repository.

## Lum.php Libraries

| Library | Description |
| ------- | ----------- |
| [lum-arrays](https://github.com/supernovus/lum.arrays.php) | Some simple array utility functions. |
| lum-cli | Coming soon. |
| [lum-compat](https://github.com/supernovus/lum.compat.php) | The foundational package that all other `lum` packages depend upon. |
| lum-conf | Coming soon. |
| [lum-core](https://github.com/supernovus/lum.core.php) | The `Core` object is a module loader (controllers, models, views, components, etc.) and can be extended with many useful plugins. |
| [lum-curl](https://github.com/supernovus/lum.curl.php) | A wrapper around the PHP `curl` extension. | 
| [lum-currency](https://github.com/supernovus/lum.currency.php) | Currency conversion and formatting utilities. |
| lum-data | Coming soon. |
| [lum-db](https://github.com/supernovus/lum.db.php) | Simple database libraries. Supports `PDO` and `mongodb` extensions. |
| [lum-encode](https://github.com/supernovus/lum.encode.php) | Simple encoding/decoding libraries. Includes `Base91`, `UBJSON`, and `Safe64`. |
| [lum-expression](https://github.com/supernovus/lum.expression.php) | Minimalistic expression parser library. |
| [lum-file](https://github.com/supernovus/lum.file.php) | File utilities. |
| [lum-framework](https://github.com/supernovus/lum.framework.php) | A _nano-sized_ app framework using a bunch of the other `lum` libraries. Provides base classes for a wide variety of Controllers, a few common Models, and a very simplistic Auth system. |
| [lum-html](https://github.com/supernovus/lum.html.php) | HTML utilities. |
| [lum-json-rpc](https://github.com/supernovus/lum.json-rpc.php) | A limited, but functional JSON-RPC implementation. |
| [lum-mailer](https://github.com/supernovus/lum.mailer.php) | A modular mail system. Currently in a state of limbo and requiring a full rewrite. |
| lum-meta | Coming soon. |
| [lum-opensrs](https://github.com/supernovus/lum.opensrs.php) | Libraries for working with OpenSRS's reseller APIs. |
| lum-router | Coming soon. |
| [lum-socket](https://github.com/supernovus/lum.socket.php) | Really old Client and Server classes using sockets. Not recommended. Use Ratchet or React instead. |
| [lum-spjs](https://github.com/supernovus/lum.spjs.php) | An old, extremely limited data manipulation engine. |
| [lum-spreadsheet](https://github.com/supernovus/lum.spreadsheet.php) | A library for working with spreadsheets (both reading existing ones, and creating new ones.) |
| [lum-test](https://github.com/supernovus/lum.test.php) | A TAP-based testing library and harness. Inspired by `Perl` and `Raku` which were the languages I used before moving to PHP. |
| [lum-text](https://github.com/supernovus/lum.text.php) | Text manipulation libraries. |
| [lum-uimsg](https://github.com/supernovus/lum.uimsg.php) | UI messages; has simplistic translation strings, and a notification system that can integrate with [lum.js](https://github.com/supernovus/lum.js) or stand on its own. |
| [lum-units](https://github.mcom/supernovus/lum.units.php) | Common unit conversion and formatting utilities. |
| [lum-uuid](https://github.com/supernovus/lum.uuid.php) | A pure-PHP UUID library. |
| lum-web | Coming soon. |
| [lum-webservice](https://github.com/supernovus/lum.webservice.php) | A client library for web-services. Designed to work along side my [lum.js](https://github.com/supernovus/lum.js) and [lum.kt](https://github.com/supernovus/android-libs) libraries. |
| [lum-xml](https://github.com/supernovus/lum.xml.php) | Simple XML utilities. Depends on my fork of [SimpleDOM](https://github.com/supernovus/simpledom). |

## Migration Script

A migration from Nano.php to Lum.php is cannot be fully automated, however
a great deal of it can be assisted using the `nano2lum.php` script included
in this repo.

I hope I have support for all of the new classes in it. I'm still testing
it, and will update it if it's missing anything.

In addition to searching for changes to your PHP files, it can also look
for references to the old Nano.js and replace them with the new Lum.js names.

### Examples

These example are non-destructive, they won't actually change the files it
will simply make a report of what files WOULD be changed if you added
the `-C` option to the command line.

```
php nano2lum.php -d /path/to/your/app -e vendor -p php > report.yaml
```

The output file would have a list of all files that would be modified,
as well as a list of any Lum libraries you'd need to add to your `composer.json`
file, and a list of any _bootstrap_ files (i.e. files that used to load the
`lib/nano/init.php` which will need to be manually updated in many cases.)

You can make the report more verbose by adding `-v` to the command line.
You can make it _really_ verbose by adding `-vv` to the command line.

Making a report for your JS files is just as simple:

```
php nano2lum.php -d /path/to/your/app -e node_modules -j js > report.yaml
```

The report would be very similar to the PHP one, except there is no
library usage or _bootstrap_ information for the JS classes.

You could combine the two into a single call:

```
php nano2lum.php -d /path/to/your/app -e vendor -e node_modules -p php -j js > report.yaml
```

For additional features of the `nano2lum.php` script, run it without any 
parameters and it will display it's built-in usage information.

Also if you don't have the PHP `yaml` extension installed, it will use
JSON output instead of Yaml. You can force JSON output by passing `-J`.
I think for these reports Yaml is easier to read which is why it's the default.

For any of the above, if you add the `-C` command the script will actually
change each of the files. It __does not make backups__, as I'm assuming you are
using a version control system and would be smart enough to only run this
script on your codebase in a development branch with all local changes 
committed already. Be careful when running any kind of script that can change 
your codebase. Make sure your work is committed first!

## Author

Timothy Totten <2010@totten.ca>

## License

[MIT](https://spdx.org/licenses/MIT.html)

