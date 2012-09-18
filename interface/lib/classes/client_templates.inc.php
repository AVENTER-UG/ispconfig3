<?php
/**
 * client_templates
 * 
 * @author Marius Cramer <m.cramer@pixcept.de> pixcept KG
 * @author (original tools.inc.php) Till Brehm, projektfarm Gmbh
 * @author (original tools.inc.php) Oliver Vogel www.muv.com
 */
 
class client_templates {

	function apply_client_templates($clientId) {
        global $app;
        
        include('../client/form/client.tform.php');
        
        /*
         * Get the master-template for the client
         */
        $sql = "SELECT template_master, template_additional FROM client WHERE client_id = " . $app->functions->intval($clientId);
        $record = $app->db->queryOneRecord($sql);
        $masterTemplateId = $record['template_master'];
        $additionalTemplateStr = $record['template_additional'];

        /*
         * if the master-Template is custom there is NO changing
         */
        if ($masterTemplateId > 0){
            $sql = "SELECT * FROM client_template WHERE template_id = " . $app->functions->intval($masterTemplateId);
            $limits = $app->db->queryOneRecord($sql);
        } else {
            // if there is no master template it makes NO SENSE adding sub templates.
            // adding subtemplates are stored in client limits, so they would add up
            // on every save action for the client -> too high limits!
            return;
        }

        /*
         * Process the additional tempaltes here (add them to the limits
         * if != -1)
         */
        $addTpl = explode('/', $additionalTemplateStr);
        foreach ($addTpl as $item){
            if (trim($item) != ''){
                $sql = "SELECT * FROM client_template WHERE template_id = " . $app->functions->intval($item);
                $addLimits = $app->db->queryOneRecord($sql);
                $app->log('Template processing subtemplate ' . $item . ' for client ' . $clientId, LOGLEVEL_DEBUG);
                /* maybe the template is deleted in the meantime */
                if (is_array($addLimits)){
                    foreach($addLimits as $k => $v){
                        /* we can remove this condition, but it is easier to debug with it (don't add ids and other non-limit values) */
                        if (strpos($k, 'limit') !== false or $k == 'ssh_chroot' or $k == 'web_php_options' or $k == 'force_suexec'){
                            $app->log('Template processing key ' . $k . ' for client ' . $clientId, LOGLEVEL_DEBUG);

                            /* process the numerical limits */
                            if (is_numeric($v)){
                                /* switch for special cases */
                                switch ($k){
                                case 'limit_cron_frequency':
                                    if ($v < $limits[$k]) $limits[$k] = $v;
                                    /* silent adjustment of the minimum cron frequency to 1 minute */
                                    /* maybe this control test should be done via validator definition in tform.php file, but I don't know how */
                                    if ($limits[$k] < 1) $limits[$k] = 1;
                                break;

                                default:
                                    if ($limits[$k] > -1){
                                        if ($v == -1){
                                            $limits[$k] = -1;
                                        }
                                        else {
                                            $limits[$k] += $v;
                                        }
                                    }
                                }
                            }
                            /* process the string limits (CHECKBOXARRAY, SELECT etc.) */
                            elseif (is_string($v)){
                                switch ($form["tabs"]["limits"]["fields"][$k]['formtype']){
                                case 'CHECKBOXARRAY':
                                    if (!isset($limits[$k])){
                                        $limits[$k] = array();
                                    }

                                    $limits_values = $limits[$k];
                                    if (is_string($limits[$k])){
                                        $limits_values = explode($form["tabs"]["limits"]["fields"][$k]["separator"],$limits[$k]);
                                    }
                                    $additional_values = explode($form["tabs"]["limits"]["fields"][$k]["separator"],$v);
                                    $app->log('Template processing key ' . $k . ' type CHECKBOXARRAY, lim / add: ' . implode(',', $limits_values) . ' / ' . implode(',', $additional_values) . ' for client ' . $clientId, LOGLEVEL_DEBUG);
                                    /* unification of limits_values (master template) and additional_values (additional template) */
                                    $limits_unified = array();
                                    foreach($form["tabs"]["limits"]["fields"][$k]["value"] as $key => $val){
                                        if (in_array($key,$limits_values) || in_array($key,$additional_values)) $limits_unified[] = $key;
                                    }
                                    $limits[$k] = implode($form["tabs"]["limits"]["fields"][$k]["separator"],$limits_unified);
                                break;
                                case 'CHECKBOX':
                                    if($k == 'force_suexec') {
                                        // 'n' is less limited than y
                                        if (!isset($limits[$k])){
                                            $limits[$k] = 'y';
                                        }
                                        if($limits[$k] == 'n' || $v == 'n') $limits[$k] = 'n';
                                    } else {
                                        // 'y' is less limited than n
                                        if (!isset($limits[$k])){
                                            $limits[$k] = 'n';
                                        }
                                        if($limits[$k] == 'y' || $v == 'y') $limits[$k] = 'y';
                                    }
                                break;
                                case 'SELECT':
                                    $limit_values = array_keys($form["tabs"]["limits"]["fields"][$k]["value"]);
                                    /* choose the lower index of the two SELECT items */
                                    $limits[$k] = $limit_values[min(array_search($limits[$k], $limit_values), array_search($v, $limit_values))];
                                break;
                                }
                            }
                        }
                    }
                }
            }
        }

        /*
         * Write all back to the database
         */
        $update = '';
        foreach($limits as $k => $v){
            if ((strpos($k, 'limit') !== false or $k == 'ssh_chroot' or $k == 'web_php_options' or $k == 'force_suexec') && !is_array($v)){
                if ($update != '') $update .= ', ';
                $update .= '`' . $k . "`='" . $v . "'";
            }
        }
        $app->log('Template processed for client ' . $clientId . ', update string: ' . $update, LOGLEVEL_DEBUG);
        if($update != '') {
            $sql = 'UPDATE client SET ' . $update . " WHERE client_id = " . $app->functions->intval($clientId);
            $app->db->query($sql);
        }
        unset($form);
    }
}