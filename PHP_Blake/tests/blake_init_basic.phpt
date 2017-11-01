--TEST--
Test function blake_init() by calling it with its expected arguments
--CREDITS--
Daniel Correa admin@sinfocol.org
--SKIPIF--
<?php if (!extension_loaded("blake14")) print "skip"; ?>
--FILE--
<?php

$type = BLAKE_256;
$salt = '0123456789abcdef';

var_dump(blake_init($type));

var_dump(blake_init($type, $salt));

?>
--EXPECTF--
resource(%d) of type (Blake state)
resource(%d) of type (Blake state)
