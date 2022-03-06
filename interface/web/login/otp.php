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

//* Check if we have an active users ession
if($_SESSION['s']['user']['active'] == 1) {
	header('Location: /index.php');
	die();
}

//* If we don't have a 2fa session go back to login page
if(!isset($_SESSION['otp'])) {
	header('Location: index.php');
	die();
}

//* Variables and settings
$error = '';
$msg = '';
$max_session_code_retry = 3;
$max_global_code_retry = 10;


//* CSRF Check if we got POST data
if(count($_POST) >= 1) {
	$app->auth->csrf_token_check();
}


// FIXME What's the deal with otp_enabled=v ??



//* Handle recovery code
if(isset($_POST['code']) && strlen($_POST['code']) == 32 && $_SESSION['otp']['recovery']) {
	//* TODO Recovery code handling
	
	$user = $app->db->queryOneRecord('SELECT otp_attempts FROM sys_user WHERE userid = ?',$_SESSION['s_pending']['user']['userid']);
	
	//* We allow one more try to enter recovery code
	if($user['otp_attempts'] > $max_global_code_retry + 1) {
		
	}
	
	// show reset form to create a new 2fa secret?
	
	die('Handle recovery code');
}


//* Begin 2fa via Email
if($_SESSION['otp']['type'] == 'email') {
	
	//* Email 2fa handler settings
	$max_code_resend = 3;
	$max_time = 600; // time in seconds until the code gets invalidated
	$code_length = 6;
	
	if(isset($_POST['code']) && strlen($_POST['code']) == $code_length && isset($_SESSION['otp']['code'])) {
		
		if(strlen($_SESSION['otp']['code']) != $code_length) die(); // wrong code lenght, this should never happen
		
		$user = $app->db->queryOneRecord('SELECT otp_attempts FROM sys_user WHERE userid = ?',$_SESSION['s_pending']['user']['userid']);
		
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
		if($_POST['code'] == $_SESSION['otp']['code']) {
			$_SESSION['s'] = $_SESSION['s_pending'];
			// Reset the attempt counter.
			$app->db->query('UPDATE `sys_user` SET otp_attempts=0 WHERE userid = ?', $_SESSION['s']['user']['userid']);
			unset($_SESSION['s_pending']);
			unset($_SESSION['otp']);
			header('Location: ../index.php');
			die();
		} else {
			//* 2fa wrong code
			$_SESSION['otp']['session_attempts']++; // FIXME can't we skip this and rely on the DB only?
			$app->db->query('UPDATE `sys_user` SET otp_attempts=otp_attempts + 1 WHERE userid = ?', $_SESSION['s_pending']['user']['userid']);
		}
	}
	
	//* set code
	if(!isset($_SESSION['otp']['code']) || empty($_SESSION['otp']['code'])) {
		// Random int between 10^($code_length-1) and 10^$code_length
		$_SESSION['otp']['code'] = rand(pow(10, $code_length - 1), pow(10, $code_length) - 1);
		$_SESSION['otp']['starttime'] = time();
	}
	
	//* Send code via email
	if(!isset($_SESSION['otp']['sent']) || $_GET['action'] == 'resend') {
		
		//* Ensure that code is not sent too often
		if(isset($_SESSION['otp']['sent']) && $_SESSION['otp']['sent'] > $max_code_resend) {
			$app->error('Code resend limit reached','index.php');
		}
		
		$app->uses('functions');
		$app->uses('getconf');
		$system_config = $app->getconf->get_global_config();
		$from = $system_config['mail']['admin_mail'];


		//* send email
		$email_to = $_SESSION['otp']['data'];
		$subject = 'ISPConfig Login authentication';
		$text = 'Your One time login code is ' . $_SESSION['otp']['code'] . PHP_EOL
			. 'This code is valid for 10 minutes' .  PHP_EOL;
		
		$app->functions->mail($email_to, $subject, $text, $from);
		
		//* increase sent counter
		if(!isset($_SESSION['otp']['sent'])) {
			$_SESSION['otp']['sent'] = 1;
		} else {
			$_SESSION['otp']['sent']++;
		}
		
	}
	
	//* Show form to enter email code
	// ... below
	

} else {
	//* unsupported 2fa type
	$app->error('Code resend limit reached','index.php');
}


$logo = $app->db->queryOneRecord("SELECT * FROM sys_ini WHERE sysini_id = 1");
if($logo['custom_logo'] != ''){
    $base64_logo_txt = $logo['custom_logo'];
} else {
    $base64_logo_txt = $logo['default_logo'];
}
$app->tpl->setVar('base64_logo_txt', $base64_logo_txt);

$app->tpl->setVar('current_theme', isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : 'default', true);


//* Load templating system and lang file
$app->uses('tpl');
$app->tpl->newTemplate('main_login.tpl.htm');
$app->tpl->setInclude('content_tpl', 'templates/otp.htm');

	
//* SET csrf token
$csrf_token = $app->auth->csrf_token_get('language_edit');
$app->tpl->setVar('_csrf_id',$csrf_token['csrf_id']);
$app->tpl->setVar('_csrf_key',$csrf_token['csrf_key']);
#$app->tpl->setVar('msg', print_r($_SESSION['otp'], 1));


require ISPC_ROOT_PATH.'/web/login/lib/lang/'.$app->functions->check_language($conf['language']).'.lng';
$app->tpl->setVar($wb);





$app->tpl_defaults();
$app->tpl->pparse();


?>
