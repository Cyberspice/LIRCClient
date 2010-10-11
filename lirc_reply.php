<?php
/**
 * LIRC_Reply.php - contains the class that represents a LIRC daemon 
 * data packet.
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
 * A class that represents the data packet by lircd sent as a reply to a 
 * command.  Typically it is constructed by LIRC_Client as the result 
 * of executing a command.
 * 
 * @author Melanie Rhianna Lewis <cyberspice@cyberspice.org.uk>
 */
class LIRC_Reply {
	
	/**
	 * There was non status in the reply.
	 */
	const STATUS_NONE = 0;
	
	/**
	 * The reply indicates the operation was successful.
	 */
	const STATUS_SUCCESS = 1;
	
	/**
	 * The reply indicates an error occurred.
	 */
	const STATUS_ERROR = 2;
	
	/**
	 * The command that generated this reply.
	 */
	protected $command;
	
	/**
	 * The reply status.
	 */
	protected $status;
	
	/**
	 * Any data associated with the reply.
	 */
	protected $data;
	
	/**
	 * Constructs a new LIRC_Reply object.
	 * 
	 * @param $command String The command that generated the reply.
	 * @param $status  String The status of reply.
	 * @param $data    Array  Any data associated with the reply.
	 */
	public function __construct($command, $status = LIRC_Reply::STATUS_NONE, $data = null) {
		$this->command = $command;
		$this->status  = $status;
		$this->data    = $data;
	}
	
	/**
	 * Returns the command that generated the reply.
	 * 
	 * @return String The command string.
	 */
	public function getCommand() {
		return $this->command;
	}
	
	/**
	 * Returns the status.  This is either STATUS_SUCCESS, STATUS_ERROR
	 * or STATUS_NONE if no status was returned.
	 * 
	 * @return Integer The status code.
	 */
	public function getStatus() {
		return $this->status;	
	}
	
	/**
	 * Returns the data returned by the reply as an Array or null if no
	 * data was returned.
	 * 
	 * @return Array The data as an array, or null.
	 */
	public function getData() {
		return $this->data;
	}
};
