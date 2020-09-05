<?php
/**
 * BGit
 * @package cli-module
 * @version 1.2.0
 */

namespace CliModule\Library;

use Cli\Library\Bash;

class BGit
{
	static function init(string $here): bool{
		$mod_conf_file = glob($here . '/modules/*/config.php');
		if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];

        $mod_conf = include $mod_conf_file;
        $git_path = $mod_conf['__git'];

        if($git_path === '~'){
        	Bash::echo('Error: No repository URL for local module');
        	return false;
        }
       	
   		// is .git dir exists?
   		if(!is_dir( $here . '/.git' ))
   			exec('cd ' . $here . ' && git init');

   		$remote = exec('cd ' . $here . ' && git config --get remote.origin.url');
   		$remote = trim($remote);

   		if($remote){
   			if($remote != $git_path){
   				Bash::echo('Error: Current module already has different remote origin');
          Bash::echo('Current Origin: ' . $remote);
   				return false;
   			}

   			return true;
   		}

   		$cmd = 'cd ' . $here . ' && git remote add origin ' . $git_path;
   		exec($cmd);

   		return true;
	}
}