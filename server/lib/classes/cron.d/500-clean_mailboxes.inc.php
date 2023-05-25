<?php

/*
Copyright (c) 2020, Florian Schaal, schaal @it UG
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

class cronjob_clean_mailboxes extends cronjob {

	// should run before quota notify and backup
	// quota notify and backup is both '0 0 * * *' 
	
	// job schedule
	protected $_schedule = '00 22 * * *';

	/* this function is optional if it contains no custom code */
	public function onPrepare() {
		global $app;

		parent::onPrepare();
	}

	/* this function is optional if it contains no custom code */
	public function onBeforeRun() {
		global $app;

		return parent::onBeforeRun();
	}

	public function onRunJob() {
		global $app, $conf;

		$this->purge_junk_thrash();
		$this->purge_soft_deleted_maildir();
		$this->purge_mdbox_removed();

		parent::onRunJob();
	}

	private function purge_junk_thrash() {
		global $app, $conf;

		$trash_names = array('Trash', 'Papierkorb', 'Deleted Items', 'Deleted Messages', 'Corbeille');
		$junk_names = array('Junk', 'Junk Email', 'SPAM');

		$expunge_cmd = 'doveadm expunge -u ? mailbox ? sentbefore ';
		$purge_cmd = 'doveadm purge -u ?';
		$recalc_cmd = 'doveadm quota recalc -u ?';

		$server_id = intval($conf['server_id']);
		$records = $app->db->queryAllRecords("
						SELECT email, maildir, purge_trash_days, purge_junk_days, imap_prefix
						FROM mail_user
						WHERE maildir_format = 'maildir' AND disableimap = 'n' AND server_id = ?
							AND (purge_trash_days > 0 OR purge_junk_days > 0)",
						$server_id);
		
		if(is_array($records) && !empty($records)) {
			foreach($records as $email) {

				// Mapping function to add a prefix to all folder names.
				$prefix_folders = function($folder) use($email) {
					return $email['imap_prefix'] . $folder;
				};

				// Add a prefix to all folder names.
				$prefixed_trash_names = array_map($prefix_folders, $trash_names);
				$prefixed_junk_names = array_map($prefix_folders, $junk_names);

				if($email['purge_trash_days'] > 0) {
					foreach($prefixed_trash_names as $trash) {
						if(is_dir($email['maildir'].'/Maildir/.'.$trash)) {
							$app->system->exec_safe($expunge_cmd.intval($email['purge_trash_days']).'d', $email['email'], $trash);
						}
					}
				}
				if($email['purge_junk_days'] > 0) {
					foreach($prefixed_junk_names as $junk) {
						if(is_dir($email['maildir'].'/Maildir/.'.$junk)) {
							$app->system->exec_safe($expunge_cmd.intval($email['purge_junk_days']).'d', $email['email'], $junk);
						}
					}
				}
				$app->system->exec_safe($purge_cmd, $email['email']);
				$app->system->exec_safe($recalc_cmd, $email['email']);
			}
		}
	}

	// Purge soft deleted mailboxes.
	private function purge_soft_deleted_maildir() {
		global $app, $conf;
		$mail_config = $app->getconf->get_server_config($conf["server_id"], 'mail');

		// Convert old values in mailbox_soft_delete field
		if(isset($mail_config['mailbox_soft_delete']) && $mail_config['mailbox_soft_delete'] == 'n') $mail_config['mailbox_soft_delete'] = 0;
		if(isset($mail_config['mailbox_soft_delete']) && $mail_config['mailbox_soft_delete'] == 'y') $mail_config['mailbox_soft_delete'] = 7;
		$mail_config['mailbox_soft_delete'] = intval($mail_config['mailbox_soft_delete']);


		if ($mail_config['mailbox_soft_delete'] > 0) {
			if(isset($mail_config['homedir_path']) || strlen($mail_config['homedir_path']) > 4) {
				$matched_dirs = glob($mail_config['homedir_path'] . "/*/[a-z0-9.-]*-deleted-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]");

				if (!empty($matched_dirs)) {
					$delay_days = $mail_config['mailbox_soft_delete'];
					foreach($matched_dirs as $dir) {
						$owner = posix_getpwuid(fileowner($dir));
						if (is_dir($dir) && is_array($owner) && $owner['name'] == 'vmail') {
							$mtime = filemtime($dir);
							if ($mtime < strtotime("-$delay_days days")) {
								// do remove
								$app->system->exec_safe('sudo -u vmail rm -rf ?', $dir);
							}
						}
					}
				}
			}
		}
	}

	// Remove messages with refcount=0 from mdbox files.
	private function purge_mdbox_removed() {
		global $app, $conf;

		$sql = "SELECT email FROM mail_user WHERE maildir_format = 'mdbox' AND server_id = ?";
		$records = $app->db->queryAllRecords($sql, $server_id);

		if(is_array($records)) {
			foreach($records as $rec) {
				$app->system->exec_safe("su -c ?", 'doveadm purge -u "' . $rec["email"] . '"');
			}
		}
	}

	/* this function is optional if it contains no custom code */
	public function onAfterRun() {
		global $app;

		parent::onAfterRun();
	}

}

?>
