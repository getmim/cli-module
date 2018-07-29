<?php
/**
 * Service builder
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;
use CliModule\Library\AutoloadAdder as ALAdder;
use CliModule\Library\BClass;

class BService
{
    static function build(string $here, string $name): bool{
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        $mod_name = $mod_conf['__name'];
        
        $lib_name = to_ns($name);
        $lib_ns   = to_ns($mod_name . '\\Service');
        $lib_file = 'modules/' . $mod_name . '/service/' . $lib_name . '.php';
        
        if(is_file($lib_file))
            Bash::error('Service with the same file name already exists');
        
        $lib_config = BClass::getConfig($lib_ns, $lib_name, '\\Mim\\Service');
        BClass::write($here, $mod_conf, $lib_config, $lib_file);
        
        // inject autoload
        ALAdder::classes($mod_conf, $lib_ns, $lib_name, $lib_file);
        $mod_conf['service'][$name] = $lib_ns . '\\' . $lib_name;
        
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($mod_conf) . ';';
        
        Fs::write($mod_conf_file, $tx);
        
        return true;
    }
}