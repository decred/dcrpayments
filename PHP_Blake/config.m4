dnl $Id$
dnl config.m4 for extension blake
dnl

PHP_ARG_ENABLE(blake, whether to enable blake support,
[  --enable-blake           Enable blake support])

if test "$PHP_BLAKE" != "no"; then
    PHP_NEW_EXTENSION(blake, blake.c, $ext_shared)
fi
