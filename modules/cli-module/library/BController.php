<?php
/**
 * Controller information collector
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;
use CliModule\Library\AutoloadAdder as ALAdder;
use CliModule\Library\BClass;

class BController
{

    static function build(string $here, string $name): bool{
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        $mod_name = $mod_conf['__name'];
        
        $ctrl_name = to_ns($name . 'Controller');
        $ctrl_ns   = to_ns($mod_name . '\\Controller');
        $ctrl_file = 'modules/' . $mod_name . '/controller/' . $ctrl_name . '.php';
        
        if(is_file($ctrl_file))
            Bash::error('Controller with the same file name already exists');
        
        $ctrl_config = BClass::getConfig($ctrl_ns, $ctrl_name, '\\Mim\\Controller');
        BClass::write($here, $mod_conf, $ctrl_config, $ctrl_file);
        
        // inject autoload
        ALAdder::classes($mod_conf, $ctrl_ns, $ctrl_name, $ctrl_file);
        
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($mod_conf) . ';';
        
        Fs::write($mod_conf_file, $tx);
        
        return true;
    }
}