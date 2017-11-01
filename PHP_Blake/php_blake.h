/*
  +----------------------------------------------------------------------+
  | PHP Version 5                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2010 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.01 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_01.txt                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Author: Daniel Correa <admin@sinfocol.org>                           |
  +----------------------------------------------------------------------+
*/

/* $Id: header 297205 2010-03-30 21:09:07Z johannes $ */

#ifndef PHP_BLAKE_H
#define PHP_BLAKE_H

#include "php.h"
#include "php_blake_types.h"
#include "php_blake_opt32.h"

#define PHP_BLAKE_EXT_NAME   "blake14"
#define PHP_BLAKE_EXT_VER    "1.0.0"
#define PHP_BLAKE_RES_NAME   "Blake state"

extern zend_module_entry blake_module_entry;
#define phpext_blake_ptr &blake_module_entry

#ifdef PHP_WIN32
#	define PHP_BLAKE_API __declspec(dllexport)
#elif defined(__GNUC__) && __GNUC__ >= 4
#	define PHP_BLAKE_API __attribute__ ((visibility("default")))
#else
#	define PHP_BLAKE_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

/*PHP functions definitions */
PHP_MINIT_FUNCTION(blake);
PHP_MSHUTDOWN_FUNCTION(blake);
PHP_MINFO_FUNCTION(blake);
PHP_FUNCTION(blake);
PHP_FUNCTION(blake_file);
PHP_FUNCTION(blake_init);
PHP_FUNCTION(blake_update);
PHP_FUNCTION(blake_final);

#ifdef ZTS
#define BLAKE_G(v) TSRMG(blake_globals_id, zend_blake_globals *, v)
#else
#define BLAKE_G(v) (blake_globals.v)
#endif

static inline void _php_blake_bin2hex(char *out, const unsigned char *in, int in_len) {
    static const char hexits[17] = "0123456789abcdef";
    int i;

    for (i = 0; i < in_len; i++) {
        out[i * 2]       = hexits[in[i] >> 4];
        out[(i * 2) + 1] = hexits[in[i] &  0x0F];
    }
}

#endif	/* PHP_BLAKE_H */


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
