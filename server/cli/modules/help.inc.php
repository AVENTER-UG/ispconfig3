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

class help_cli extends cli {

    function __construct() {
        $cmd_opt = [];
        $cmd_opt['help'] = 'showHelp';
        $cmd_opt['-h'] = 'showHelp';
        $cmd_opt['--help'] = 'showHelp';
        $this->addCmdOpt($cmd_opt);
    }

    public function errorShowHelp($arg) {
        global $conf;
        
        $this->swriteln("\nError: Unknown Commandline Option\n");
        $this->showHelp($arg);
    }

    public function showHelp($arg) {
        global $conf;

        $this->swriteln("---------------------------------");
        $this->swriteln("- Available commandline modules -");
        $this->swriteln("---------------------------------");

        $module_dir = dirname(__FILE__);

        // loop trough modules
        $files = glob($module_dir . '/*.inc.php');
        foreach ($files as $file) {
            $filename = basename($file);
            $filename = str_replace('.inc.php', '', $filename);
            $this->swriteln("\033[1m\033[31mispc ".$filename."\033[0m");
        }

        $this->swriteln("---------------------------------");
        $this->swriteln();
        

    }

}

