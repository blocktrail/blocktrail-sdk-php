BlockTrail PHP SDK
==================

This is the BlockTrail PHP SDK. This SDK contains methods for easily interacting with the BlockTrail API.
Below are examples to get you started. For additional examples, please see our official documentation
at https://www.blocktrail.com/api/docs

[![Latest Stable Version](https://poser.pugx.org/blocktrail/blocktrail-sdk-php/v/stable.png)](https://packagist.org/packages/blocktrail/blocktrail-sdk-php)
[![Build Status](https://travis-ci.org/blocktrail/blocktrail-sdk-php.png)](https://travis-ci.org/blocktrail/blocktrail-sdk-php)

Installation
------------
To install the SDK, you will need to be using [Composer](http://getcomposer.org/) in your project.
If you aren't using Composer yet, it's really simple! Here's how to install composer and the BlockTrail PHP SDK.

```PHP
# Install Composer
curl -sS https://getcomposer.org/installer | php

# Add the BlockTrail SDK as a dependency
php composer.phar require blocktrail/blocktrail-sdk-php:~1.0
``` 

Next, require Composer's autoloader, in your application, to automatically load the BlockTrail SDK in your project:
```PHP
require 'vendor/autoload.php';
use BlockTrail\SDK\APIClient;
```

Usage
-----
...

Additional Info
---------------

For usage examples on each API endpoint, head over to our official documentation pages.

Support and Feedback
--------------------

Be sure to visit the BlockTrail API official [documentation website](https://www.blocktrail.com/api/docs)
for additional information about our API.

If you find a bug, please submit the issue in Github directly. 
[BlockTrail-PHP-SDK Issues](https://github.com/blocktrail/blocktrail-sdk-php/issues)

As always, if you need additional assistance, drop us a note at 
[support@blocktrail.com](mailto:support@blocktrail.com).

Community Donations & Contributions
-----------------------------------

This project supports community developers via http://tip4commit.com. If participating, developers will receive a Bitcoin tip for each commit that is merged to the master branch.

Note: Core developers, who receive a tip, will donate those tips back to the project's tip jar. This includes all BlockTrail employees. While BlockTrail sponsors this project, it does not accept or receive any tips.

If you'd like to support the community, add Bitcoins to the tip jar: <address>.

[![tip for next commit](http://tip4commit.com/projects/214.svg)](http://tip4commit.com/projects/214)

Unit Tests and Coding Style
---------------------------
The project follows the PSR2 coding style, which can easily be validated with `./vendor/bin/phpcs --standard=./phpcs.xml -n -a ./src/`.
Unit Tests are created with PHPunit and can be ran with `./vendor/bin/phpunit`
