<?php
/**
 * Syncer module controller
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Controller;

use Cli\Library\Bash;
use CliApp\Library\{
    Config,
    ConfigInjector,
    Module,
    Syncer
};

class SyncerController extends \CliModule\Controller
{
    private function getTarget(): ?array{
        $targets = $this->req->param->target;
        $paths   = [];
        $here    = getcwd();
        
        // get absolute path of each target
        foreach($targets as $target){
            if(substr($target, 0, 1) === '/')
                $paths[] = $target;
            else
                $paths[] = realpath($here . '/' . $target);
        }
        
        // make sure each target is valid app dir
        foreach($paths as $path){
            if(!Module::isAppBase($path))
                Bash::error('Target `' . $path . '` is not valid app dir');
        }
        
        return $paths;
    }
    
    private function callSync(array $config, string $here, string $target){
        $mode = 'update';
        $target_mod_dir = $target . '/modules/' . $config['__name'];
        if(!is_dir($target_mod_dir))
            $mode = 'install';
        
        if(!Syncer::sync($here, $target, $config['__files'], $mode)){
            Bash::error('Unable to sync module sources');
            return;
        }
        
        $module_conf_file = $target . '/etc/config/main.php';
        
        // inject application config
        ConfigInjector::inject($module_conf_file, $config);
        
        // Add gitignore
        Module::addGitIgnoreDb($target, $config);
        
        Config::init($target);
    }
    
    public function watchAction(): void{
        $here = $this->validateModuleHere();
        if(!($targets = $this->getTarget()))
            return;
        
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        Bash::echo('Watching module files for changes. Press `CTRL+C` to end the watcher');
        
        $last_files = [];
        
        while(true){
            $mod_conf = include $mod_conf_file;
            
            $change_found = false;
            
            $mod_files = [];
            
            $files = Syncer::scan($here, $here, $mod_conf['__files']);
            foreach($files['source']['files'] as $file => $rule){
                $file_abs = $here . '/' . $file;
                $mod_files[$file_abs] = filemtime($file_abs);
            }
            
            if(!$last_files){
                $change_found = true;
                $last_files = $mod_files;
            }
            
            // compare mod file and cache
            foreach($mod_files as $file => $time){
                if(!isset($last_files[$file])){
                    Bash::echo('New file ( ' . $file . ' )');
                    $change_found = true;
                    $last_files[$file] = $time;
                }
                
                if($last_files[$file] != $time){
                    Bash::echo('File changes ( ' . $file . ' )');
                    $change_found = true;
                    $last_files[$file] = $time;
                }
            }
            
            // compare cache with mod files
            foreach($last_files as $file => $time){
                if(!isset($mod_files[$file])){
                    Bash::echo('File removed ( ' . $file . ' )');
                    unset($last_files[$file]);
                    $change_found = true;
                }
            }
            
            if($change_found){
                foreach($targets as $target){
                    Bash::echo('Sync to `' . $target . '`');
                    $this->callSync($mod_conf, $here, $target);
                }
            }
            
            sleep(1);
        }
    }
    
    public function syncAction(): void{
        $here = $this->validateModuleHere();
        if(!($targets = $this->getTarget()))
            return;
        
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        
        foreach($targets as $target)
            $this->callSync($mod_conf, $here, $target);
        
        Bash::echo('Module synced');
    }
}