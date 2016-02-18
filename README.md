BlockTrail PHP SDK
==================
This is the BlockTrail PHP SDK. This SDK contains methods for easily interacting with the BlockTrail API.
Below are examples to get you started. For additional examples, please see our official documentation
at https://www.blocktrail.com/api/docs/lang/php


[![Latest Stable Version](https://poser.pugx.org/blocktrail/blocktrail-sdk/v/stable.svg)](https://packagist.org/packages/blocktrail/blocktrail-sdk)
[![Latest Unstable Version](https://poser.pugx.org/blocktrail/blocktrail-sdk/v/unstable.svg)](https://packagist.org/packages/blocktrail/blocktrail-sdk)
[![License](https://poser.pugx.org/blocktrail/blocktrail-sdk/license.svg)](https://packagist.org/packages/blocktrail/blocktrail-sdk)

[![Build Status](https://travis-ci.org/blocktrail/blocktrail-sdk-php.svg?branch=master)](https://travis-ci.org/blocktrail/blocktrail-sdk-php)

Upgrading from v1.2.x to v1.3.0
-----------------------------
**IMPORTANT** `v1.3.0` adds the option to choose a fee strategy and **by default chooses DYNAMIC**, please check [docs/CHANGELOG.md](docs/CHANGELOG.md) for the details!!

IMPORTANT! FLOATS ARE EVIL!!
----------------------------
As is best practice with financial data, The API returns all values as an integer, the Bitcoin value in Satoshi's.
**In PHP even more than in other languages it's really easy to make mistakes whem converting from float to integer etc!**

When doing so it's really important that you use the `bcmath` or `gmp` libraries to avoid weird rounding errors!
The BlockTrail SDK has some easy to use functions to do this for you, we recommend using these
and we also **strongly** recommend doing all Bitcoin calculation and storing of data in integers
and only convert to/from Bitcoin float values for displaying it to the user.

```php
use Blocktrail\SDK\BlocktrailSDK;

echo "123456789 Satoshi to BTC: " . BlocktrailSDK::toBTC(123456789) . " \n";
echo "1.23456789 BTC to Satoshi: " . BlocktrailSDK::toSatoshi(1.23456789) . " \n";

```

A bit more about this can be found [in our documentation](https://www.blocktrail.com/api/docs/lang/php#api_coin_format).

Requirements
------------
The SDK requires PHP 5.4+ and the Intl, GMP, BCMath and MCrypt PHP extensions.  
To install these on Ubuntu use:
```
sudo apt-get install php5-bcmath php5-intl php5-gmp php5-mcrypt
sudo php5enmod mcrypt
```
*BCMath should already be part of the default php5 package*

***On Windows*** you need to uncomment the extensions in your `php.ini` if they are not already enabled:
```
extension=php_intl.dll  
extension=php_gmp.dll  
```

Installation
------------
To install the SDK, you will need to be using [Composer](http://getcomposer.org/) in your project.
If you aren't using Composer yet, it's really simple! Here's how to install composer and the BlockTrail PHP SDK.

```
# Install Composer
curl -sS https://getcomposer.org/installer | php

# Add the BlockTrail SDK as a dependency
php composer.phar require blocktrail/blocktrail-sdk
``` 

Next, require Composer's autoloader, in your application, to automatically load the BlockTrail SDK in your project:
```PHP
require 'vendor/autoload.php';
use Blocktrail\SDK\BlocktrailSDK;
```

Or if put the following in your `composer.json`:
```
"blocktrail/blocktrail-sdk": "1.2.*"
```


***Windows Developers***  
A note for windows developers: you may encounter an issue in php with cURL and SSL certificates, where cURL is unable to verify a server's cert with a CA ((error 60)[http://curl.haxx.se/libcurl/c/libcurl-errors.html]).  
Too often the suggested solution is to disable ssl cert verification in cURL, but this completely defeats the point of using SSL. Instead you should take two very simple steps to solve the issue permanently:  

1. download `cacert.pem` from the [curl website](http://curl.haxx.se/docs/caextract.html). This is a bundle of trusted CA root certs extracted from mozilla.org. Save it in a folder within your php installation.  
2. open your `php.ini` and add/edit the following line (use an absolute path to where you placed the cert bundle):  
  ```
  curl.cainfo = C:\php\certs\cacert.pem
  ```


Usage
-----
Please visit our official documentation at https://www.blocktrail.com/api/docs/lang/php for the usage.

Support and Feedback
--------------------
Be sure to visit the BlockTrail API official [documentation website](https://www.blocktrail.com/api/docs/lang/php)
for additional information about our API.

If you find a bug, please submit the issue in Github directly. 
[BlockTrail-PHP-SDK Issues](https://github.com/blocktrail/blocktrail-sdk-php/issues)

If you need additional assistance, contact one of our developers at [devs@blocktrail.com](mailto:devs@blocktrail.com).

Unit Tests and Coding Style
---------------------------
The project follows the PSR2 coding style, which can easily be validated with `./vendor/bin/phpcs --standard=./phpcs.xml -n -a ./src/`.
Unit Tests are created with PHPunit and can be ran with `./vendor/bin/phpunit`

License
-------
The BlockTrail PHP SDK is released under the terms of the MIT license. See LICENCE.md for more information or see http://opensource.org/licenses/MIT.
