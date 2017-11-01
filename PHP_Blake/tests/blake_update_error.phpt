--TEST--
Test function blake_update() by calling it more than or less than its expected arguments
--CREDITS--
Daniel Correa admin@sinfocol.org
--SKIPIF--
<?php if (!extension_loaded("blake14")) print "skip"; ?>
--FILE--
<?php

$state = blake_init(BLAKE_256);
$data = "\xc0\xff\xee";
$extra_arg = '';

var_dump(blake_update($state, $data, $extra_arg));

var_dump(blake_update($state));

?>
--EXPECTF--
Warning: blake_update() expects exactly 2 parameters, 3 given in %s on line %d
NULL

Warning: blake_update() expects exactly 2 parameters, 1 given in %s on line %d
NULL
