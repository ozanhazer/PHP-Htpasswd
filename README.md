# PHP-Htpasswd

PHP Htpasswd writer for Apache. You can add or delete users, or update their passwords.

## Features

 * Supports `crypt`, `md5` and `sha1` algorithms
 * Locks the htpasswd file to prevent conflicts while writing.
 * Throws an error on invalid usernames.
 * Unit tested.
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

See [the Apache documentation](https://httpd.apache.org/docs/2.2/misc/password_encryptions.html) for encryption details. 

## Tips

* Do not prefer `ENCTYPE_CRYPT` on Windows servers since it's not available on Windows by default.
* `ENCTYPE_CRYPT` passwords are limited to 8 characters and extra characters will be ignored so the library will trigger 
  a notice if long passwords are provided.
