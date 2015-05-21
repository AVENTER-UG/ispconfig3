/*
Copyright (c) 2007 - 2013, Till Brehm, projektfarm Gmbh
Copyright (c) 2013, Florian Schaal, info@schaal-24.de
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



This Javascript is invoked by
	* mail/templates/mail_domain_edit.htm to show and/or create the key-pair
*/

var request = false;

//function setRequest(action) {
function setRequest() {
	if (window.XMLHttpRequest) {
			request = new XMLHttpRequest();
	} else if (window.ActiveXObject) {
		try {
			request = new ActiveXObject('Msxml2.XMLHTTP');
		}
		catch (e) {
			try {
				request = new ActiveXObject('Microsoft.XMLHTTP');
			}
			catch (e) {}
		}
	}
	if (!request) {
		alert("Error creating XMLHTTP-instance");
		return false;
	} else {
		// jQuery depends on domain-module active / inactive
		var check = jQuery('#domain_module').val();
		if ( check == "1" ) {
			var skillsSelect = document.getElementById("domain");
			var domain = skillsSelect.options[skillsSelect.selectedIndex].text;
		} else { 
			var domain = jQuery('#domain').val();
		}

		// we nedd the client-id to get the dkim-strength of the right mail-server
		try {
			var clientid = document.getElementById("client_group_id").selectedIndex; // admin and reseller
		}
		catch (e) {
			var clientid = jQuery('#client_id').val();; // user
		}

		var selector=jQuery('#dkim_selector').val();
		var publickey=jQuery('#dkim_public').val();
		request.open('POST', 'mail/mail_domain_dkim_create.php', true);
		request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		request.send('domain='+domain+'&dkim_selector='+selector+'&dkim_public='+publickey+'&client_id='+clientid);
		request.onreadystatechange = interpretRequest;
	}
}

function interpretRequest() {
	switch (request.readyState) {
		case 4:
			if ( request.status != 200 ) {
				alert("Request done but NOK\nError:"+request.status);
			} else {
				document.getElementsByName('dkim_selector')[0].value = request.responseXML.getElementsByTagName('selector')[0].firstChild.nodeValue;
				document.getElementsByName('dkim_private')[0].value = request.responseXML.getElementsByTagName('privatekey')[0].firstChild.nodeValue;
				document.getElementsByName('dkim_public')[0].value = request.responseXML.getElementsByTagName('publickey')[0].firstChild.nodeValue;
				document.getElementsByName('dns_record')[0].value = request.responseXML.getElementsByTagName('dns_record')[0].firstChild.nodeValue;
			}
		break;
		default:
		break;
	}
}

//setRequest('show');
