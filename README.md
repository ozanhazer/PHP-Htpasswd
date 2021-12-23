# PHP-Htpasswd

PHP Htpasswd writer for Apache. You can add or delete users, or update their passwords.

## Features

 * Supports `crypt`, `md5` and `sha1` algorithms
 * Locks the htpasswd file to prevent conflicts while writing.
 * Throws an error on invalid usernames.
 * Tested on Windows and debian (apache only)
 * Whole htpasswd file is read into the memory so be careful if you have lots of users
(In fact you should consider a different kind of authentication mechanism if you
have that many users)

## Usage

Install: `composer require ozanhazer/php-htpasswd`

```php
$htpasswd = new Htpasswd('.htpasswd');
$htpasswd->addUser('ozan', '123456');
$htpasswd->updateUser('ozan', '654321');
$htpasswd->deleteUser('ozan');
```

Apache htpasswd can be encrypted in three ways: crypt (unix only), a modified version of md5 and sha1.
You can define the encryption method when you're setting the password:
```php
$htpasswd->addUser('ozan', '123456', Htpasswd::ENCTYPE_APR_MD5);
$htpasswd->addUser('ozan', '123456', Htpasswd::ENCTYPE_SHA1);
```

(Yes, you may use different algorithms per user in the same passwd file...)

**Make sure that you use either ```ENCTYPE_APR_MD5``` or ```ENCTYPE_SHA1``` on Windows servers as "crypt" is not available on Windows.**

See [the Apache documentation](https://httpd.apache.org/docs/2.2/misc/password_encryptions.html) for encryption details. 
