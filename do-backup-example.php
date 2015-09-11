<?php

/*
 CryptoBackup (Example run)
*/

require_once(dirname(__FILE__)."/cryptobackup.class.php");

$bk = new cryptobackup();

// Added some directories to backup..
$bk->addDirectory('/tmp/dir1/');
$bk->addDirectory('/tmp/dir2/');

// Where to store the state file and create the temporary tar/gpg file.
$bk->local_backup_dir = "/home/backup-tmp/";

// Where to store it.
$bk->addMegaCredentials("mail@example.net", "password", "/Root/backup/");
$bk->addScpCredentials("server.example.net","22","root", "/home/user/Projects/CryptoBackup/scp.key", "/home/backup-test/");


$bk->gpg_recipients = "-r mail@example.net";

// Run... ;)
$bk->backup_run();

?>