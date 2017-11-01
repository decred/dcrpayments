--TEST--
Test function blake_final() by calling it more than or less than its expected arguments
--CREDITS--
Daniel Correa admin@sinfocol.org
--SKIPIF--
<?php if (!extension_loaded("blake14")) print "skip"; ?>
--FILE--
<?php

$state = blake_init(BLAKE_512);
$raw_output = false;
$extra_arg = '';

var_dump(blake_final($state, $raw_output, $extra_arg));

var_dump(blake_final());

var_dump(blake_final($state));

var_dump(blake_final($state));

?>
--EXPECTF--
Warning: blake_final() expects at most 2 parameters, 3 given in %s on line %d
NULL

Warning: blake_final() expects at least 1 parameter, 0 given in %s on line %d
NULL
string(128) "a8cfbbd73726062df0c6864dda65defe58ef0cc52a5625090fa17601e1eecd1b628e94f396ae402a00acc9eab77b4d4c2e852aaaa25a636d80af3fc7913ef5b8"

Warning: blake_final(): %d is not a valid Blake state resource in %s on line %d
bool(false)
