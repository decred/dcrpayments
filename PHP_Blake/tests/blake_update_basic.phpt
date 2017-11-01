--TEST--
Test function blake_update() by calling it with its expected arguments
--CREDITS--
Daniel Correa admin@sinfocol.org
--SKIPIF--
<?php if (!extension_loaded("blake14")) print "skip"; ?>
--FILE--
<?php

$state = blake_init(BLAKE_256);
$data = "\xc0\xff\xee";

var_dump(blake_update($state, $data));

?>
--EXPECT--
bool(true)
