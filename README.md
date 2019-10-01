# Nano.php v6

Nano.php has been deprecated. It's replaced by the Lum.php set of libraries.
These are available in Composer, and are handled differently from Nano.php.

If you want to migrate your code, you will need to find the new class name
and update any code refering to the old class. Otherwise, Nano.php v5 still
exists in the `nano5` branch of this repository.

## Lum.php Libraries

* [lum-core](https://github.com/supernovus/lum.core.php)
* [lum-framework](https://github.com/supernovus/lum.framework.php)
* [lum-arrays](https://github.com/supernovus/lum.arrays.php)
* [lum-curl](https://github.com/supernovus/lum.curl.php)
* [lum-currency](https://github.com/supernovus/lum.currency.php)
* [lum-db](https://github.com/supernovus/lum.db.php)
* [lum-encode](https://github.com/supernovus/lum.encode.php)
* [lum-expression](https://github.com/supernovus/lum.expression.php)
* [lum-file](https://github.com/supernovus/lum.file.php)
* [lum-html](https://github.com/supernovus/lum.html.php)
* [lum-json-patch](https://github.com/supernovus/lum.json-patch.php)
* [lum-json-rpc](https://github.com/supernovus/lum.json-rpc.php)
* [lum-mailer](https://github.com/supernovus/lum.mailer.php)
* [lum-opensrs](https://github.com/supernovus/lum.opensrs.php)
* [lum-socket](https://github.com/supernovus/lum.socket.php)
* [lum-spjs](https://github.com/supernovus/lum.spjs.php)
* [lum-spreadsheet](https://github.com/supernovus/lum.spreadsheet.php)
* [lum-test](https://github.com/supernovus/lum.test.php)
* [lum-text](https://github.com/supernovus/lum.text.php)
* [lum-uimsg](https://github.com/supernovus/lum.uimsg.php)
* [lum-units](https://github.com/supernovus/lum.units.php)
* [lum-uuid](https://github.com/supernovus/lum.uuid.php)
* [lum-webservice](https://github.com/supernovus/lum.webservice.php)
* [lum-xml](https://github.com/supernovus/lum.xml.php)

## Changes to the bootstrap process

Nano.php had it's own init.php file that registered the `spl_autoload`
autoloader, and if found, the composer autoloaders. As Lum.php is using
composer by default, the process has changed slightly. Assuming your app
is still using `spl_autoload` style autoloading, here's an example of how
the changes will took place.

### Nano.php with just autoloader

```php
require_once 'lib/nano/init.php';  // Load the bootstrap file.
\Nano\register();                  // Registers spl_autoload in './lib'.
// The rest of your script here.
```

### Lum.php with just autoloader

```php
require_once 'vendor/autoload.php' // Registers Composer autoloaders.
\Lum\Autoload::register();         // Registers spl_autoload in './lib'.
// The rest of your code here.
```

### Nano.php with Nano core object

```php
require_once 'lib/nano/init.php';  // Load the bootstrap file.
$nano = \Nano\initialize();        // Register spl_autoload and create $nano.
// The rest of your code here.
```

### Lum.php with Lum core object

```php
require_once 'vendor/autoload.php';  // Registers Composer autoloaders.
\Lum\Autoload::register();           // Registers spl_autoload in './lib'.
$core = \Lum\Core::getInstance();    // Get or create a $core object.
/// The rest of your code here.
```

## Author

Timothy Totten <2010@totten.ca>

## License

[MIT](https://spdx.org/licenses/MIT.html)

