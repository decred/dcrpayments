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
#ifndef PHP_BLAKE_OPT32_H
#define PHP_BLAKE_OPT32_H

#define NB_ROUNDS32 14
#define NB_ROUNDS64 16
#define PHP_BLAKE_224 224
#define PHP_BLAKE_256 256
#define PHP_BLAKE_384 384
#define PHP_BLAKE_512 512

/*
  type for raw data
*/
typedef unsigned char bit_sequence; 

/* 
  64-bit word 
*/
typedef u32 data_length; 

/*
  byte-to-word conversion and vice-versa (little endian)  
*/
#define U8TO32_BE(p) \
  (((u32)((p)[0]) << 24) | \
   ((u32)((p)[1]) << 16) | \
   ((u32)((p)[2]) <<  8) | \
   ((u32)((p)[3])      ))

#define U8TO64_BE(p) \
  (((u64)U8TO32_BE(p) << 32) | (u64)U8TO32_BE((p) + 4))

#define U32TO8_BE(p, v) \
  do { \
    (p)[0] = (bit_sequence)((v) >> 24);  \
    (p)[1] = (bit_sequence)((v) >> 16); \
    (p)[2] = (bit_sequence)((v) >>  8); \
    (p)[3] = (bit_sequence)((v)      ); \
  } while (0)

#define U64TO8_BE(p, v) \
  do { \
    U32TO8_BE((p),     (u32)((v) >> 32));	\
    U32TO8_BE((p) + 4, (u32)((v)      ));	\
  } while (0)

/* 
   hash structure
*/
typedef struct  { 
    int hashbitlen;  /* length of the hash value (bits) */
    int datalen;     /* amount of remaining data to hash (bits) */
    int init;        /* set to 1 when initialized */
    int nullt;       /* Boolean value for special case \ell_i=0 */
    /*
    variables for the 32-bit version  
    */
    u32 h32[8];         /* current chain value (initialized to the IV) */
    u32 t32[2];         /* number of bits hashed so far */
    bit_sequence data32[64];     /* remaining data to hash (less than a block) */
    u32 salt32[4];      /* salt (null by default) */
    /*
    variables for the 64-bit version  
    */
    u64 h64[8];      /* current chain value (initialized to the IV) */
    u64 t64[2];      /* number of bits hashed so far */
    bit_sequence data64[128];  /* remaining data to hash (less than a block) */
    u64 salt64[4];   /* salt (null by default) */
} php_hash_state;

/*
  the 10 permutations of {0,...15}
*/
static const unsigned char sigma[][16] = {
    {  0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 10, 11, 12, 13, 14, 15 } ,
    { 14, 10,  4,  8,  9, 15, 13,  6,  1, 12,  0,  2, 11,  7,  5,  3 } ,
    { 11,  8, 12,  0,  5,  2, 15, 13, 10, 14,  3,  6,  7,  1,  9,  4 } ,
    {  7,  9,  3,  1, 13, 12, 11, 14,  2,  6,  5, 10,  4,  0, 15,  8 } ,
    {  9,  0,  5,  7,  2,  4, 10, 15, 14,  1, 11, 12,  6,  8,  3, 13 } ,
    {  2, 12,  6, 10,  0, 11,  8,  3,  4, 13,  7,  5, 15, 14,  1,  9 } ,
    { 12,  5,  1, 15, 14, 13,  4, 10,  0,  7,  6,  3,  9,  2,  8, 11 } ,
    { 13, 11,  7, 14, 12,  1,  3,  9,  5,  0, 15,  4,  8,  6,  2, 10 } ,
    {  6, 15, 14,  9, 11,  3,  0,  8, 12,  2, 13,  7,  1,  4, 10,  5 } ,
    { 10,  2,  8,  4,  7,  6,  1,  5, 15, 11,  9, 14,  3, 12, 13 , 0 }, 
    {  0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 10, 11, 12, 13, 14, 15 } ,
    { 14, 10,  4,  8,  9, 15, 13,  6,  1, 12,  0,  2, 11,  7,  5,  3 } ,
    { 11,  8, 12,  0,  5,  2, 15, 13, 10, 14,  3,  6,  7,  1,  9,  4 } ,
    {  7,  9,  3,  1, 13, 12, 11, 14,  2,  6,  5, 10,  4,  0, 15,  8 } ,
    {  9,  0,  5,  7,  2,  4, 10, 15, 14,  1, 11, 12,  6,  8,  3, 13 } ,
    {  2, 12,  6, 10,  0, 11,  8,  3,  4, 13,  7,  5, 15, 14,  1,  9 } ,
    { 12,  5,  1, 15, 14, 13,  4, 10,  0,  7,  6,  3,  9,  2,  8, 11 } ,
    { 13, 11,  7, 14, 12,  1,  3,  9,  5,  0, 15,  4,  8,  6,  2, 10 } ,
    {  6, 15, 14,  9, 11,  3,  0,  8, 12,  2, 13,  7,  1,  4, 10,  5 } ,
    { 10,  2,  8,  4,  7,  6,  1,  5, 15, 11,  9, 14,  3, 12, 13 , 0 }  
  };

/*
  constants for BLAKE-32 and BLAKE-28
*/
static const u32 c32[16] = {
    0x243F6A88, 0x85A308D3,
    0x13198A2E, 0x03707344,
    0xA4093822, 0x299F31D0,
    0x082EFA98, 0xEC4E6C89,
    0x452821E6, 0x38D01377,
    0xBE5466CF, 0x34E90C6C,
    0xC0AC29B7, 0xC97C50DD,
    0x3F84D5B5, 0xB5470917 
};

/*
  constants for BLAKE-64 and BLAKE-48
*/
static const u64 c64[16] = {
    L64(0x243F6A8885A308D3), L64(0x13198A2E03707344),
    L64(0xA4093822299F31D0), L64(0x082EFA98EC4E6C89),
    L64(0x452821E638D01377), L64(0xBE5466CF34E90C6C),
    L64(0xC0AC29B7C97C50DD), L64(0x3F84D5B5B5470917),
    L64(0x9216D5D98979FB1B), L64(0xD1310BA698DFB5AC),
    L64(0x2FFD72DBD01ADFB7), L64(0xB8E1AFED6A267E96),
    L64(0xBA7C9045F12C7F99), L64(0x24A19947B3916CF7),
    L64(0x0801F2E2858EFC16), L64(0x636920D871574E69)
};

/*
  padding data
*/
static const bit_sequence padding[129] =
  {
    0x80, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
};

/*
  initial values ( IVx for BLAKE-x)
*/
static const u32 IV256[8]={
    0x6A09E667, 0xBB67AE85,
    0x3C6EF372, 0xA54FF53A,
    0x510E527F, 0x9B05688C,
    0x1F83D9AB, 0x5BE0CD19
};
static const u32 IV224[8]={
    0xC1059ED8, 0x367CD507,
    0x3070DD17, 0xF70E5939,
    0xFFC00B31, 0x68581511,
    0x64F98FA7, 0xBEFA4FA4
};
static const u64 IV384[8]={
    L64(0xCBBB9D5DC1059ED8), L64(0x629A292A367CD507),
    L64(0x9159015A3070DD17), L64(0x152FECD8F70E5939),
    L64(0x67332667FFC00B31), L64(0x8EB44A8768581511),
    L64(0xDB0C2E0D64F98FA7), L64(0x47B5481DBEFA4FA4)
};
static const u64 IV512[8]={
    L64(0x6A09E667F3BCC908), L64(0xBB67AE8584CAA73B),
    L64(0x3C6EF372FE94F82B), L64(0xA54FF53A5F1D36F1),
    L64(0x510E527FADE682D1), L64(0x9B05688C2B3E6C1F),
    L64(0x1F83D9ABFB41BD6B), L64(0x5BE0CD19137E2179)
};

#endif	/* PHP_BLAKE_OPT32_H */


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
