--TEST--
Test function blake_init() by calling it more than or less than its expected arguments
--CREDITS--
Daniel Correa admin@sinfocol.org
--SKIPIF--
<?php if (!extension_loaded("blake14")) print "skip"; ?>
--FILE--
<?php

$type = BLAKE_256;
$salt = '0123456789abcdef';
$extra_arg = '';
$non_existent_constant = 123;
$wrong_salt = 'abcdef';

var_dump(blake_init($type, $salt, $extra_arg));

var_dump(blake_init());

var_dump(blake_init($non_existent_constant));

var_dump(blake_init(BLAKE_224, $wrong_salt));

var_dump(blake_init(BLAKE_256, $wrong_salt));

var_dump(blake_init(BLAKE_384, $wrong_salt));

var_dump(blake_init(BLAKE_512, $wrong_salt));

?>
--EXPECTF--
Warning: blake_init() expects at most 2 parameters, 3 given in %s on line %d
NULL

Warning: blake_init() expects at least 1 parameter, 0 given in %s on line %d
NULL

Warning: Bad Hash-Bit Length in %s on line %d
bool(false)

Warning: Salt should be 128-bit (16 bytes) in %s on line %d
bool(false)

Warning: Salt should be 128-bit (16 bytes) in %s on line %d
bool(false)

Warning: Salt should be 256-bit (32 bytes) in %s on line %d
bool(false)

Warning: Salt should be 256-bit (32 bytes) in %s on line %d
bool(false)
