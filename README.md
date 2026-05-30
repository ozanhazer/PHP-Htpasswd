# PHP-Htpasswd

![Unit Tests](https://github.com/ozanhazer/PHP-Htpasswd/actions/workflows/php.yml/badge.svg)

A lightweight PHP library for reading and writing Apache htpasswd files. You can add or delete users or update their passwords.

## Requirements

- PHP 7.2 or higher (tested on 8.2–8.5)

## Features

- Supports `crypt`, APR-MD5, SHA-1 and bcrypt algorithms
- Locks the htpasswd file to prevent conflicts while writing
- Throws exceptions on invalid usernames
- Unit tested

> **Note:** The entire htpasswd file is loaded into memory. If you have a very large number of users, consider a different authentication mechanism.

## Installation

```bash
composer require ozanhazer/php-htpasswd
```

## Usage

The `Htpasswd` class has no namespace, so it works in both non-namespaced and namespaced projects:

```php
use Htpasswd;

$htpasswd = new Htpasswd('/path/to/.htpasswd');
$htpasswd->addUser('ozan', '123456');
$htpasswd->updateUser('ozan', '654321');
$htpasswd->deleteUser('ozan');
```

### Encryption algorithms

Apache htpasswd supports three password formats. You can specify the algorithm when adding or updating a user:

```php
$htpasswd->addUser('ozan', '123456', Htpasswd::ENCTYPE_APR_MD5);
$htpasswd->addUser('ozan', '123456', Htpasswd::ENCTYPE_SHA1);
$htpasswd->addUser('ozan', '123456', Htpasswd::ENCTYPE_BCRYPT);
$htpasswd->addUser('ozan', '123456', Htpasswd::ENCTYPE_CRYPT);  // default
```

Different users in the same htpasswd file can use different algorithms.

See the [Apache documentation](https://httpd.apache.org/docs/2.4/misc/password_encryptions.html) for details on each format.

## Tips

- Avoid `ENCTYPE_CRYPT` on Windows — it is not available by default.
- `ENCTYPE_CRYPT` passwords are limited to 8 characters; extra characters are silently ignored. The library will trigger a notice if a longer password is provided.
- SHA-1 (`ENCTYPE_SHA1`) is considered weak. APR-MD5 is the most broadly compatible option; bcrypt (`ENCTYPE_BCRYPT`) is the strongest and is recommended for Apache 2.4+.
