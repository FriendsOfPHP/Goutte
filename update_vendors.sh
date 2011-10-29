#!/bin/sh

COMPONENTS='BrowserKit ClassLoader CssSelector DomCrawler Finder Process'

cd vendor/Symfony/Component
for COMPONENT in $COMPONENTS
do
    cd $COMPONENT && git fetch origin && git reset --hard origin/master && cd ..
done
cd ../../..

cd vendor/zend
git fetch origin && git reset --hard origin/master
cd ../..
