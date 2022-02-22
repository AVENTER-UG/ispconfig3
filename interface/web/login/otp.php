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

//* Handle recovery code
if(isset($_POST['code']) && strlen($_POST['code']) == 32 && $_SESSION['otp']['recovery'])) {
	//* TODO Recovery code handling
	
	$user = $app->db->queryOneRecord('SELECT otp_attempts FROM sys_user WHERE userid = ?',$_SESSION['s_pending']['user']['userid']);
	
	//* We allow one more try to enter recovery code
	if($user['otp_attempts'] > $max_global_code_retry + 1) {
		
	}
	
	
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
		|| time() > $_SESSION['otp']['starttime'] + $max_time) {
			unset($_SESSION['otp']);
			unset($_SESSION['s_pending']);
			$app->error('2FA failed','index.php');
		}
		
		//* 2fa success
		if($_POST['code'] == $_SESSION['otp']['code']) {
			$_SESSION['s'] = $_SESSION['s_pending'];
			unset($_SESSION['s_pending']);
			unset($_SESSION['otp']);
			header('Location: ../index.php');
								die();
		} else {
			//* 2fa wrong code
			$_SESSION['otp']['session_attempts']++;
			$app->db->query()
		}
	}
	
	//* set code
	if(!isset($_SESSION['otp']['code']) || empty($_SESSION['otp']['code'])) {
		// TODO Code generator
		$_SESSION['otp']['code'] = 123456;
		$_SESSION['otp']['starttime'] = time();
	}
	
	//* Send code via email
	if(!isset($_SESSION['otp']['sent']) || $_GET['action'] == 'resend') {
		
		//* Ensure that code is not sent too often
		if(isset($_SESSION['otp']['sent']) && $_SESSION['otp']['sent'] > $max_code_resend) {
			$app->error('Code resend limit reached','index.php');
		}
		
		$app->uses('functions');
		
		//* send email
		$email_to = $_SESSION['otp']['data'];
		$subject = 'ISPConfig Login authentication';
		$text = '';
		$from = 'root@localhost';
		
		$app->functions->mail($email_to, $subject, $text, $from);
		
		//* increase sent counter
		if(!isset($_SESSION['otp']['sent'])) {
			$_SESSION['otp']['sent'] = 1;
		} else {
			$_SESSION['otp']['sent']++;
		}
		
	}
	
	//* Show form to enter email code
	
	

} else {
	//* unsupported 2fa type
	$app->error('Code resend limit reached','index.php');
}





//* Load templating system and lang file
$app->uses('tpl');
$app->tpl->newTemplate('main_login.tpl.htm');
$app->tpl->setInclude('content_tpl', 'templates/otp.htm');

	
//* SET csrf token
$csrf_token = $app->auth->csrf_token_get('language_edit');
$app->tpl->setVar('_csrf_id',$csrf_token['csrf_id']);
$app->tpl->setVar('_csrf_key',$csrf_token['csrf_key']);


$app->load_language_file('web/login/lib/lang/'.$conf["language"].'.lng');





$app->tpl_defaults();
$app->tpl->pparse();


?>