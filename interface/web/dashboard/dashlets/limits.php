<?php

class dashlet_limits
{
    public function show($limit_to_client_id = 0)
    {
        global $app, $conf;

        $limits = array();

        /* Limits to be shown*/
        
        $limits[] = array('field' => 'limit_mailquota',
            'db_table' => 'mail_user',
            'db_where' => 'quota > 0',  /* Count only posive value of quota, negative value -1 is unlimited */
            'q_type' => 'quota');

        $limits[] = array('field' => 'limit_maildomain',
            'db_table' => 'mail_domain',
            'db_where' => '');

        $limits[] = array('field' => 'limit_mailmailinglist',
            'db_table' => 'mail_mailinglist',
            'db_where' => '');

        $limits[] = array('field' => 'limit_mailbox',
            'db_table' => 'mail_user',
            'db_where' => '');

        $limits[] = array('field' => 'limit_mailalias',
            'db_table' => 'mail_forwarding',
            'db_where' => "type = 'alias'");

        $limits[] = array('field' => 'limit_mailaliasdomain',
            'db_table' => 'mail_forwarding',
            'db_where' => "type = 'aliasdomain'");

        $limits[] = array('field' => 'limit_mailforward',
            'db_table' => 'mail_forwarding',
            'db_where' => "type = 'forward'");

        $limits[] = array('field' => 'limit_mailcatchall',
            'db_table' => 'mail_forwarding',
            'db_where' => "type = 'catchall'");

        $limits[] = array('field' => 'limit_mailrouting',
            'db_table' => 'mail_transport',
            'db_where' => "");

        $limits[] = array('field' => 'limit_mail_wblist',
            'db_table' => 'mail_access',
            'db_where' => "");

        $limits[] = array('field' => 'limit_mailfilter',
            'db_table' => 'mail_user_filter',
            'db_where' => "");

        $limits[] = array('field' => 'limit_fetchmail',
            'db_table' => 'mail_get',
            'db_where' => "");

        $limits[] = array('field' => 'limit_spamfilter_wblist',
            'db_table' => 'spamfilter_wblist',
            'db_where' => "");

        $limits[] = array('field' => 'limit_spamfilter_user',
            'db_table' => 'spamfilter_users',
            'db_where' => "");

        $limits[] = array('field' => 'limit_spamfilter_policy',
            'db_table' => 'spamfilter_policy',
            'db_where' => "");

        $limits[] = array('field' => 'limit_web_quota',
            'db_table' => 'web_domain',
            'db_where' => 'hd_quota > 0', /* Count only posive value of quota, negative value -1 is unlimited */
            'q_type' => 'hd_quota');
            
        $limits[] = array('field' => 'limit_web_domain',
            'db_table' => 'web_domain',
            'db_where' => "type = 'vhost'");

        $limits[] = array('field' => 'limit_web_subdomain',
            'db_table' => 'web_domain',
            'db_where' => "(type = 'subdomain' OR type = 'vhostsubdomain')");

        $limits[] = array('field' => 'limit_web_aliasdomain',
            'db_table' => 'web_domain',
                          'db_where' => "(type = 'alias' OR type = 'vhostalias')");

        $limits[] = array('field' => 'limit_ftp_user',
            'db_table' => 'ftp_user',
            'db_where' => "");

        $limits[] = array('field' => 'limit_shell_user',
            'db_table' => 'shell_user',
            'db_where' => "");

        $limits[] = array('field' => 'limit_dns_zone',
            'db_table' => 'dns_soa',
            'db_where' => "");

        $limits[] = array('field' => 'limit_dns_slave_zone',
            'db_table' => 'dns_slave',
            'db_where' => "");

        $limits[] = array('field' => 'limit_dns_record',
            'db_table' => 'dns_rr',
            'db_where' => "");

        $limits[] = array('field' => 'limit_database_quota',
            'db_table' => 'web_database',
            'db_where' => 'database_quota > 0', /* Count only posive value of quota, negative value -1 is unlimited */
            'q_type' => 'database_quota');
            
        $limits[] = array('field' => 'limit_database',
            'db_table' => 'web_database',
            'db_where' => "");

        $limits[] = array('field' => 'limit_cron',
            'db_table' => 'cron',
            'db_where' => "");

        $limits[] = array('field' => 'limit_client',
            'db_table' => 'client',
            'db_where' => "");

        $limits[] = array('field' => 'limit_domain',
            'db_table' => 'domain',
            'db_where' => "");


        //* Loading Template
        $app->uses('tpl,tform');

        $tpl = new tpl;
        $tpl->newTemplate("dashlets/templates/limits.htm");

        $wb = array();
        $lng_file = 'lib/lang/'.$_SESSION['s']['language'].'_dashlet_limits.lng';
        if (is_file($lng_file)) {
            include $lng_file;
        }
        $lng_file = ISPC_ROOT_PATH . '/lib/lang/'.$_SESSION['s']['language'].'.lng';
        if (is_file($lng_file)) {
            include $lng_file;
        }
        $tpl->setVar($wb);

        if ($limit_to_client_id != null) {
          $client_id = $limit_to_client_id;
        }
        elseif ($limit_to_client_id == null && $app->auth->is_reseller()) {
          $client_id = $_SESSION['s']['user']['client_id'];
        }
        $client = $app->db->queryOneRecord("SELECT * FROM client WHERE client_id = ?", $client_id);

        $rows = array();
        foreach ($limits as $limit) {
            $field = $limit['field'];
            $value = $client[$field];
            if ($app->auth->is_admin() && $limit_to_client_id == 0) {
                $value = -1;
            } else {
                $value = $client[$field];
            }

            if ($value != 0 || $value == $wb['unlimited_txt']) {
                $suffix = '';
                if (isset($limit['q_type']) && $limit['q_type'] != '') {
                    $usage = $this->_get_assigned_quota($limit, $client_id);
                    $suffix = ' MB';
                } else {
                    $usage = $this->_get_limit_usage($limit, $client_id);
                }
                $percentage = ($value == '-1' || intval($value) == 0 || trim($value) == '' ? -1 : round(100 * (int)$usage / (int)$value));
                $progressbar = $percentage > 100 ? 100 : $percentage;
                $value_formatted = ($value == '-1') ? $wb['unlimited_txt'] : ($value . $suffix);
                $rows[] = array('field' => $field,
                    'field_txt' => $wb[$field.'_txt'],
                    'value' => $value_formatted,
                    'value_raw' => $value,
                    'usage' => $usage,
                    'usage_raw' => $usage,
                    'percentage' => $percentage,
                    'progressbar' => $progressbar
                );
            }
        }
        $rows = $app->functions->htmlentities($rows);
        $tpl->setLoop('rows', $rows);


        return $tpl->grab();
    }

    public function _get_limit_usage($limit, $limit_to_client_id)
    {
        global $app;

        $sql = "SELECT count(sys_userid) as number FROM ?? WHERE ";
        if ($limit['db_where'] != '') {
            $sql .= $limit['db_where']." AND ";
        }
        $sql .= $app->tform->getAuthSQL('r', '', '', $app->functions->clientid_to_groups_list($limit_to_client_id));

        $rec = $app->db->queryOneRecord($sql, $limit['db_table']);
        return $rec['number'];
    }

    public function _get_assigned_quota($limit, $limit_to_client_id)
    {
        global $app;

        $sql = "SELECT sum(??) as number FROM ?? WHERE ";
        if ($limit['db_where'] != '') {
            $sql .= $limit['db_where']." AND ";
        }
        $sql .= $app->tform->getAuthSQL('r', '', '', $app->functions->clientid_to_groups_list($limit_to_client_id));
        $rec = $app->db->queryOneRecord($sql, $limit['q_type'], $limit['db_table']);
        if ($limit['db_table'] == 'mail_user') {
            $quotaMB = $rec['number'] / 1048576;
        } // Mail quota is in bytes, must be converted to MB
        else {
            $quotaMB = $app->functions->intval($rec['number']);
      }
      return $quotaMB;
    }

    /**
     * Lookup a client's group + all groups he is reselling.
     *
     * @return string Comma separated list of groupid's
     */
    function clientid_to_groups_list($client_id) {
      global $app;

      if ($client_id != null) {
        // Get the clients groupid, and incase it's a reseller the groupid's of it's clients.
        $group = $app->db->queryOneRecord("SELECT GROUP_CONCAT(groupid) AS groups FROM `sys_group` WHERE client_id IN (SELECT client_id FROM `client` WHERE client_id=? OR parent_client_id=?)", $client_id, $client_id);
        return $group['groups'];
      }
      return null;
    }
}
