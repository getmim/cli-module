<?php
/**
 * Module config collector
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Mim\Library\Fs;
use Cli\Library\Bash;
use CliApp\Library\ConfigInjector;

class ConfigCollector
{
    private static $default = [
        'license' => 'MIT',
        'author' => [
            'name' => '',
            'email' => '',
            'website' => ''
        ]
    ];
    
    private static function autoload(&$config, string $here): void{
        $config['autoload'] = [
            'classes' => [],
            'files' => []
        ];
    }
    
    private static function author(&$config, string $here): void{
        $def_name = self::$default['author']['name'];
        $def_email = self::$default['author']['email'];
        $def_website = self::$default['author']['website'];
        
        $options= [
            [
                'name'      => '__author',
                'question'  => 'Module author',
                'children'  => [
                    [
                        'name'      => 'name',
                        'question'  => 'Name',
                        'default'   => $def_name,
                        'rule'      => '!^.+$!'
                    ],
                    [
                        'name'      => 'email',
                        'question'  => 'Email',
                        'default'   => $def_email,
                        'rule'      => '!^.+$!'
                    ],
                    [
                        'name'      => 'website',
                        'question'  => 'Website',
                        'default'   => $def_website,
                        'rule'      => '!^.+$!'
                    ]
                ]
            ]
        ];
        
        ConfigInjector::injectConfigs($config, $options);
    }
    
    private static function dependency(&$config, string $here): void{
        $config['__dependencies'] = [];
        
        $types = ['required', 'optional'];
        
        foreach($types as $type){
            $config['__dependencies'][$type] = [];
            
            $ask_conf = [
                'type' => 'bool',
                'text' => 'Would you like to add some ' . $type . ' dependency?',
                'default' => false
            ];
            
            if(!Bash::ask($ask_conf))
                continue;
                    
            while(true){
                $mods = [];
                while(true){
                    $ask_text = $mods
                        ? 'Add module alternative'
                        : 'Add new dependency';
                    
                    $ask_conf = [
                        'type' => 'any',
                        'text' => $ask_text,
                        'space' => $mods ? 6 : 4
                    ];
                    
                    $mod = Bash::ask($ask_conf);
                    if(!$mod)
                        break;
                    
                    $ask_conf = [
                        'type' => 'any',
                        'text' => 'Module git repository',
                        'space' => 8
                    ];
                    $mod_git = Bash::ask($ask_conf);
                    
                    $mods[$mod] = $mod_git ? $mod_git : null;
                }
                
                if($mods)
                    $config['__dependencies'][$type][] = $mods;
                else
                    break;
            }
        }
    }
    
    private static function files(&$config, string $here): void{
        $module_dir = 'modules/' . $config['__name'];
        
        Bash::echo('Please add list of files rules of the module');
        
        $files = [];
        
        while(true){
            $conf = [
                'type' => 'any',
                'text' => 'Add new file path',
                'space' => 4
            ];
            
            if(!$files)
                $conf['default'] = $module_dir;
            
            $line = Bash::ask($conf);
            if(!$line)
                break;
            
            $conf = [
                'type' => 'any',
                'text' => 'File rule',
                'space' => 8,
                'default' => 'install, update, remove'
            ];
            
            $rule = Bash::ask($conf);
            $rule = explode(',', $rule);
            $rule = array_map(function($a){ return trim($a); }, $rule);
            
            $files[$line] = $rule;
        }
        
        if(!$files){
            $files = [
                $module_dir => ['install', 'update', 'remove']
            ];
        }
        
        $config['__files'] = $files;
    }
    
    private static function general(&$config, string $here): void{
        $def_name = basename($here);
        
        $options= [
            [
                'name'      => '__name',
                'question'  => 'Module name',
                'default'   => $def_name,
                'rule'      => '!^[a-z0-9-]+$!'
            ],
            [
                'name'      => '__version',
                'question'  => 'Module version',
                'default'   => '0.0.1',
                'rule'      => '!^.+$!'
            ]
        ];
        
        ConfigInjector::injectConfigs($config, $options);
    }
    
    private static function gitignore(&$config, string $here): void{
        $ask_conf = [
            'type' => 'bool',
            'text' => 'Is there any gitignore content to add?',
            'default' => false
        ];
        if(!Bash::ask($ask_conf))
            return;
        
        $config['__gitignore'] = [];
        
        while(true){
            $conf = [
                'text' => 'Add new gitignore line',
                'type' => 'any',
                'space' => 4
            ];
            $line = Bash::ask($conf);
            if(!$line)
                break;
            
            $config['__gitignore'][$line] = true;
        }
    }
    
    private static function license(&$config, string $here): void{
        $def_license = self::$default['license'];
        
        $options= [
            [
                'name'      => '__license',
                'question'  => 'Module license',
                'default'   => $def_license,
                'rule'      => '!^.+$!'
            ]
        ];
        
        ConfigInjector::injectConfigs($config, $options);
    }
    
    private static function parseDefault(){
        $def_file = BASEPATH . '/etc/cache/module-init.php';
        if(is_file($def_file))
            self::$default = include $def_file;
    }
    
    private static function repository(&$config, string $here): void{
        $def_repo = 'git@github.com:getmim/' . $config['__name'] . '.git';
        
        $options= [
            [
                'name'      => '__git',
                'question'  => 'Module git repository',
                'default'   => $def_repo,
                'rule'      => '!^.+$!'
            ]
        ];
        
        ConfigInjector::injectConfigs($config, $options);
    }
    
    private static function saveDefault($config){
        $def_file = BASEPATH . '/etc/cache/module-init.php';
        
        $data = [
            'license' => $config['__license'],
            'author'  => $config['__author']
        ];
        
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= 'return ' . to_source($data) . ';';
        
        $f = fopen($def_file, 'w');
        fwrite($f, $tx);
        fclose($f);
    }
    
    static function collect(string $here): ?array{
        $config = [];
        
        $methods = [
            'parseDefault',
            'general',
            'repository',
            'license',
            'author',
            'files',
            'dependency',
            'gitignore',
            'autoload',
            'saveDefault'
        ];
        
        foreach($methods as $method)
            self::$method($config, $here);
        
        return $config;
    }
}