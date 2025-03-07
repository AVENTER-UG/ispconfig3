<?php
require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

/******************************************
* Begin Form configuration
******************************************/

$list_def_file = "list/mail_user.list.php";

/******************************************
* End Form configuration
******************************************/

//* Check permissions for module
$app->auth->check_module_permissions('mail');

$app->load('listform_actions');


class list_action extends listform_actions {

	function onShow() {
		global $app, $conf;

		$app->uses('getconf');
		$mail_config = $app->getconf->get_global_config('mail');

		if($mail_config['mailboxlist_webmail_link'] == 'y') {
			$app->tpl->setVar('mailboxlist_webmail_link', 1);
		} else {
			$app->tpl->setVar('mailboxlist_webmail_link', 0);
		}

		if($mail_config["enable_custom_login"] == "y") {
			$app->tpl->setVar("enable_custom_login", 1);
		} else {
			$app->tpl->setVar("enable_custom_login", 0);
		}
		$app->tpl->setVar('mailbox_show_last_access', $mail_config['mailbox_show_last_access']);

		parent::onShow();
	}

}

$list = new list_action;
$list->SQLOrderBy = 'ORDER BY mail_user.email';
$list->onLoad();


?>
