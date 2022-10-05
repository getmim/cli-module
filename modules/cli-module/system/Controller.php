<?php
/**
 * Module base controller
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule;

use Mim\Library\Fs;
use Cli\Library\Bash;

class Controller extends \Cli\Controller
{
    protected function validateModuleHere(): ?string{
        $here = getcwd();
        if(!$this->isModuleBase($here))
            Bash::error('Please run the command under valid module dir');
        return $here;
    }
    
    protected function isModuleBase(string $path): bool{
        // should has ./modules dir
        if(!is_dir($path . '/modules'))
            return false;
        
        $module_dir = $path . '/modules';
        // should has only one module in the ./modules dir
        $files = Fs::scan($module_dir);
        if(!$files)
            return false;

        // remove ignored files
        $files = array_values(array_diff($files, ['.gitkeep', '.DS_Store']));
        
        if(count($files) !== 1)
            return false;

        $module_name = $files[0];
        $module_dir.= '/' . $module_name;
        
        if(!is_dir($module_dir))
            return false;
        
        // should has config file
        $module_config_file = $module_dir . '/config.php';
        return is_file($module_config_file);
    }
}
