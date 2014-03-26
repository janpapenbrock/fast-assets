fast-assets
===========

[![Build Status](https://travis-ci.org/janpapenbrock/fast-assets.svg)](https://travis-ci.org/janpapenbrock/fast-assets) [![Coverage Status](https://coveralls.io/repos/janpapenbrock/fast-assets/badge.png)](https://coveralls.io/r/janpapenbrock/fast-assets) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/janpapenbrock/fast-assets/badges/quality-score.png?s=dda9c472028f7b38b3a06a3425d4a682b224282f)](https://scrutinizer-ci.com/g/janpapenbrock/fast-assets/)

Magento CSS and JavaScript - the fast way.

Don't slow down your users by having them to load 15 JS and 5 CSS files on their first visit to ye grande Magento shoppe.

This module collects all JS and CSS before delivering the page, merges them, does some minifying magic and - ta da - your user has to make 1 JS and 1 CSS request exactly.

For excellent performance, compiled CSS and JS files are stored in the filesystem. For each controller action a different compiled file is possible, and therefore the association between controller action and filename is stored in cache for a configurable time.

Features
--------

- Multi-store ready
- Multi-server / NFS-share ready (option to use dir on top level instead of inside skin directories)
- Full Page Cache (i.e. Varnish) ready (option to switch between synchronous and asynchronous merge of JS/CSS files)

To do
-----

- Full unit tests
- Option to specify pattern to decide which files should be pulled with cURL and load others from disk via file path
- Option for specifying cache value lifetime
- Option to minify JS/CSS files, probably using https://github.com/tedivm/JShrink (JS) and https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port (CSS)
- Add hooks to cache clearing actions to clear fast-assets cache values
- Option: On asynchronous mode, disallow full page caching when files are not yet merged
