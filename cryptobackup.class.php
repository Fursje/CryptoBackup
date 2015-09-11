<?php

/*
	CryptoBackup (v0.2.1 12/09/2015)
	Description: Simple script that allows you to create incremental crypto backups.

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

class cryptobackup {

	// Editable variables
	public $debug = True;
	public $log2disk = True;
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
		$time = time();
		$this->incremental_state_file = sprintf("backup-week-%d.state",date("W",$time));
		$this->_debug("__construct: ". $this->incremental_state_file);
		$this->incremental_state_file_sync = sprintf("backup-week-%d-%d-%s.state",date("W",time()),date("z",time()),$time);
		$this->_debug("__construct: ". $this->incremental_state_file_sync);
		$this->backup_file = sprintf("backup-week-%d-%d-%s.tgz",date("W",time()),date("z",time()),$time);
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
	public function addScpCredentials($hostname, $port, $username, $key_file, $remote_dir) {
		// Todo: SanityCheck; keyfile/remote_dir exists.
		if (file_exists($key_file)) {
			$this->addTransferMethod("scp");
			$this->method_scp[] = array(
				'hostname' => $hostname,
				'port' => $port,
				'username' => $username,
				'key_file' => $key_file,
				'remote_dir' => $remote_dir
			);
		}
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
		$debug_line = sprintf("[%s][DEBUG] %s \n",date("r",time()), $line);
		if ($this->debug) {
			print $debug_line;
		}
		if ($this->log2disk) {
			$file = dirname(__FILE__)."cryptobackup.log";
			file_put_contents($file,$debug_line,FILE_APPEND);
		}
	}

	// Remote Storage
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
	private function _upload_scp() {
		// Suppose we want to always transfer the .pgp files
		foreach ($this->method_scp as $key=>$value) {
			$cmdline = sprintf("/usr/bin/scp -i '%s' -P %d '%s' '%s' %s@%s:'%s'",$value['key_file'], $value['port'], $this->local_backup_dir.$this->backup_file.".gpg", $this->local_backup_dir.$this->incremental_state_file_sync.".gpg", $value['username'], $value['hostname'], $value['remote_dir']);
			$this->_debug("_upload_scp: $cmdline");
			$sysout = system($cmdline,$return_var);
			if ($return_var != 0) {
				$this->_debug("_upload_scp: upload might have failed for 1 or more files.. return var[".$return_var."]");
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
					$this->_upload_scp();
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

	private function _cleanup_files_check($files,&$cleanup_rm) {
		$cleanup_rm = array();
		$cleanup_good = array();
		$current_time = time();
		$cleanup_older_then = $current_time - ($this->keep_backup_weeks * 604800);
		$this->_debug("_cleanup_files_check: cleanup files; older then:[$cleanup_older_then] current_time[$current_time]");
		foreach ($files as $file) {
			if (preg_match("/^backup\-week\-([\d]{1,})\-[\d]{1,}\-([\d]{1,}).*$/",$file,$match)) {
				if ($match['2'] <= $cleanup_older_then) {
					// to old
					$this->_debug("_cleanup_files_check: file [$file] is to old.. so remove it.[".$match['2']."<=$cleanup_older_then]");
					$cleanup_rm[] = $file;
				} else {
					$cleanup_good[] = $file;
				}
			}
		}
		return True;
	}
	// Remote Cleanup..
	private function _cleanup_list_scp() {
		foreach ($this->method_scp as $key=>$value) {
			$remove_files = array();
			$remote_file = array();
			$cmdline = sprintf("/usr/bin/ssh -i '%s' %s@%s 'ls %s'", $value['key_file'], $value['username'], $value['hostname'], $value['remote_dir']);
			$this->_debug("_cleanup_list_scp: $cmdline");
			$sysout = array();
			exec($cmdline,$sysout,$return_var);
			if ($return_var != 0) {
				$this->_debug("_cleanup_list_scp: getting remote file list failed.. return var[".$return_var."]");
				continue;
			} else {
				if ($this->_cleanup_files_check($sysout,$remove_files)) {
					foreach ($remove_files as $file) {
						$remote_file = sprintf("'%s/%s'",$value['remote_dir'],$file);
						$cmdline = sprintf("/usr/bin/ssh -i '%s' %s@%s \"rm %s\"", $value['key_file'], $value['username'], $value['hostname'], $remote_file);
						$this->_debug("_cleanup_list_scp: $cmdline");
						exec($cmdline,$sysout,$return_var);
						if ($return_var != 0) {
							$this->_debug("_cleanup_list_scp: remote delete gave an error.. return var[".$return_var."]");
							continue;
						}
					}
				}
			}
		}
	}

	private function _cleanup_list_mega() {
		foreach ($this->method_mega as $key=>$value) {
			$remove_files = array();
			$remote_file = array();
			$cmdline = sprintf("/usr/bin/megals --no-ask-password --username %s --password %s -n %s", $value['username'], $value['password'], $value['remote_dir']);
			$this->_debug("_cleanup_list_mega: $cmdline");
			$sysout = array();
			exec($cmdline,$sysout,$return_var);
			if ($return_var != 0) {
				$this->_debug("_cleanup_list_mega: getting remote file list failed.. return var[".$return_var."]");
				continue;
			} else {
				if ($this->_cleanup_files_check($sysout,$remove_files)) {
					foreach ($remove_files as $file) {
						$cmdline = sprintf("/usr/bin/megarm --no-ask-password --username %s --password %s '%s'", $value['username'], $value['password'], $value['remote_dir'].$file);
						$this->_debug("_cleanup_list_mega: $cmdline");
						exec($cmdline,$sysout,$return_var);
						if ($return_var != 0) {
							$this->_debug("_cleanup_list_mega: remote delete gave an error.. return var[".$return_var."]");
							continue;
						}
					}
				}
			}
		}
	}
	public function backup_cleanup() {
		foreach ($this->transfer_method as $transfer_method) {
			switch ($transfer_method) {
				case 'scp':
					$this->_cleanup_list_scp();
					break;
				case 'mega':
					$this->_cleanup_list_mega();
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

		// automatically call the cleanup?
		# $this->backup_cleanup();
	}


}

?>