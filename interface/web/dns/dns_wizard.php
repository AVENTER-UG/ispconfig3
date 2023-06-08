<?php

/*
Copyright (c) 2008, Till Brehm, projektfarm Gmbh
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

//* Check permissions for module
$app->auth->check_module_permissions('dns');


// Loading the template
$app->uses('tpl,validate_dns,tform');
$app->tpl->newTemplate("form.tpl.htm");
$app->tpl->setInclude('content_tpl', 'templates/dns_wizard.htm');
$app->load_language_file('/web/dns/lib/lang/'.$_SESSION['s']['language'].'_dns_wizard.lng');

// Check if dns record limit has been reached. We will check only users, not admins
if($_SESSION["s"]["user"]["typ"] == 'user') {
	$app->tform->formDef['db_table_idx'] = 'id';
	$app->tform->formDef['db_table'] = 'dns_soa';
	if(!$app->tform->checkClientLimit('limit_dns_zone')) {
		$app->error($app->lng('limit_dns_zone_txt'));
	}
	if(!$app->tform->checkResellerLimit('limit_dns_zone')) {
		$app->error('Reseller: '.$app->lng('limit_dns_zone_txt'));
	}
}

// import variables
$template_id = (isset($_POST['template_id']))?$app->functions->intval($_POST['template_id']):0;
$sys_groupid = (isset($_POST['client_group_id']))?$app->functions->intval($_POST['client_group_id']):0;

// get the correct server_id
if (isset($_POST['server_id'])) {
	$server_id = $app->functions->intval($_POST['server_id']);
	$post_server_id = true;
} elseif (isset($_POST['server_id_value'])) {
	$server_id = $app->functions->intval($_POST['server_id_value']);
	$post_server_id = true;
} else {
	$settings = $app->getconf->get_global_config('dns');
	$server_id = $app->functions->intval($settings['default_dnsserver']);
	$post_server_id = false;
}

// Load the templates
$records = $app->db->queryAllRecords("SELECT * FROM dns_template WHERE visible = 'Y' ORDER BY name ASC");
$template_id_option = '';
$n = 0;
foreach($records as $rec){
	$checked = ($rec['template_id'] == $template_id)?' SELECTED':'';
	$template_id_option .= '<option value="'.$rec['template_id'].'"'.$checked.'>'.$rec['name'].'</option>';
	if($n == 0 && $template_id == 0) $template_id = $rec['template_id'];
	$n++;
}
unset($n);
$app->tpl->setVar("template_id_option", $template_id_option);

$app->uses('ini_parser,getconf');
$domains_settings = $app->getconf->get_global_config('domains');

// If the user is administrator
if($_SESSION['s']['user']['typ'] == 'admin') {

	// Load the list of servers
	$records = $app->db->queryAllRecords("SELECT server_id, server_name FROM server WHERE mirror_server_id = 0 AND dns_server = 1 ORDER BY server_name");
	$server_id_option = '';
	foreach($records as $rec){
		$checked = ($rec['server_id'] == $server_id)?' SELECTED':'';
		$server_id_option .= '<option value="'.$rec['server_id'].'"'.$checked.'>'.$rec['server_name'].'</option>';
	}
	$app->tpl->setVar("server_id", $server_id_option);

	if ($domains_settings['use_domain_module'] != 'y') {
		// load the list of clients
		$sql = "SELECT sys_group.groupid, sys_group.name, CONCAT(IF(client.company_name != '', CONCAT(client.company_name, ' :: '), ''), client.contact_name, ' (', client.username, IF(client.customer_no != '', CONCAT(', ', client.customer_no), ''), ')') as contactname FROM sys_group, client WHERE sys_group.client_id = client.client_id AND sys_group.client_id > 0 ORDER BY client.company_name, client.contact_name, sys_group.name";
		$clients = $app->db->queryAllRecords($sql);
		$clients = $app->functions->htmlentities($clients);
		$client_select = '';
		if($_SESSION["s"]["user"]["typ"] == 'admin') $client_select .= "<option value='0'></option>";
		if(is_array($clients)) {
			foreach( $clients as $client) {
				$selected = ($client["groupid"] == $sys_groupid)?'SELECTED':'';
				$client_select .= "<option value='$client[groupid]' $selected>$client[contactname]</option>\r\n";
			}
		}

		$app->tpl->setVar("client_group_id", $client_select);
	}
}

if ($_SESSION["s"]["user"]["typ"] != 'admin' && $app->auth->has_clients($_SESSION['s']['user']['userid'])) {

	// Get the limits of the client
	$client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);
	$client = $app->db->queryOneRecord("SELECT client.client_id, client.contact_name, CONCAT(IF(client.company_name != '', CONCAT(client.company_name, ' :: '), ''), client.contact_name, ' (', client.username, IF(client.customer_no != '', CONCAT(', ', client.customer_no), ''), ')') as contactname, sys_group.name FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = ?", $client_group_id);
	$client = $app->functions->htmlentities($client);

	if ($domains_settings['use_domain_module'] != 'y') {
		// load the list of clients
		$sql = "SELECT sys_group.groupid, sys_group.name, CONCAT(IF(client.company_name != '', CONCAT(client.company_name, ' :: '), ''), client.contact_name, ' (', client.username, IF(client.customer_no != '', CONCAT(', ', client.customer_no), ''), ')') as contactname FROM sys_group, client WHERE sys_group.client_id = client.client_id AND client.parent_client_id = ? ORDER BY client.company_name, client.contact_name, sys_group.name";
		$clients = $app->db->queryAllRecords($sql, $client['client_id']);
		$clients = $app->functions->htmlentities($clients);
		$tmp = $app->db->queryOneRecord("SELECT groupid FROM sys_group WHERE client_id = ?", $client['client_id']);
		$client_select = '<option value="'.$tmp['groupid'].'">'.$client['contactname'].'</option>';
		if(is_array($clients)) {
			foreach( $clients as $client) {
				$selected = ($client["groupid"] == $sys_groupid)?'SELECTED':'';
				$client_select .= "<option value='$client[groupid]' $selected>$client[contactname]</option>\r\n";
			}
		}

		$app->tpl->setVar("client_group_id", $client_select);
	}
}

if($_SESSION["s"]["user"]["typ"] != 'admin')
{
	$client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);
	$client_dns = $app->db->queryOneRecord("SELECT dns_servers FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = ?", $client_group_id);

	$client_dns['dns_servers_ids'] = explode(',', $client_dns['dns_servers']);

	$only_one_server = count($client_dns['dns_servers_ids']) === 1;
	$app->tpl->setVar('only_one_server', $only_one_server);

	if ($only_one_server) {
		$app->tpl->setVar('server_id_value', $client_dns['dns_servers_ids'][0]);
	}

	$sql = "SELECT server_id, server_name FROM server WHERE server_id IN ?";
	$dns_servers = $app->db->queryAllRecords($sql, $client_dns['dns_servers_ids']);

	$options_dns_servers = "";

	foreach ($dns_servers as $dns_server) {
		$options_dns_servers .= '<option value="'.$dns_server['server_id'].'"'.($_POST['server_id'] == $dns_server['server_id'] ? ' selected="selected"' : '').'>'.$dns_server['server_name'].'</option>';
	}

	$app->tpl->setVar("server_id", $options_dns_servers);
	unset($options_dns_servers);

}

//* TODO: store dnssec-keys in the database - see below for non-admin-users
//* hide dnssec if we found dns-mirror-servers
$sql = "SELECT count(*) AS count FROM server WHERE mirror_server_id > 0 and dns_server = 1";
$rec=$app->db->queryOneRecord($sql);

$template_record = $app->db->queryOneRecord("SELECT * FROM dns_template WHERE template_id = ?", $template_id);
$fields = explode(',', $template_record['fields']);
if(is_array($fields)) {
	foreach($fields as $field) {
		if($field == 'DNSSEC' && $rec['count'] > 0) {
			//hide dnssec
		} else {
			$app->tpl->setVar($field."_VISIBLE", 1);
			$field = strtolower($field);
			$app->tpl->setVar($field, $_POST[$field], true);
		}
	}
}

/*
 * Now we have to check, if we should use the domain-module to select the domain
 * or not
 */
if ($domains_settings['use_domain_module'] == 'y') {
	/*
	 * The domain-module is in use.
	*/
	$domains = $app->tools_sites->getDomainModuleDomains("dns_soa");
	$domain_select = "<option value=''></option>";
	if(is_array($domains) && sizeof($domains) > 0) {
		/* We have domains in the list, so create the drop-down-list */
		foreach( $domains as $domain) {
			$domain_select .= "<option value=" . $domain['domain'] ;
			if ($domain['domain'] == $_POST['domain']) {
				$domain_select .= " selected";
			}
			$domain_select .= ">" . $app->functions->idn_decode($domain['domain']) . "</option>\r\n";
		}
	}
	else {
		/*
		 * We have no domains in the domain-list. This means, we can not add ANY new domain.
		 * To avoid, that the variable "domain_option" is empty and so the user can
		 * free enter a domain, we have to create a empty option!
		*/
		$domain_select .= "<option value=''></option>\r\n";
	}
	$app->tpl->setVar("domain_option", $domain_select);
}

if($_POST['create'] == 1) {

	//* CSRF Check
	$app->auth->csrf_token_check();

	$app->uses('dns_wizard');
	$create = $app->dns_wizard->create($_POST);
	if ($create == 'ok') {
		header("Location: dns_soa_list.php");
		exit;
	} else {
		$app->tpl->setVar("error", $create);
	}

}



$app->tpl->setVar("title", 'DNS Wizard');

//* SET csrf token
$csrf_token = $app->auth->csrf_token_get('dns_wizard');
$app->tpl->setVar('_csrf_id',$csrf_token['csrf_id']);
$app->tpl->setVar('_csrf_key',$csrf_token['csrf_key']);

$lng_file = 'lib/lang/'.$app->functions->check_language($_SESSION['s']['language']).'_dns_wizard.lng';
include $lng_file;
$app->tpl->setVar($wb);

$app->tpl_defaults();
$app->tpl->pparse();


?>
