<?php

/*
 CryptoBackup (v0.1 09/09/2015)
 Description: Simple script that allows you to create incremental crypto backups,
and store it through;
 - scp
 - mega
 - ?

 Howto 
  Decrypt: 
   # gpg --decrypt --output test.tgz backup-week-37-251.tgz.gpg

 Todo:
  create cleanup script
  rewrite some functions
  ...

 Dependencies:
  - https://github.com/megous/megatools/ (only if you use mega transfer method)
*/

class cryptobackup {

	// Editable variables
	public $debug = True;
	public $transfer_method = "mega"; // scp or mega
	public $local_backup_dir = "/tmp/backup_dir/";
	public $cleanup_afterwards = True;

	# Transfer methods
	public $method_scp = array('host'=>'','username'=>'','ssh_key'=>'','remote_dir'=>'');
	// Make sure the remote_dir exists
	public $method_mega = array('username'=>'','password'=>'','remote_dir'=>'/Root/backup/');

	# PGP Variables
	public $gpg_flags = "--no-default-recipient --force-mdc --encrypt";
	public $gpg_recipients = "-r user@example.net";

	# Backup Cleanup
	public $keep_backup_weeks = 3; // no im not going to change the week interval..

	// Rather not touch variables.. (warned you!)
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
		$cmdline = sprintf("/usr/bin/megaput --no-progress --path=%s %s %s",$this->method_mega['remote_dir'], $this->local_backup_dir.$this->backup_file.".gpg", $this->local_backup_dir.$this->incremental_state_file_sync.".gpg");
		$this->_debug("_upload_mega: $cmdline");
		$sysout = system($cmdline,$return_var);
		if ($return_var != 0) {
			$this->_debug("_upload_mega: upload might have failed for 1 or more files.. return var[".$return_var."]");
			return False;
		}
		return True;	
	}

	private function _upload() {
		// hmm not sure about this yet, but lets rename the .state file so we can have daily states also
		rename($this->local_backup_dir.$this->incremental_state_file.".gpg", $this->local_backup_dir.$this->incremental_state_file_sync.".gpg");

		switch ($this->transfer_method) {
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
	// Public Functions
	public function backup_run() {
		if (count($this->backup_dirs) == 0) { die("\tset some backup dirs first..."); }
		if (!file_exists($this->local_backup_dir)) { die("\t tmp backup storage directory missing.."); }
		if (file_exists($this->local_backup_dir.$this->backup_file)) { die("Backup from today already exists?! Exit for now.."); }
		if (!$this->_create_archive()) { die("\t creating the backup tar failed.."); }
		if (!$this->_create_crypt()) { die("creating gpg blob failed.."); }

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
