<?php

/*
	Form Definition

	Tabledefinition

	Datatypes:
	- INTEGER (Forces the input to Int)
	- DOUBLE
	- CURRENCY (Formats the values to currency notation)
	- VARCHAR (no format check, maxlength: 255)
	- TEXT (no format check)
	- DATE (Dateformat, automatic conversion to timestamps)

	Formtype:
	- TEXT (Textfield)
	- TEXTAREA (Textarea)
	- PASSWORD (Password textfield, input is not shown when edited)
	- SELECT (Select option field)
	- RADIO
	- CHECKBOX
	- CHECKBOXARRAY
	- FILE

	VALUE:
	- Wert oder Array

	Hint:
	The ID field of the database table is not part of the datafield definition.
	The ID field must be always auto incement (int or bigint).

	Search:
	- searchable = 1 or searchable = 2 include the field in the search
	- searchable = 1: this field will be the title of the search result
	- searchable = 2: this field will be included in the description of the search result


*/

$form["title"]   = "Client";
$form["description"]    = "";
$form["name"]   = "client";
$form["record_name_field"] = "username";
$form["action"]  = "client_edit.php";
$form["db_table"] = "client";
$form["db_table_idx"] = "client_id";
$form["db_history"] = "yes";
$form["tab_default"] = "info";
$form["list_default"] = "client_list.php";
$form["auth"]  = 'yes';

$form["auth_preset"]["userid"]  = 0; // 0 = id of the user, > 0 id must match with id of current user
$form["auth_preset"]["groupid"] = 0; // 0 = default groupid of the user, > 0 id must match with groupid of current user
$form["auth_preset"]["perm_user"] = 'riud'; //r = read, i = insert, u = update, d = delete
$form["auth_preset"]["perm_group"] = 'riud'; //r = read, i = insert, u = update, d = delete
$form["auth_preset"]["perm_other"] = ''; //r = read, i = insert, u = update, d = delete

//* Languages
$language_list = array();
$handle = @opendir(ISPC_ROOT_PATH.'/lib/lang');
while ($file = @readdir($handle)) {
	if ($file != '.' && $file != '..') {
		if(@is_file(ISPC_ROOT_PATH.'/lib/lang/'.$file) and substr($file, -4, 4) == '.lng') {
			$tmp = substr($file, 0, 2);
			$language_list[$tmp] = $tmp;
		}
	}
}

//* Load themes
$themes_list = array();
$handle = @opendir(ISPC_THEMES_PATH);
while ($file = @readdir($handle)) {
	if (substr($file, 0, 1) != '.') {
		if(@is_dir(ISPC_THEMES_PATH."/$file")) {
			if(!file_exists(ISPC_THEMES_PATH."/$file/ispconfig_version") || (@file_exists(ISPC_THEMES_PATH."/$file/ispconfig_version") && trim(@file_get_contents(ISPC_THEMES_PATH."/$file/ispconfig_version")) == ISPC_APP_VERSION)) {
				$themes_list[$file] = $file;
			}
		}
	}
}

$form["tabs"]['info'] = array (
	'title'  => "Info",
	'width'  => 100,
	'template'  => "templates/client_edit_info.htm",
	'fields'  => array ()
);
$form["tabs"]['address'] = array (
	'title'  => "Address",
	'width'  => 100,
	'template'  => "templates/client_edit_address.htm",
	'fields'  => array (
		//#################################
		// Begin Datatable fields
		//#################################
		'company_name' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'gender' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'SELECT',
			'default' => '',
			'value'  => array('' => '', 'm' => 'gender_m_txt', 'f' => 'gender_f_txt')
		),
		'contact_firstname' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 1,
			'filters'   => array( 0 => array( 'event' => 'SAVE',
												'type' => 'TRIM'),
								  1 => array( 'event' => 'SAVE',
												'type' => 'STRIPTAGS'),
								  2 => array( 'event' => 'SAVE',
												'type' => 'STRIPNL')
			),
		),
		'contact_name' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'validators' => array ( 0 => array ( 'type' => 'NOTEMPTY',
					'errmsg'=> 'contact_error_empty'),
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 1,
			'filters'   => array( 0 => array( 'event' => 'SAVE',
												'type' => 'TRIM'),
								  1 => array( 'event' => 'SAVE',
												'type' => 'STRIPTAGS'),
								  2 => array( 'event' => 'SAVE',
												'type' => 'STRIPNL')
			),
		),
		'customer_no' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'UNIQUE',
					'errmsg'=> 'customer_no_error_unique',
					'allowempty' => 'y'),
			),
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'username' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'NOTEMPTY',
					'errmsg'=> 'username_error_empty'),
				1 => array ( 'type' => 'CUSTOM',
					'class' => 'validate_client',
					'function' => 'username_unique',
					'errmsg'=> 'username_error_unique'),
				2 => array ( 'type' => 'CUSTOM',
					'class' => 'validate_client',
					'function' => 'username_collision',
					'errmsg'=> 'username_error_collision'),
				3 => array ( 'type' => 'REGEX',
					'regex' => '/^[\w\.\-\_]{0,64}$/',
					'errmsg'=> 'username_error_regex'),
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'password' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'PASSWORD',
			'validators' => array(
				0 => array(
					'type' => 'CUSTOM',
					'class' => 'validate_password',
					'function' => 'password_check',
					'errmsg' => 'weak_password_txt'
				)
			),
			'encryption'=> 'CRYPT',
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'language' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'SELECT',
			'validators' => array ( 0 => array ( 'type' => 'NOTEMPTY',
					'errmsg'=> 'language_error_empty'),
			),
			'default' => $conf["language"],
			'value'  => $language_list,
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'usertheme' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'SELECT',
			'default' => $conf["theme"],
			'value'  => $themes_list,
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'street' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'zip' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'city' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'state' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'country' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'SELECT',
                       'default' => (isset($conf['default_country'])) ? strtoupper($conf['default_country']) : ((isset($conf['language'])) ? strtoupper($conf['language']) : ''),
			'datasource' => array (  'type'          => 'SQL',
				'querystring'   => 'SELECT iso,printable_name FROM country ORDER BY printable_name ASC',
				'keyfield'      => 'iso',
				'valuefield'    => 'printable_name'
			),
			'value'  => ''
		),
		'telephone' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'mobile' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'fax' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'email' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array( 0 => array( 'event' => 'SAVE',
					'type' => 'IDNTOASCII'),
				1 => array( 'event' => 'SHOW',
					'type' => 'IDNTOUTF8'),
				2 => array( 'event' => 'SAVE',
					'type' => 'TOLOWER')
			),
			'validators' => array (
				0 => array ( 'type' => 'ISEMAILADDRESS', 'errmsg'=> 'email_error_isemail'),
				1 => array ( 'type' => 'NOTEMPTY',
					'errmsg'=> 'email_error_empty'),
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'internet' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => 'https://',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		// Deprecated
		'icq' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'vat_id' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'validators' => array (  0 => array ( 'type' => 'CUSTOM',
					'class' => 'validate_client',
					'function' => 'check_vat_id',
					'errmsg'=> 'invalid_vat_id'),
			),
			'filters'   => array( 0 => array( 	'event' => 'SAVE',
												'type' => 'TRIM'),
								1 => array( 	'event' => 'SAVE',
												'type' => 'TOUPPER'),
								2 => array( 	'event' => 'SAVE',
												'type' => 'NOWHITESPACE'),
								3 => array( 	'event' => 'SAVE',
												'type' => 'STRIPTAGS'),
								4 => array( 	'event' => 'SAVE',
												'type' => 'STRIPNL')
			),
		),
		'company_id' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'bank_account_owner' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'bank_account_number' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'bank_code' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'bank_name' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		'bank_account_iban' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'filters'   => array( 0 => array( 	'event' => 'SAVE',
												'type' => 'TRIM'),
								1 => array( 	'event' => 'SAVE',
												'type' => 'TOUPPER'),
								2 => array( 	'event' => 'SAVE',
												'type' => 'NOWHITESPACE'),
								3 => array( 	'event' => 'SAVE',
												'type' => 'STRIPTAGS'),
								4 => array( 	'event' => 'SAVE',
												'type' => 'STRIPNL')
			),
		),
		'bank_account_swift' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'filters'   => array( 0 => array( 	'event' => 'SAVE',
												'type' => 'TRIM'),
								1 => array( 	'event' => 'SAVE',
												'type' => 'TOUPPER'),
								2 => array( 	'event' => 'SAVE',
												'type' => 'NOWHITESPACE'),
								3 => array( 	'event' => 'SAVE',
												'type' => 'STRIPTAGS'),
								4 => array( 	'event' => 'SAVE',
												'type' => 'STRIPNL')
			),
		),
		'notes' => array (
			'datatype' => 'TEXT',
			'formtype' => 'TEXTAREA',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS')
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '',
			'maxlength' => '',
			'rows'  => '10',
			'cols'  => '30'
		),
		'paypal_email' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array( 0 => array( 'event' => 'SAVE',
					'type' => 'IDNTOASCII'),
				1 => array( 'event' => 'SHOW',
					'type' => 'IDNTOUTF8'),
				2 => array( 'event' => 'SAVE',
					'type' => 'TOLOWER')
			),
			'validators' => array (
				0 => array ( 'type' => 'ISEMAILADDRESS', 'allowempty' => 'y', 'errmsg'=> 'email_error_isemail'),
			),
			'default' => '',
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => '',
			'searchable' => 2
		),
		'agb' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'dsgvo' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'locked' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'canceled' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'added_date' => array (
			'datatype'	=> 'DATE',
			'formtype'	=> 'TEXT',
			'default'	=> date($app->lng('conf_format_dateshort')),
			'value'		=> '',
			'separator'	=> '',
			'width'		=> '15',
			'maxlength'	=> '15',
			'rows'		=> '',
			'cols'		=> ''
		),
		'added_by' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters'   => array(
					0 => array( 'event' => 'SAVE',
					'type' => 'STRIPTAGS'),
					1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => $_SESSION['s']['user']['username'],
			'value'  => '',
			'separator' => '',
			'width'  => '30',
			'maxlength' => '255',
			'rows'  => '',
			'cols'  => ''
		),
		//#################################
		// END Datatable fields
		//#################################
	)
);

$form["tabs"]['limits'] = array (
	'title'  => "Limits",
	'width'  => 80,
	'template'  => "templates/client_edit_limits.htm",
	'fields'  => array (
		//#################################
		// Begin Datatable fields
		//#################################
		'template_master' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'SELECT',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'master_templates'
			),
			'value'  => ''
		),
		'template_additional' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
		),
		'default_mailserver' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'SELECT',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'value'  => '',
			'name'  => 'default_mailserver'
		),
		'mail_servers' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'MULTIPLE',
			'separator' => ',',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'validators'    => array (  0 => array ( 'type' => 'CUSTOM',
					'class' => 'validate_client',
					'function' => 'check_used_servers',
					'errmsg'=> 'mail_servers_used'),
			),
			'value'  => '',
			'name'  => 'mail_servers'
		),
		'limit_maildomain' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_maildomain_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailbox' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailbox_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailalias' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailalias_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailaliasdomain' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailaliasdomain_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailmailinglist' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailmailinglist_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailforward' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailforward_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailcatchall' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailcatchall_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailrouting' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailrouting_error_notint'),
			),
			'default' => '0',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mail_wblist' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mail_wblist_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailfilter' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailfilter_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_fetchmail' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailfetchmail_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mailquota' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_mailquota_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_spamfilter_wblist' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_spamfilter_wblist_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_spamfilter_user' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_spamfilter_user_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_spamfilter_policy' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_spamfilter_policy_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_mail_backup' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'y',
			'valuelimit' => 'client:limit_mail_backup',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_relayhost' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_relayhost',
			'value'  => array(0 => 'n', 1 => 'y')
		),
        'default_xmppserver' => array (
            'datatype' => 'INTEGER',
            'formtype' => 'SELECT',
            'default' => '1',
            'datasource' => array (  'type' => 'CUSTOM',
                'class'=> 'custom_datasource',
                'function'=> 'client_servers'
            ),
            'value'  => '',
            'name'  => 'default_xmppserver'
        ),
        'xmpp_servers' => array (
            'datatype' => 'VARCHAR',
            'formtype' => 'MULTIPLE',
            'separator' => ',',
            'default' => '1',
            'datasource' => array (  'type' => 'CUSTOM',
                'class'=> 'custom_datasource',
                'function'=> 'client_servers'
            ),
            'validators'    => array (
                0 => array ( 'type' => 'CUSTOM',
                    'class' => 'validate_client',
                    'function' => 'check_used_servers',
                    'errmsg'=> 'xmpp_servers_used'),
            ),
            'value'  => '',
            'name'  => 'xmpp_servers'
        ),
        'limit_xmpp_domain' => array(
            'datatype' => 'INTEGER',
            'formtype' => 'TEXT',
            'validators' => array (  0 => array ( 'type' => 'ISINT',
                'errmsg'=> 'limit_xmpp_domain_error_notint'),
            ),
            'default' => '-1',
            'value'  => '',
            'separator' => '',
            'width'  => '10',
            'maxlength' => '10',
            'rows'  => '',
            'cols'  => ''
        ),
        'limit_xmpp_user' => array(
            'datatype' => 'INTEGER',
            'formtype' => 'TEXT',
            'validators' => array (  0 => array ( 'type' => 'ISINT',
                'errmsg'=> 'limit_xmpp_user_error_notint'),
            ),
            'default' => '-1',
            'value'  => '',
            'separator' => '',
            'width'  => '10',
            'maxlength' => '10',
            'rows'  => '',
            'cols'  => ''
        ),
        'limit_xmpp_muc' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default' => 'n',
			'valuelimit' => 'client:limit_xmpp_muc',
            'value'  => array(0 => 'n', 1 => 'y')
        ),
        'limit_xmpp_anon' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default' => 'n',
			'valuelimit' => 'client:limit_xmpp_anon',
            'value'  => array(0 => 'n', 1 => 'y')
        ),
        'limit_xmpp_vjud' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default' => 'n',
			'valuelimit' => 'client:limit_xmpp_vjud',
            'value'  => array(0 => 'n', 1 => 'y')
        ),
        'limit_xmpp_proxy' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default' => 'n',
			'valuelimit' => 'client:limit_xmpp_proxy',
            'value'  => array(0 => 'n', 1 => 'y')
        ),
        'limit_xmpp_status' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default' => 'n',
			'valuelimit' => 'client:limit_xmpp_status',
            'value'  => array(0 => 'n', 1 => 'y')
        ),
        'limit_xmpp_pastebin' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default' => 'n',
			'valuelimit' => 'client:limit_xmpp_pastebin',
            'value'  => array(0 => 'n', 1 => 'y')
        ),
        'limit_xmpp_httparchive' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default' => 'n',
			'valuelimit' => 'client:limit_xmpp_httparchive',
            'value'  => array(0 => 'n', 1 => 'y')
        ),
		'default_webserver' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'SELECT',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'value'  => '',
			'name'  => 'default_webserver'
		),
		'web_servers' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'MULTIPLE',
			'separator' => ',',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'validators'    => array (  0 => array ( 'type' => 'CUSTOM',
					'class' => 'validate_client',
					'function' => 'check_used_servers',
					'errmsg'=> 'web_servers_used'),
			),
			'value'  => '',
			'name'  => 'web_servers'
		),
		'limit_web_domain' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_web_domain_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_web_quota' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_web_quota_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'web_php_options' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOXARRAY',
			'validators' => array (  0 => array ( 'type' => 'NOTEMPTY',
					'errmsg'=> 'web_php_options_notempty'),
			),
			'default' => '',
			'separator' => ',',
			'valuelimit' => 'system:sites:web_php_options;client:web_php_options',
			'value'  => array('no' => 'Disabled', 'fast-cgi' => 'Fast-CGI', 'cgi' => 'CGI', 'mod' => 'Mod-PHP', 'suphp' => 'SuPHP', 'php-fpm' => 'PHP-FPM', 'hhvm' => 'HHVM')
		),
		'limit_cgi' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_cgi',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_ssi' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_ssi',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_perl' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_perl',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_ruby' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_ruby',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_python' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_python',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'force_suexec' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'y',
			'valuelimit' => 'client:force_suexec',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_hterror' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_hterror',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_wildcard' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_wildcard',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_ssl' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_ssl',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_ssl_letsencrypt' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_ssl_letsencrypt',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_web_aliasdomain' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_web_aliasdomain_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_web_subdomain' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_web_subdomain_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_ftp_user' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_ftp_user_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_shell_user' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_shell_user_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'ssh_chroot' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOXARRAY',
			'validators' => array (  0 => array ( 'type' => 'NOTEMPTY',
					'errmsg'=> 'ssh_chroot_notempty'),
			),
			'default' => '',
			'separator' => ',',
			'valuelimit' => 'client:ssh_chroot',
			'value'  => array('no' => 'None', 'jailkit' => 'Jailkit')
		),
		'limit_webdav_user' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_webdav_user_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_backup' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'y',
			'valuelimit' => 'client:limit_backup',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'limit_directive_snippets' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'CHECKBOX',
			'default' => 'n',
			'valuelimit' => 'client:limit_directive_snippets',
			'value'  => array(0 => 'n', 1 => 'y')
		),
		'default_dnsserver' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'SELECT',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'value'  => '',
			'name'  => 'default_dnsserver'
		),
		'dns_servers' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'MULTIPLE',
			'separator' => ',',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'validators'    => array (  0 => array ( 'type' => 'CUSTOM',
					'class' => 'validate_client',
					'function' => 'check_used_servers',
					'errmsg'=> 'dns_servers_used'),
			),
			'value'  => '',
			'name'  => 'dns_servers'
		),
		'limit_dns_zone' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_dns_zone_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'default_slave_dnsserver' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'SELECT',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'value'  => '',
			'name'  => 'default_slave_dnsserver'
		),
		'limit_dns_slave_zone' => array (
			'datatype'      => 'INTEGER',
			'formtype'      => 'TEXT',
			'validators'    => array (      0 => array (    'type'  => 'ISINT',
					'errmsg'=> 'limit_dns_slave_zone_error_notint'),
			),
			'default'       => '-1',
			'value'         => '',
			'separator'     => '',
			'width'         => '10',
			'maxlength'     => '10',
			'rows'          => '',
			'cols'          => ''
		),
		'limit_dns_record' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_dns_record_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_client' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_client_error_notint'),
			),
			'default' => '0',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'default_dbserver' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'SELECT',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'value'  => '',
			'name'  => 'default_dbserver'
		),
		'db_servers' => array (
			'datatype' => 'VARCHAR',
			'formtype' => 'MULTIPLE',
			'separator' => ',',
			'default' => '1',
			'datasource' => array (  'type' => 'CUSTOM',
				'class'=> 'custom_datasource',
				'function'=> 'client_servers'
			),
			'validators'    => array (  0 => array ( 'type' => 'CUSTOM',
					'class' => 'validate_client',
					'function' => 'check_used_servers',
					'errmsg'=> 'db_servers_used'),
			),
			'value'  => '',
			'name'  => 'db_servers'
		),
		'limit_database' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_database_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_database_user' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_database_user_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_database_quota' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_database_quota_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_cron' => array (
			'datatype'  => 'INTEGER',
			'formtype'  => 'TEXT',
			'validators'    => array (  0 => array (    'type'  => 'ISINT',
					'errmsg'=> 'limit_cron_error_notint'),
			),
			'default'   => '0',
			'value'     => '',
			'separator' => '',
			'width'     => '10',
			'maxlength' => '10',
			'rows'      => '',
			'cols'      => ''
		),
		'limit_cron_type' => array (
			'datatype'  => 'VARCHAR',
			'formtype'  => 'SELECT',
			'default'   => '',
			'valuelimit' => 'client:limit_cron_type',
			'value'     => array('full' => 'Full Cron', 'chrooted' => 'Chrooted Cron', 'url' => 'URL Cron')
		),
		'limit_cron_frequency' => array (
			'datatype'  => 'INTEGER',
			'formtype'  => 'TEXT',
			'validators'    => array (  0 => array (    'type'  => 'ISINT',
					'errmsg'=> 'limit_cron_error_frequency'),
			),
			'default'   => '-1',
			'value'     => '',
			'separator' => '',
			'width'     => '10',
			'maxlength' => '10',
			'rows'      => '',
			'cols'      => ''
		),
		'limit_traffic_quota' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_traffic_quota_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_openvz_vm' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_openvz_vm_error_notint'),
			),
			'default' => '0',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		'limit_openvz_vm_template_id' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'SELECT',
			'default' => '',
			'datasource' => array (  'type' => 'SQL',
				'querystring' => 'SELECT template_id,template_name FROM openvz_template WHERE 1 ORDER BY template_name',
				'keyfield'=> 'template_id',
				'valuefield'=> 'template_name'
			),
			'valuelimit' => 'client:limit_openvz_vm_template_id',
			'value'  => array(0 => ' ')
		),
		'limit_aps' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'ISINT',
					'errmsg'=> 'limit_aps_error_notint'),
			),
			'default' => '-1',
			'value'  => '',
			'separator' => '',
			'width'  => '10',
			'maxlength' => '10',
			'rows'  => '',
			'cols'  => ''
		),
		//#################################
		// END Datatable fields
		//#################################
	)
);

/*
$form["tabs"]['ipaddress'] = array (
	'title' 	=> "IP Addresses",
	'width' 	=> 100,
	'template' 	=> "templates/client_edit_ipaddress.htm",
	'fields' 	=> array (
	##################################
	# Beginn Datatable fields
	##################################
		'ip_address' => array (
			'datatype'	=> 'TEXT',
			'formtype'	=> 'CHECKBOXARRAY',
			'default'	=> '',
			'value'		=> array('192.168.0.1' => '192.168.0.1', '192.168.0.2' => '192.168.0.2'),
			'separator'	=> ';'
		),
	##################################
	# END Datatable fields
	##################################
	)
);
*/


?>
