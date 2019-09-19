# Nano.php v5

## Notice for v6

I am working on the plans for v6 of this library set, to be renamed Lum.php
and split into a set of Composer libraries. See the TODO.md for details.

## Notice for v5

Some things were never finished in this version.

In particular, the bin/initapp.php and anything in skel/ are not updated to the
new libraries yet. Don't use them.

## Summary

Nano is a PHP toolkit for building web application frameworks.

The core library is a small object that loads plugins which give it
different abilities as required by your application needs.

Several extendable abstract classes for controllers and models are included, 
as well as a flexible routing plugin, configuration plugin, and a set of
simple helper libraries providing some useful functions for your applications.

## Requirements

* A Unix-based operating system, or compatibility layer.
* PHP 7.0 or higher.
* A bourne compatible shell such as Bash for the .sh scripts in 'bin/'.
* The Perl 5 'prove' utility for tests in 't'.
* The 'rsync', 'find', 'xargs', and 'perl' utilities for 'bin/initapp.php'.
* The phpDocumentor application if you want to build the API documents.

## Usage

Right now this collection of libraries is mostly used by myself for my own 
projects. I am working on a friendly script that will use templates to build
skeleton applications that you can fill out from there, but it's not yet
finished. Feel free to browse the source for anything useful.

Many parts of this library set are designed to work with the
[Nano.js](https://github.com/supernovus/nano.js) libraries for client side
scripting. In the future further Nano themed library sets may be released.

## Changes

Version 5 takes the foundation from version 4, and builds upon it.
The namespace has become \Nano\ as going forward I don't want to have
version specific namespaces. A few of the changes from v4 to v5:

 * Optimized Web Service experience.
 * Removed any unusued libraries and plugins.
 * Cleaned up database libraries.
 * Changed controller foundation to be more streamlined.
 * Completely overhauled authentication/authorization related code.

## Author

Timothy Totten <2010@totten.ca>

## License

[MIT](https://spdx.org/licenses/MIT.html)

