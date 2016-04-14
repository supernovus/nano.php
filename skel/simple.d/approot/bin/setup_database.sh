#!/bin/sh

mkdir db 
touch db/staging.sqlite 
sqlite3 db/staging.sqlite '.read doc/db-schema/users.sql'

