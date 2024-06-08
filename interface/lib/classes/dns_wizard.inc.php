<?php
/*
Copyright (c) 2023, Adam Biciste <adam@freshost.cz>
All rights reserved.

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

class dns_wizard
{
	function create(array $data)
	{
        global $app;
        $app->uses('getconf');

        // get system settings
        $settings = $app->getconf->get_global_config();

        $error = '';

        // get the correct server_id
        if (isset($data['server_id'])) {
            $server_id = $app->functions->intval($data['server_id']);
            $post_server_id = true;
        } elseif (isset($data['server_id_value'])) {
            $server_id = $app->functions->intval($data['server_id_value']);
            $post_server_id = true;
        } else {
            $server_id = $app->functions->intval($settings['dns']['default_dnsserver']);
            if(empty($server_id)) {
                $tmp = $app->db->queryOneRecord('SELECT server_id FROM server WHERE dns_server = 1 LIMIT 0,1');
                if(!empty($tmp['server_id'])) {
                    $server_id = $tmp['server_id'];
                } else {
                    $error .= $app->lng('error_no_server_id').'<br />';
                }
            }
            $post_server_id = false;
        }

        if ($post_server_id)
        {
            $client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);
            $client = $app->db->queryOneRecord("SELECT dns_servers FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = ?", $client_group_id);

            $client['dns_servers_ids'] = explode(',', $client['dns_servers']);

            // Check if chosen server is in authorized servers for this client
            if (!(is_array($client['dns_servers_ids']) && in_array($server_id, $client['dns_servers_ids'])) && $_SESSION["s"]["user"]["typ"] != 'admin') {
                $error .= $app->lng('error_not_allowed_server_id').'<br />';
            }
        }
        /*
        else
        {
            $error .= $app->lng('error_no_server_id').'<br />';
        }
        */

        // apply filters
        if(isset($data['domain']) && $data['domain'] != ''){
            /* check if the domain module is used - and check if the selected domain can be used! */
            if ($settings['domains']['use_domain_module'] == 'y') {
                // get domain_id for domain
                $tmp = $app->db->queryOneRecord('SELECT domain_id from domain where domain = ?', $data['domain']);
                $domain_id = $app->functions->intval( $tmp['domain_id']);
                
                if ($_SESSION["s"]["user"]["typ"] == 'admin' || $app->auth->has_clients($_SESSION['s']['user']['userid'])) {
                    $data['client_group_id'] = $app->tools_sites->getClientIdForDomain($domain_id);
                }
                $domain_check = $app->tools_sites->checkDomainModuleDomain($domain_id);
                if($domain_check === false) {
                    // invalid domain selected
                    $data['domain'] = '';
                } else {
                    $data['domain'] = $domain_check;
                }
            } else {
                $data['domain'] = $app->functions->idn_encode($data['domain']);
                $data['domain'] = strtolower($data['domain']);
            }
        }
        if(isset($data['ns1']) && $data['ns1'] != ''){
            $data['ns1'] = $app->functions->idn_encode($data['ns1']);
            $data['ns1'] = strtolower($data['ns1']);
        }
        if(isset($data['ns2']) && $data['ns2'] != ''){
            $data['ns2'] = $app->functions->idn_encode($data['ns2']);
            $data['ns2'] = strtolower($data['ns2']);
        }
        if(isset($data['email']) && $data['email'] != ''){
            $data['email'] = $app->functions->idn_encode($data['email']);
            $data['email'] = strtolower($data['email']);
        }


        # fixme: this regex is pretty poor for domain validation
        if(isset($data['domain']) && $data['domain'] == '') $error .= $app->lng('error_domain_empty').'<br />';
        elseif(isset($data['domain']) && !preg_match('/^[\w\.\-]{1,64}\.[a-zA-Z0-9\-]{2,63}$/', $data['domain'])) $error .= $app->lng('error_domain_regex').'<br />';

        if(isset($data['ip']) && $data['ip'] == '') $error .= $app->lng('error_ip_empty').'<br />';

        //if(isset($data['ipv6']) && $data['ipv6'] == '') $error .= $app->lng('error_ipv6_empty').'<br />';

        # fixme: this regex is pretty poor for hostname validation
        if(isset($data['ns1']) && $data['ns1'] == '') $error .= $app->lng('error_ns1_empty').'<br />';
        elseif(isset($data['ns1']) && !preg_match('/^[\w\.\-]{1,64}\.[a-zA-Z0-9]{2,63}$/', $data['ns1'])) $error .= $app->lng('error_ns1_regex').'<br />';

        if(isset($data['ns2']) && $data['ns2'] == '') $error .= $app->lng('error_ns2_empty').'<br />';
        elseif(isset($data['ns2']) && !preg_match('/^[\w\.\-]{1,64}\.[a-zA-Z0-9]{2,63}$/', $data['ns2'])) $error .= $app->lng('error_ns2_regex').'<br />';

        if(isset($data['email']) && $data['email'] == '') $error .= $app->lng('error_email_empty').'<br />';
        elseif(isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) $error .= $app->lng('error_email_regex').'<br />';

        // make sure that the record belongs to the client group and not the admin group when admin inserts it
        if($_SESSION["s"]["user"]["typ"] == 'admin' && isset($data['client_group_id'])) {
            $sys_groupid = $app->functions->intval($data['client_group_id']);
        } elseif($app->auth->has_clients($_SESSION['s']['user']['userid']) && isset($data['client_group_id'])) {
            $sys_groupid = $app->functions->intval($data['client_group_id']);
        } else {
            $sys_groupid = $_SESSION["s"]["user"]["default_group"];
        }

        $tform_def_file = "../../web/dns/form/dns_soa.tform.php";
        $app->uses('tform');
        $app->tform->loadFormDef($tform_def_file);

        if($_SESSION['s']['user']['typ'] != 'admin') {
            if(!$app->tform->checkClientLimit('limit_dns_zone')) {
                $error .= $app->tform->wordbook["limit_dns_zone_txt"];
            }
            if(!$app->tform->checkResellerLimit('limit_dns_zone')) {
                $error .= $app->tform->wordbook["limit_dns_zone_txt"];
            }
        }


        // replace template placeholders
        $template_id = (isset($data['template_id']))?$app->functions->intval($data['template_id']):0;
        $template_record = $app->db->queryOneRecord("SELECT * FROM dns_template WHERE template_id = ?", $template_id);
        $tpl_content = $template_record['template'];
        if($data['domain'] != '') $tpl_content = str_replace('{DOMAIN}', $data['domain'], $tpl_content);
        if($data['ip'] != '') $tpl_content = str_replace('{IP}', $data['ip'], $tpl_content);
        if($data['ipv6'] != '') $tpl_content = str_replace('{IPV6}',$data['ipv6'],$tpl_content);
        if($data['ns1'] != '') $tpl_content = str_replace('{NS1}', $data['ns1'], $tpl_content);
        if($data['ns2'] != '') $tpl_content = str_replace('{NS2}', $data['ns2'], $tpl_content);
        if($data['email'] != '') $tpl_content = str_replace('{EMAIL}', $data['email'], $tpl_content);
        // $enable_dnssec = (($data['dnssec'] == 'Y') ? 'Y' : 'N');
        // if(isset($data['dnssec'])) $vars['dnssec_wanted'] = 'Y';
        if(isset($data['dnssec'])) $tpl_content = str_replace('[ZONE]', '[ZONE]'."\n".'dnssec_wanted=Y', $tpl_content);
        if(isset($data['dkim']) && preg_match('/^[\w\.\-\/]{1,255}\.[a-zA-Z0-9\-]{2,63}[\.]{0,1}$/', $data['domain'])) {
            $sql = $app->db->queryOneRecord("SELECT dkim_public, dkim_selector FROM mail_domain WHERE domain = ? AND dkim = 'y' AND ".$app->tform->getAuthSQL('r'), $data['domain']);
            $public_key = $sql['dkim_public'];
            if ($public_key!='') {
                if (empty($sql['dkim_selector'])) $sql['dkim_selector'] = 'default';
                $dns_record=str_replace(array("\r\n", "\n", "\r", "-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----"), '', $public_key);
                $tpl_content .= "\n".'TXT|'.$sql['dkim_selector'].'._domainkey.'.$data['domain'].'.|v=DKIM1; t=s; p='.$dns_record;
            }
        }

        // Parse the template
        $tpl_rows = explode("\n", $tpl_content);
        $section = '';
        $vars = array();
        $vars['xfer']='';
        $vars['dnssec_wanted']='N';
        $vars['dnssec_algo']='ECDSAP256SHA256';
        $dns_rr = array();
        foreach($tpl_rows as $row) {
            $row = trim($row);
            if(substr($row, 0, 1) == '[') {
                if($row == '[ZONE]') {
                    $section = 'zone';
                } elseif($row == '[DNS_RECORDS]') {
                    $section = 'dns_records';
                } else {
                    die('Unknown section type');
                }
            } else {
                if($row != '') {
                    // Handle zone section
                    if($section == 'zone') {
                        $parts = explode('=', $row);
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        if($key != '') $vars[$key] = $val;
                    }
                    // Handle DNS Record rows
                    if($section == 'dns_records') {
                        $parts = explode('|', $row);
                        $dns_rr[] = array(
                            'name' => $parts[1],
                            'type' => $parts[0],
                            'data' => $parts[2],
                            'aux'  => $parts[3],
                            'ttl'  => $parts[4]
                        );
                    }
                }
            }

        } // end foreach

        if($vars['origin'] == '') $error .= $app->lng('error_origin_empty').'<br />';
        if($vars['ns'] == '') $error .= $app->lng('error_ns_empty').'<br />';
        if($vars['mbox'] == '') $error .= $app->lng('error_mbox_empty').'<br />';
        if($vars['refresh'] == '') $error .= $app->lng('error_refresh_empty').'<br />';
        if($vars['retry'] == '') $error .= $app->lng('error_retry_empty').'<br />';
        if($vars['expire'] == '') $error .= $app->lng('error_expire_empty').'<br />';
        if($vars['minimum'] == '') $error .= $app->lng('error_minimum_empty').'<br />';
        if($vars['ttl'] == '') $error .= $app->lng('error_ttl_empty').'<br />';

        if($error == '') {
            // Insert the soa record
            $sys_userid = $_SESSION['s']['user']['userid'];
            $origin = $vars['origin'];
            $ns = $vars['ns'];
            $mbox = str_replace('@', '.', $vars['mbox']);
            $refresh = $vars['refresh'];
            $retry = $vars['retry'];
            $expire = $vars['expire'];
            $minimum = $vars['minimum'];
            $ttl = $vars['ttl'];
            $xfer = $vars['xfer'];
            $also_notify = $vars['also_notify'];
            $update_acl = $vars['update_acl'];
            $dnssec_wanted = $vars['dnssec_wanted'];
            $dnssec_algo = $vars['dnssec_algo'];
            $serial = $app->validate_dns->increase_serial(0);

            $insert_data = array(
                "sys_userid" => $sys_userid,
                "sys_groupid" => $sys_groupid,
                "sys_perm_user" => 'riud',
                "sys_perm_group" => 'riud',
                "sys_perm_other" => '',
                "server_id" => $server_id,
                "origin" => $origin,
                "ns" => $ns,
                "mbox" => $mbox,
                "serial" => $serial,
                "refresh" => $refresh,
                "retry" => $retry,
                "expire" => $expire,
                "minimum" => $minimum,
                "ttl" => $ttl,
                "active" => 'N', // Activated later when all DNS records are added.
                "xfer" => $xfer,
                "also_notify" => $also_notify,
                "update_acl" => $update_acl,
                "dnssec_wanted" => $dnssec_wanted,
                "dnssec_algo" => $dnssec_algo
            );

            $dns_soa_id = $app->db->datalogInsert('dns_soa', $insert_data, 'id');
            if($dns_soa_id > 0) $app->plugin->raiseEvent('dns:wizard:on_after_insert', $dns_soa_id);

            // Insert the dns_rr records
            if(is_array($dns_rr) && $dns_soa_id > 0) {
                foreach($dns_rr as $rr) {
                    $insert_data = array(
                        "sys_userid" => $sys_userid,
                        "sys_groupid" => $sys_groupid,
                        "sys_perm_user" => 'riud',
                        "sys_perm_group" => 'riud',
                        "sys_perm_other" => '',
                        "server_id" => $server_id,
                        "zone" => $dns_soa_id,
                        "name" => $rr['name'],
                        "type" => $rr['type'],
                        "data" => $rr['data'],
                        "aux" => $rr['aux'],
                        "ttl" => $rr['ttl'],
                        "active" => 'Y'
                    );
                    $dns_rr_id = $app->db->datalogInsert('dns_rr', $insert_data, 'id');
                }
            }

            // Activate the DNS zone.
            $app->db->datalogUpdate('dns_soa', array('active' => 'Y'), 'id', $dns_soa_id);

            return 'ok';

        } else {
            return $error;
        }
	}

}
