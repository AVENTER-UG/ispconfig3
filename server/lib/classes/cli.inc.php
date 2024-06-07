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

class cli {

    private $cmd_opt = array();

    // Add commandline option map
    protected function addCmdOpt($cmd_opt) {
        $this->cmd_opt = $cmd_opt;
    }

    // Get commandline option map
    protected function getCmdOpt() {
        return $this->cmd_opt;
    }

    // Run command module
    public function process($arg) {
        $function = '';
        $opt_string = '';
        $last_arg = 1;
        for($n = 1; $n < count($arg); $n++) {
            $a = ($n > 1)?$a.':'.$arg[$n]:$arg[$n];
            if(isset($this->cmd_opt[$a])) {
                $function = $this->cmd_opt[$a];
                $last_arg = $n + 1;
            }
        }

		// Check function name
		if(!preg_match("/[a-z0-9\-]{0,20}/",$function)) die("Invalid commandline option\n");

        // Build new arg array of the remaining arguments
        $new_arg = [];
        if($last_arg < count($arg)) {
            for($n = $last_arg; $n < count($arg); $n++) {
                $new_arg[] = $arg[$n];
            }
        }
        
        if($function != '') {
            $this->$function($new_arg);
        } else {
			$this->showHelp($new_arg);
            //$this->error("Invalid option");
        }
    }

    // Query function
    public function simple_query($query, $answers, $default, $name = '') {
		global $autoinstall, $autoupdate;
		$finished = false;
		do {
			if($name != '' && isset($autoinstall[$name]) && $autoinstall[$name] != '') {
				if($autoinstall[$name] == 'default') {
					$input = $default;
				} else {
					$input = $autoinstall[$name];
				}
			} elseif($name != '' && isset($autoupdate[$name]) && $autoupdate[$name] != '') {
				if($autoupdate[$name] == 'default') {
					$input = $default;
				} else {
					$input = $autoupdate[$name];
				}
			} else {
				$answers_str = implode(',', $answers);
				$this->swrite($this->lng($query).' ('.$answers_str.') ['.$default.']: ');
				$input = $this->sread();
			}

			//* Stop the installation
			if($input == 'quit') {
				$this->swriteln($this->lng("Command terminated by user.\n"));
				die();
			}

			//* Select the default
			if($input == '') {
				$answer = $default;
				$finished = true;
			}

			//* Set answer id valid
			if(in_array($input, $answers)) {
				$answer = $input;
				$finished = true;
			}

		} while ($finished == false);
		$this->swriteln();
		return $answer;
	}

	public function free_query($query, $default, $name = '') {
		global $autoinstall, $autoupdate;
		if($name != '' && isset($autoinstall[$name]) && $autoinstall[$name] != '') {
			if($autoinstall[$name] == 'default') {
				$input = $default;
			} else {
				$input = $autoinstall[$name];
			}
		} elseif($name != '' && isset($autoupdate[$name]) && $autoupdate[$name] != '') {
			if($autoupdate[$name] == 'default') {
				$input = $default;
			} else {
				$input = $autoupdate[$name];
			}
		} else {
			$this->swrite($this->lng($query).' ['.$default.']: ');
			$input = $this->sread();
		}

		//* Stop the installation
		if($input == 'quit') {
			$this->swriteln($this->lng("Command terminated by user.\n"));
			die();
		}

		$answer =  ($input == '') ? $default : $input;
		$this->swriteln();
		return $answer;
	}

    public function lng($text) {
		return $text;
	}

    public function sread() {
        $input = fgets(STDIN);
        return rtrim($input);
    }
    
    public function swrite($text = '') {
        echo $text;
    }
    
    public function swriteln($text = '') {
        echo $text."\n";
    }

    public function error($msg) {
        $this->swriteln($msg);
        die();
    }

}
