<?php

/*
Copyright (c) 2024, Till Brehm, ISPConfig UG
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

class user_cli extends cli {

    function __construct() {
        $cmd_opt = [];
        $cmd_opt['user'] = 'showHelp';
        $cmd_opt['user:set-password'] = 'setPassword';
        $this->addCmdOpt($cmd_opt);
    }

    public function setPassword($arg) {
        global $app, $conf;

        // Get username
        $username = $arg[0];

        // Check empty username
        if(empty($username)) {
          $this->swriteln();
          $this->swriteln('Error: Username may not be empty.');
          $this->swriteln();
          $this->showHelp($arg);
          die();
        }

        // Check for invalid chars
        if(!preg_match('/^[\w\.\-\_]{1,64}$/',$username)) {
          $this->swriteln();
          $this->swriteln('Error: Username contains invalid characters.');
          $this->swriteln();
          $this->showHelp($arg);
          die();
        }

        // Get user from ISPConfig database
        $user = $app->db->queryOneRecord("SELECT * FROM `sys_user` WHERE `username` = ?",$username);
        
        // Check if user exists
        if(empty($user)) {
          $this->swriteln();
          $this->swriteln('Error: Username does not exist.');
          $this->swriteln();
          $this->showHelp($arg);
          die();
        }

        // Include auth class from interface
        include_once '/usr/local/ispconfig/interface/lib/classes/auth.inc.php';
        $app->auth = new auth;

        $ok = false;

        while ($ok == false) {
          
          $ok = true;
          // Ask for new password
          $min_password_len = $app->auth->get_min_password_length();
          $new_password = $this->free_query('Enter new password for the user '.$username .' or quit', $app->auth->get_random_password($min_password_len));

          if(strlen($new_password) < $min_password_len) {
            $this->swriteln('The minimum password length is '. $min_password_len);
            $this->swriteln();
            $ok = false;
          }

          if($ok) {
            $new_password2 = $this->free_query('Repeat the password', '');
          }

          if($ok && $new_password != $new_password2) {
            $this->swriteln('Passwords do not match.');
            $this->swriteln();
            $ok = false;
          }

          if($ok) {
            $crypted_password = $app->auth->crypt_password($new_password);
            $app->db->query("UPDATE `sys_user` SET `passwort` = ? WHERE `username` = ?",$crypted_password,$username);
            $this->swriteln('Password for user '.$username.' has been changed.');
            $this->swriteln();
          }
      }
    }

    public function showHelp($arg) {
      global $conf;

      $this->swriteln("---------------------------------");
      $this->swriteln("- Available commandline options -");
      $this->swriteln("---------------------------------");
      $this->swriteln("ispc user set-password <username> - Set a new password for the ISPConfig user <username>.");
      $this->swriteln("---------------------------------");
      $this->swriteln();
    }

}

