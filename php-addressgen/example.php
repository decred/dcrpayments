<?php
define ('USE_EXT', 'GMP');
if (!extension_loaded("blake14")) {
  die("Blake14 extension is not loaded\n");
}
require_once("bwwc-include-all.php");

$decred_epks = array(
    "zeroes mainnet" => "dpubZJJ4wyb7P1a3rSHvFBghmU2Dj3beY2Zq2uuaJwzdxRCGBK6G7JdYfLqWWSzdS399o6YSxKTPU4niEUffLc1y5AKxpYS3VMqH298ropHUbft",
    "zeroes testnet" => "tpubVs5Ln59WKEasvuDMxwMUrWjPHa6uuTyFZX3hZjnEnab428P4pKbeMbQCVgTHtXP3VEJQayVfzEriGSvFrQisxKPHJgMQErBQRAEYL78Buw2",
);

foreach ($decred_epks as $_ => $epkstr) {
    list($epk, $err) = Bip32ExtendedPubkey::decred_parse_string($epkstr);
    if (!is_null($err)) {
        warn("decred_parse_string failed for '{$epkstr}': {$err}");
        continue;
    }

    for ($i = 0; $i < 20; $i++) {
        $new_dcr_address = BWWC__generate_decred_address_from_bip32_epk($epkstr, $i);
        print "derived address $i is {$new_dcr_address}\n";
    }
}

function fail($s) {
    print "TEST FAIL: " . rtrim($s) . "\n";
}

function fatal($s) {
    print "FATAL: " . rtrim($s) . "\n";
    exit(1);
}

function success($s) {
    print "TEST SUCCESS: " . rtrim($s) . "\n";
}

function warn($s) {
    print "WARN: " . rtrim($s) . "\n";
}
?>
