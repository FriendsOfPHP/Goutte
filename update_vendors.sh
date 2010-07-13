#!/bin/sh

CURRENT=`pwd`/src/vendor

# Symfony2
cd $CURRENT/symfony && git pull

# Zend Framework
cd $CURRENT/zend && git pull
