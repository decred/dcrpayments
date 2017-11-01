--TEST--
Test function blake() by calling it with its expected arguments
--CREDITS--
Daniel Correa admin@sinfocol.org
--SKIPIF--
<?php if (!extension_loaded("blake14")) print "skip"; ?>
--FILE--
<?php

$data = "\xc0\xff\xee";
$type = BLAKE_224;
$salt = '0123456789abcdef';
$raw_output = false;

var_dump(blake($data, $type));

var_dump(blake($data, $type, $salt));

var_dump(blake($data, $type, $salt, $raw_output));

var_dump(blake($data, $type, ''));

var_dump(blake($data, $type, '', $raw_output));

?>
--EXPECT--
string(56) "80964b0e5b77d2a1aeb4b2b642cc46f3619e4c97abf430109f777498"
string(56) "2470f1382ba4d376f96b71226978c1dab85f8f7eb63c25b0d0f286eb"
string(56) "2470f1382ba4d376f96b71226978c1dab85f8f7eb63c25b0d0f286eb"
string(56) "80964b0e5b77d2a1aeb4b2b642cc46f3619e4c97abf430109f777498"
string(56) "80964b0e5b77d2a1aeb4b2b642cc46f3619e4c97abf430109f777498"
