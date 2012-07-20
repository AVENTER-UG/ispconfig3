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

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 'AS IS' AND
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

/*
	Form Definition

	Tabellendefinition

	Datentypen:
	- INTEGER (Wandelt Ausdr�cke in Int um)
	- DOUBLE
	- CURRENCY (Formatiert Zahlen nach W�hrungsnotation)
	- VARCHAR (kein weiterer Format Check)
	- TEXT (kein weiterer Format Check)
	- DATE (Datumsformat, Timestamp Umwandlung)

	Formtype:
	- TEXT (normales Textfeld)
	- TEXTAREA (normales Textfeld)
	- PASSWORD (Feldinhalt wird nicht angezeigt)
	- SELECT (Gibt Werte als option Feld aus)
	- RADIO
	- CHECKBOX
	- CHECKBOXARRAY
	- FILE

	VALUE:
	- Wert oder Array

	Hinweis:
	Das ID-Feld ist nicht bei den Table Values einzuf�gen.


*/

$form['title'] 		= 'tpl_default-v2_head_txt';
$form['description'] 	= 'tpl_default-v2_desc_txt';
$form['name'] 		= 'tpl_default-v2';
$form['action']		= 'tpl_default-v2.php';
$form['db_table']	= 'sys_user'; // needs to be 'sys_user_theme'
$form['db_table_idx']	= 'userid'; //??
$form["db_history"]	= "no";
$form['tab_default']	= 'main';
$form['list_default']	= 'index.php';
$form['auth']		= 'no'; //?

//* 0 = id of the user, > 0 id must match with id of current user
$form['auth_preset']['userid']  = 0; 
//* 0 = default groupid of the user, > 0 id must match with groupid of current user
$form['auth_preset']['groupid'] = 0; 

//** Permissions are: r = read, i = insert, u = update, d = delete
$form['auth_preset']['perm_user']  = 'riud';
$form['auth_preset']['perm_group'] = 'riud';
$form['auth_preset']['perm_other'] = '';

//* Pick out modules
//* TODO: limit to activated modules of the user
$modules_list = array();
$handle = @opendir(ISPC_WEB_PATH); 
while ($file = @readdir ($handle)) { 
    if ($file != '.' && $file != '..') {
        if(@is_dir(ISPC_WEB_PATH."/$file")) {
            if(is_file(ISPC_WEB_PATH."/$file/lib/module.conf.php") and $file != 'login' && $file != 'designer' && $file != 'mailuser') {
				$modules_list[$file] = $file;
			}
        }
	}
}

//* Languages
$language_list = array();
$handle = @opendir(ISPC_ROOT_PATH.'/lib/lang'); 
while ($file = @readdir ($handle)) { 
    if ($file != '.' && $file != '..') {
        if(@is_file(ISPC_ROOT_PATH.'/lib/lang/'.$file) and substr($file,-4,4) == '.lng') {
			$tmp = substr($file, 0, 2);
			$language_list[$tmp] = $tmp;
        }
	}
} 

//* Load themes
$themes_list = array();
$handle = @opendir(ISPC_THEMES_PATH); 
while ($file = @readdir ($handle)) { 
    if (substr($file, 0, 1) != '.') {
        if(@is_dir(ISPC_THEMES_PATH."/$file")) {
			$themes_list[$file] = $file;
        }
	}
}

$form['tabs']['main'] = array (
	'title' 	=> 'Settings',
	'width' 	=> 80,
	'template' 	=> 'templates/interface_settings.htm',
	'fields' 	=> array (
	##################################
	# Beginn Datenbankfelder
	##################################
                
	##################################
	# ENDE Datenbankfelder
	##################################
	)
);


?>
