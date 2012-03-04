#!/bin/bash
## Run this from your project folder.

[ -d "db" ] && echo "'db' directory already exists." && exit 1
[ $# -lt 1] && echo "usage: $0 <web server group>" && exit 1

mkdir db
touch db/database.sqlite

chmod 0770 db
chmod 0660 db/database.sqlite

sudo chgrp -R $1 db
