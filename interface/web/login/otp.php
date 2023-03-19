<?php

/*
Copyright (c) 2021, Till Brehm, ISPConfig UG
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

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

// Check if we have an active users ession.
if($_SESSION['s']['user']['active'] == 1) {
	header('Location: /index.php');
	die();
}

// If we don't have a 2fa session go back to login page.
if(!isset($_SESSION['otp'])) {
	header('Location: index.php');
	die();
}

// Variables and settings.
$error = '';
$msg = '';
$max_session_code_retry = 3;
$max_global_code_retry = 10;
$otp_recovery_code_length = 32;

// CSRF Check if we got POST data.
if(count($_POST) >= 1) {
	$app->auth->csrf_token_check();
}

require ISPC_ROOT_PATH.'/web/login/lib/lang/'.$app->functions->check_language($conf['language']).'.lng';

function finish_2fa_success($msg = '') {
	global $app;
	$_SESSION['s'] = $_SESSION['s_pending'];
	unset($_SESSION['s_pending']);
	unset($_SESSION['otp']);
	$username = $_SESSION['s']['user']['username'];
	if (!empty($msg)) {
		$msg = ' ' . $msg;
	}
	$app->auth_log('Successful login for user \''. $username .'\'' . $msg . ' from '. $_SERVER['REMOTE_ADDR'] .' at '. date('Y-m-d H:i:s') . ' with session ID ' .session_id());
	$app->db->query('UPDATE `sys_user` SET otp_attempts=0 WHERE userid = ?', $_SESSION['s']['user']['userid']);
	session_write_close();
	header('Location: ../index.php');
	die();
}

// Handle recovery code
if(isset($_POST['code']) && strlen($_POST['code']) == $otp_recovery_code_length) {
	//* TODO Recovery code handling

	$user = $app->db->queryOneRecord('SELECT otp_attempts, otp_recovery FROM sys_user WHERE userid = ?', $_SESSION['s_pending']['user']['userid']);

	//* We allow one more try to enter recovery code
	if($user['otp_attempts'] > $max_global_code_retry + 1) {
		die("Sorry, contact your administrator.");
	}

	if (password_verify($_POST['code'], $user['otp_recovery'])) {
		finish_2fa_success('via 2fa recovery code');
	}
	else {
		$app->db->query('UPDATE `sys_user` SET otp_attempts=otp_attempts + 1 WHERE userid = ?', $_SESSION['s_pending']['user']['userid']);
	}
}


// Begin 2fa via Email.
if($_SESSION['otp']['type'] == 'email') {

	//* Email 2fa handler settings
	$max_code_resend = 3;
	$max_time = 600; // time in seconds until the code gets invalidated
	$code_length = 6;

	if(isset($_POST['code']) && strlen($_POST['code']) == $code_length && isset($_SESSION['otp']['code_hash'])) {

		$user = $app->db->queryOneRecord('SELECT otp_attempts FROM sys_user WHERE userid = ?', $_SESSION['s_pending']['user']['userid']);

		//* Check if we reached limits
		if($_SESSION['otp']['sent'] > $max_code_resend
			|| $_SESSION['otp']['session_attempts'] > $max_session_code_retry
			|| $user['otp_attempts'] > $max_global_code_retry
			|| time() > $_SESSION['otp']['starttime'] + $max_time
			) {
			unset($_SESSION['otp']);
			unset($_SESSION['s_pending']);
			$app->error('2FA failed','index.php');
		}

		//* 2fa success
		if(password_verify($_POST['code'], $_SESSION['otp']['code_hash'])) {
			finish_2fa_success('with 2fa');
		} else {
			//* 2fa wrong code
			$_SESSION['otp']['session_attempts']++;
			$app->db->query('UPDATE `sys_user` SET otp_attempts=otp_attempts + 1 WHERE userid = ?', $_SESSION['s_pending']['user']['userid']);
		}
	}

	// Send code via email.
	if (!isset($_SESSION['otp']['sent']) || $_GET['action'] == 'resend') {

		$mail_otp_code_retry_timeout = 30;
		if (isset($_SESSION['otp']['starttime']) && $_SESSION['otp']['starttime'] > time() - $mail_otp_code_retry_timeout) {
			$token_sent_message = sprintf($wb['otp_code_email_sent_wait_txt'], $mail_otp_code_retry_timeout);
		}
		else {

			// Generate new code
			$new_otp_code = random_int(100000, 999999);
			$_SESSION['otp']['code_hash'] = password_hash($new_otp_code, PASSWORD_DEFAULT);
			//$_SESSION['otp']['code_debug'] = $new_otp_code; # for DEBUG only.
			$_SESSION['otp']['starttime'] = time();

			// Ensure that code is not sent too often
			if(isset($_SESSION['otp']['sent']) && $_SESSION['otp']['sent'] > $max_code_resend) {
				$app->error('Code resend limit reached', 'index.php');
			}

			$app->uses('functions');
			$app->uses('getconf');
			$server_config_array = $app->getconf->get_global_config();

			$app->uses('getconf,ispcmail');
			$mail_config = $server_config_array['mail'];
			if($mail_config['smtp_enabled'] == 'y') {
				$mail_config['use_smtp'] = true;
				$app->ispcmail->setOptions($mail_config);
			}

			$clientuser = $app->db->queryOneRecord('SELECT email FROM sys_user u LEFT JOIN client c ON (u.client_id=c.client_id) WHERE u.userid = ?', $_SESSION['s_pending']['user']['userid']);
			if (!empty($clientuser['email'])) {
				$email_to = $clientuser['email'];
			}
			else {
				// Admin users are not related to a client, thus use the globally configured email address.
				$email_to = $mail_config['admin_mail'];
			}

			$app->ispcmail->setSender($mail_config['admin_mail'], $mail_config['admin_name']);
			$app->ispcmail->setSubject($wb['otp_code_email_subject_txt']);
			$app->ispcmail->setMailText(sprintf($wb['otp_code_email_template_txt'], $new_otp_code));
			$send_result = $app->ispcmail->send($email_to);
			$app->ispcmail->finish();

			if ($send_result) {

				// Increase sent counter.
				if(!isset($_SESSION['otp']['sent'])) {
					$_SESSION['otp']['sent'] = 1;
				} else {
					$_SESSION['otp']['sent']++;
				}

				$token_sent_message = $wb['otp_code_email_sent_txt'] . ' ' . $email_to;
			}
			else {
				$token_sent_message = sprintf($wb['otp_code_email_sent_failed_txt'], $email_to);
			}
		}
	}

	// Show form to enter email code
	// ... below

} else {
	$app->error('Otp method unknown', 'index.php');
}


$logo = $app->db->queryOneRecord("SELECT * FROM sys_ini WHERE sysini_id = 1");
if($logo['custom_logo'] != ''){
    $base64_logo_txt = $logo['custom_logo'];
} else {
    $base64_logo_txt = $logo['default_logo'];
}
$app->tpl->setVar('base64_logo_txt', $base64_logo_txt);

$app->tpl->setVar('current_theme', isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : 'default', true);
if (!empty($token_sent_message)) {
  $app->tpl->setVar('token_sent_message', $token_sent_message);
}

// Load templating system and lang file.
$app->uses('tpl');
$app->tpl->newTemplate('main_login.tpl.htm');
$app->tpl->setInclude('content_tpl', 'templates/otp.htm');


// SET csrf token.
$csrf_token = $app->auth->csrf_token_get('otp');
$app->tpl->setVar('_csrf_id',$csrf_token['csrf_id']);
$app->tpl->setVar('_csrf_key',$csrf_token['csrf_key']);
//$app->tpl->setVar('msg', print_r($_SESSION['otp'], 1)); // For DEBUG only.

$app->tpl->setVar($wb);

$app->tpl_defaults();
$app->tpl->pparse();


?>
