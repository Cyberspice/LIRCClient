<?php
/**
 * LIRC_Remote.php - contains the class that represents a LIRC daemon 
 * remote control definition.
 *
 * This is released under MIT license, see license.txt for details
 * 
 * @author    Melanie Rhianna Lewis <cyberspice@cyberspice.org.uk>
 * @copyright Melanie Rhianna Lewis (c) 2010
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @since     PHP 5.2
 * @package   LIRC
 */

/**
 * A class that represents a remote control and the keys it provides
 * as supported by a LIRC daemon configuration.  Typically this is used
 * internally by LIRC_Client.
 * 
 * @author Melanie Rhianna Lewis <cyberspice@cyberspice.org.uk>
 */
class LIRC_Remote {
	
	/**
	 * The remote control name.
	 */
	protected $name;
	
	/**
	 * An array of strings comprising the keys supported by the remote.
	 */
	protected $keys;
	
	/**
	 * Constructs a new LIRC_Remote object with the specified name and the
	 * specified keys as an array of strings.  The array of keys should be an
	 * associated array with both the key and the value being the name of the
	 * key.
	 * 
	 * @param $name String The remote control name.
	 * @param $keys Array  An array of remote control keys.
	 */
	public function __construct($name, $keys) {
		$this->name = $name;
		$this->keys = $keys;
	}
	
	/**
	 * Returns the name of the remote control.
	 * 
	 * @return String The name of the remote control.
	 */
	public function getName() {
		return $this->name;
	}
	
	/**
	 * Returns an associated array of keys supported by the remote.
	 * Both the key and value comprises a String that is the name of the
	 * key.
	 * 
	 * @return Array The keys supported by the remote.
	 */
	public function getKeys() {
		return $this->keys;
	}
	
	/**
	 * Predicate that indicates whether the specified key is supported by the
	 * remote.
	 * 
	 * @param $key String The key name.
	 * 
	 * @return bool True if supported otherwise false.
	 */
	public function supportsKey($key) {
		if (is_null($this->keys[$key])) {
			return false;
		}
		
		return true;
	}
};
