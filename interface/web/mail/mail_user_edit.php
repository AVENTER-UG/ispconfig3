<?php
/*
Copyright (c) 2005, Till Brehm, projektfarm Gmbh
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


/******************************************
* Begin Form configuration
******************************************/

$tform_def_file = "form/mail_user.tform.php";

/******************************************
* End Form configuration
******************************************/

require_once('../../lib/config.inc.php');
require_once('../../lib/app.inc.php');

//* Check permissions for module
$app->auth->check_module_permissions('mail');

// Loading classes
$app->uses('tpl,tform,tform_actions');
$app->load('tform_actions');

class page_action extends tform_actions {
	
	
	function onShowNew() {
		global $app, $conf;
		
		// we will check only users, not admins
		if($_SESSION["s"]["user"]["typ"] == 'user') {
			
			// Get the limits of the client
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			$client = $app->db->queryOneRecord("SELECT limit_mailbox FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			
			// Check if the user may add another mailbox.
			if($client["limit_mailbox"] >= 0) {
				$tmp = $app->db->queryOneRecord("SELECT count(mailuser_id) as number FROM mail_user WHERE sys_groupid = $client_group_id");
				if($tmp["number"] >= $client["limit_mailbox"]) {
					$app->error($app->tform->wordbook["limit_mailbox_txt"]);
				}
			}
		}
		
		parent::onShowNew();
	}
	
	function onShowEnd() {
		global $app, $conf;
		
		$email = $this->dataRecord["email"];
		$email_parts = explode("@",$email);
		$app->tpl->setVar("email_local_part",$email_parts[0]);
		
		// Getting Domains of the user
		$sql = "SELECT domain FROM mail_domain WHERE ".$app->tform->getAuthSQL('r');
		$domains = $app->db->queryAllRecords($sql);
		$domain_select = '';
		if(is_array($domains)) {
			foreach( $domains as $domain) {
				$selected = ($domain["domain"] == @$email_parts[1])?'SELECTED':'';
				$domain_select .= "<option value='$domain[domain]' $selected>$domain[domain]</option>\r\n";
			}
		}
		$app->tpl->setVar("email_domain",$domain_select);
		unset($domains);
		unset($domain_select);
		
		// Get the spamfilter policys for the user
		$tmp_user = $app->db->queryOneRecord("SELECT policy_id FROM spamfilter_users WHERE email = '".$this->dataRecord["email"]."'");
		$sql = "SELECT id, policy_name FROM spamfilter_policy WHERE ".$app->tform->getAuthSQL('r');
		$policys = $app->db->queryAllRecords($sql);
		$policy_select = "<option value='0'>".$app->tform->wordbook["no_policy"]."</option>";
		if(is_array($policys)) {
			foreach( $policys as $p) {
				$selected = ($p["id"] == $tmp_user["policy_id"])?'SELECTED':'';
				$policy_select .= "<option value='$p[id]' $selected>$p[policy_name]</option>\r\n";
			}
		}
		$app->tpl->setVar("policy",$policy_select);
		unset($policys);
		unset($policy_select);
		unset($tmp_user);
		
		// Convert quota from Bytes to MB
		$app->tpl->setVar("quota",$this->dataRecord["quota"] / 1024 / 1024);
		
		parent::onShowEnd();
	}
	
	function onSubmit() {
		global $app, $conf;
		
		// Check if Domain belongs to user
		if(isset($_POST["email_domain"])) {
			$domain = $app->db->queryOneRecord("SELECT server_id, domain FROM mail_domain WHERE domain = '".$app->db->quote($_POST["email_domain"])."' AND ".$app->tform->getAuthSQL('r'));
			if($domain["domain"] != $_POST["email_domain"]) $app->tform->errorMessage .= $app->tform->wordbook["no_domain_perm"];
		}
		
		
		// if its an insert, check that the password is not empty
		if($this->id == 0 && $_POST["password"] == '') {
			$app->tform->errorMessage .= $app->tform->wordbook["error_no_pwd"]."<br>";
		}
		
		// Ccheck the client limits, if user is not the admin
		if($_SESSION["s"]["user"]["typ"] != 'admin') { // if user is not admin
			// Get the limits of the client
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			$client = $app->db->queryOneRecord("SELECT limit_mailbox, limit_mailquota FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			

			// Check if the user may add another mailbox.
			if($this->id == 0 && $client["limit_mailbox"] >= 0) {
				$tmp = $app->db->queryOneRecord("SELECT count(mailuser_id) as number FROM mail_user WHERE sys_groupid = $client_group_id");
				if($tmp["number"] >= $client["limit_mailbox"]) {
					$app->tform->errorMessage .= $app->tform->wordbook["limit_mailbox_txt"]."<br>";
				}
				unset($tmp);
			}
			
			// Check the quota and adjust
			if($client["limit_mailquota"] >= 0) {
				$tmp = $app->db->queryOneRecord("SELECT sum(quota) as mailquota FROM mail_user WHERE mailuser_id != ".intval($this->id)." AND sys_groupid = $client_group_id");
				$mailquota = $tmp["mailquota"] / 1024 / 1024;
				$new_mailbox_quota = intval($this->dataRecord["quota"]);
				if($mailquota + $new_mailbox_quota > $client["limit_mailquota"]) {
					$max_free_quota = $client["limit_mailquota"] - $mailquota;
					$app->tform->errorMessage .= $app->tform->wordbook["limit_mailquota_txt"].": ".$max_free_quota."<br>";
					// Set the quota field to the max free space
					$this->dataRecord["quota"] = $max_free_quota;
				}
				unset($tmp);
				unset($tmp_quota);
			}
		} // end if user is not admin
		

		// compose the email field
		if(isset($_POST["email_local_part"]) && isset($_POST["email_domain"])) {
			$this->dataRecord["email"] = $_POST["email_local_part"]."@".$_POST["email_domain"];
		
			// Set the server id of the mailbox = server ID of mail domain.
			$this->dataRecord["server_id"] = $domain["server_id"];
		
			unset($this->dataRecord["email_local_part"]);
			unset($this->dataRecord["email_domain"]);
		
			// Convert quota from MB to Bytes
			$this->dataRecord["quota"] = $this->dataRecord["quota"] * 1024 * 1024;
		
			// setting Maildir, Homedir, UID and GID
			$app->uses('getconf');
			$mail_config = $app->getconf->get_server_config($domain["server_id"],'mail');
			$maildir = str_replace("[domain]",$domain["domain"],$mail_config["maildir_path"]);
			$maildir = str_replace("[localpart]",$_POST["email_local_part"],$maildir);
			$this->dataRecord["maildir"] = $maildir;
			$this->dataRecord["homedir"] = $mail_config["homedir_path"];
			$this->dataRecord["uid"] = $mail_config["mailuser_uid"];
			$this->dataRecord["gid"] = $mail_config["mailuser_gid"];
		}

		
		parent::onSubmit();
	}
	
	function onAfterInsert() {
		global $app, $conf;
		
		// Set the domain owner as mailbox owner
		$domain = $app->db->queryOneRecord("SELECT sys_groupid, server_id FROM mail_domain WHERE domain = '".$app->db->quote($_POST["email_domain"])."' AND ".$app->tform->getAuthSQL('r'));
		$app->db->query("UPDATE mail_user SET sys_groupid = ".$domain["sys_groupid"]." WHERE mailuser_id = ".$this->id);
		
		// send a welcome email to create the mailbox
		mail($this->dataRecord["email"],$app->tform->wordbook["welcome_mail_subject"],$app->tform->wordbook["welcome_mail_message"]);
		
		// Spamfilter policy
		$policy_id = intval($this->dataRecord["policy"]);
		if($policy_id > 0) {
			$tmp_user = $app->db->queryOneRecord("SELECT id FROM spamfilter_users WHERE email = '".mysql_real_escape_string($this->dataRecord["email"])."'");
			if($tmp_user["id"] > 0) {
				// There is already a record that we will update
				$sql = "UPDATE spamfilter_users SET policy_id = $ploicy_id WHERE id = ".$tmp_user["id"];
				$app->db->query($sql);
			} else {
				// We create a new record
				$sql = "INSERT INTO `spamfilter_users` (`sys_userid`, `sys_groupid`, `sys_perm_user`, `sys_perm_group`, `sys_perm_other`, `server_id`, `priority`, `policy_id`, `email`, `fullname`, `local`) 
				        VALUES (".$_SESSION["s"]["user"]["userid"].", ".$domain["sys_groupid"].", 'riud', 'riud', '', ".$domain["server_id"].", 1, ".$policy_id.", '".mysql_real_escape_string($this->dataRecord["email"])."', '".mysql_real_escape_string($this->dataRecord["email"])."', 'Y')";
				$app->db->query($sql);
			}
		}  // endif spamfilter policy
		
	}
	
	function onAfterUpdate() {
		global $app, $conf;
		
		// Set the domain owner as mailbox owner
		if(isset($_POST["email_domain"])) {
			$domain = $app->db->queryOneRecord("SELECT sys_groupid, server_id FROM mail_domain WHERE domain = '".$app->db->quote($_POST["email_domain"])."' AND ".$app->tform->getAuthSQL('r'));
			$app->db->query("UPDATE mail_user SET sys_groupid = ".$domain["sys_groupid"]." WHERE mailuser_id = ".$this->id);
		
			// Spamfilter policy
			$policy_id = intval($this->dataRecord["policy"]);
			$tmp_user = $app->db->queryOneRecord("SELECT id FROM spamfilter_users WHERE email = '".mysql_real_escape_string($this->dataRecord["email"])."'");
			if($policy_id > 0) {
				if($tmp_user["id"] > 0) {
					// There is already a record that we will update
					$sql = "UPDATE spamfilter_users SET policy_id = $policy_id WHERE id = ".$tmp_user["id"];
					$app->db->query($sql);
				} else {
					// We create a new record
					$sql = "INSERT INTO `spamfilter_users` (`sys_userid`, `sys_groupid`, `sys_perm_user`, `sys_perm_group`, `sys_perm_other`, `server_id`, `priority`, `policy_id`, `email`, `fullname`, `local`) 
				        	VALUES (".$_SESSION["s"]["user"]["userid"].", ".$domain["sys_groupid"].", 'riud', 'riud', '', ".$domain["server_id"].", 1, ".$policy_id.", '".mysql_real_escape_string($this->dataRecord["email"])."', '".mysql_real_escape_string($this->dataRecord["email"])."', 'Y')";
					$app->db->query($sql);
				}
			}else {
				if($tmp_user["id"] > 0) {
					// There is already a record but the user shall have no policy, so we delete it
					$sql = "DELETE FROM spamfilter_users WHERE id = ".$tmp_user["id"];
					$app->db->query($sql);
				}
			} // endif spamfilter policy
		}
		
	}
	
}

$app->tform_actions = new page_action;
$app->tform_actions->onLoad();

?>