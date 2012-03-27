.PHONY: DEFAULT clean

DEFAULT:
	cat Makefile

jquery:
	wget http://code.jquery.com/jquery.min.js

jsmin: jsmin.c
	gcc -o jsmin jsmin.c

## Build a minified version using jsmin.
%.min.js : %.js jsmin
	./jsmin < $< > $@

## Build a "compiled" version using Closure Compiler.
%.cc.js : %.js jsc compiler.jar
	./jsc $< 

clean:
	rm -v jsmin

