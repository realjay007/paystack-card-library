<?php
namespace PAY;

/**
 * Util.php
 *
 * Utility function class
 * @author Julius Ijie
 */

class Util {

	/**
	 * Create a "Random" String
	 *
	 * @param	string $type	type of random string.  basic, alpha, alnum, numeric, nozero, unique, md5, encrypt and sha1
	 * @param	int $len	number of characters
	 * @return string
	 */
	public static function randomString($type = 'alnum', $len = 8): string {
		$pool = 'HELLOWORLD';
		switch ($type)
		{
			case 'basic':
				return mt_rand();
			case 'alnum':
			case 'numeric':
			case 'nozero':
			case 'alpha':
				switch ($type)
				{
					case 'alpha':
						$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
						break;
					case 'alnum':
						$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
						break;
					case 'numeric':
						$pool = '0123456789';
						break;
					case 'nozero':
						$pool = '123456789';
						break;
				}
				return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
			case 'md5':
				return md5(uniqid(mt_rand()));
			case 'sha1':
				return sha1(uniqid(mt_rand(), TRUE));
		}
		return '';
	}

}