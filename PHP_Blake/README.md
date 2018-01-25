PHP_Blake
====
PHP_Blake is a PHP7 module implementation of 14 round BLAKE-256 as used by Decred.

# Building

- ```phpize```
- ```./configure```
- ```make```

# Installation

- ```sudo make install```
- ```Load blake.so module in /etc/php/conf.d or similar```

# Tests

```TEST_PHP_EXECUTABLE=/usr/bin/php php run-tests.php -m -v tests/````

```php -r "echo blake('123', BLAKE_256) . PHP_EOL;"```

# History

- Base code is from https://github.com/BlueDragon747/PHP_Blake
- PHP7 support and conversion to 14 rounds implemented by the Decred developers.
