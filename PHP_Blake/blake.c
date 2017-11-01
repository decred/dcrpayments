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

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php_blake.h"
#include "ext/standard/info.h"
#include "ext/standard/file.h"

static int php_blake_le_hashstate;

#if (PHP_MAJOR_VERSION >= 5)
# define DEFAULT_CONTEXT FG(default_context)
#else
# define DEFAULT_CONTEXT NULL
#endif

/* {{{ arginfo */
ZEND_BEGIN_ARG_INFO_EX(arginfo_blake, 0, 0, 2)
    ZEND_ARG_INFO(0, data)
    ZEND_ARG_INFO(0, type)
    ZEND_ARG_INFO(0, salt)
    ZEND_ARG_INFO(0, raw_output)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_blake_init, 0, 0, 1)
    ZEND_ARG_INFO(0, type)
    ZEND_ARG_INFO(0, salt)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO(arginfo_blake_update, 0)
    ZEND_ARG_INFO(0, state)
    ZEND_ARG_INFO(0, data)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_blake_final, 0, 0, 1)
    ZEND_ARG_INFO(0, state)
    ZEND_ARG_INFO(0, raw_output)
ZEND_END_ARG_INFO()
/* }}} */

/* {{{ blake_functions[]
 */
const zend_function_entry blake_functions[] = {
    PHP_FE(blake,        arginfo_blake)
    PHP_FE(blake_init,   arginfo_blake_init)
    PHP_FE(blake_update, arginfo_blake_update)
    PHP_FE(blake_final,  arginfo_blake_final)
    {NULL, NULL, NULL}
};
/* }}} */

/* {{{ blake_module_entry
 */
zend_module_entry blake_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
    STANDARD_MODULE_HEADER,
#endif
    PHP_BLAKE_EXT_NAME,
    blake_functions,
    PHP_MINIT(blake),
    PHP_MSHUTDOWN(blake),
    NULL,
    NULL,
    PHP_MINFO(blake),
#if ZEND_MODULE_API_NO >= 20010901
    PHP_BLAKE_EXT_VER,
#endif
    STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_BLAKE
ZEND_GET_MODULE(blake)
#endif

/* {{{ Internal Compress32
Compress bit sequence */
static void _php_blake_compress32(php_hash_state *state, const bit_sequence *datablock ) {
    #define ROT32(x,n) (((x) << (32 - n)) | ((x) >> (n)))
    #define ADD32(x,y) ((u32)((x) + (y)))
    #define XOR32(x,y) ((u32)((x) ^ (y)))

    #define G32(a,b,c,d,i) \
    do {\
        v[a] = XOR32(m[sigma[round][i]], c32[sigma[round][i + 1]]) + ADD32(v[a], v[b]);\
        v[d] = ROT32(XOR32(v[d], v[a]), 16);\
        v[c] = ADD32(v[c], v[d]);\
        v[b] = ROT32(XOR32(v[b], v[c]), 12);\
        v[a] = XOR32(m[sigma[round][i + 1]], c32[sigma[round][i]])+ADD32(v[a], v[b]); \
        v[d] = ROT32(XOR32(v[d], v[a]), 8);\
        v[c] = ADD32(v[c], v[d]);\
        v[b] = ROT32(XOR32(v[b], v[c]), 7);\
    } while (0)

    u32 v[16];
    u32 m[16];
    int round;

    /* get message */
    m[ 0] = U8TO32_BE(datablock + 0);
    m[ 1] = U8TO32_BE(datablock + 4);
    m[ 2] = U8TO32_BE(datablock + 8);
    m[ 3] = U8TO32_BE(datablock +12);
    m[ 4] = U8TO32_BE(datablock +16);
    m[ 5] = U8TO32_BE(datablock +20);
    m[ 6] = U8TO32_BE(datablock +24);
    m[ 7] = U8TO32_BE(datablock +28);
    m[ 8] = U8TO32_BE(datablock +32);
    m[ 9] = U8TO32_BE(datablock +36);
    m[10] = U8TO32_BE(datablock +40);
    m[11] = U8TO32_BE(datablock +44);
    m[12] = U8TO32_BE(datablock +48);
    m[13] = U8TO32_BE(datablock +52);
    m[14] = U8TO32_BE(datablock +56);
    m[15] = U8TO32_BE(datablock +60);

    /* initialization */
    v[ 0] = state->h32[0];
    v[ 1] = state->h32[1];
    v[ 2] = state->h32[2];
    v[ 3] = state->h32[3];
    v[ 4] = state->h32[4];
    v[ 5] = state->h32[5];
    v[ 6] = state->h32[6];
    v[ 7] = state->h32[7];
    v[ 8] = state->salt32[0];
    v[ 8] ^= 0x243F6A88;
    v[ 9] = state->salt32[1];
    v[ 9] ^= 0x85A308D3;
    v[10] = state->salt32[2];
    v[10] ^= 0x13198A2E;
    v[11] = state->salt32[3];
    v[11] ^= 0x03707344;
    v[12] =  0xA4093822;
    v[13] =  0x299F31D0;
    v[14] =  0x082EFA98;
    v[15] =  0xEC4E6C89;

    if (state->nullt == 0) {
        v[12] ^= state->t32[0];
        v[13] ^= state->t32[0];
        v[14] ^= state->t32[1];
        v[15] ^= state->t32[1];
    }

    for (round = 0; round < NB_ROUNDS32; ++round) {
        /* column step */
        G32( 0, 4, 8,12, 0);
        G32( 1, 5, 9,13, 2);
        G32( 2, 6,10,14, 4);
        G32( 3, 7,11,15, 6);
        /* diagonal step */
        G32( 3, 4, 9,14,14);
        G32( 2, 7, 8,13,12);
        G32( 0, 5,10,15, 8);
        G32( 1, 6,11,12,10);
    }

    state->h32[0] ^= v[ 0];
    state->h32[1] ^= v[ 1];
    state->h32[2] ^= v[ 2];
    state->h32[3] ^= v[ 3];
    state->h32[4] ^= v[ 4];
    state->h32[5] ^= v[ 5];
    state->h32[6] ^= v[ 6];
    state->h32[7] ^= v[ 7];
    state->h32[0] ^= v[ 8];
    state->h32[1] ^= v[ 9];
    state->h32[2] ^= v[10];
    state->h32[3] ^= v[11];
    state->h32[4] ^= v[12];
    state->h32[5] ^= v[13];
    state->h32[6] ^= v[14];
    state->h32[7] ^= v[15];
    state->h32[0] ^= state->salt32[0];
    state->h32[1] ^= state->salt32[1];
    state->h32[2] ^= state->salt32[2];
    state->h32[3] ^= state->salt32[3];
    state->h32[4] ^= state->salt32[0];
    state->h32[5] ^= state->salt32[1];
    state->h32[6] ^= state->salt32[2];
    state->h32[7] ^= state->salt32[3];
}
/* }}} */

/* {{{ Internal Compress64
Compress bit sequence */
static void _php_blake_compress64(php_hash_state *state, const bit_sequence *datablock) {
    #define ROT64(x,n) (((x) << (64 - n)) | ((x) >> (n)))
    #define ADD64(x,y) ((u64)((x) + (y)))
    #define XOR64(x,y) ((u64)((x) ^ (y)))

    #define G64(a,b,c,d,i)\
    do { \
        v[a] = ADD64(v[a], v[b]) + XOR64(m[sigma[round][i]], c64[sigma[round][i + 1]]);\
        v[d] = ROT64(XOR64(v[d], v[a]), 32);\
        v[c] = ADD64(v[c], v[d]);\
        v[b] = ROT64(XOR64(v[b], v[c]), 25);\
        v[a] = ADD64(v[a], v[b]) + XOR64(m[sigma[round][i + 1]], c64[sigma[round][i]]);\
        v[d] = ROT64(XOR64(v[d], v[a]), 16);\
        v[c] = ADD64(v[c], v[d]);\
        v[b] = ROT64(XOR64(v[b], v[c]), 11);\
    } while (0)

    u64 v[16];
    u64 m[16];
    int round;

    /* get message */
    m[ 0] = U8TO64_BE(datablock +  0);
    m[ 1] = U8TO64_BE(datablock +  8);
    m[ 2] = U8TO64_BE(datablock + 16);
    m[ 3] = U8TO64_BE(datablock + 24);
    m[ 4] = U8TO64_BE(datablock + 32);
    m[ 5] = U8TO64_BE(datablock + 40);
    m[ 6] = U8TO64_BE(datablock + 48);
    m[ 7] = U8TO64_BE(datablock + 56);
    m[ 8] = U8TO64_BE(datablock + 64);
    m[ 9] = U8TO64_BE(datablock + 72);
    m[10] = U8TO64_BE(datablock + 80);
    m[11] = U8TO64_BE(datablock + 88);
    m[12] = U8TO64_BE(datablock + 96);
    m[13] = U8TO64_BE(datablock +104);
    m[14] = U8TO64_BE(datablock +112);
    m[15] = U8TO64_BE(datablock +120);

    /* initialization */
    v[ 0] = state->h64[0];
    v[ 1] = state->h64[1];
    v[ 2] = state->h64[2];
    v[ 3] = state->h64[3];
    v[ 4] = state->h64[4];
    v[ 5] = state->h64[5];
    v[ 6] = state->h64[6];
    v[ 7] = state->h64[7];
    v[ 8] = state->salt64[0];
    v[ 8] ^= L64(0x243F6A8885A308D3);
    v[ 9] = state->salt64[1];
    v[ 9] ^= L64(0x13198A2E03707344);
    v[10] = state->salt64[2];
    v[10] ^= L64(0xA4093822299F31D0);
    v[11] = state->salt64[3];
    v[11] ^= L64(0x082EFA98EC4E6C89);
    v[12] = L64(0x452821E638D01377);
    v[13] = L64(0xBE5466CF34E90C6C);
    v[14] = L64(0xC0AC29B7C97C50DD);
    v[15] = L64(0x3F84D5B5B5470917);

    if (state->nullt == 0) {
        v[12] ^= state->t64[0];
        v[13] ^= state->t64[0];
        v[14] ^= state->t64[1];
        v[15] ^= state->t64[1];
    }

    for (round = 0; round < NB_ROUNDS64; ++round) {
        G64( 0, 4, 8,12, 0);
        G64( 1, 5, 9,13, 2);
        G64( 2, 6,10,14, 4);
        G64( 3, 7,11,15, 6);
        G64( 3, 4, 9,14,14);
        G64( 2, 7, 8,13,12);
        G64( 0, 5,10,15, 8);
        G64( 1, 6,11,12,10);
    }

    state->h64[0] ^= v[ 0];
    state->h64[1] ^= v[ 1];
    state->h64[2] ^= v[ 2];
    state->h64[3] ^= v[ 3];
    state->h64[4] ^= v[ 4];
    state->h64[5] ^= v[ 5];
    state->h64[6] ^= v[ 6];
    state->h64[7] ^= v[ 7];
    state->h64[0] ^= v[ 8];
    state->h64[1] ^= v[ 9];
    state->h64[2] ^= v[10];
    state->h64[3] ^= v[11];
    state->h64[4] ^= v[12];
    state->h64[5] ^= v[13];
    state->h64[6] ^= v[14];
    state->h64[7] ^= v[15];
    state->h64[0] ^= state->salt64[0];
    state->h64[1] ^= state->salt64[1];
    state->h64[2] ^= state->salt64[2];
    state->h64[3] ^= state->salt64[3];
    state->h64[4] ^= state->salt64[0];
    state->h64[5] ^= state->salt64[1];
    state->h64[6] ^= state->salt64[2];
    state->h64[7] ^= state->salt64[3];
}
/* }}} */

/* {{{ Internal Init
Initialize blake hash */
static void _php_blake_init(php_hash_state *state, int hashbitlen) {
    int i;

    if ((hashbitlen == PHP_BLAKE_224) || (hashbitlen == PHP_BLAKE_256)) {
        /* 224- and 256-bit versions (32-bit words) */
        if (hashbitlen == PHP_BLAKE_224)
            memcpy(state->h32, IV224, sizeof(IV224));      
        else 
            memcpy(state->h32, IV256, sizeof(IV256));

        state->t32[0] = 0;
        state->t32[1] = 0;

        for (i = 0; i < 64; ++i)
            state->data32[i] = 0;

        state->salt32[0] = 0;
        state->salt32[1] = 0;
        state->salt32[2] = 0;
        state->salt32[3] = 0;
    } else if ((hashbitlen == PHP_BLAKE_384) || (hashbitlen == PHP_BLAKE_512)) {
        /* 384- and 512-bit versions (64-bit words) */
        if (hashbitlen == PHP_BLAKE_384)
            memcpy(state->h64, IV384, sizeof(IV384));      
        else 
            memcpy(state->h64, IV512, sizeof(IV512));

        state->t64[0] = 0;
        state->t64[1] = 0;

        for(i = 0; i < 64; ++i)
            state->data64[i] = 0;

        state->salt64[0] = 0;
        state->salt64[1] = 0;
        state->salt64[2] = 0;
        state->salt64[3] = 0;
    }

    state->hashbitlen = hashbitlen;
    state->datalen = 0;
    state->init = 1;
    state->nullt = 0;
}
/* }}} */

/* {{{ Internal AddSalt
Add salt to the hash */
static void _php_blake_addsalt(php_hash_state *state, const bit_sequence *salt) {
    if (state->hashbitlen < PHP_BLAKE_384) {
        state->salt32[0] = U8TO32_BE(salt + 0);
        state->salt32[1] = U8TO32_BE(salt + 4);
        state->salt32[2] = U8TO32_BE(salt + 8);
        state->salt32[3] = U8TO32_BE(salt +12);
    } else {
        state->salt64[0] = U8TO64_BE(salt + 0);
        state->salt64[1] = U8TO64_BE(salt + 8);
        state->salt64[2] = U8TO64_BE(salt +16);
        state->salt64[3] = U8TO64_BE(salt +24);
    }
}
/* }}} */

/* {{{ Internal Update32
Update blake hash state */
static void _php_blake_update32(php_hash_state *state, const bit_sequence *data, data_length databitlen) {
    int fill;
    int left; /* to handle data inputs of up to 2^64-1 bits */

    if ((databitlen == 0) && (state->datalen != 512))
        return;

    left = (state->datalen >> 3); 
    fill = 64 - left;

    /* compress remaining data filled with new bits */
    if (left && (((databitlen >> 3) & 0x3F) >= fill)) {
        memcpy((void *)(state->data32 + left), (void *)data, fill );
        /* update counter */
        state->t32[0] += 512;
        if (state->t32[0] == 0)
            state->t32[1]++;

        _php_blake_compress32(state, state->data32);
        data += fill;
        databitlen -= (fill << 3);

        left = 0;
    }

    /* compress data until enough for a block */
    while (databitlen >= 512) {
        /* update counter */
        state->t32[0] += 512;
        if (state->t32[0] == 0)
            state->t32[1]++;
        _php_blake_compress32(state, data);
        data += 64;
        databitlen -= 512;
    }

    if (databitlen > 0) {
        memcpy((void *)(state->data32 + left), (void *)data, databitlen >> 3);
        state->datalen = (left << 3) + databitlen;
        /* when non-8-multiple, add remaining bits (1 to 7)*/
        if (databitlen & 0x7)
            state->data32[left + (databitlen >> 3)] = data[databitlen >> 3];
    }else
        state->datalen=0;
}
/* }}} */

/* {{{ Internal Update64
Update blake hash state */
static void _php_blake_update64(php_hash_state *state, const bit_sequence *data, data_length databitlen) {
    int fill;
    int left;

    if ((databitlen == 0) && (state->datalen != 1024))
        return;

    left = (state->datalen >> 3);
    fill = 128 - left;

    /* compress remaining data filled with new bits */
    if (left && (((databitlen >> 3) & 0x7F) >= fill)) {
        memcpy((void *)(state->data64 + left), (void *)data, fill );
        /* update counter  */
        state->t64[0] += 1024;

        _php_blake_compress64(state, state->data64);
        data += fill;
        databitlen -= (fill << 3); 

        left = 0;
    }

    /* compress data until enough for a block */
    while (databitlen >= 1024) {
        /* update counter */
        state->t64[0] += 1024;
        _php_blake_compress64(state, data);
        data += 128;
        databitlen -= 1024;
    }

    if (databitlen > 0) {
        memcpy((void *)(state->data64 + left), (void *)data, (databitlen >> 3) & 0x7F );
        state->datalen = (left << 3) + databitlen;

        /* when non-8-multiple, add remaining bits (1 to 7)*/
        if (databitlen & 0x7)
            state->data64[left + (databitlen >> 3)] = data[databitlen >> 3];
    } else
        state->datalen=0;
}
/* }}} */

/* {{{ Internal Update
Update blake hash state */
static void _php_blake_update(php_hash_state *state, const bit_sequence *data, data_length databitlen) {
    if (state->hashbitlen < PHP_BLAKE_384)
        _php_blake_update32(state, data, databitlen);
    else
        _php_blake_update64(state, data, databitlen);
}
/* }}} */

/* {{{ Internal Final32
Finalize blake hash */
static void _php_blake_final32(php_hash_state *state, bit_sequence *hashval) {
    bit_sequence msglen[8];
    bit_sequence zz = 0x00, zo = 0x01, oz = 0x80, oo = 0x81;

    /* 
    copy nb. bits hash in total as a 64-bit BE word
    */
    u32 low, high;
    low  = state->t32[0] + state->datalen;
    high = state->t32[1];
    if (low < state->datalen)
        high++;
    U32TO8_BE(msglen + 0, high);
    U32TO8_BE(msglen + 4, low);

    if (state->datalen % 8 == 0) {
        /* message bitlength multiple of 8 */
        if (state->datalen == 440) {
            /* special case of one padding byte */
            state->t32[0] -= 8;
            if (state->hashbitlen == PHP_BLAKE_224)
                _php_blake_update32(state, &oz, 8);
            else
                _php_blake_update32(state, &oo, 8);
        } else {
            if (state->datalen < 440) {
                /* use t=0 if no remaining data */
                if (state->datalen == 0)
                    state->nullt = 1;
                /* enough space to fill the block  */
                state->t32[0] -= 440 - state->datalen;
                _php_blake_update32(state, padding, 440 - state->datalen);
            } else {
                /* NOT enough space, need 2 compressions */
                state->t32[0] -= 512 - state->datalen;
                _php_blake_update32(state, padding, 512 - state->datalen );
                state->t32[0] -= 440;
                _php_blake_update32(state, padding + 1, 440);  /* padd with zeroes */
                state->nullt = 1; /* raise flag to set t=0 at the next compress */
            }
            if (state->hashbitlen == PHP_BLAKE_224) 
                _php_blake_update32(state, &zz, 8);
            else
                _php_blake_update32(state, &zo, 8);
            state->t32[0] -= 8;
        }
        state->t32[0] -= 64;
        _php_blake_update32(state, msglen, 64);    
    } else {
        /* message bitlength NOT multiple of 8 */
        /*  add '1' */
        state->data32[state->datalen / 8] &= (0xFF << (8 - state->datalen % 8)); 
        state->data32[state->datalen / 8] ^= (0x80 >> (state->datalen % 8)); 

        if ((state->datalen > 440) && (state->datalen < 447)) {
            /*  special case of one padding byte */
            if (state->hashbitlen == PHP_BLAKE_224) 
                state->data32[state->datalen / 8] ^= 0x00;
            else
                state->data32[state->datalen / 8] ^= 0x01;
            state->t32[0] -= (8 - (state->datalen % 8));
            /* set datalen to a 8 multiple */
            state->datalen = (state->datalen & (data_length)L64(0xfffffffffffffff8)) + 8;
        } else {
            if (state->datalen < 440) {
                /* enough space to fill the block */
                state->t32[0] -= 440 - state->datalen;
                state->datalen = (state->datalen & (data_length)L64(0xfffffffffffffff8)) + 8;
                _php_blake_update(state, padding + 1, 440 - state->datalen);
            } else {
                if (state->datalen > 504) {
                    /* special case */
                    state->t32[0] -= 512 - state->datalen;
                    state->datalen = 512;
                    _php_blake_update32(state, padding + 1, 0);
                    state->t32[0] -= 440;
                    _php_blake_update32(state, padding + 1, 440);
                    state->nullt = 1; /* raise flag for t=0 at the next compress */
                } else {
                    /* NOT enough space, need 2 compressions */
                    state->t32[0] -= 512 - state->datalen;
                    /* set datalen to a 8 multiple */
                    state->datalen = (state->datalen & (data_length)L64(0xfffffffffffffff8)) + 8;
                    _php_blake_update32(state, padding + 1, 512 - state->datalen);
                    state->t32[0] -= 440;
                    _php_blake_update32(state, padding + 1, 440);
                    state->nullt = 1; /* raise flag for t=0 at the next compress */
                }
            }
            state->t32[0] -= 8;
            if (state->hashbitlen == PHP_BLAKE_224) 
                _php_blake_update32(state, &zz, 8);
            else
                _php_blake_update32(state, &zo, 8);
        }
        state->t32[0] -= 64;
        _php_blake_update32(state, msglen, 64); 
    }

    U32TO8_BE(hashval + 0, state->h32[0]);
    U32TO8_BE(hashval + 4, state->h32[1]);
    U32TO8_BE(hashval + 8, state->h32[2]);
    U32TO8_BE(hashval +12, state->h32[3]);
    U32TO8_BE(hashval +16, state->h32[4]);
    U32TO8_BE(hashval +20, state->h32[5]);
    U32TO8_BE(hashval +24, state->h32[6]);
    if (state->hashbitlen == PHP_BLAKE_256) {
        U32TO8_BE(hashval +28, state->h32[7]);
    }
}
/* }}} */

/* {{{ Internal Final64
Finalize blake hash */
static void _php_blake_final64(php_hash_state *state, bit_sequence *hashval) {
    bit_sequence msglen[16];
    bit_sequence zz = 0x00, zo = 0x01, oz = 0x80, oo = 0x81;

    /* copy nb. bits hash in total as a 128-bit BE word */
    u64 low, high;
    low  = state->t64[0] + state->datalen;
    high = state->t64[1];
    if (low < state->datalen)
        high++;
    U64TO8_BE(msglen + 0, high);
    U64TO8_BE(msglen + 8, low);

    if (state->datalen % 8 == 0) {
        /* message bitlength multiple of 8 */
        if ( state->datalen == 888 ) {
            /* special case of one padding byte */
            state->t64[0] -= 8; 
            if (state->hashbitlen == PHP_BLAKE_384) 
                _php_blake_update64(state, &oz, 8);
            else
                _php_blake_update64(state, &oo, 8);
        } else {
            if (state->datalen < 888) {
                /* use t=0 if no remaining data */
                if (state->datalen == 0) 
                    state->nullt = 1;
                /* enough space to fill the block */
                state->t64[0] -= 888 - state->datalen;
                _php_blake_update64(state, padding, 888 - state->datalen);
            } else {
                /* NOT enough space, need 2 compressions */
                state->t64[0] -= 1024 - state->datalen; 
                _php_blake_update64(state, padding, 1024 - state->datalen);
                state->t64[0] -= 888;
                _php_blake_update64(state, padding + 1, 888);  /* padd with zeros */
                state->nullt = 1; /* raise flag to set t=0 at the next compress */
            }
            if (state->hashbitlen == PHP_BLAKE_384) 
                _php_blake_update64(state, &zz, 8);
            else
                _php_blake_update(state, &zo, 8);
            state->t64[0] -= 8;
        }
        state->t64[0] -= 128;
        _php_blake_update(state, msglen, 128);    
    } else {
        /* message bitlength NOT multiple of 8 */
        /* add '1' */
        state->data64[state->datalen / 8] &= (0xFF << (8 - state->datalen % 8)); 
        state->data64[state->datalen / 8] ^= (0x80 >> (state->datalen % 8)); 

        if ((state->datalen > 888) && (state->datalen < 895)) {
            /*  special case of one padding byte */
            if (state->hashbitlen == PHP_BLAKE_384)
                state->data64[state->datalen / 8] ^= zz;
            else
                state->data64[state->datalen / 8] ^= zo;
            state->t64[0] -= (8 - (state->datalen % 8));
            /* set datalen to a 8 multiple */
            state->datalen = (state->datalen & (data_length)L64(0xfffffffffffffff8)) + 8;
        } else {
            if (state->datalen < 888) {
                /* enough space to fill the block */
                state->t64[0] -= 888 - state->datalen;
                state->datalen = (state->datalen & (data_length)L64(0xfffffffffffffff8)) + 8;
                _php_blake_update64(state, padding + 1, 888 - state->datalen);
            } else {
                if (state->datalen > 1016) {
                    /* special case */
                    state->t64[0] -= 1024 - state->datalen;
                    state->datalen = 1024;
                    _php_blake_update64(state, padding + 1, 0);
                    state->t64[0] -= 888;
                    _php_blake_update64(state, padding + 1, 888);
                    state->nullt = 1; /* raise flag for t=0 at the next compress */
                } else {
                    /* NOT enough space, need 2 compressions */
                    state->t64[0] -= 1024 - state->datalen;
                    /* set datalen to a 8 multiple */
                    state->datalen = (state->datalen & (data_length)L64(0xfffffffffffffff8)) + 8;
                    _php_blake_update64(state, padding + 1, 1024 - state->datalen);
                    state->t64[0] -= 888;
                    _php_blake_update64(state, padding + 1, 888);
                    state->nullt = 1; /* raise flag for t=0 at the next compress */
                }
            }
            state->t64[0] -= 8;
            if (state->hashbitlen == PHP_BLAKE_384) 
                _php_blake_update64(state, &zz, 8);
            else
                _php_blake_update64(state, &zo, 8);
        }
        state->t64[0] -= 128;
        _php_blake_update(state, msglen, 128); 
    }

    U64TO8_BE(hashval + 0, state->h64[0]);
    U64TO8_BE(hashval + 8, state->h64[1]);
    U64TO8_BE(hashval +16, state->h64[2]);
    U64TO8_BE(hashval +24, state->h64[3]);
    U64TO8_BE(hashval +32, state->h64[4]);
    U64TO8_BE(hashval +40, state->h64[5]);
    if (state->hashbitlen == PHP_BLAKE_512) {
        U64TO8_BE( hashval +48, state->h64[6]);
        U64TO8_BE( hashval +56, state->h64[7]);
    }
}
/* }}} */

/* {{{ Internal Final
Finalize blake hash */
static void _php_blake_final(php_hash_state *state, bit_sequence *hashval) {
    if (state->hashbitlen < PHP_BLAKE_384)
        _php_blake_final32(state, hashval);
    else
        _php_blake_final64(state, hashval);
}
/* }}} */

/* {{{ Do the hash!
Handle parameters for blake function */
static void _php_blake_hash(INTERNAL_FUNCTION_PARAMETERS) {
    bit_sequence *data, *salt = NULL, *digest = NULL;
	int data_len, digest_len, salt_len = 0;
    zend_bool raw_output = 0;
    long type = 0;
    php_stream *stream = NULL;
    php_hash_state *state = NULL;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sl|sb", &data, &data_len, &type, &salt, &salt_len, &raw_output) == FAILURE) {
		return;
	}

    //Check the bit length of the constant passed in the second argument of the function
    if (type != PHP_BLAKE_224 && type != PHP_BLAKE_256 && type != PHP_BLAKE_384 && type != PHP_BLAKE_512) {
        php_error(E_WARNING, "Bad Hash-Bit Length");
        RETURN_FALSE;
    }

    //Check salt length
    if (salt_len > 0) {
          if (type < PHP_BLAKE_384) {
            if (salt_len != 16) {
                php_error(E_WARNING, "Salt should be 128-bit (16 bytes)");
                RETURN_FALSE;
            }
          } else {
            if (salt_len != 32) {
                php_error(E_WARNING, "Salt should be 256-bit (32 bytes)");
                RETURN_FALSE;
            }
          }
    }

    //Allocate memory of the blake state and the digest
    state = emalloc(sizeof(php_hash_state));
    digest_len = type / 8;
    digest = emalloc(digest_len + 1);

    //Initialize blake hash and add salt if apply
    _php_blake_init(state, type);
    if (salt_len > 0)
        _php_blake_addsalt(state, salt);

    _php_blake_update(state, data, data_len * 8);

    //Finalize the blake hash and send the result to digest
    _php_blake_final(state, digest);

    digest[digest_len] = 0;
    efree(state);
    if (raw_output) {
        RETURN_STRINGL(digest, digest_len);
    } else {
        char *hex_digest = safe_emalloc(digest_len, 2, 1);
        _php_blake_bin2hex(hex_digest, (bit_sequence*)digest, digest_len);
        hex_digest[2 * digest_len] = 0;
        efree(digest);
        RETURN_STRINGL(hex_digest, 2 * digest_len);
    }
}
/* }}} */

/* {{{ proto string blake(string data, int type[, string salt[, bool raw_output = false]])
Generate blake hash */
PHP_FUNCTION(blake) {
    _php_blake_hash(INTERNAL_FUNCTION_PARAM_PASSTHRU);
}
/* }}} */

/* {{{ proto resource blake_init(int type[, string salt])
Initialize blake hash */
PHP_FUNCTION(blake_init) {
    bit_sequence *salt = NULL;
    int salt_len = 0;
    long type = 0;
    php_hash_state * state = NULL;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "l|s", &type, &salt, &salt_len) == FAILURE) {
        return;
    }

    //Check the bit length of the constant passed in the second argument of the function
    if (type != PHP_BLAKE_224 && type != PHP_BLAKE_256 && type != PHP_BLAKE_384 && type != PHP_BLAKE_512) {
        php_error(E_WARNING, "Bad Hash-Bit Length");
        RETURN_FALSE;
    }

    //Check salt length
    if (salt_len > 0) {
          if (type < PHP_BLAKE_384) {
            if (salt_len != 16) {
                php_error(E_WARNING, "Salt should be 128-bit (16 bytes)");
                RETURN_FALSE;
            }
          } else {
            if (salt_len != 32) {
                php_error(E_WARNING, "Salt should be 256-bit (32 bytes)");
                RETURN_FALSE;
            }
          }
    }

    //Allocate memory of the blake state and the digest
    state = emalloc(sizeof(php_hash_state));

    //Initialize blake hash and add salt if apply
    _php_blake_init(state, type);
    if (salt_len > 0)
        _php_blake_addsalt(state, salt);

    RETURN_RES(zend_register_resource(state, php_blake_le_hashstate));
}
/* }}} */

/* {{{ proto bool blake_update(resource state, string data)
Update blake hash */
PHP_FUNCTION(blake_update) {
    zval *zhash;
    bit_sequence *data;
    int data_len;
    php_hash_state *state = NULL;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rs", &zhash, &data, &data_len) == FAILURE) {
        return;
    }

    if ((state = (php_hash_state *)zend_fetch_resource(Z_RES_P(zhash), PHP_BLAKE_RES_NAME, php_blake_le_hashstate)) == NULL) {
        RETURN_FALSE;
    }

    //Update hash state
    _php_blake_update(state, data, data_len * 8);

    RETURN_TRUE;
}
/* }}} */

/* {{{ proto string blake_final(resource state[, bool raw_output = false])
Finalize blake hash */
PHP_FUNCTION(blake_final) {
    int digest_len;
    zval *zhash;
    zend_bool raw_output = 0;
    zend_resource *le;
    php_hash_state *state = NULL;
    bit_sequence *digest = NULL;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r|b", &zhash, &raw_output) == FAILURE) {
        return;
    }

    if ((state = (php_hash_state *)zend_fetch_resource(Z_RES_P(zhash), PHP_BLAKE_RES_NAME, php_blake_le_hashstate)) == NULL) {
        RETURN_FALSE;
    }

    //Allocate memory for digest
    digest_len = state->hashbitlen / 8;
    digest = emalloc(digest_len + 1);

    //Finalize the blake hash and send the result to digest
    _php_blake_final(state, digest);

    digest[digest_len] = 0;

    /* zend_list_REAL_delete() */
    if (zend_hash_index_find(&EG(regular_list), Z_RES_P(zhash)->handle)==SUCCESS) {
        /* This is a hack to avoid letting the resource hide elsewhere (like in separated vars)
        FETCH_RESOURCE is intelligent enough to handle dealing with any issues this causes */
        //le->refcount = 1;
    } /* FAILURE is not an option */
    zend_list_delete(Z_RES_P(zhash));

    if (raw_output) {
        RETURN_STRINGL(digest, digest_len);
    } else {
        char *hex_digest = safe_emalloc(digest_len, 2, 1);
        _php_blake_bin2hex(hex_digest, (bit_sequence*)digest, digest_len);
        hex_digest[2 * digest_len] = 0;
        efree(digest);
        RETURN_STRINGL(hex_digest, 2 * digest_len);
    }
}
/* }}} */

/* {{{ dtor
*/
static void php_hash_state_dtor(zend_resource *rsrc TSRMLS_DC)
{
	php_hash_state *state = (php_hash_state*)rsrc->ptr;

	if (state) {
        efree(state);
    }
}
/* }}} */

/* {{{ PHP_MINIT_FUNCTION
 */
PHP_MINIT_FUNCTION(blake) {
    php_blake_le_hashstate = zend_register_list_destructors_ex(php_hash_state_dtor, NULL, PHP_BLAKE_RES_NAME, module_number);

    REGISTER_LONG_CONSTANT("BLAKE_224", PHP_BLAKE_224, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("BLAKE_256", PHP_BLAKE_256, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("BLAKE_384", PHP_BLAKE_384, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("BLAKE_512", PHP_BLAKE_512, CONST_CS | CONST_PERSISTENT);

    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION
 */
PHP_MSHUTDOWN_FUNCTION(blake) {
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(blake) {
    unsigned char version[10];
    slprintf(version, 10, "%s", PHP_BLAKE_EXT_VER);

    php_info_print_table_start();
    php_info_print_table_row(2, "blake support", "enabled");
    php_info_print_table_row(2, "Blake Engines", "Blake224, Blake256, Blake384, Blake512");
    php_info_print_table_row(2, "Blake Version", version);
    php_info_print_table_end();
}
/* }}} */
/* The previous line is meant for vim and emacs, so it can correctly fold and 
   unfold functions in source code. See the corresponding marks just before 
   function definition, where the functions purpose is also documented. Please 
   follow this convention for the convenience of others editing your code.
*/


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
