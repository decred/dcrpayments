<?php
//===========================================================================
// Wrapper for bcmath or gmp with a preference on gmp.  This allows the code
// to use these calls without having to worry about the underlying
// implementation being used.
//
class BigMathUtils {
	public static function add($left, $right) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_add($left, $right);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcadd($left, $right);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}

	public static function sub($left, $right) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_sub($left, $right);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcsub($left, $right);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}

	public static function mul($left, $right) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_mul($left, $right);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcmul($left, $right);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}

	public static function div($left, $right) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_div($left, $right);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcdiv($left, $right);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}

	public static function comp($left, $right) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_cmp($left, $right);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bccomp($left, $right);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}
	
	public static function mod($left, $mod) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_mod($left, $mod);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcmod($left, $mod);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}

	public static function bin2dec($value) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_import($value);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcmath_Utils::base2dec($value, 256);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}

	public static function dec2bin($dec) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_export($dec);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcmath_Utils::dec2base($dec, 256);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}

	public static function hexdec($hex) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_Utils::gmp_hexdec($hex);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcmath_Utils::bchexdec($hex);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}

	public static function bigand($x, $y) {
		if (extension_loaded('gmp') && USE_EXT=='GMP') {
			return gmp_and($x, $y);
		} else if (extension_loaded('bcmath') && USE_EXT=='BCMATH') {
			return bcmath_Utils::bcand($x, $y);
		} else {
			throw new ErrorException("Please install BCMATH or GMP");
		}
	}
}
//===========================================================================

//===========================================================================
class Base58 {
	static private $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	// Returns the base58 encoding used by Bitcoin for the provided binary
	// string.
	public static function encode($bytes) {
		if (strlen($bytes) === 0) {
			return '';
		}

		$encoded = '';
		$base = strlen(self::$alphabet);
		$num = BigMathUtils::bin2dec($bytes);
		while (BigMathUtils::comp($num, $base) >= 0) {
			$div = BigMathUtils::div($num, $base);
			$mod = BigMathUtils::mod($num, $base);
			$encoded = self::$alphabet[intval($mod)] . $encoded;
			$num = $div;
		}
		if (intval($num) !== 0) {
			$encoded = self::$alphabet[intval($num)] . $encoded;
		}

		// Pad leading zeroes with the first alphabet character.
		$n = 0;
		while ($bytes[$n++] === "\x00") {
			$encoded = self::$alphabet[0] . $encoded;
		}
		return $encoded;
	}

	// Returns the base58 check encoding used by Bitcoin addresses for the
	// provided bytes and version (network) byte.
	public static function bitcoin_encode_check($bytes, $ver_byte) {
		$b = $ver_byte . $bytes;
		$b .= substr(hash('sha256', hash('sha256', $b, TRUE), TRUE), 0, 4);
		return self::encode($b);
	}
	
	// Decodes the provided base58 encoded string used by Bitcoin into
	// a binary string.
	public static function decode($base58) {
		$decoded = 0;
		$base = strlen(self::$alphabet);
		$j = 1;
		for($i = strlen($base58)-1; $i>= 0; $i--) {
			$mul = BigMathUtils::mul($j, strpos(self::$alphabet, $base58[$i]));
			$decoded = BigMathUtils::add($decoded, $mul);
			$j = BigMathUtils::mul($j, $base);
		}
		return BigMathUtils::dec2bin($decoded, 256);
	}

	// Returns the base58 check encoding used by Decred addresses for the
	// provided bytes and version (network) bytes.
	public static function decred_encode_check($bytes, $ver_bytes) {
		$b = $ver_bytes . $bytes;
		$b .= substr(hex2bin(blake(hex2bin(blake($b, BLAKE_256)), BLAKE_256)), 0, 4);
		return self::encode($b);
	}
}
//===========================================================================

//===========================================================================
class Bip32ExtendedPubkey {
	// Bitcoin network version identifiers.
	private static $bitcoin_mainnet_public = "\x04\x88\xb2\x1e"; // xpub
	private static $bitcoin_testnet_public = "\x04\x35\x87\xcf"; // tpub

	// Bitcoin public mainnet pubkey hash address identifier.
	private static $bitcoin_mainnet_pkh_id = "\x00"; // 1

	// Decred network version identifiers.
	private static $decred_mainnet_public = "\x02\xfd\xa9\x26"; // dpub
	private static $decred_testnet_public = "\x04\x35\x87\xd1"; // tpub

	// Decred public mainnet pubkey hash address identifier.
	private static $decred_mainnet_pkh_id = "\x07\x3f"; // Ds
	// Decred public testnet2 pubkey hash address identifier.
	private static $decred_testnet_pkh_id = "\x0f\x21"; // Ts

	// Index at which a hardended key starts.  Each extended key has 2^31 normal
	// child keys and 2^31 hardened child keys.  Thus the range for normal child
	// keys is [0, 2^31 - 1] and the range for hardened child keys is
	// [2^31, 2^32 - 1].  As the name implies, this class only works with
	// extended public keys which means hardened child keys are not supported.
	public static $hardened_key_start = 0x80000000; // 2^31

	// ECC crypto curve and generator.
	private static $curve = NULL;
	private static $generator = NULL;

	private $network = "unknown";
	// All binary data.
	private $key;
	private $chain_code;
	private $parent_fp;
	private $depth;
	private $child_num;

	public function __construct($key, $chain_code, $parent_fp, $depth, $child_num, $network) {
		$this->key = $key;
		$this->chain_code = $chain_code;
		$this->parent_fp = $parent_fp;
		$this->depth = $depth;
		$this->child_num = $child_num;
		$this->network = $network;
	}

	// Returns a CurveFp object initialized with the parameters for the
	// secp256k1 curve.  It is  memoized so multiple calls do
	// not create new instances.
	private static function get_curve() {
		if (!is_null(self::$curve)) {
			return self::$curve;
		}

		$_p  = BigMathUtils::hexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F');
		$_b  = BigMathUtils::hexdec('0x0000000000000000000000000000000000000000000000000000000000000007');
		self::$curve = new CurveFp($_p, 0, $_b);
		return self::$curve;
	}

	// Returns a Point object initialized with the parameters for the
	// base point  of secp256k1 curve.  It is  memoized so multiple calls do
	// not create new instances.
	private static function get_generator() {
		if (!is_null(self::$generator)) {
			return self::$generator;
		}

		$curve = self::get_curve();
		$_Gx = BigMathUtils::hexdec('0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798');
		$_Gy = BigMathUtils::hexdec('0x483ada7726a3c4655da4fbfc0e1108a8fd17b448a68554199c47d08ffb10d4b8');
		$_N  = BigMathUtils::hexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141');
		self::$generator = new Point($curve, $_Gx, $_Gy, $_N);
		return self::$generator;
	}

	// Returns the Y value on the secp256k1 curve given the X point and
	// compressed key type (0x02 or 0x03).
	private static function decompress_point($curve, $pkx, $key_type) {
		// Y = +-sqrt(x^3 + B)
		// Done using modular exponentiation using Q = P+1/4.
		$x3b = BigMathUtils::add(BigMathUtils::mul($pkx, BigMathUtils::mul($pkx, $pkx)), $curve->getB());
		$_Q = BigMathUtils::hexdec('0x3FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFBFFFFF0C');
		$y = NumberTheory::modular_exp($x3b, $_Q, $curve->getPrime());
		$is_y_odd = BigMathUtils::mod($y, 2) == 1;

		// Choose the positive or negative based on the key type.
		@$is_y_bit_set = (($key_type & 0x01) == 0x01);
		if ($is_y_bit_set != $is_y_odd) {
			$y = BigMathUtils::sub($curve->getPrime(), $y);
		}
		return $y;
	}

	// Parses a bitcoin base58-encoded extended public key and checks its
	// validity.
	//
	// Returns:
	//   array(Bip32ExtendedPubkey or NULL, 'error' or NULL)
	//
	// NOTE: One or the other will always be set, so the caller can easily
	// check for errors without overloading the data domain:
	// 	list($epk, $err) = Bip32ExtendedPubkey::bitcoin_parse_string($epkstr);
	// 	if (!is_null($err)) {
	//   		/* handle error */
	// 	} else {
	// 		/* safe to use $epk */
	// 	}
	public static function bitcoin_parse_string($bip32_epkstr) {
		if ((USE_EXT != 'BCMATH') && (USE_EXT != 'GMP')) {
			return array(NULL, 'bcmath or gmp extension required');
		}

		// The serialized format is:
		//   version (4) || depth (1) || parent fingerprint (4) ||
		//   child num (4) || chain code (32) || key data (33) || checksum (4)

		// serialized_key_len is the length of a serialized public or private
		// extended key without $he final checksum.
		$serialized_key_len =  4 + 1 + 4 + 4 + 32 + 33; // 78 bytes

		// The base58-decoded extended key must consist of a serialized payload
		// plus an additional 4 bytes for the checksum.
		$decoded = Base58::decode($bip32_epkstr);
		if (strlen($decoded) !== $serialized_key_len+4) {
			return array(NULL, 'bad length');
		}

		// Split the payload and checksum up and ensure the checksum matches.
		$payload = substr($decoded, 0, strlen($decoded)-4);
		$checksum = substr($decoded, -4);
		$expected_checksum = substr(hash('sha256', hash('sha256', $payload, TRUE), TRUE), 0, 4);
		if ($checksum !== $expected_checksum) {
			return array(NULL, 'bad checksum');
		}

		$version = substr($payload, 0, 4);
		switch($version) {
		case self::$bitcoin_mainnet_public:
			$network = "mainnet";
			break;
		case self::$bitcoin_testnet_public:
			$network = "testnet";
			break;
		default:
			return array(NULL, 'unknown network');
		}

		$depth = substr($payload, 4, 1);
		$parent_fp = substr($payload, 5, 4);
		$child_num = substr($payload, 9, 4);
		$chain_code = substr($payload, 13, 32);
		$key = substr($payload, 45, 33);

		// The key is a public compressed key if it starts with 0x02 or 0x03.
		$key_type = $payload[45];
		$isPublic = (($key_type === "\x02") || ($key_type === "\x03"));
		if (!$isPublic) {
			return array(NULL, 'not extended pubkey');
		}

		// Ensure the public key is actually on the secp256k1 curve.
		$curve = self::get_curve();
		$pkx = BigMathUtils::hexdec('0x'.bin2hex(substr($payload, 46, 32)));
		$pky = self::decompress_point($curve, $pkx, $key_type);
		if (!$curve->contains($pkx, $pky)) {
			return array(NULL, 'point is not on the curve');
		}

		return array(new self($key, $chain_code, $parent_fp, $depth, $child_num, $network), NULL);
	}

	// Parses a Decred base58-encoded extended public key and checks its
	// validity.
	//
	// Returns:
	//   array(Bip32ExtendedPubkey or NULL, 'error' or NULL)
	//
	// NOTE: One or the other will always be set, so the caller can easily
	// check for errors without overloading the data domain:
	// 	list($epk, $err) = Bip32ExtendedPubkey::decred_parse_string($epkstr);
	// 	if (!is_null($err)) {
	//   		/* handle error */
	// 	} else {
	// 		/* safe to use $epk */
	// 	}
	public static function decred_parse_string($bip32_epkstr) {
		if ((USE_EXT != 'BCMATH') && (USE_EXT != 'GMP')) {
			return array(NULL, 'bcmath or gmp extension required');
		}

		// The serialized format is:
		//   version (4) || depth (1) || parent fingerprint (4) ||
		//   child num (4) || chain code (32) || key data (33) || checksum (4)

		// serialized_key_len is the length of a serialized public or private
		// extended key without $he final checksum.
		$serialized_key_len =  4 + 1 + 4 + 4 + 32 + 33; // 78 bytes

		// The base58-decoded extended key must consist of a serialized payload
		// plus an additional 4 bytes for the checksum.
		$decoded = Base58::decode($bip32_epkstr);
		if (strlen($decoded) !== $serialized_key_len+4) {
			return array(NULL, 'bad length');
		}

		// Split the payload and checksum up and ensure the checksum matches.
		$payload = substr($decoded, 0, strlen($decoded)-4);
		$checksum = substr($decoded, -4);
		$expected_checksum = substr(hex2bin(blake(hex2bin(blake($payload, BLAKE_256)), BLAKE_256)), 0, 4);
		if ($checksum !== $expected_checksum) {
			return array(NULL, "bad decred checksum expected '{$expected_checksum}' got '${checksum}'");
		}

		$version = substr($payload, 0, 4);
		if ($version !== self::$decred_mainnet_public
		&& $version !== self::$decred_testnet_public) {
			return array(NULL, 'unknown network');
		}
		$version = substr($payload, 0, 4);
		switch($version) {
		case self::$decred_mainnet_public:
			$network = "mainnet";
			break;
		case self::$decred_testnet_public:
			$network = "testnet";
			break;
		default:
			return array(NULL, 'unknown network');
		}

		$depth = substr($payload, 4, 1);
		$parent_fp = substr($payload, 5, 4);
		$child_num = substr($payload, 9, 4);
		$chain_code = substr($payload, 13, 32);
		$key = substr($payload, 45, 33);

		// The key is a public compressed key if it starts with 0x02 or 0x03.
		$key_type = $payload[45];
		$isPublic = (($key_type === "\x02") || ($key_type === "\x03"));
		if (!$isPublic) {
			return array(NULL, 'not extended pubkey');
		}

		// Ensure the public key is actually on the secp256k1 curve.
		$curve = self::get_curve();
		$pkx = BigMathUtils::hexdec('0x'.bin2hex(substr($payload, 46, 32)));
		$pky = self::decompress_point($curve, $pkx, $key_type);
		if (!$curve->contains($pkx, $pky)) {
			return array(NULL, 'point is not on the curve');
		}

		return array(new self($key, $chain_code, $parent_fp, $depth, $child_num, $network), NULL);
	}

	// Checks whether a base58-encoded extended public key is valid.  When
	// it's invalid, a short description of why it is invalid is returned.
	//
	// Returns:
	//   true or 'error'
	public static function bitcoin_epk_is_valid($bip32_epkstr) {
		if ((USE_EXT != 'BCMATH') && (USE_EXT != 'GMP')) {
			return 'bcmath or gmp extension required';
		}

		list (, $err) = self::bitcoin_parse_string($bip32_epkstr);
		if (is_null($err)) {
			return true;
		}
		return $err;
	}

	// Serializes a Point to a 33-byte compressed binary format.
	private static function serialize_compressed_point($point) {
		// Compressed key type is 02 for positive Y values and 03 for
		// negative Y values.
		$child_key_type = "\x02";
		if (BigMathUtils::bigand($point->getY(), 1) == 1) {
			$child_key_type = "\x03";
		}
		$paddedx = str_pad(BigMathUtils::dec2bin($point->getX()), 32, "\x0", STR_PAD_LEFT);
		return $child_key_type . $paddedx;
	}
	
	// Derives the child public extended key at the given index and returns
	// the public key encoded using base58 check encoding for the main
	// bitcoin network.  This is also known as a pay-to-pubkey-hash address.
	//
	// Returns:
	//   array('address' or NULL, 'error' or NULL)
	//
	// NOTE: One or the other will always be set, so the caller can easily
	// check for errors without overloading the data domain:
	// 	list($addr, $err) = Bip32ExtendedPubkey::bitcoin_child_addr($index);
	// 	if (!is_null($err)) {
	//   		/* handle error */
	// 	} else {
	// 		/* safe to use $addr */
	// 	}
	public function bitcoin_child_addr($index) {
		if ((USE_EXT != 'BCMATH') && (USE_EXT != 'GMP')) {
			return array(NULL, 'bcmath or gmp extension required');
		}

		if ($index >= self::$hardened_key_start) {
			return array(NULL, 'cannot derive a hardened key from a public key');
		}

		// The data used to derive the child public key per BIP32 is:
		//   serP(parentPubKey) || ser32(i)
		$data = $this->key . pack('N', $index);

		// Take the HMAC-SHA512 of the current key's chain code and the derived
		// data:
		//   I = HMAC-SHA512(Key = chainCode, Data = data)
		$ilr = hash_hmac('sha512', $data, $this->chain_code, TRUE);

		// Split "I" into two 32-byte sequences Il and Ir where:
		//   Il = intermediate key used to derive the child
		//   Ir = child chain code
		$il = substr($ilr, 0, strlen($ilr)/2);
		$child_chain_code = substr($ilr, -(strlen($ilr)/2));

		// The derived public key relies on treating the left 32-byte
		// sequence calculated above (Il) as a 256-bit integer that must be
		// within the valid range for a secp256k1 private key.  There is a small
		// chance (< 1 in 2^127) this condition will not hold, and in that case,
		// a child extended key can't be created for this index and the caller
		// should simply increment to the next index.
		$curve = self::get_curve();
		$generator = self::get_generator();
		$il_num = BigMathUtils::bin2dec($il);
		if ((BigMathUtils::comp($il_num, 0) == 0) || BigMathUtils::comp($il_num, $generator->getOrder()) >= 0) {
			return array(NULL, 'the extended key at this index is invalid');
		}

		// The algorithm used to derive the public child key is:
		//   childKey = serP(point(parse256(Il)) + parentKey)
		//
		// Calculate the corresponding intermediate public key for
		// intermediate private key.
		$ipk = Point::mul($il_num, $generator);
		if ((BigMathUtils::comp($ipk->getX(), 0) == 0) || (BigMathUtils::comp($ipk->getY(), 0) == 0)) {
			return array(NULL, 'the extended key at this index is invalid');
		}

		// Convert the serialized compressed parent public key into X
		// and Y coordinates so it can be added to the intermediate
		// public key.
		$parent_key_type = $this->key[0];
		$parentx = BigMathUtils::hexdec('0x'.bin2hex(substr($this->key, 1, 32)));
		$parenty = self::decompress_point($curve, $parentx, $parent_key_type);
		$parent_point = new Point($curve, $parentx, $parenty);

		// Add the intermediate public key to the parent public key to
		// derive the final child key.
		$child_point = Point::add($ipk, $parent_point);
		$child_key = self::serialize_compressed_point($child_point);

		// Return the address which is the Base58Check encoded hash160
		// of the new serialized compressed dervied child key.
		$h160 = hash('ripemd160', hash('sha256', $child_key, TRUE), TRUE);

		switch ($this->network) {
		case "mainnet":
			$addr = Base58::encode_check($h160, self::$bitcoin_mainnet_pkh_id);
			break;
		case "testnet":
			$addr = Base58::encode_check($h160, self::$bitcoin_testnet_pkh_id);
			break;
		}

		return array($addr, NULL);
	}
//===========================================================================

	// Derives the child public extended key at the given index and returns
	// the public key encoded using base58 check encoding for the main
	// bitcoin network.  This is also known as a pay-to-pubkey-hash address.
	//
	// Returns:
	//   array('address' or NULL, 'error' or NULL)
	//
	// NOTE: One or the other will always be set, so the caller can easily
	// check for errors without overloading the data domain:
	// 	list($addr, $err) = Bip32ExtendedPubkey::decred_child_addr($index);
	// 	if (!is_null($err)) {
	//   		/* handle error */
	// 	} else {
	// 		/* safe to use $addr */
	// 	}
	public function decred_child_addr($index) {
		if ((USE_EXT != 'BCMATH') && (USE_EXT != 'GMP')) {
			return array(NULL, 'bcmath or gmp extension required');
		}

		if ($index >= self::$hardened_key_start) {
			return array(NULL, 'cannot derive a hardened key from a public key');
		}

		// The data used to derive the child public key per BIP32 is:
		//   serP(parentPubKey) || ser32(i)
		$data = $this->key . pack('N', $index);

		// Take the HMAC-SHA512 of the current key's chain code and the derived
		// data:
		//   I = HMAC-SHA512(Key = chainCode, Data = data)
		$ilr = hash_hmac('sha512', $data, $this->chain_code, TRUE);

		// Split "I" into two 32-byte sequences Il and Ir where:
		//   Il = intermediate key used to derive the child
		//   Ir = child chain code
		$il = substr($ilr, 0, strlen($ilr)/2);
		$child_chain_code = substr($ilr, -(strlen($ilr)/2));

		// The derived public key relies on treating the left 32-byte
		// sequence calculated above (Il) as a 256-bit integer that must be
		// within the valid range for a secp256k1 private key.  There is a small
		// chance (< 1 in 2^127) this condition will not hold, and in that case,
		// a child extended key can't be created for this index and the caller
		// should simply increment to the next index.
		$curve = self::get_curve();
		$generator = self::get_generator();
		$il_num = BigMathUtils::bin2dec($il);
		if ((BigMathUtils::comp($il_num, 0) == 0) || BigMathUtils::comp($il_num, $generator->getOrder()) >= 0) {
			return array(NULL, 'the extended key at this index is invalid');
		}

		// The algorithm used to derive the public child key is:
		//   childKey = serP(point(parse256(Il)) + parentKey)
		//
		// Calculate the corresponding intermediate public key for
		// intermediate private key.
		$ipk = Point::mul($il_num, $generator);
		if ((BigMathUtils::comp($ipk->getX(), 0) == 0) || (BigMathUtils::comp($ipk->getY(), 0) == 0)) {
			return array(NULL, 'the extended key at this index is invalid');
		}

		// Convert the serialized compressed parent public key into X
		// and Y coordinates so it can be added to the intermediate
		// public key.
		$parent_key_type = $this->key[0];
		$parentx = BigMathUtils::hexdec('0x'.bin2hex(substr($this->key, 1, 32)));
		$parenty = self::decompress_point($curve, $parentx, $parent_key_type);
		$parent_point = new Point($curve, $parentx, $parenty);

		// Add the intermediate public key to the parent public key to
		// derive the final child key.
		$child_point = Point::add($ipk, $parent_point);
		$child_key = self::serialize_compressed_point($child_point);

		// Return the address which is the Base58Check encoded hash160
		// of the new serialized compressed dervied child key.
		$h160 = hash('ripemd160', hex2bin(blake($child_key, BLAKE_256)), TRUE);

		switch ($this->network) {
		case "mainnet":
			$addr = Base58::decred_encode_check($h160, self::$decred_mainnet_pkh_id);
			break;
		case "testnet":
			$addr = Base58::decred_encode_check($h160, self::$decred_testnet_pkh_id);
			break;
		}
		return array($addr, NULL);
	}
}
//===========================================================================

//===========================================================================
function BWWC__generate_bitcoin_address_from_bip32_epk($bip32_epk, $index) {
	list($epk, $err) = Bip32ExtendedPubkey::bitcoin_parse_string($bip32_epk);	
	if (!is_null($err)) {
		// TODO: Log it...
		print $err . "\n";
		return false;
	}

	list($addr, $err) = $epk->bitcoin_child_addr($index);
	if (!is_null($err)) {
		print $err . "\n";
		return false;
	}

	return $addr;	
}
//===========================================================================

//===========================================================================
function BWWC__generate_decred_address_from_bip32_epk($bip32_epk, $index) {
	list($epk, $err) = Bip32ExtendedPubkey::decred_parse_string($bip32_epk);
	if (!is_null($err)) {
		// TODO: Log it...
		print $err . "\n";
		return false;
	}

	list($addr, $err) = $epk->decred_child_addr($index);
	if (!is_null($err)) {
		print $err . "\n";
		return false;
	}

	return $addr;
}
//===========================================================================
?>
