<?php

/*
Copyright (c) 2024, Herman van Rink, Initfour websolutions
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

class cronjob_mailbox_stats_hourly extends cronjob {

	// job schedule
	protected $_schedule = '0 * * * *';
	protected $mailbox_traffic = array();
	protected $mail_boxes = array();
	protected $mail_rewrites = array();

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

		// Skip if no last access info needs to be shown.
		$mail_config = $app->getconf->get_global_config('mail');
		if (!isset($mail_config['mailbox_show_last_access']) || $mail_config['mailbox_show_last_access'] != 'y') {
			return;
		}

		$sql = "SELECT mailuser_id FROM mail_user WHERE server_id = ?";
		$records = $app->db->queryAllRecords($sql, $conf['server_id']);
    if(count($records) > 0) {
			$this->update_last_mail_login();
		}

		parent::onRunJob();
	}

	/* this function is optional if it contains no custom code */
	public function onAfterRun() {
		global $app;

		parent::onAfterRun();
	}

	// Parse the dovecot/postfix logs to update the last login time for mail_user's.
	private function update_last_mail_login() {
		global $app;

		// used for all monitor cronjobs
		$app->load('monitor_tools');
		$this->_tools = new monitor_tools();

		// Get the data of the log
		$log_lines = $this->_tools->_getLogData('log_mail', 10000000);

		$updatedUsers = [];

		// Loop over all lines.
		$line = strtok($log_lines, PHP_EOL);
		while ($line !== FALSE) {
			$matches = [];
			// Match pop3/imap logings, or alternately smtp logins.
			if (preg_match('/(.*) (imap|pop3)-login: Login: user=\<([\w\.@-]+)\>/', $line, $matches) || preg_match('/(.*) sasl_method=PLAIN, sasl_username=([\w\.@-]+)/', $line, $matches)) {
				$user = $matches[3] ?? $matches[2];
				$updatedUsers[] = $user;
			}

			// get the next line
			$line = strtok(PHP_EOL);
		}

		$uniqueUsers = array_unique($updatedUsers);

		$app->log('Updating last_access stats for ' . count($uniqueUsers) . ' mail users', LOGLEVEL_DEBUG);

		// Date/time rounded to hours.
		$now = time() - (time() % (60 * 60 * 24));
		$nowFormatted = date('Y-m-d H:i:s', $now);
		$sqlStatement = "UPDATE mail_user SET last_access=? WHERE email=?";

		// Save to master db.
		foreach ($uniqueUsers as $user) {
			$ret = $app->dbmaster->query($sqlStatement, $now, $user);
		}
	}
}
