#!/bin/bash

[ $# -lt 1 ] && echo "usage: $0 <AppName>" && exit 1

APPNAME=$1
APPDIR=`echo $APPNAME | tr '[A-Z]' '[a-z]'`

[ -d "$APPDIR" ] && echo "$APPDIR already exists." && exit 1

NANOBIN=`dirname $0`
NANODIR=`dirname $NANOBIN`
NANOLIB="$NANODIR/lib"
NANOSRC="$NANODIR/skel/app"

rsync -av $NANOSRC/ $APPDIR/
mkdir -p $APPDIR/bin
cp -v $NANOBIN/adduser.php $APPDIR/bin
cp -v $NANOBIN/make-sqlite.sh $APPDIR/bin
chmod +x $APPDIR/bin/*
ln -sv $NANOLIB/nano4 $APPDIR/lib/nano4
mv $APPDIR/lib/exampleapp $APPDIR/lib/$APPDIR
find $APPDIR -name '*.php' | xargs perl -pi -e "s/ExampleApp/$APPNAME/g"

echo "New $APPNAME application set up in $APPDIR"

