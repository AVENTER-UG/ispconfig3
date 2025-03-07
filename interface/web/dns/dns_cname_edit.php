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

/******************************************
* Begin Form configuration
******************************************/

$tform_def_file = "form/dns_cname.tform.php";

/******************************************
* End Form configuration
******************************************/

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once './dns_edit_base.php';

// Loading classes
class page_action extends dns_page_action {

	protected function checkDuplicate() {
		global $app;
		//* Check for duplicates where IP and hostname are the same
		$tmp = $app->db->queryOneRecord("SELECT count(dns_rr.id) as number FROM dns_rr LEFT JOIN dns_soa ON dns_rr.zone = dns_soa.id WHERE (( name = replace(?, concat('.', dns_soa.origin), '') or name = ? or name = concat(?,'.', dns_soa.origin)) and dns_rr.zone = ? and dns_rr.id != ?)", $this->dataRecord["name"], $this->dataRecord["name"], $this->dataRecord["name"], $this->dataRecord["zone"], $this->id);
		if($tmp['number'] > 0) return true;
		return false;
	}

	function onSubmit() {
		global $app, $conf;
		// Get the parent soa record of the domain
		$soa = $app->db->queryOneRecord("SELECT * FROM dns_soa WHERE id = ? AND " . $app->tform->getAuthSQL('r'), $_POST["zone"]);
		// Replace @ to example.com. in data field
		if($this->dataRecord["data"] === '@') {
			$this->dataRecord["data"] = $soa['origin'];
		}
		parent::onSubmit();
	}
}

$page = new page_action;
$page->onLoad();

?>
