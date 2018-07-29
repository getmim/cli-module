<?php
/**
 * Model builder
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;
use CliModule\Library\AutoloadAdder as ALAdder;
use CliModule\Library\BClass;

class BModel
{
    static function build(string $here, string $table): bool{
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        $mod_name = $mod_conf['__name'];
        
        $lib_name = to_ns($table);
        $lib_ns   = to_ns($mod_name . '\\Model');
        $lib_file = 'modules/' . $mod_name . '/model/' . $lib_name . '.php';
        
        if(is_file($lib_file))
            Bash::error('Model with the same file name already exists');
            
        $lib_config = [
            'name' => $lib_name,
            'ns' => $lib_ns,
            'extends' => '\\Mim\\Model',
            'implements' => [],
            'methods' => [],
            'properties' => [
                [
                    'name' => 'table',
                    'prefix' => 'protected',
                    'value' => $table
                ],
                [
                    'name' => 'fields',
                    'prefix' => 'protected',
                    'value' => []
                ]
            ]
        ];
        
        BClass::write($here, $mod_conf, $lib_config, $lib_file);
        
        // inject autoload
        ALAdder::classes($mod_conf, $lib_ns, $lib_name, $lib_file);
        
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($mod_conf) . ';';
        
        Fs::write($mod_conf_file, $tx);
        
        return true;
    }
}