php-addressgen
====
php-addressgen is a library for generating Decred addresses.

## Setup

Requires:

- [PHP_Blake](https://github.com/decred/dcrpayments/tree/master/PHP_Blake) module
- [GMP](http://php.net/manual/en/book.gmp.php) module

There is a simple example that derives addresses from extended public keys:

- php example.php

## History

- Base code is from [bitcoin-payments-for-woocommerce](https://github.com/gesman/bitcoin-payments-for-woocommerce).
- BIP32 support added by the Decred developers.  [Diff](https://gist.github.com/davecgh/8feff6b5ecd66736f96ea010056f06b5)
- Conversion to BLAKE-256 performed by the Decred developers.
