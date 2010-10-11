<?php
/**
 * LIRC_ClientException.php - contains the class that represents a LIRC
 * client exception.
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
 * A class that represents an exception in the operation of a LIRC_Client
 * object.  This is typically thrown when an error occurs during the
 * construction of a LIRC_Client object.
 */
class LIRC_ClientException extends Exception {
	
	/**
	 * The exception that generated this exception (if any).
	 */
	private $previous;
	
	/**
	 * Constructs a new exception
	 * 
	 * @param $message  String  The error message
	 * @param $code     Integer An error code
	 * @param $previous Object  The exception that caused this exception
	 */
	public function __construct($message = null, $code = 0, $previous = null) {
		parent::__construct($message, $code);
		$this->previous = $previous;
	}
	
	/**
	 * Returns the previous exception in the chain, I.e. the exception
	 * that generated this exception, or null if this is the first 
	 * exception in the chain.
	 * 
	 * @return Object the previous exception.
	 */
	public function getPrevious() {
		return $this->previous;
	}
}
