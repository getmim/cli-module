<?php
/**
 * Syncer module controller
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Controller;

use Mim\Library\Fs;
use Cli\Library\Bash;
use CliApp\Library\Apps;
use CliApp\Library\Config;
use CliApp\Library\ConfigInjector;
use CliApp\Library\Module;
use CliApp\Library\Syncer;

class SyncerController extends \CliModule\Controller
{
    private function getTarget(): ?string
    {
        $host = $target = $this->req->param->host;
        $apps = Apps::getAll();
        $here = getcwd();
        if ($apps) {
            if (isset($apps[$host])) {
                $target = $apps[$host];
            }
        }

        if (substr($target, 0, 1) !== '/') {
            $target = realpath($here . '/' . $target);
        }

        if (!Module::isAppBase($target)) {
            Bash::error('Target `' . $target . '` is not valid app dir');
        }

        return $target;
    }
    
    private function callSync(array $config, string $here, string $target)
    {
        $mode = 'update';
        $target_mod_dir = $target . '/modules/' . $config['__name'];
        if (!is_dir($target_mod_dir)) {
            if (!Bash::ask([
                'text' => 'Current module is not exists on `' . $target . '`, whould you like to install it instead?',
                'type' => 'bool',
                'default' => false
            ])) {
                return false;
            }
            $mode = 'install';

            // add current project to application modules
            Module::addModuleDb($target, (object)[
                'name' => $config['__name'],
                'urls' => [
                    'file' => (object)[
                        'used' => true,
                        'value' => $config['__git']
                    ]
                ]
            ]);
        }
        
        if (!Syncer::sync($here, $target, $config['__files'], $mode)) {
            Bash::error('Unable to sync module sources');
            return false;
        }
        
        $module_conf_file = $target . '/etc/config/main.php';
        
        // inject application config
        ConfigInjector::inject($module_conf_file, $config, $target);
        
        // Add gitignore
        Module::addGitIgnoreDb($target, $config);

        // install new dependencies
        if (isset($config['__dependencies'])) {
            Module::installDependencies($target, $config['__dependencies']);
        }
        
        Config::init($target);

        return true;
    }

    private function removeNewFile(string $file, string $here, string $target): void
    {
        $target_filename = $target . substr($file, strlen($here));
        if (!is_file($target_filename)) {
            return;
        }
        unlink($target_filename);
        $target_filename = dirname($target_filename);
        Fs::cleanUp($target_filename);
    }
    
    public function watchAction(): void
    {
        $here = $this->validateModuleHere();
        if (!($target = $this->getTarget())) {
            return;
        }
        
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if (!$mod_conf_file || !is_file($mod_conf_file[0])) {
            Bash::error('Module config file not found');
        }
        $mod_conf_file = $mod_conf_file[0];
        
        Bash::echo('Watching module files for changes. Press `CTRL+C` to end the watcher');
        
        $last_files = [];
        
        while (true) {
            $mod_conf = include $mod_conf_file;
            
            $change_found = false;
            
            $mod_files = [];
            
            $files = Syncer::scan($here, $here, $mod_conf['__files']);
            foreach ($files['source']['files'] as $file => $rule) {
                $file_abs = $here . '/' . $file;
                $mod_files[$file_abs] = filemtime($file_abs);
            }
            
            if (!$last_files) {
                $change_found = true;
                $last_files = $mod_files;
            }
            
            // compare mod file and cache
            foreach ($mod_files as $file => $time) {
                if (!isset($last_files[$file])) {
                    Bash::echo('New file ( ' . $file . ' )');
                    $change_found = true;
                    $last_files[$file] = $time;
                }
                
                if ($last_files[$file] != $time) {
                    Bash::echo('File changes ( ' . $file . ' )');
                    $change_found = true;
                    $last_files[$file] = $time;
                }
            }
            
            // compare cache with mod files
            foreach ($last_files as $file => $time) {
                if (!isset($mod_files[$file])) {
                    Bash::echo('File removed ( ' . $file . ' )');
                    $this->removeNewFile($file, $here, $target);
                    unset($last_files[$file]);
                    $change_found = true;
                }
            }
            
            if ($change_found) {
                Bash::echo('Sync to `' . $target . '`');
                if (!$this->callSync($mod_conf, $here, $target)) {
                    return;
                }
                Bash::echo('Continue watching');
            }
            
            sleep(1);
        }
    }
    
    public function syncAction(): void
    {
        $here = $this->validateModuleHere();
        if (!($target = $this->getTarget())) {
            return;
        }
        
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if (!$mod_conf_file || !is_file($mod_conf_file[0])) {
            Bash::error('Module config file not found');
        }
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        
        $this->callSync($mod_conf, $here, $target);
        
        Bash::echo('Accepted module synced');
    }
}
