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
                    'prefix' => 'protected static',
                    'value' => $table
                ],
                [
                    'name' => 'chains',
                    'prefix' => 'protected static',
                    'value' => []
                ],
                [
                    'name' => 'q',
                    'prefix' => 'protected static',
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

        $mig_file = 'modules/' . $mod_name . '/migrate.php';

        self::_addMigrate($here, $lib_ns, $lib_name, $mig_file);
        
        return true;
    }

    private static function _addMigrate(string $here, string $ns, string $name, string $file): void{
        $nl = PHP_EOL;

        $migrates = [];
        if(is_file($file))
            $migrates = include $file;

        $migrates[ $ns . '\\' . $name ] = [
            'fields' => [
                'id' => [
                    'type' => 'INT',
                    'attrs' => [
                        'unsigned' => true,
                        'primary_key' => true,
                        'auto_increment' => true
                    ],
                    'index' => 1000
                ],
                'user' => [
                    'type' => 'INT',
                    'attrs' => [
                        'unsigned' => TRUE,
                        'null' => FALSE
                    ],
                    'index' => 2000
                ],
                'updated' => [
                    'type' => 'TIMESTAMP',
                    'attrs' => [
                        'default' => 'CURRENT_TIMESTAMP',
                        'update' => 'CURRENT_TIMESTAMP'
                    ],
                    'index' => 10000
                ],
                'created' => [
                    'type' => 'TIMESTAMP',
                    'attrs' => [
                        'default' => 'CURRENT_TIMESTAMP'
                    ],
                    'index' => 11000
                ]
            ]
        ];

        ksort($migrates);
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($migrates) . ';';
        
        Fs::write($file, $tx);
    }
}