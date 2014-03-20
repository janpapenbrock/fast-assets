fast-assets
===========

Magento CSS and JavaScript - the fast way.

Features
--------

- Option to use dir on top level instead of inside skin directories
- Option to switch between synchronous and asynchronous merge of JS/CSS files


To do
-----

- Full unit tests
- Option to minify JS/CSS files, probably using https://github.com/tedivm/JShrink (JS) and https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port (CSS)
- Add hooks to cache clearing actions to clear fast-assets cache values
- Option: On asynchronous mode, disallow full page caching when files are not yet merged
