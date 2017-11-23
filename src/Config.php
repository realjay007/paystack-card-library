<?php
namespace PAY;

/**
 * Config.php
 *
 * Config library for the library ;-)
 *
 * @author Julius Ijie
 */

class Config {

	/**
	 * Data store
	 * @var array
	 */
	protected static $_data_ = array();

	/**
	 * Load a config file
	 * @param string $path
	 */
	public function loadConfig(string $_file_) {
		if(array_key_exists($_file_, self::$_data_)) return self::$_data_[$_file_];
		$_path_ = __DIR__.'/config/'.$_file_.'.php';

		if(file_exists($_path_)) {
			include $_path_;
			$config = $config ?? null;
			return self::$_data_[$_file_] = $config;
		}
		else throw new \Exception('Unable to find config file');
	}

	/**
	 * Set a value
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set(string $name, $value) {
		self::$_data_[$name] = $value;
	}

	/**
	 * Get a value
	 * @param string $name
	 */
	public function __get(string $name) {
		return self::$_data_[$name] ?? null;
	}

	/**
	 * Check if a value is set
	 * @param string $name
	 * @return bool
	 */
	public function __isset(string $name): bool {
		return array_key_exists($name, self::$_data_);
	}

	/**
	 * Unset a value
	 * @param string $name
	 */
	public function __unset(string $name) {
		if(array_key_exists($name, self::$_data_)) unset(self::$_data_[$name]);
	}

}