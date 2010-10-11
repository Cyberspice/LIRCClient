<?php
/**
 * LIRC_Client.php - contains the class that represents a LIRC daemon 
 * client.
 *
 * This is released under MIT license, see license.txt for details
 * 
 * @author    Melanie Rhianna Lewis <cyberspice@cyberspice.org.uk>
 * @copyright Melanie Rhianna Lewis (c) 2010
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @since     PHP 5.2
 * @package   LIRC
 */
 
require_once('lirc_clientexception.php');
require_once('lirc_remote.php');
require_once('lirc_reply.php');

if (!defined('LIRC_SOCKET')) {
/**
 * The default Lirc Daemon control socket.
 */
define ('LIRC_SOCKET', '/var/run/lirc/lircd');
}

/**
 * A class that represents a client to the LIRC Infra Red control daemon.  It
 * provides an interface for sending key presses via a LIRC supported IR
 * blaster and for receiving key press information from LIRC IR detectors.
 */
class LIRC_Client {
	
	/**
	 * Reply parser state indicating waiting for a reply.
	 */
	const REPLY_STATE_BEGIN = 0;
	
	/**
	 * Reply parser state indicating that the reply has started.
	 */
	const REPLY_STATE_POST_BEGIN = 1;
	
	/**
	 * Reply parser state indicating the command that generated the reply has
	 * been received.
	 */
	const REPLY_STATE_POST_COMMAND = 2;
	
	/**
	 * Reply parser state indicating the reply status has been received.
	 */
	const REPLY_STATE_POST_STATUS = 3;
	
	/**
	 * Reply parser state indicating data will be received.
	 */
	const REPLY_STATE_DATA_START = 4;
	
	/**
	 * Reply parser state indicating data is being received.
	 */
	const REPLY_STATE_DATA = 5;
	
	/**
	 * Reply parser state indicating all data has been received.
	 */
	const REPLY_STATE_POST_DATA = 6;
	
	/**
	 * Reply parser state indicating the end of the packet.
	 */
	const REPLY_STATE_END = 7;
	
	/**
	 * The Lircd communication stream.
	 */
	private $sockstream = null;
	
	/**
	 * The name of the remote to communicate using.
	 */
	private $remote = null;
	
	/**
	 * Indicates if currently sending a key.
	 */
	private $sendingKey = false;
	
	/**
	 * The version of Lircd running as the server.
	 */
	private $daemonVersion = null;
	
	/**
	 * A hash of remotes supported and the keys supported by those remotes.
	 */
	private $remotes = null;
	
	/**
	 * A protected method that parses a reply from the LIRC daemon 
	 * constructing a LIRC_Reply object from the data received.
	 * 
	 * The method is a state machine that reads data from the LIRC daemon and
	 * processes it until it receives the 'END' message at which point it
	 * returns the processed data.
	 * 
	 * @return Object a LIRC_Reply object.
	 */
	protected function parseReply() {
		$state   = self::REPLY_STATE_POST_BEGIN;
		$command = "";
		$status  = LIRC_Reply::STATUS_NONE;
		
		while (!feof($this->sockstream) && ($state != LIRC_Client::REPLY_STATE_END)) {
			$line = rtrim(fgets($this->sockstream));
			
			switch ($state) {
				// A reply from lircd has begun.  Get the command.
				case self::REPLY_STATE_POST_BEGIN:
					$command = $line;
					$state   = self::REPLY_STATE_POST_COMMAND;
					break;
					
				// The cammand has been received from lircd.  Get the status.
				case self::REPLY_STATE_POST_COMMAND:
					if ($line == 'END') {
						$state = self::REPLY_STATE_END;
					} else 
					if ($line == 'SUCCESS') {
						$status = LIRC_Reply::STATUS_SUCCESS;
						$state  = self::REPLY_STATE_POST_STATUS;
					} else
					if ($line == 'ERROR') {
						$status = LIRC_Reply::STATUS_ERROR;
						$state  = self::REPLY_STATE_POST_STATUS;
					}
					break;
					
				// The status has been received from lircd.  Is there data?
				case self::REPLY_STATE_POST_STATUS:
					if ($line == 'END') {
						$state = self::REPLY_STATE_END;
					} else 
					if ($line == 'DATA') {
						$state = self::REPLY_STATE_DATA_START;
					}
					break;
							
				// Start reading data from lircd.
				case self::REPLY_STATE_DATA_START:
					if ($line == 'END') { // Just in case - shouldn't happen!
						$state = self::REPLY_STATE_END;
					} else {
						$total = $line;
						$data  = array();
						$count = 0;
						$state = self::REPLY_STATE_DATA;
					}	
					break;	
									
				// Read the data from lircd.
				case self::REPLY_STATE_DATA:
					if ($count < $total) {
						$data[] = $line;
						$count ++;
					}
					
					if ($count == $total) {
						$state = LIRC_Client::REPLY_STATE_POST_DATA;
					}
					break;
					
				// The data has been read from lircd
				case self::REPLY_STATE_POST_DATA:
					if ($line == 'END') {
						$state = LIRC_Client::REPLY_STATE_END;
					}
					break;
					
				// End of reply processing
				case self::REPLY_STATE_END:
					break; // Does nothing (handled in the while).
			}
		}
		
		return new LIRC_Reply($command, $status, $data);
	}
	
	/**
	 * A protected method that waits for a reply from the LIRC daemon and 
	 * then returns it.
	 * 
	 * The command field of the LIRC_Reply object returned should be check to
	 * ensure it was actually the reply you were looking for.  For example 
	 * SIGHUP will generate a SIGHUP reply out of band.
	 * 
	 * @return Object a LIRC_Reply object or false if end of file.
	 */
	protected function waitForReply() {
		while (!feof($this->sockstream)) {
			$line = rtrim(fgets($this->sockstream));
			
			// Throw away all until we get the start of a reply.
			if ($line == "BEGIN") {
				return $this->parseReply();
			}
		}
		
		return false;
	}
	
	/**
	 * A protected method that waits for the command status to be returned
	 * by the LIRC daemon returning true if it was successful otherwise
	 * false.
	 * 
	 * @param $command String the command that was sent
	 * 
	 * @return bool true if the command was successful otherwise false
	 */
	protected function getCommandStatus($command) {
		do {
			$reply = $this->waitForReply();
		} while (($reply != false) && ($reply->getCommand() != $command));
		
		if ($reply->getStatus() != LIRC_Reply::STATUS_SUCCESS) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * A protected method that queries the LIRC daemon to obtain the daemon's
	 * version number.  This can be used for compatibility with differing
	 * versions of daemons.
	 * 
	 * @return bool true if successful otherwise false.
	 */
	protected function queryVersion() {
		fprintf($this->sockstream, "VERSION\n");
		$reply = $this->waitForReply();
		$data  = $reply->getData();
		
		$this->daemonVersion = $data[0];
		
		return true;
	}
	
	/**
	 * A protected method that queries the LIRC daemon to obtain the remotes,
	 * and the valid keys for those remotes, supported by the current LIRC
	 * configuration.
	 *
	 * @returns bool true if successful otherwise false.
	 */
	protected function queryRemotes() {
		// The LIST command on its own returns a list of remotes supported by
		// the daemon.
		fprintf($this->sockstream, "LIST\n");
		$reply   = $this->waitForReply();
		$data    = $reply->getData();
		$remotes = array();
		
		// Iterate through the remotes getting the keys supported by that
		// remote.
		foreach ($data as $name) {
			// The LIST command followed by a valid remote name returns the
			// keys supported by that remote as an index key pair.
			fprintf($this->sockstream, "LIST ".$name."\n");
			$reply  = $this->waitForReply();
			$keys   = array();
			
			foreach ($reply->getData() as $keydata) {
				// Get the keyname
				list($index,$keyname) = explode(' ', $keydata);
				$keys[$keyname] = $keyname;
			}
			
			try {
				$remote = new LIRC_Remote($name, $keys);
			} catch (Exception $e) {
				return false;
			}
			
			// Add the remote
			$remotes[$name] = $remote;
		}
		
		$this->remotes = $remotes;
		return true;
	}
	
	/**
	 * Constructs a new LIRC_Client object.  
	 * 
	 * If the socket name is not supplied then the default LIRC daemon 
	 * socket will be used.  This is specified as a define in the source 
	 * file but can be overridded by defining it prior to including the 
	 * file.
	 * 
	 * If the remote name is not supplied the first remote found will be
	 * used.  This is ideal if the LIRC daemon is configured to only 
	 * support one remote.  Preferably a remote name should be supplied
	 * even if it is known that only one is supported in the LIRC daemon
	 * configuration.
	 * 
	 * @param $remote String The remote name (case insensitive).
	 * @param $socket The LIRC Daemon control socket name.
	 */
	public function __construct($remote = null, $socket = null) {
		// If a socket name is not supplied use the default.
		if (is_null($socket)) {
			$socket = LIRC_SOCKET;
		}
		
		// Try and open a connection to the LIRC daemon
		$this->sockstream = fsockopen('unix://'.$socket, 0, $errno, $errmsg);
		if (!$this->sockstream) {
			throw new LIRC_ClientException('LIRC_ClientException:'.$errno.' '.$errmsg);
		}
		
		// Get the daemon version
		if (!$this->queryVersion()) {
			throw new LIRC_ClientException('LIRC_ClientException: queryVersion() failed!');
		}
		
		// Get the remotes (and their keys) supported by the daemon install
		if (!$this->queryRemotes()) {
			throw new LIRC_ClientException('LIRC_ClientException: queryRemotes() failed!');
		}
		
		// Set the remote (if supplied) ensuring it is one supported by the 
		// daemon.
		if (!is_null($remote)) {
			$remote = strtoupper($remote);	
			if (is_null($this->remotes[$remote])) {
				throw new LIRC_ClientException('LIRC_ClientException: Remote \''.$remote.'\' not supported!');
			}
			
			$this->remote = $this->remotes[$remote];
		} else {
		// Set the first remote to be the one selected
			$remotes = array_values($this->remotes);
			$this->remote = $remotes[0];
		}
	}
	
	/**
	 * Sets the remote to use when sending keys.  If the daemon is not
	 * supported by the LIRC daemon an error is indicated.
	 * 
	 * @param $remote String The remote name (case insensitive)
	 * 
	 * @return Bool true is successful otherwise false.
	 */
	public function setRemote($remote) {
		if (!$this->sockstream) {
			return false;
		}
		
		$remote = strtoupper($remote);	
		if (is_null($this->remotes[$remote])) {
			return false;
		}
		
		$this->remote = $this->remotes[$remote];
		return true;
	}
	
	/**
	 * Send a key press.   The specified key must be supported by the 
	 * currently selected remote (See setRemote()) or an error is indicated.
	 * 
	 * @param $key   String  The key to send
	 * @param $count Integer Optional number of times to send the key
	 */
	public function sendKey($key, $count = 0) {
		if (!$this->sockstream) {
			return false;
		}
		
		$key = strtoupper($key);
		
		// Check the key is the keys for this remote
		if (!$this->remote->supportsKey($key)) {
			return false;
		}
		
		if ($count > 0) {
			$command = "SEND_ONCE ".$this->remote->getName()." ".$key." ".$count;
		} else {
			$command = "SEND_ONCE ".$this->remote->getName()." ".$key;
		}
		
		fprintf($this->sockstream, $command."\n");
		return $this->getCommandStatus($command);
	}
	
	/**
	 * Starts sending a key press.  It will continue to send the keypress until
	 * sendKeyStop() is called.   The specified key must be supported by the 
	 * currently selected remote (See setRemote()) or an error is indicated.
	 * 
	 * @param $key String The key to send.
	 * @return Bool true if successful otherwise false.
	 */
	public function sendKeyStart($key) {
		if (!$this->sockstream) {
			return false;
		}
		
		$key = strtoupper($key);
		
		// Check the key is the keys for this remote
		if (!$this->remote->supportsKey($key)) {
			return false;
		}
		
		$command = "SEND_START ".$this->remote." ".$key;
		
		fprintf($this->sockstream, $command."\n");
		if (!$this->getCommandStatus($command)) {
			return false;
		}
		
		$this->sendingKey = $key;
		return true;
	}
	
	/**
	 * Stops sending a key press.  The sending of the key press will have 
	 * been started by sendKeyStart().
	 * 
	 * @return Bool true if successful otherwise false.
	 */
	public function sendKeyStop() {
		if (!$this->sockstream) {
			return false;
		}
		
		if (!$this->sendingKey) {
			return false;
		}
		
		$command = "SEND_STOP ".$this->remote." ".$sendingKey;
		
		fprintf($this->sockstream, $command."\n");
		if (!$this->getCommandStatus($command)) {
			return false;
		}
		
		$this->sendingKey = false;
		return true;
	}
	
	/**
	 * Waits for a key press packet from the daemon calling the specified
	 * callback passing a LIRC_Reply object when the key press is received.
	 * 
	 * A reply message could be received from the daemon before a key press
	 * packet is received so optionally a second callback can be supplied
	 * that is passed any reply messages received prior to receiving the
	 * key press.
	 * 
	 * The key press callback should take a single argument in which is 
	 * passed an associative array comprising four entries, 'code', 'repeat',
	 * 'key' and 'remote' being the key code, the number of repeats, the
	 * key name and the remote name respectively.  The callback should return
	 * true if successful otherwise false.
	 * 
	 * The reply callback should take a single argument in which is passed 
	 * the reply object and return either true on success, or false.
	 * 
	 * @param $callback      Mixed Name of callback function or an 
	 * array comprising an object and method name pair.
	 * @param $replycallback Mixed Name of callback function or an 
	 * array comprising an object and method name pair.
	 * 
	 * @return Bool true if successful otherwise false.
	 */
	public function waitForKey($callback, $replycallback = null) {
		if (!$this->sockstream) {
			return false;
		}
		
		if (!is_callable($callback)) {
			return false;
		}
				
		// Loop until we receive a key press packet or an error occurs.
		while (!feof($this->sockstream)) {
			$line = rtrim(fgets($this->sockstream));
			echo "waitForKey(): << $line\n";
			
			if ($line == "BEGIN") {
				$reply = $this->parseReply();
				if (is_callable($replycallback)) {
					if (!call_user_func($replycallback, $reply)) {
						return false;
					}
				}
			} else {
				list($code,$repeat,$key,$remote) = explode(' ', $line);
				if (!call_user_func($callback, 
					array('code'=>$code, 'repeat'=>$repeat, 'key'=>$key, 'remote'=>$remote))) {
					return false;
				} else {
					return true;
				}
			}
		}
	}
	
	/**
	 * The destructor.  Closes any open streams and frees any used resources.
	 */
	public function __destruct() {
		fclose($this->sockstream);
	}
	
};

