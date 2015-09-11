# CryptoBackup
Simple script that allows you to create incremental crypto backups, and store it via/on (scp,mega.nz).

# Howto
Decrypt: 
 * gpg --decrypt --output test.tgz backup-week-37-251.tgz.gpg

# Dependencies
* GnuPG -> https://www.gnupg.org/
* MegaTools -> https://github.com/megous/megatools/ (if you use the mega backend)

# Todo
* Fix hardcoded program paths.
