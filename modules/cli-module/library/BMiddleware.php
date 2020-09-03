<?php
/**
 * BMiddleware
 * @package cli-module
 * @version 1.1.0
 */

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;
use CliModule\Library\AutoloadAdder as ALAdder;
use CliModule\Library\BClass;

class BMiddleware
{

    static function build(string $here, string $name): bool{
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        $mod_name = $mod_conf['__name'];
        
        $mdl_name = to_ns($name . 'Middleware');
        $mdl_ns   = to_ns($mod_name . '\\Middleware');
        $mdl_file = 'modules/' . $mod_name . '/middleware/' . $mdl_name . '.php';
        
        if(is_file($mdl_file))
            Bash::error('Middleware with the same file name already exists');
        
        $mdl_config = BClass::getConfig($mdl_ns, $mdl_name, '\\Mim\\Middleware');
        BClass::write($here, $mod_conf, $mdl_config, $mdl_file);
        
        // inject autoload
        ALAdder::classes($mod_conf, $mdl_ns, $mdl_name, $mdl_file);
        
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($mod_conf) . ';';
        
        Fs::write($mod_conf_file, $tx);
        
        return true;
    }
}