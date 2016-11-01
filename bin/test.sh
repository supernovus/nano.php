#!/bin/bash

if [ -z "$1" ]; then

REQUEST_METHOD="GET" REQUEST_URI="/" prove -e php

else

REQUEST_METHOD="GET" REQUEST_URI="/" php $1

fi

