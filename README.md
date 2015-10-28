# CryptoBackup
Simple script that allows you to create incremental encrypted backups, and transfer it via scp or store it at mega.nz

# Howto
Decrypt: 
 * gpg --decrypt --output test.tgz backup-week-37-251.tgz.gpg

# Dependencies
* GnuPG -> https://www.gnupg.org/
* MegaTools -> https://github.com/megous/megatools/ (if you use the mega backend)

# Todo
* Fix hardcoded program paths.
* Add more backends? googledrive via https://github.com/odeke-em/drive
