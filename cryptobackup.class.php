<?php

/*
 CryptoBackup (v0.2 10/09/2015)
 Description: Simple script that allows you to create incremental crypto backups.

*/

class cryptobackup {

	// Editable variables
	public $debug = True;
	public $transfer_method = array();
	public $local_backup_dir = "/tmp/backup_dir/";
	public $cleanup_afterwards = True;

	# Transfer methods
	#public $method_scp = array('host'=>'','username'=>'','ssh_key'=>'','remote_dir'=>'');
	#public $method_mega = array('username'=>'','password'=>'','remote_dir'=>'/Root/backup/');

	public $method_scp = array();
	public $method_mega = array();

	# PGP Variables
	public $gpg_flags = "--no-default-recipient --force-mdc --encrypt";
	public $gpg_recipients = "-r user@example.net";

	# Backup Cleanup
	public $keep_backup_weeks = 3; // no im not going to change the week interval..

	// Rather not touch variables.. (warned you!)
	private $transfer_method_valid = array('scp','mega');
	private $backup_dirs = array();
	private $incremental_state_file = "";
	private $incremental_state_file_sync = "";
	private $backup_file = "";	

	public function __construct() {
		$this->incremental_state_file = sprintf("backup-week-%d.state",date("W",time()));
		$this->_debug("__construct: ". $this->incremental_state_file);
		$this->incremental_state_file_sync = sprintf("backup-week-%d-%d.state",date("W",time()),date("z",time()));
		$this->_debug("__construct: ". $this->incremental_state_file_sync);
		$this->backup_file = sprintf("backup-week-%d-%d.tgz",date("W",time()),date("z",time()));
		$this->_debug("__construct: ". $this->backup_file);
	}
	public function __destruct() {
		if ($this->cleanup_afterwards) {
			$this->_cleanup_leftovers();
		}

	}
	public function addMegaCredentials($username, $password, $remote_dir) {
		// Todo: SanityCheck; remote_dir exists/create it?
		$this->addTransferMethod("mega");
		$this->method_mega[] = array(
			'username' => $username,
			'password' => $password,
			'remote_dir' => $remote_dir
		);
	}
	public function addScpCredentials($username, $key_file, $remote_dir) {
		// Todo: SanityCheck; keyfile/remote_dir exists.
		$this->addTransferMethod("scp");
		$this->method_scp[] = array(
			'username' => $username,
			'password' => $password,
			'remote_dir' => $remote_dir
		);
	}
	public function addTransferMethod($method) {
		if (!in_array($method,$this->transfer_method_valid)) { 
			$this->_debug("setTransferMethod: invalid method: [$method]");
			return False; 
		}
		if (!in_array($method,$this->transfer_method)) {
			$this->transfer_method[] = $method;
			$this->_debug("setTransferMethod: $method");
			return True;
		}
		return False;
	}

	public function addDirectory($directorie) {
		if (file_exists($directorie)) {
			if (!in_array($directorie, $this->backup_dirs)) {
				$this->backup_dirs[] = $directorie;
				$this->_debug("addDirectory: $directorie");
			}
		} else { return False; }
	}

	private function _create_archive() {
		$cmdline = sprintf("/bin/tar -zcf %s --listed-incremental %s %s",$this->local_backup_dir.$this->backup_file,$this->local_backup_dir.$this->incremental_state_file, implode(" ",$this->backup_dirs));
		$this->_debug("_create_archive: $cmdline");
		$sysout = system($cmdline,$return_var);
		if ($return_var != 0) {
			$this->_debug("_create_archive: tar failed.. return var[".$return_var."]");
			return False;
		}
		return True;
	}

	private function _create_crypt() {
		$need_crypting = array();
		$need_crypting[] = $this->backup_file;
		$need_crypting[] = $this->incremental_state_file;

		foreach ($need_crypting as $file) {
			if (file_exists($this->local_backup_dir.$file.".gpg")) {
				$this->_debug("_create_crypt: destination file already exists.. removing $file.gpg");
				unlink($this->local_backup_dir.$file.".gpg");
			}
			// Create gpg file
			$cmdline = sprintf("/usr/bin/gpg %s %s %s",$this->gpg_flags, $this->gpg_recipients, $this->local_backup_dir.$file);
			$this->_debug("_create_crypt: $cmdline");
			$sysout = system($cmdline,$return_var);
			if ($return_var != 0) {
				$this->_debug("_create_crypt: gpg failed.. return var[".$return_var."]");
				return False;
			}

		}
		return True;
	}

	private function _cleanup_leftovers() {
		$cleanup_files = array();
		$cleanup_files[] = $this->backup_file;
		$cleanup_files[] = $this->backup_file.".gpg";
		$cleanup_files[] = $this->incremental_state_file_sync.".gpg";

		foreach ($cleanup_files as $file) {
			unlink($this->local_backup_dir.$file);
			$this->_debug("_cleanup_leftovers: file[".$this->local_backup_dir.$file."]");
		}
	}

	private function _debug($line) {
		if ($this->debug) {
			print sprintf("[%s][DEBUG] %s \n",date("r",time()), $line);
		}
	}

	private function _upload_mega() {
		// # megaput --path=/Root/backup/ backup-week-37-251.tgz.gpg backup-week-37.state.gpg
		// Suppose we want to always transfer the .pgp files
		foreach ($this->method_mega as $key=>$value) {
			$cmdline = sprintf("/usr/bin/megaput --username %s --password %s --no-progress --path=%s %s %s",$value['username'], $value['password'], $value['remote_dir'], $this->local_backup_dir.$this->backup_file.".gpg", $this->local_backup_dir.$this->incremental_state_file_sync.".gpg");
			$this->_debug("_upload_mega: $cmdline");
			$sysout = system($cmdline,$return_var);
			if ($return_var != 0) {
				$this->_debug("_upload_mega: upload might have failed for 1 or more files.. return var[".$return_var."]");
				#return False; // Todo: fix better error checking?
			}
		}
		return True;	
	}

	private function _upload() {
		// hmm not sure about this yet, but lets rename the .state file so we can have daily states also
		rename($this->local_backup_dir.$this->incremental_state_file.".gpg", $this->local_backup_dir.$this->incremental_state_file_sync.".gpg");

		foreach ($this->transfer_method as $transfer_method) {
			switch ($transfer_method) {
				case 'scp':
					# code...
					print "not implemented yet..\n";
					break;
				case 'mega':
					$this->_upload_mega();
					break;
				default:
					print "like.. no?";
					break;
			}
		}
	}
	// Public Functions
	public function backup_run() {
		// Sanity Check
		// Todo: fugly..
		if (count($this->backup_dirs) == 0) { die("Error: set some backup dirs first..."); }
		if (!file_exists($this->local_backup_dir)) { die("Error: tmp backup storage directory missing.."); }
		if (file_exists($this->local_backup_dir.$this->backup_file)) { die("Error: Backup from today already exists?! Exit for now.."); }
		if (count($this->transfer_method) == 0) { die("Error: You need to set atleast 1 transfer method."); }

		// Start stuff..
		if (!$this->_create_archive()) { die("Error: creating the backup tar failed.."); }
		if (!$this->_create_crypt()) { die("Error: creating gpg blob failed.."); }

		// Transfer
		$this->_upload();

		// debug
		#$this->cleanup_afterwards = False;
	}

	public function backup_cleanup() {
		// Yeah.. need to figure this out sometime ;)
	}


}

?>
