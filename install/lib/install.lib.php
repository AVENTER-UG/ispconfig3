<?php

/*
Copyright (c) 2007, Till Brehm, projektfarm Gmbh
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
error_reporting(E_ALL|E_STRICT);

if(version_compare(phpversion(), '7.0', '<')) {
	require_once 'compatibility.inc.php';
}

$FILE = realpath('../install.php');

//** Get distribution identifier
//** IMPORTANT!
//   This is the same code as in server/lib/classes/monitor_tools.inc.php
//   So if you change it here, you also have to change it in there!
//
//	This function returns a string that describes the installed
//	Linux distribution. e.g. debian40 for Debian GNU/Linux 4.0

function get_distname() {

	$distname = '';
	$distver = '';
	$distid = '';
	$distbaseid = '';

	//** Debian or Ubuntu
	if(file_exists('/etc/debian_version')) {
		
		// Check if this is Ubuntu and not Debian
		if (strstr(trim(file_get_contents('/etc/issue')), 'Ubuntu') || (is_file('/etc/os-release') && stristr(file_get_contents('/etc/os-release'), 'Ubuntu'))) {
			
			$issue = file_get_contents('/etc/issue');
			
			// Use content of /etc/issue file
			if(strstr($issue,'Ubuntu')) {
				if (strstr(trim($issue), 'LTS')) {
					$lts=" LTS";
				} else {
					$lts="";
				}

				$distname = 'Ubuntu';
				$distid = 'debian40';
				$distbaseid = 'debian';
				$ver = explode(' ', $issue);
				$ver = array_filter($ver);
				$ver = next($ver);
				$mainver = explode('.', $ver);
				$mainver = array_filter($mainver);
				$mainver = current($mainver).'.'.next($mainver);
			// Use content of /etc/os-release file
			} else {
				$os_release = file_get_contents('/etc/os-release');
				if (strstr(trim($os_release), 'LTS')) {
					$lts = " LTS";
				} else {
					$lts = "";
				}
				
				$distname = 'Ubuntu';
				$distid = 'debian60';
				$distbaseid = 'debian';

				preg_match("/.*VERSION=\"(.*)\".*/ui", $os_release, $ver);
				$ver = str_replace("LTS", "", $ver[1]);
				$ver = explode(" ", $ver, 2);
				$ver = reset($ver);
				$mainver = $ver;
				$mainver = explode('.', $ver);
				$mainver = array_filter($mainver);
				$mainver = current($mainver).'.'.next($mainver);
			}
			switch ($mainver){
			case "24.04":
				$relname = "(Noble Numbat)";
				$distconfid = 'ubuntu2404';
				break;
			case "22.04":
				$relname = "(Jammy Jellyfish)";
				$distconfid = 'ubuntu2204';
				break;
			case "20.04":
				$relname = "(Focal Fossa)";
				$distconfid = 'ubuntu2004';
				break;
			case "18.04":
				$relname = "(Bionic Beaver)";
				$distconfid = 'ubuntu1804';
				break;
			case "17.10":
				$relname = "(Artful Aardvark)";
				$distconfid = 'ubuntu1710';
				break;
			case "17.04":
				$relname = "(Zesty Zapus)";
				$distconfid = 'ubuntu1604';
				break;
			case "16.10":
				$relname = "(Yakkety Yak)";
				$distconfid = 'ubuntu1604';
				break;
			case "16.04":
				$relname = "(Xenial Xerus)";
				$distconfid = 'ubuntu1604';
				break;
			case "15.10":
				$relname = "(Wily Werewolf)";
				break;
			case "15.04":
				$relname = "(Vivid Vervet)";
				break;
			case "14.10":
				$relname = "(Utopic Unicorn)";
				break;
			case "14.04":
				$relname = "(Trusty Tahr)";
				break;
			case "13.10":
				$relname = "(Saucy Salamander)";
				break;
			case "13.04":
				$relname = "(Raring Ringtail)";
				break;
			case "12.10":
				$relname = "(Quantal Quetzal)";
				break;
			case "12.04":
				$relname = "(Precise Pangolin)";
				break;
			case "11.10":
				$relname = "(Oneiric Ocelot)";
				break;
			case "11.14":
				$relname = "(Natty Narwhal)";
				break;
			case "10.10":
				$relname = "(Maverick Meerkat)";
				break;
			case "10.04":
				$relname = "(Lucid Lynx)";
				break;
			case "9.10":
				$relname = "(Karmic Koala)";
				break;
			case "9.04":
				$relname = "(Jaunty Jackpole)";
				break;
			case "8.10":
				$relname = "(Intrepid Ibex)";
				break;
			case "8.04":
				$relname = "(Hardy Heron)";
				break;
			case "7.10":
				$relname = "(Gutsy Gibbon)";
				break;
			case "7.04":
				$relname = "(Feisty Fawn)";
				break;
			case "6.10":
				$relname = "(Edgy Eft)";
				break;
			case "6.06":
				$relname = "(Dapper Drake)";
				break;
			case "5.10":
				$relname = "(Breezy Badger)";
				break;
			case "5.04":
				$relname = "(Hoary Hedgehog)";
				break;
			case "4.10":
				$relname = "(Warty Warthog)";
				break;
			default:
				$relname = "UNKNOWN";
				$distconfid = 'ubuntu2004';
			}
			$distver = $ver.$lts." ".$relname;
			swriteln("Operating System: ".$distname.' '.$distver."\n");
		} elseif(trim(file_get_contents('/etc/debian_version')) == '4.0') {
			$distname = 'Debian';
			$distver = '4.0';
			$distid = 'debian40';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian 4.0 or compatible\n");
		} elseif(strstr(trim(file_get_contents('/etc/debian_version')), '5.0')) {
			$distname = 'Debian';
			$distver = 'Lenny';
			$distid = 'debian40';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian Lenny or compatible\n");
		} elseif(strstr(trim(file_get_contents('/etc/debian_version')), '6.0') || trim(file_get_contents('/etc/debian_version')) == 'squeeze/sid') {
			$distname = 'Debian';
			$distver = 'Squeeze/Sid';
			$distid = 'debian60';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian 6.0 (Squeeze/Sid) or compatible\n");
		} elseif(strstr(trim(file_get_contents('/etc/debian_version')), '7.0') || substr(trim(file_get_contents('/etc/debian_version')),0,2) == '7.' || trim(file_get_contents('/etc/debian_version')) == 'wheezy/sid') {
			$distname = 'Debian';
			$distver = 'Wheezy/Sid';
			$distid = 'debian60';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian 7.0 (Wheezy/Sid) or compatible\n");
		} elseif(substr(trim(file_get_contents('/etc/debian_version')),0,1) == '8') {
			$distname = 'Debian';
			$distver = 'Jessie';
			$distid = 'debian60';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian 8.0 (Jessie) or compatible\n");
		} elseif(substr(trim(file_get_contents('/etc/debian_version')),0,1) == '9') {
			$distname = 'Debian';
			$distver = 'Stretch';
			$distconfid = 'debian90';
			$distid = 'debian60';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian 9.0 (Stretch) or compatible\n");
		} elseif(substr(trim(file_get_contents('/etc/debian_version')),0,2) == '10') {
			$distname = 'Debian';
			$distver = 'Buster';
			$distconfid = 'debian100';
			$distid = 'debian60';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian 10.0 (Buster) or compatible\n");
		} elseif(substr(trim(file_get_contents('/etc/debian_version')),0,2) == '11') {
			$distname = 'Debian';
			$distver = 'Bullseye';
			$distconfid = 'debian110';
			$distid = 'debian60';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian 11.0 (Bullseye) or compatible\n");
		} elseif(substr(trim(file_get_contents('/etc/debian_version')),0,2) == '12') {
			$distname = 'Debian';
			$distver = 'Bookworm';
			$distconfid = 'debian120';
			$distid = 'debian60';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian 12.0 (Bookworm) or compatible\n");
		} elseif(strstr(trim(file_get_contents('/etc/debian_version')), '/sid')) {
			$distname = 'Debian';
			$distver = 'Testing';
			$distid = 'debian60';
			$distconfid = 'debiantesting';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian Testing\n");
		} else {
			$distname = 'Debian';
			$distver = 'Unknown';
			$distid = 'debian60';
			$distconfid = 'debian120';
			$distbaseid = 'debian';
			swriteln("Operating System: Debian or compatible, unknown version.\n");
		}
	}

	//** OpenSuSE
	elseif(file_exists('/etc/SuSE-release')) {
		if(stristr(file_get_contents('/etc/SuSE-release'), '11.0')) {
			$distname = 'openSUSE';
			$distver = '11.0';
			$distid = 'opensuse110';
			$distbaseid = 'opensuse';
			swriteln("Operating System: openSUSE 11.0 or compatible\n");
		} elseif(stristr(file_get_contents('/etc/SuSE-release'), '11.1')) {
			$distname = 'openSUSE';
			$distver = '11.1';
			$distid = 'opensuse110';
			$distbaseid = 'opensuse';
			swriteln("Operating System: openSUSE 11.1 or compatible\n");
		} elseif(stristr(file_get_contents('/etc/SuSE-release'), '11.2')) {
			$distname = 'openSUSE';
			$distver = '11.2';
			$distid = 'opensuse112';
			$distbaseid = 'opensuse';
			swriteln("Operating System: openSUSE 11.2 or compatible\n");
		}  else {
			$distname = 'openSUSE';
			$distver = 'Unknown';
			$distid = 'opensuse112';
			$distbaseid = 'opensuse';
			swriteln("Operating System: openSUSE or compatible, unknown version.\n");
		}
	}

	//** RHEL (including compatible clones) & Fedora
	elseif(file_exists('/etc/redhat-release') && file_exists('/etc/os-release')) {

	$content = file_get_contents('/etc/os-release');

	preg_match('/(?<=PRETTY_NAME=\").+?(?=\")/', $content, $prettyname);
	preg_match('/(?<=NAME=\").+?(?=\")/', $content, $name);
	preg_match('/(?<=VERSION=\").+?(?=\")/', $content, $version);
	preg_match('/(?<=VERSION_ID=\").+?(?=\")/', $content, $versionid);

	if(stristr($prettyname[0], 'Fedora 32 (Thirty Two)')) {
		$distname = 'Fedora';
		$distver = '32';
		$distid = 'fedora32';
		$distbaseid = 'fedora';
		swriteln("Operating System: Fedora 32 or compatible\n");
	} elseif(stristr($prettyname[0], 'Fedora 33 (Thirty Three)')) {
		$distname = 'Fedora';
		$distver = '33';
		$distid = 'fedora33';
		$distbaseid = 'fedora';
		swriteln("Operating System: Fedora 33 or compatible\n");
	//** RHEL 7 and compatible clones
	} elseif(preg_match('/^(?:7|7\.[0-9]{1,2})$/', $versionid[0])) {
		preg_match_all('/([0-9]{1,2})\.?([0-9]{0,2})\.?([0-9]*)/', file_get_contents('/etc/redhat-release'), $centos7_version);
		$distname = $name[0];
		$distver = is_array($centos7_version)? implode('.', array_filter(array($centos7_version[1][0],$centos7_version[2][0],$centos7_version[3][0]),'strlen')) : $version[0];
		$distid = 'centos72';
		$distbaseid = 'fedora';
		swriteln("Operating System: " . $distname . " " .  $distver . "\n");
	//** RHEL 8 and compatible clones
	} elseif(preg_match('/^(?:8|8\.[0-9]{1,2})$/', $versionid[0])) {
		$distname = $name[0];
		$distver = $version[0];
		$distid = 'centos80';
		$distbaseid = 'fedora';
		swriteln("Operating System: " . $prettyname[0] . "\n");
	//** RHEL 9 and compatible clones
	} elseif(preg_match('/^(?:9|9\.[0-9]{1,2})$/', $versionid[0])) {
		$distname = $name[0];
		$distver = $version[0];
		$distid = 'centos90';
		$distbaseid = 'fedora';
		swriteln("Operating System: " . $prettyname[0] . "\n");
	} else {
		$distname = 'Redhat';
		$distver = 'Unknown';
		$distid = 'fedora9';
		$distbaseid = 'fedora';
		swriteln("Operating System: Redhat or compatible\n");
	}
	//** CentOS 6
	} elseif(file_exists('/etc/redhat-release') && !file_exists('/etc/os-release') && !file_exists('/etc/els-release')) {

		$content = file_get_contents('/etc/redhat-release');

		if(stristr($content, 'CentOS Linux release 6') || stristr($content, 'CentOS release 6')) {
			preg_match_all('/(6\.?([0-9]{0,2})\.?(\s)?([a-zA-Z()]+))$/', $content, $centos6_version);
			$distname = 'CentOS Linux';
			$distver = $centos6_version[0][0] ? $centos6_version[0][0] : '6';
			$distid = 'centos53';
			$distbaseid = 'fedora';
			swriteln("Operating System: " . $distname . " " .  $distver . "\n");
        } else {
			$distname = 'Redhat';
			$distver = 'Unknown';
			$distid = 'fedora9';
			$distbaseid = 'fedora';
		}
	//** CentOS 6 Extended Lifecycle Support by CloudLinux
	} elseif(file_exists('/etc/redhat-release') && file_exists('/etc/els-release') && !file_exists('/etc/os-release')) {

		$content = file_get_contents('/etc/els-release');

		if(stristr($content, 'CentOS Linux release 6') || stristr($content, 'CentOS release 6')) {
			preg_match_all('/(6)\.?([0-9]{0,2})?\.?\s([a-zA-Z(), ]+)?$/', $content, $centos6_version);
			$distname = 'CentOS Linux';
			$distver = $centos6_version[0][0] ? $centos6_version[0][0] : '6';
			$distid = 'centos53';
			$distbaseid = 'fedora';
			swriteln("Operating System: " . $distname . " " .  $distver . "\n");
		} else {
			$distname = 'Redhat';
			$distver = 'Unknown';
			$distid = 'fedora9';
			$distbaseid = 'fedora';
		}
	}

	//** Gentoo
	elseif(file_exists('/etc/gentoo-release')) {

		$content = file_get_contents('/etc/gentoo-release');

		preg_match_all('/([0-9]{1,2})/', $content, $version);
		$distname = 'Gentoo';
		$distver = $version[0][0].$version[0][1];
		$distid = 'gentoo';
		$distbaseid = 'gentoo';
		swriteln("Operating System: Gentoo $distver or compatible\n");

	} else {
		die('Unrecognized GNU/Linux distribution');
	}
	
	// Set $distconfid to distid, if no different id for the config is defined
	if(!isset($distconfid)) $distconfid = $distid;

	return array('name' => $distname, 'version' => $distver, 'id' => $distid, 'confid' => $distconfid, 'baseid' => $distbaseid);
}

function sread() {
	$input = fgets(STDIN);
	return rtrim($input);
}

function swrite($text = '') {
	echo $text;
}

function swriteln($text = '') {
	echo $text."\n";
}

function ilog($msg){
	exec("echo `date` \"- [ISPConfig] - \"".$msg.' >> '.ISPC_LOG_FILE);
}

function error($msg){
	ilog($msg);
	die($msg."\n");
}

function caselog($command, $file = '', $line = '', $success = '', $failure = ''){
	exec($command, $arr, $ret_val);
	$arr = NULL;
	if(!empty($file) && !empty($line)){
		$pre = $file.', Line '.$line.': ';
	} else {
		$pre = '';
	}
	if($ret_val != 0){
		if($failure == '') $failure = 'could not '.$command;
		ilog($pre.'WARNING: '.$failure);
	} else {
		if($success == '') $success = $command;
		ilog($pre.$success);
	}
}

function phpcaselog($ret_val, $msg, $file = '', $line = ''){
	if(!empty($file) && !empty($line)){
		$pre = $file.', Line '.$line.': ';
	} else {
		$pre = '';
	}
	if($ret_val == true){
		ilog($pre.$msg);
	} else {
		ilog($pre.'WARNING: could not '.$msg);
	}
	return $ret_val;
}

function mkdirs($strPath, $mode = '0755'){
	if(isset($strPath) && $strPath != ''){
		//* Verzeichnisse rekursiv erzeugen
		if(is_dir($strPath)){
			return true;
		}
		$pStrPath = dirname($strPath);
		if(!mkdirs($pStrPath, $mode)){
			return false;
		}
		$old_umask = umask(0);
		$ret_val = mkdir($strPath, octdec($mode));
		umask($old_umask);
		return $ret_val;
	}
	return false;
}

function rfsel($file, $file2) {
	clearstatcache();
	if(is_file($file)) return rf($file);
	else return rf($file2);
}

function rf($file){
	clearstatcache();
	if(is_file($file)) {
		if(!$fp = fopen($file, 'rb')){
			ilog('WARNING: could not open file '.$file);
		}
		return filesize($file) > 0 ? fread($fp, filesize($file)) : '';
	} else {
		return '';
	}
}

function wf($file, $content){
	if(!$ret_val = mkdirs(dirname($file))) return false;
	if(!$fp = fopen($file, 'wb')){
		ilog('WARNING: could not open file '.$file);
		// implicitly returned false because the following fwrite and fclose both fail,
		// but to be explicit:
		$ret_val = false;
	}
	fwrite($fp, $content) or $ret_val = false;
	fclose($fp) or $ret_val = false;
	return $ret_val;
}

function af($file, $content){
	if(!$ret_val = mkdirs(dirname($file))) return false;
	if(!$fp = fopen($file, 'ab')){
		ilog('WARNING: could not open file '.$file);
		$ret_val = false;
	}
	fwrite($fp, $content) or $ret_val = false;
	fclose($fp) or $ret_val = false;
	return $ret_val;
}

function aftsl($file, $content){
	$ret_val = true;
	if(!$fp = fopen($file, 'ab')){
		ilog('WARNING: could not open file '.$file);
		$ret_val = false;
	}
	fwrite($fp, $content) or $ret_val = false;
	fclose($fp) or $ret_val = false;
	return $ret_val;
}

function unix_nl($input){
	$output = str_replace("\r\n", "\n", $input);
	$output = str_replace("\r", "\n", $output);
	return $output;
}

function remove_blank_lines($input, $file = 1){
	//TODO ? Leerzeilen l�schen
	if($file){
		$content = unix_nl(rf($input)); // WTF -pedro !
	}else{
		$content = $input;
	}
	$lines = explode("\n", $content);
	$new_lines = array();
	if(!empty($lines)){
		foreach($lines as $line){
			if(trim($line) != '') $new_lines[] = $line;
		}
	}

	$content = implode("\n", $new_lines);

	if($file){
		wf($input, $content);
	}else{
		return $content;
	}
}

function no_comments($file, $comment = '#'){
	$content = unix_nl(rf($file));
	$lines = explode("\n", $content);
	if(!empty($lines)){
		foreach($lines as $line){
			if(strstr($line, $comment)){
				$pos = strpos($line, $comment);
				if($pos != 0){
					$new_lines[] = substr($line, 0, $pos);
				}else{
					$new_lines[] = '';
				}
			}else{
				$new_lines[] = $line;
			}
		}
	}
	if(is_array($new_lines)){
		$content_without_comments = implode("\n", $new_lines);
		$new_lines = NULL;
		return $content_without_comments;
	} else {
		return '';
	}
}

function comment_out($file, $string){
	$inhalt = no_comments($file);
	$gesamt_inhalt = rf($file);
	$modules = explode(',', $string);
	foreach($modules as $val){
		$val = trim($val);
		if(strstr($inhalt, $val)){
			$gesamt_inhalt = str_replace($val, '##ISPConfig INSTALL## '.$val, $gesamt_inhalt);
		}
	}
	wf($file, $gesamt_inhalt);
}

function is_word($string, $text, $params = ''){
	//* params: i ??
	return preg_match("/\b$string\b/$params", $text);
	/*
	if(preg_match("/\b$string\b/$params", $text)) {
		return true;
	} else {
		return false;
	}
	*/
}

function grep($content, $string, $params = ''){
	// params: i, v, w
	$content = unix_nl($content);
	$lines = explode("\n", $content);
	foreach($lines as $line){
		if(!strstr($params, 'w')){
			if(strstr($params, 'i')){
				if(strstr($params, 'v')){
					if(!stristr($line, $string)) $find[] = $line;
				} else {
					if(stristr($line, $string)) $find[] = $line;
				}
			} else {
				if(strstr($params, 'v')){
					if(!strstr($line, $string)) $find[] = $line;
				} else {
					if(strstr($line, $string)) $find[] = $line;
				}
			}
		} else {
			if(strstr($params, 'i')){
				if(strstr($params, 'v')){
					if(!is_word($string, $line, 'i')) $find[] = $line;
				} else {
					if(is_word($string, $line, 'i')) $find[] = $line;
				}
			} else {
				if(strstr($params, 'v')){
					if(!is_word($string, $line)) $find[] = $line;
				} else {
					if(is_word($string, $line)) $find[] = $line;
				}
			}
		}
	}
	if(is_array($find)){
		$ret_val = implode("\n", $find);
		if(substr($ret_val, -1) != "\n") $ret_val .= "\n";
		$find = NULL;
		return $ret_val;
	} else {
		return false;
	}
}

function edit_xinetd_conf($service){
	$xinetd_conf = '/etc/xinetd.conf';
	$contents = unix_nl(rf($xinetd_conf));
	$lines = explode("\n", $contents);
	$j = sizeof($lines);
	for($i=0;$i<sizeof($lines);$i++){
		if(grep($lines[$i], $service, 'w')){
			$fundstelle_anfang = $i;
			$j = $i;
			$parts = explode($lines[$i], $contents);
		}
		if($j < sizeof($lines)){
			if(strstr($lines[$i], '}')){
				$fundstelle_ende = $i;
				$j = sizeof($lines);
			}
		}
	}
	if(isset($fundstelle_anfang) && isset($fundstelle_ende)){
		for($i=$fundstelle_anfang;$i<=$fundstelle_ende;$i++){
			if(strstr($lines[$i], 'disable')){
				$disable = explode('=', $lines[$i]);
				$disable[1] = ' yes';
				$lines[$i] = implode('=', $disable);
			}
		}
	}
	$fundstelle_anfang = NULL;
	$fundstelle_ende = NULL;
	$contents = implode("\n", $lines);
	wf($xinetd_conf, $contents);
}

//* Converts a ini string to array
function ini_to_array($ini) {
	$config = array();
	$ini = str_replace("\r\n", "\n", $ini);
	$lines = explode("\n", $ini);
	foreach($lines as $line) {
		$line = trim($line);
		if($line != '') {
			if(preg_match("/^\[([\w\d_]+)\]$/", $line, $matches)) {
				$section = strtolower($matches[1]);
			} elseif(preg_match("/^([\w\d_]+)=(.*)$/", $line, $matches) && $section != null) {
				$item = trim($matches[1]);
				if(!isset($config[$section])) $config[$section] = array();
				$config[$section][$item] = trim($matches[2]);
			}
		}
	}
	return $config;
}


//* Converts a config array to a string
function array_to_ini($config_array) {
	$content = '';
	foreach($config_array as $section => $data) {
		$content .= "[$section]\n";
		foreach($data as $item => $value) {
			if($item != ''){
				$content .= "$item=$value\n";
			}
		}
		$content .= "\n";
	}
	return $content;
}

function is_user($user){
	global $mod;
	$user_datei = '/etc/passwd';
	$users = no_comments($user_datei);
	$lines = explode("\n", $users);
	if(is_array($lines)){
		foreach($lines as $line){
			if(trim($line) != ''){
				list($f1, $f2, $f3, $f4, $f5, $f6, $f7) = explode(':', $line);
				if($f1 == $user) return true;
			}
		}
	}
	return false;
}

function is_group($group){
	global $mod;
	$group_datei = '/etc/group';
	$groups = no_comments($group_datei);
	$lines = explode("\n", $groups);
	if(is_array($lines)){
		foreach($lines as $line){
			if(trim($line) != ''){
				list($f1, $f2, $f3, $f4) = explode(':', $line);
				if($f1 == $group) return true;
			}
		}
	}
	return false;
}

function replaceLine($filename, $search_pattern, $new_line, $strict = 0, $append = 1) {
	if($lines = @file($filename)) {
		$out = '';
		$found = 0;
		foreach($lines as $line) {
			if($strict == 0) {
				if(stristr($line, $search_pattern)) {
					$out .= $new_line."\n";
					$found = 1;
				} else {
					$out .= $line;
				}
			} else {
				if(trim($line) == $search_pattern) {
					$out .= $new_line."\n";
					$found = 1;
				} else {
					$out .= $line;
				}
			}
			if (!$found) {
				if (trim($line) == $new_line) {
					$found = 1;
				}
			}
		}
		if($found == 0) {
			//* add \n if the last line does not end with \n or \r
			if(substr($out, -1) != "\n" && substr($out, -1) != "\r") $out .= "\n";
			//* add the new line at the end of the file
			if($append == 1) $out .= $new_line."\n";
		}
		file_put_contents($filename, $out);
	}
}

function removeLine($filename, $search_pattern, $strict = 0) {
	if($lines = @file($filename)) {
		$out = '';
		foreach($lines as $line) {
			if($strict == 0) {
				if(!stristr($line, $search_pattern)) {
					$out .= $line;
				}
			} else {
				if(!trim($line) == $search_pattern) {
					$out .= $line;
				}
			}
		}
		file_put_contents($filename, $out);
	}
}

function hasLine($filename, $search_pattern, $strict = 0) {
	if($lines = @file($filename)) {
		foreach($lines as $line) {
			if($strict == 0) {
				if(stristr($line, $search_pattern)) {
					return true;
				}
			} else {
				if(trim($line) == $search_pattern) {
					return true;
				}
			}
		}
	}
	return false;
}

function is_installed($appname) {
	exec('which '.escapeshellcmd($appname).' 2> /dev/null', $out, $returncode);
	if(isset($out[0]) && stristr($out[0], $appname) && $returncode == 0) {
		return true;
	} else {
		return false;
	}
}

/*
* Get the port number of the ISPConfig controlpanel vhost
*/

function get_ispconfig_port_number() {
	global $conf;
	if(is_file($conf['nginx']['vhost_conf_dir'].'/ispconfig.vhost')) {
		$ispconfig_vhost_file = $conf['nginx']['vhost_conf_dir'].'/ispconfig.vhost';
		$regex = '/listen (\d+)/';
	} else {
		$ispconfig_vhost_file = $conf['apache']['vhost_conf_dir'].'/ispconfig.vhost';
		$regex = '/\<VirtualHost.*\:(\d{1,})\>/';
	}

	if(is_file($ispconfig_vhost_file)) {
		$tmp = file_get_contents($ispconfig_vhost_file);
		preg_match($regex, $tmp, $matches);
		$port_number = @intval($matches[1]);
		if($port_number > 0) {
			return $port_number;
		} else {
			return '8080';
		}
	}
}

/*
* Get the port number of the ISPConfig apps vhost
*/

function get_apps_vhost_port_number() {
	global $conf;
	if(is_file($conf['nginx']['vhost_conf_dir'].'/apps.vhost')) {
		$ispconfig_vhost_file = $conf['nginx']['vhost_conf_dir'].'/apps.vhost';
		$regex = '/listen (\d+)/';
	} else {
		$ispconfig_vhost_file = $conf['apache']['vhost_conf_dir'].'/apps.vhost';
		$regex = '/\<VirtualHost.*\:(\d{1,})\>/';
	}

	if(is_file($ispconfig_vhost_file)) {
		$tmp = file_get_contents($ispconfig_vhost_file);
		preg_match($regex, $tmp, $matches);
		$port_number = @intval($matches[1]);
		if($port_number > 0) {
			return $port_number;
		} else {
			return '8081';
		}
	}
}

/*
 * Check if SSL is anabled in the ISPConfig controlpanel vhost.
 */
function is_ispconfig_ssl_enabled() {
	global $conf;
	$ispconfig_vhost_file = $conf['apache']['vhost_conf_dir'].'/ispconfig.vhost';

	if(is_file($ispconfig_vhost_file)) {
		$tmp = file_get_contents($ispconfig_vhost_file);
		if(stristr($tmp, 'SSLCertificateFile')) {
			return true;
		} else {
			return false;
		}
	}
}

/*
* Is anonymization enabled in ispconfig.conf file
*/

function get_logging_state() {
	global $conf;
	$ispconfig_conf_file = $conf['apache']['vhost_conf_dir'].'/ispconfig.conf';

	if(is_file($ispconfig_conf_file)) {
		$tmp = file_get_contents($ispconfig_conf_file);
		if(stristr($tmp, '/usr/local/ispconfig/server/scripts/vlogger -p -s access.log')) {
			return 'anon';
		} elseif(stristr($tmp, '/usr/local/ispconfig/server/scripts/vlogger -s access.log')) {
			return 'yes';
		} else {
			return 'no';
		}
	} else {
		return 'yes';
	}
}

/**
 Function to find the hash file for timezone detection
 (c) 2012 Marius Cramer, pixcept KG, m.cramer@pixcept.de
 */


function find_hash_file($hash, $dir, $basedir = '') {
	$res = opendir($dir);
	if(!$res) return false;

	if(substr($basedir, -1) === '/') $basedir = substr($basedir, 0, strlen($basedir) - 1);
	if(substr($dir, -1) === '/') $dir = substr($dir, 0, strlen($dir) - 1);
	if($basedir === '') $basedir = $dir;

	while($cur = readdir($res)) {
		if($cur == '.' || $cur == '..') continue;
		$entry = $dir.'/'.$cur;
		if(is_dir($entry)) {
			$result = find_hash_file($hash, $entry, $basedir);
			if($result !== false) return $result;
		} elseif(md5_file($entry) === $hash) {
			$entry = substr($entry, strlen($basedir) + 1);
			if(substr($entry, 0, 7) === '/posix/') $entry = substr($entry, 7);
			return $entry;
		}
	}
	closedir($res);
	return false;
}


/**
 Function to get the timezone of the Linux system
 (c) 2012 Marius Cramer, pixcept KG, m.cramer@pixcept.de
 */
function get_system_timezone() {
	$timezone = false;
	if(file_exists('/etc/timezone') && is_readable('/etc/timezone')) {
		$timezone = trim(file_get_contents('/etc/timezone'));
		if(file_exists('/usr/share/zoneinfo/' . $timezone) == false) $timezone = false;
	}

	if(!$timezone && is_link('/etc/localtime')) {
		$timezone = readlink('/etc/localtime');
		$timezone = str_replace('/usr/share/zoneinfo/', '', $timezone);
		$timezone = str_replace('..', '', $timezone);
		if(substr($timezone, 0, 6) === 'posix/') $timezone = substr($timezone, 6);
	} elseif(!$timezone) {
		$hash = md5_file('/etc/localtime');
		$timezone = find_hash_file($hash, '/usr/share/zoneinfo');
	}

	if(!$timezone) {
		exec('date +%Z', $tzinfo);
		$timezone = $tzinfo[0];
	}
	
	if(substr($timezone, 0, 1) === '/') $timezone = substr($timezone, 1);

	return $timezone;
}

function getapacheversion($get_minor = false) {
	global $app;
	
	$cmd = '';
	if(is_installed('apache2ctl')) $cmd = 'apache2ctl -v';
	elseif(is_installed('apachectl')) $cmd = 'apachectl -v';
	else {
		ilog("Could not check apache version, apachectl not found.");
		return '2.2';
	}
	
	exec($cmd, $output, $return_var);
	if($return_var != 0 || !$output[0]) {
		ilog("Could not check apache version, apachectl did not return any data.");
		return '2.2';
	}
	
	if(preg_match('/version:\s*Apache\/(\d+)(\.(\d+)(\.(\d+))*)?(\D|$)/i', $output[0], $matches)) {
		return $matches[1] . (isset($matches[3]) ? '.' . $matches[3] : '') . (isset($matches[5]) && $get_minor == true ? '.' . $matches[5] : '');
	} else {
		ilog("Could not check apache version, did not find version string in apachectl output.");
		return '2.2';
	}
}

function getapachemodules() {
	global $app;
	
	$cmd = '';
	if(is_installed('apache2ctl')) $cmd = 'apache2ctl -t -D DUMP_MODULES';
	elseif(is_installed('apachectl')) $cmd = 'apachectl -t -D DUMP_MODULES';
	else {
		ilog("Could not check apache modules, apachectl not found.");
		return array();
	}
	
	exec($cmd . ' 2>/dev/null', $output, $return_var);
	if($return_var != 0 || !$output[0]) {
		ilog("Could not check apache modules, apachectl did not return any data.");
		return array();
	}
	
	$modules = array();
	for($i = 0; $i < count($output); $i++) {
		if(preg_match('/^\s*(\w+)\s+\((shared|static)\)\s*$/', $output[$i], $matches)) {
			$modules[] = $matches[1];
		}
	}
	
	return $modules;
}

?>
