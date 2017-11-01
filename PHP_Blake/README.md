PHP_Blake
====

This is a PoC-quality development shim for doing 14 round BLAKE-256 in PHP7.

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

# Status

- Tests crash due to the flaky PHP module bits. It does look like this module
can be simplified and look more like https://github.com/strawbrary/php-blake2.
A native PHP implementation would be interesting too.

# History

- Base code is from https://github.com/BlueDragon747/PHP_Blake
- PHP7 and 14 round Decred support implemented by @jolan.
