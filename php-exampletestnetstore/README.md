php-exampletestnetstore
====

This is a simple/basic mock up of a store running on testnet.  It is intended
to demonstrate an end-to-end example of integrating Decred payments.

## Setup

- Install the [PHP_BLAKE](https://github.com/decred/dcrpayments/tree/master/PHP_Blake) module
- Install the [pdo_sqlite](http://php.net/manual/en/ref.pdo-sqlite.php) module
- Follow the method described in the [main README](https://github.com/decred/dcrpayments)
  to generate an extended public key and replace "tpub..." in index.php with it.

## Running

- ```php -S localhost:8000```

## Screenshots

The following series of screenshots demonstrate the full checkout sequence that
a user would see.  Note that Decred does not have the notion of a
"1 confirmation" transaction since each block must be voted on before being
fully verified and attached to the chain.

![Store Navigation](https://i.imgur.com/gW8vdnu.png)
![Checkout - Wait for TX to be seen](https://i.imgur.com/FuCYXbM.png)
![Checkout - Wait for TX to be mined](https://i.imgur.com/zzpWbhQ.png)
![Checkout - Wait for TX to be confirmed](https://i.imgur.com/PrE2u7E.png)
