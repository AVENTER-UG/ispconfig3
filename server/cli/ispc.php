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

if(posix_getuid() != 0) {
    die("This command must be run as root user\n");
}

//define('SCRIPT_PATH', dirname($_SERVER["SCRIPT_FILENAME"]));
require "../lib/config.inc.php";
require "../lib/app.inc.php";

$app->setCaller('server');
$app->load('cli');

//* Check input
$module = $argv[1];
if(empty($module) || $module == '-h' || $module == '--help') $module = 'help';
if(!preg_match("/[a-z0-9]{3,20}/",$module)) die("Invalid commandline option\n");
//* Check if cli module exists and run it
if(is_file('modules/'.$module.'.inc.php')) {
    include_once 'modules/'.$module.'.inc.php';
    $class = $module.'_cli';
    $m = new $class;
    $m->process($argv);
} else {
    include_once 'modules/help.inc.php';
    $m = new help_cli;
    $m->errorShowHelp($argv);
}



