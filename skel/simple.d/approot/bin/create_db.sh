#!/bin/sh

mkdir db 
touch db/staging.sqlite

SQ=`which sqlite3 2>/dev/null`
[ $? -eq 1 ] && echo "No sqlite3 binary found, load doc/db-schema/users.sql into db/staging.sqlite manually after installing a binary for SQLite." && exit 1

$SQ db/staging.sqlite '.read doc/db-schema/users.sql'"

