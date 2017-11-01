--TEST--
Test function blake_final() by calling it with its expected arguments
--CREDITS--
Daniel Correa admin@sinfocol.org
--SKIPIF--
<?php if (!extension_loaded("blake14")) print "skip"; ?>
--FILE--
<?php

$state = blake_init(BLAKE_512);
$raw_output = false;

var_dump(blake_final($state));

?>
--EXPECT--
string(128) "a8cfbbd73726062df0c6864dda65defe58ef0cc52a5625090fa17601e1eecd1b628e94f396ae402a00acc9eab77b4d4c2e852aaaa25a636d80af3fc7913ef5b8"
