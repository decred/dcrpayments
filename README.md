dcrpayments
====

dcrpayments is a mono repository that houses various libraries/utilities that
are useful for integrating a Decred payment gateway.

## Status

The Go stack is considered production quality.  The PHP stack is currently
undergoing clean up and refactoring and is nearing production quality as well.
If you are developing a custom integration, testing may be performed on the
Decred test network by using the [testnet faucet](https://faucet.decred.org).

## Go Project Listing

- [dcrpayd](https://github.com/decred/dcrpayments/tree/master/dcrpayd) -
  Microservice for serving addresses and price quotes.
- [util](https://github.com/decred/dcrpayments/tree/master/util) - Utility
library for deriving payment addresses and checking block explorers for
completion of payments.

## PHP Project Listing

- [decred-php](https://github.com/R3VoLuT1OneR/decred-php) - [WIP] Production
  quality library and tools for working with Decred.
- [php-exampletestnetstore](https://github.com/decred/dcrpayments/tree/master/php-exampletestnetstore) - End-to-end example of creating an order, presenting a Decred address for payment, and checking block
explorers to check whether a transaction which satisfies the payment requirement
comes in.

The following are deprecated in favor of the aforementioned decred-php and will
be removed soon:

- [php-addressgen](https://github.com/decred/dcrpayments/tree/master/php-addressgen) -
[BIP-0032](https://github.com/bitcoin/bips/blob/master/bip-0032.mediawiki) address generation libary with Bitcoin/Decred support
- [PHP_Blake](https://github.com/decred/dcrpayments/tree/master/PHP_Blake) - PHP
  module that adds a 14 round BLAKE-256 hash function used by php-addressgen

## General Concepts/Setup

The best way to begin is to see the [Getting Started](https://docs.decred.org/getting-started/beginner-guide/)
guide and follow the "Command-Line Path" and "Testnet guide" instructions so you
have dcrd/dcrwallet running on locally on testnet.  From there, create an
account to test receiving payments:

```bash no-highlight
$ dcrctl --wallet createnewaccount dcrpayments
$ dcrctl --wallet getmasterpubkey dcrpayments
```

You should see a long string starting with **tpub** which represents the master
public key for this account returned as output.  This is used by dcrpayd and
the example testnetstore to deterministically derive payment addresses from the
account.  This lets the software use i.e. address #1 for order #1, address #2
for order #2, and so on to prevent customers or competitors from seeing how many
items have been sold and whatnot.  If master public key is ever obtained by a
third party, they will **not** be able to spend your funds, however they will be
able to see the addresses/balance of this account.  For this reason, it is best
to have the "real" wallet disconnected from the actual payment infrastructure.

One quirk is that the wallet under normal operation for personal activity will
use a [gap](https://github.com/bitcoin/bips/blob/master/bip-0044.mediawiki)
between addresses.  Gaps are not desired for e-commerce at this time so we can
simply tell the wallet to watch the first 10,000 addresses.

```bash no-highlight
dcrctl --wallet accountsyncaddressindex dcrpayments 0 10000
```

This step will need to be performed again if the wallet is ever restored from
[seed](https://docs.decred.org/faq/wallets-and-seeds/).  At this point, you
should be able to accept up to 10,000 orders and can simply extend the address
index if you surpass 10,000 orders.

## Future Work

Development will begin soon to provide integrations with popular/common
e-commerce platforms such as
Shopify, WooCommerce, Magento, WordPress E-Commerce, OScommerce,
OpenCart, PrestaShop, XCart, Commerce:SEO, Gravity Forms, Zen Cart,
Spree Commerce, Ubercart, Ecwid, Drupal Commerce, Membership Pro, Virtue Mart,
etc.

## Contact

Feel free to come join us on any of the other platforms listed on the
[Decred Community](https://decred.org/community/) page.  The development or
payments_integration channels are the best place for discussion.

## Issue Tracker

The
[integrated github issue tracker](https://github.com/decred/dcrpayments/issues)
is used for this project.
