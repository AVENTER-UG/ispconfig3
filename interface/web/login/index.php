<?php

/*
Copyright (c) 2005 - 2015, Till Brehm, ISPConfig UG
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

/**
 * @param app $app
 * @param $error
 * @param $conf
 * @param $module
 * @return void
 */
function process_login_request(app $app, &$error, $conf, $module)
{
	//** Check variables
	if (!preg_match("/^[\w\.\-\_\@]{1,128}$/", $app->functions->idn_encode($_POST['username']))) $error = $app->lng('user_regex_error');
	if (!preg_match("/^.{1,256}$/i", $_POST['password'])) $error = $app->lng('pw_error_length');

	//** importing variables
	$ip = md5($_SERVER['REMOTE_ADDR']);
	$username = $_POST['username'];
	$password = $_POST['password'];
	$loginAs = false;
	$time = time();

	if ($username == '' || $password == '' || $error != '') {
		//* Username or password empty
		if ($error == '') $error = $app->lng('error_user_password_empty');
		$app->plugin->raiseEvent('login_empty', $username);
		return;
	}

	$loginAs = is_login_as($app, $username, $password);

	//* Check if there are already wrong logins
	$sql = "SELECT * FROM `attempts_login` WHERE `ip`= ? AND  `login_time` > (NOW() - INTERVAL 1 MINUTE) LIMIT 1";
	$alreadyfailed = $app->db->queryOneRecord($sql, $ip);

	//* too many failed logins
	if ($alreadyfailed['times'] > 5) {
		$error = $app->lng('error_user_too_many_logins');
		return;
	}

	$user = validate_and_fetch_user($app, $username, $password, $loginAs, $conf);

	if ($user) {
		if ($user['active'] != 1) {
			$error = $app->lng('error_user_blocked');
			return;
		}

		// Maintenance mode - allow logins only when maintenance mode is off or if the user is admin
		if ($app->is_under_maintenance() && $user['typ'] != 'admin') return;

		if ($user['typ'] == 'admin' && !is_admin_ip_whitelisted($_SERVER['REMOTE_ADDR'], $conf)) {
			// TODO: if it's not a security risk (information disclosure) to
			// let the user know they are not whitelisted, then change this
			// error message to a more appropriate one
			$error = $app->lng('error_user_password_incorrect');
			return;
		}

		// User login right, so attempts can be deleted
		$sql = "DELETE FROM `attempts_login` WHERE `ip`=?";
		$app->db->query($sql, $ip);
		$user = $app->db->toLower($user);

		if ($loginAs) $oldSession = $_SESSION['s'];

		// Session regenerate causes login problems on some systems, see Issue #3827
		// Set session_regenerate_id to no in security settings, it you encounter
		// this problem.
		$app->uses('getconf');
		$security_config = $app->getconf->get_security_config('permissions');
		if (isset($security_config['session_regenerate_id']) && $security_config['session_regenerate_id'] == 'yes') {
			if (!$loginAs) session_regenerate_id(true);
		}
		$_SESSION = array();
		if ($loginAs) $_SESSION['s_old'] = $oldSession; // keep the way back!
		$_SESSION['s']['user'] = $user;
		$_SESSION['s']['user']['theme'] = isset($user['app_theme']) ? $user['app_theme'] : 'default';
		$_SESSION['s']['language'] = $app->functions->check_language($user['language']);
		$_SESSION["s"]['theme'] = $_SESSION['s']['user']['theme'];
		if ($loginAs) $_SESSION['s']['plugin_cache'] = $_SESSION['s_old']['plugin_cache'];

		if (is_file(ISPC_WEB_PATH.'/'.$_SESSION['s']['user']['startmodule'].'/lib/module.conf.php')) {
			include_once $app->functions->check_include_path(ISPC_WEB_PATH.'/'.$_SESSION['s']['user']['startmodule'].'/lib/module.conf.php');
			$menu_dir = ISPC_WEB_PATH.'/'.$_SESSION['s']['user']['startmodule'].'/lib/menu.d';
			include_menu_dir_files($menu_dir);
			$_SESSION['s']['module'] = $module;
		}
		// check if the user theme is valid
		if ($_SESSION['s']['user']['theme'] != 'default') {
			$tmp_path = ISPC_THEMES_PATH."/".$_SESSION['s']['user']['theme'];
			if (!@is_dir($tmp_path) || !@file_exists($tmp_path."/ispconfig_version") || trim(file_get_contents($tmp_path."/ispconfig_version")) != ISPC_APP_VERSION) {
				// fall back to default theme if this one is not compatible with current ispc version
				$_SESSION['s']['user']['theme'] = 'default';
				$_SESSION['s']['theme'] = 'default';
				$_SESSION['show_error_msg'] = $app->lng('theme_not_compatible');
			}
		}


		/*
		* We need LOGIN_REDIRECT instead of HEADER_REDIRECT to load the
		* new theme, if the logged-in user has another
		*/

		if ($loginAs) {
			echo 'LOGIN_REDIRECT:'.$_SESSION['s']['module']['startpage'];
			$app->plugin->raiseEvent('login', $username);
			$app->auth_log('Successful login for user \''. $username .'\' ' . $msg . ' from '. $_SERVER['REMOTE_ADDR'] .' at '. date('Y-m-d H:i:s') . ' with session ID ' .session_id());
			exit;
		} else {

			//* Do 2FA authentication
			if(isset($user['otp_type']) && $user['otp_type'] != 'none') {

				//* Save session in pending state and destroy original session
				$_SESSION['s_pending'] = $_SESSION['s'];
				unset($_SESSION['s']);

				//* Create OTP session
				$_SESSION['otp']['session_attempts'] = 0;
				$_SESSION['otp']['type'] = $user['otp_type'];
				$_SESSION['otp']['data'] = $user['otp_data'];
				//$_SESSION['otp']['recovery_debug'] = $user['otp_recovery']; // For DEBUG only.

				//* Redirect to otp script
				header('Location: otp.php');
				die();
			} else {
				$app->plugin->raiseEvent('login', $username);
				$app->auth_log('Successful login for user \''. $username .'\' ' . $msg . ' from '. $_SERVER['REMOTE_ADDR'] .' at '. date('Y-m-d H:i:s') . ' with session ID ' .session_id());
				header('Location: ../index.php');
				die();
			}
		}
	} else {
		if (!$alreadyfailed['times']) {
			//* user login the first time wrong
			$sql = "INSERT INTO `attempts_login` (`ip`, `times`, `login_time`) VALUES (?, 1, NOW())";
			$app->db->query($sql, $ip);
		} elseif ($alreadyfailed['times'] >= 1) {
			//* update times wrong
			$sql = "UPDATE `attempts_login` SET `times`=`times`+1, `login_time`=NOW() WHERE `ip` = ? AND `login_time` < NOW() ORDER BY `login_time` DESC LIMIT 1";
			$app->db->query($sql, $ip);
		}
		//* Incorrect login - Username and password incorrect
		$error = $app->lng('error_user_password_incorrect');
		if ($app->db->errorMessage != '') $error .= '<br />'.$app->db->errorMessage != '';

		$app->plugin->raiseEvent('login_failed', $username);
		$app->auth_log('Failed login for user \''. $username .'\' from '. $_SERVER['REMOTE_ADDR'] .' at '. date('Y-m-d H:i:s'));
	}
}

/**
 * Checks if the given admin's IP address is whitelisted.
 * @param string $ip
 * @return bool
 */
function is_admin_ip_whitelisted($ip, $conf)
{
	// if there is no config value, we assume that webmaster doesn't use this feature
	if (!isset($conf['admin_ip_whitelist_file'])) return true;

	// if the file doesn't exist, we assume that webmaster doesn't use this feature
	if (!file_exists($conf['admin_ip_whitelist_file'])) return true;

	$file_lines = file($conf['admin_ip_whitelist_file']);

	$matches = array_filter($file_lines, function($v) use ($ip) {
		$line = trim($v);

		// exclude empty lines and comments
		if ($line === '' || $line[0] === '#') return false;

		return ipv6_matches_cidr($ip, $line) || ipv4_matches_cidr($ip, $line);
	});

	return count($matches) > 0;
}

// based on https://www.php.net/manual/en/ref.network.php (comments)
/**
 * Checks if the given IPv4 address matches the given CIDR.
 * @param string $ip The IPv4 address.
 * @param string $cidr The CIDR in the IPv4 format.
 * @return bool
 */
function ipv4_matches_cidr ($ip, $cidr)
{
	if (strpos($ip, '.') === false) return false;

	list ($net, $mask) = explode ('/', $cidr);
	if (!$mask) $mask = 32;

	$ip_net = ip2long ($net);
	$ip_ip = ip2long ($ip);
	$ip_mask = ~((1 << (32 - $mask)) - 1);

	return (($ip_ip & $ip_mask) == ($ip_net & $ip_mask));
}

// based on https://stackoverflow.com/a/7951507/2428861
/**
 * Checks if the given IPv6 address matches the given CIDR.
 * @param string $ip The IPv6 address.
 * @param string $cidr The CIDR in the IPv6 format.
 * @return bool
 */
function ipv6_matches_cidr($ip, $cidr)
{
	if (strpos($ip, ':') === false) return false;

	list ($net, $mask) = explode('/', $cidr);
	if (!$mask) $mask = 128;

	$ip_net = in_addr_to_bitstring(inet_pton($net));
	$ip_ip = in_addr_to_bitstring(inet_pton($ip));

	return substr($ip_ip, 0, $mask) === substr($ip_net, 0, $mask);
}

/**
 * Converts the output of {@see inet_pton()} to string of bits.
 * @param string $in_addr The in_addr representation of the IP address.
 * @return string String of bits representing given in_addr representation of the IP address.
 */
function in_addr_to_bitstring($in_addr)
{
	$result = '';
	foreach (str_split($in_addr) as $c) {
		$result .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
	}
	return $result;
}

/**
 * Validates user credentials and fetches the user if validation succeeded
 * @param app $app
 * @param $username
 * @param $password
 * @param $loginAs
 * @param $conf
 * @return array | bool
 */
function validate_and_fetch_user(app $app, $username, $password, $loginAs, $conf)
{
	if ($loginAs) {
		$sql = "SELECT * FROM sys_user WHERE USERNAME = ? and PASSWORT = ?";
		return $app->db->queryOneRecord($sql, (string)$username, (string)$password);
	}

	if (stristr($username, '@')) {
		//* mailuser login
		$sql = "SELECT * FROM mail_user WHERE login = ? or email = ?";
		$mailuser = $app->db->queryOneRecord($sql, (string)$username, $app->functions->idn_encode($username));

		return $mailuser
			? build_fake_user($app, $username, $password, $mailuser, $conf)
			: false;
	}

	//* normal cp user login
	$sql = "SELECT * FROM sys_user WHERE USERNAME = ?";
	$user = $app->db->queryOneRecord($sql, (string)$username);
	if (!$user) return false;

	$saved_password = stripslashes($user['passwort']);
	if (substr($saved_password, 0, 1) == '$') {
		//* The password is encrypted with crypt
		return crypt(stripslashes($password), $saved_password) == $saved_password
			? $user
			: false;
	}

	//* The password is md5 encrypted
	if (md5($password) != $saved_password) return false;

	// update password with secure algo
	$sql = 'UPDATE `sys_user` SET `passwort` = ? WHERE `username` = ?';
	$app->db->query($sql, $app->auth->crypt_password($password), (string)$username);

	return $user;
}

/**
 * @param app $app
 * @param $username
 * @param $password
 * @param array $mailuser
 * @param array $user
 * @param $conf
 * @return array
 */
function build_fake_user(app $app, $username, $password, array $mailuser, $conf)
{
	$saved_password = stripslashes($mailuser['password']);
	//* Check if mailuser password is correct
	if (crypt(stripslashes($password), $saved_password) == $saved_password) {
		//* Get the sys_user language of the client of the mailuser
		$sys_user_lang = $app->db->queryOneRecord("SELECT language FROM sys_user WHERE default_group = ?", $mailuser['sys_groupid']);

		//* we build a fake user here which has access to the mailuser module only and userid 0
		$user = array();
		$user['userid'] = 0;
		$user['active'] = 1;
		$user['startmodule'] = 'mailuser';
		$user['modules'] = 'mailuser';
		$user['typ'] = 'user';
		$user['email'] = $mailuser['email'];
		$user['username'] = $username;
		if (is_array($sys_user_lang) && $sys_user_lang['language'] != '') {
			$user['language'] = $sys_user_lang['language'];
		} else {
			$user['language'] = $conf['language'];
		}
		$user['theme'] = $conf['theme'];
		$user['app_theme'] = $conf['theme'];
		$user['mailuser_id'] = $mailuser['mailuser_id'];
		$user['default_group'] = $mailuser['sys_groupid'];
	}

	return $user;
}

/**
 * Checks if there is a "login as" instead of a "normal" login
 * @param app $app
 * @param $username
 * @param $password
 * @return bool
 */
function is_login_as(app $app, $username, $password)
{
	if (isset($_SESSION['s']['user']) && $_SESSION['s']['user']['active'] == 1) {
		/*
		 * only the admin or reseller can "login as" so if the user is NOT an admin or reseller, we
		 * open the startpage (after killing the old session), so the user
		 * is logout and has to start again!
		 */
		if ($_SESSION['s']['user']['typ'] != 'admin' && !$app->auth->has_clients($_SESSION['s']['user']['userid'])) {
			/*
			 * The actual user is NOT a admin or reseller, but maybe he
			 * has logged in as "normal" user before...
			 */

			if (isset($_SESSION['s_old']) && ($_SESSION['s_old']['user']['typ'] == 'admin' || $app->auth->has_clients($_SESSION['s_old']['user']['userid']))) {
				/* The "old" user is admin or reseller, so everything is ok
				 * if he is reseller, we need to check if he logs in to one of his clients
				 */
				if ($_SESSION['s_old']['user']['typ'] != 'admin') {

					/* this is the one currently logged in (normal user) */
					$old_client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);
					$old_client = $app->db->queryOneRecord("SELECT client.client_id, client.parent_client_id FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = ?", $old_client_group_id);

					/* this is the reseller, that shall be re-logged in */
					$sql = "SELECT * FROM sys_user WHERE USERNAME = ? and PASSWORT = ?";
					$tmp = $app->db->queryOneRecord($sql, (string)$username, (string)$password);
					$client_group_id = $app->functions->intval($tmp['default_group']);
					$tmp_client = $app->db->queryOneRecord("SELECT client.client_id FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = ?", $client_group_id);

					if (!$tmp_client || $old_client["parent_client_id"] != $tmp_client["client_id"] || $tmp["default_group"] != $_SESSION["s_old"]["user"]["default_group"]) {
						die("You don't have the right to 'login as' this user!");
					}
					unset($old_client);
					unset($tmp_client);
					unset($tmp);
				}
			} else {
				die("You don't have the right to 'login as'!");
			}
		} elseif ($_SESSION['s']['user']['typ'] != 'admin' && (!isset($_SESSION['s_old']['user']) || $_SESSION['s_old']['user']['typ'] != 'admin')) {
			/* a reseller wants to 'login as', we need to check if he is allowed to */
			$res_client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);
			$res_client = $app->db->queryOneRecord("SELECT client.client_id FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = ?", $res_client_group_id);

			/* this is the user the reseller wants to 'login as' */
			$sql = "SELECT * FROM sys_user WHERE USERNAME = ? and PASSWORT = ?";
			$tmp = $app->db->queryOneRecord($sql, (string)$username, (string)$password);
			$tmp_client = $app->db->queryOneRecord("SELECT client.client_id, client.parent_client_id FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = ?", $tmp["default_group"]);

			if (!$tmp || $tmp_client["parent_client_id"] != $res_client["client_id"]) {
				die("You don't have the right to login as this user!");
			}
			unset($res_client);
			unset($tmp);
			unset($tmp_client);
		}
		$loginAs = true;

	} else {
		/* normal login */
		$loginAs = false;
	}

	return $loginAs;
}

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

include_once '../common.php';

// Check if we have an active users session and no login_as.
if ($_SESSION['s']['user']['active'] == 1 && @$_POST['login_as'] != 1) {
	header('Location: /index.php');
	die();
}

$app->uses('tpl');
$app->tpl->newTemplate('main_login.tpl.htm');
$app->tpl->setInclude('content_tpl', 'templates/index.htm');

$error = '';

$app->load_language_file('web/login/lib/lang/'.$conf["language"].'.lng');

// Maintenance mode
$maintenance_mode = false;
$maintenance_mode_error = '';
$server_config_array = $app->getconf->get_global_config('misc');
if ($app->is_under_maintenance()) {
	$maintenance_mode = true;
	$maintenance_mode_error = $app->lng('error_maintenance_mode');
}

//* Login Form was sent
if (count($_POST) > 0) {
	process_login_request($app, $error, $conf, $module);
}

// Maintenance mode - show message when people try to log in and also when people are forcibly logged off
if ($maintenance_mode_error != '') $error = '<strong>'.$maintenance_mode_error.'</strong><br><br>'.$error;
if ($error != '') {
	$error = '<div class="box box_error">'.$error.'</div>';
}

$app->load('getconf');
$sys_config = $app->getconf->get_global_config('misc');

$security_config = $app->getconf->get_security_config('permissions');
if ($security_config['password_reset_allowed'] == 'yes') {
	$app->tpl->setVar('pw_lost_show', 1);
} else {
	$app->tpl->setVar('pw_lost_show', 0);
}

$app->tpl->setVar('error', $error);
$app->tpl->setVar('error_txt', $app->lng('error_txt'));
$app->tpl->setVar('login_txt', $app->lng('login_txt'));
$app->tpl->setVar('pw_lost_txt', $app->lng('pw_lost_txt'));
$app->tpl->setVar('username_txt', $app->lng('username_txt'));
$app->tpl->setVar('password_txt', $app->lng('password_txt'));
$app->tpl->setVar('stay_logged_in_txt', $app->lng('stay_logged_in_txt'));
$app->tpl->setVar('login_button_txt', $app->lng('login_button_txt'));
$app->tpl->setVar('session_timeout', $server_config_array['session_timeout']);
$app->tpl->setVar('session_allow_endless', $server_config_array['session_allow_endless']);
//$app->tpl->setInclude('content_tpl', 'login/templates/index.htm');
$app->tpl->setVar('current_theme', isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : 'default', true);
//die(isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : 'default');

// Logo
$logo = $app->db->queryOneRecord("SELECT * FROM sys_ini WHERE sysini_id = 1");
if ($logo['custom_logo'] != '') {
	$base64_logo_txt = $logo['custom_logo'];
} else {
	$base64_logo_txt = $logo['default_logo'];
}
$tmp_base64 = explode(',', $base64_logo_txt, 2);
$logo_dimensions = $app->functions->getimagesizefromstring(base64_decode($tmp_base64[1]));
$app->tpl->setVar('base64_logo_width', $logo_dimensions[0].'px');
$app->tpl->setVar('base64_logo_height', $logo_dimensions[1].'px');
$app->tpl->setVar('base64_logo_txt', $base64_logo_txt);

// Title
if (!empty($sys_config['company_name'])) {
	$app->tpl->setVar('company_name', $sys_config['company_name'].' :: ');
}

// Custom Login
if ($sys_config['custom_login_text'] != '') {
	$custom_login = @($sys_config['custom_login_link'] != '') ? '<a href="'.$sys_config['custom_login_link'].'" target="_blank">'.$sys_config['custom_login_text'].'</a>' : $sys_config['custom_login_text'];
}
$app->tpl->setVar('custom_login', $custom_login);

$app->tpl_defaults();

$app->tpl->pparse();

?>
