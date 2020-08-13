<?php
/**
 * Class builder
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;

class BClass
{
    static function getConfig(string $ns, string $name, string $extends=null, string $type="class"): array{
        $result = [
            'name'      => $name,
            'ns'        => $ns,
            'extends'   => $extends,
            'implements'=> [],
            'methods'   => [],
            'properties'=> [],
            'uses'      => []
        ];
        
        Bash::echo('We\'re going to create ' . $type . ' `' . $ns . '\\' . $name . '`');
        
        // .extends
        $extends_conf = [
            'text'      => 'Extends, incude ns',
            'space'     => 2
        ];
        if($extends)
            $extends_conf['default'] = $extends;
        
        $extends = Bash::ask($extends_conf);
        
        if($extends){
            if(false === strstr($extends, '\\'))
                $extends = '\\' . ucfirst($extends) . '\\Controller';
            $result['extends'] = $extends;
        }
        
        // .implements
        while(true){
            $space = $result['implements'] ? 3 : 2;
            $text  = $result['implements']
                ? 'Add more interface to implements, include ns'
                : 'Add interface to implements, include ns';
            
            $imp = Bash::ask([
                'text'  => $text,
                'space' => $space
            ]);
            if(!$imp)
                break;
            $result['implements'][] = $imp;
        }

        // properties
        $last_prefix = 'public';
        while(true){
            $space = $result['properties'] ? 3 : 2;
            $text  = $result['properties']
                ? 'Add more property'
                : 'Add property';
            
            $mth = Bash::ask([
                'text'  => $text,
                'space' => $space
            ]);
            if(!$mth)
                break;
            
            $prefix = Bash::ask([
                'text' => 'Property prefix [public,static,protected,private]',
                'space' => $space + 2,
                'default' => $last_prefix
            ]);

            $last_prefix = $prefix;
            
            $res = [
                'name' => $mth,
                'prefix' => $prefix
            ];

            if($type != 'interface' && false === strstr($mth, '=')){
                $res_val = Bash::ask([
                    'text' => 'Property default value',
                    'space' => $space + 2
                ]);
                if($res_val){
                    if(is_numeric($res_val))
                        $res_val = (int)$res_val;
                    $res['value'] = $res_val;
                }
            }
            
            $result['properties'][] = $res;
        }
        
        // methods
        while(true){
            $space = $result['methods'] ? 3 : 2;
            $text  = $result['methods']
                ? 'Add more method'
                : 'Add method';
            
            $mth = Bash::ask(['text'  => $text,'space' => $space]);
            if(!$mth)
                break;
            
            $prefix = Bash::ask([
                'text' => 'Method prefix [public,static,protected,private]',
                'space' => $space + 2,
                'default' => $last_prefix
            ]);

            $last_prefix = $prefix;
            
            $result['methods'][] = [
                'name' => $mth,
                'prefix' => $prefix
            ];
        }

        // uses
        while(true){
            $space = $result['uses'] ? 3 : 2;
            $text  = $result['uses'] ? 'Add more uses class' : 'Add uses class';

            $class = Bash::ask(['text'=>$text, 'space'=>$space]);
            if(!$class)
                break;

            $alias = null;

            $class = explode(' ', $class);
            if(count($class) === 2)
                $alias = $class[1];
            elseif(count($class) === 3 && $class[1] === 'as')
                $alias = $class[2];
            $class = $class[0];

            if($class === 'formatter')
                $class = 'LibFormatter\Library\Formatter';
            elseif($class === 'form')
                $class = 'LibForm\Library\Form';

            $className = explode('\\', $class);
            $className = end($className);

            if(!$alias){
                $alias = $className;

                if(false !== strstr($class, '\\Model\\')){
                    $lastLower = preg_replace('!^.+[A-Z]([a-z]+)!', '$1', $className);
                    $alias     = preg_replace('![a-z]!', '', $className) . $lastLower;
                }

                $useAs = Bash::ask([
                    'text'    => 'Alias name',
                    'space'   => $space + 2,
                    'default' => $alias
                ]);
            }

            $result['uses'][] = [
                'class' => $class,
                'alias' => $alias === $className ? NULL : $alias
            ];
        }
        
        return $result;
    }
    
    static function write(string $here, array $module, array $config, string $path, string $type='class'): void{
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= '/**' . $nl;
        $tx.= ' * ' . $config['name'] . $nl;
        $tx.= ' * @package ' . $module['__name'] . $nl;
        $tx.= ' * @version ' . $module['__version'] . $nl;
        $tx.= ' */' . $nl;
        $tx.= $nl;
        $tx.= 'namespace ' . trim($config['ns'], '\\ ') . ';' . $nl;
        $tx.= $nl;

        if(isset($config['uses'])){
            foreach($config['uses'] as $uses){
                $tx.= 'use ' . $uses['class'];
                if($uses['alias'])
                    $tx.= ' as ' . $uses['alias'];
                $tx.= ';' . $nl;
            }
            $tx.= $nl;
        }

        $tx.= $type . ' ';
        $tx.= $config['name'];
        
        if(isset($config['extends']))
            $tx.= ' extends ' . $config['extends'];
        
        $tx.= $nl;
        
        if($config['implements']){
            $tx.= '    implements ' . $nl;
            $ifaces = [];
            foreach($config['implements'] as $cls)
                $ifaces[] = $cls;
            $tx.= '        ';
            $tx.= implode(",$nl        ", $ifaces);
            $tx.= $nl;
        }
        
        $tx.= '{' . $nl;
        
        foreach($config['properties'] as $mth){
            $mth_name = trim(chop($mth['name'], ';'));
            if(false !== strstr($mth_name, '='))
                $mth_name = preg_replace('! *= *!', ' = ', $mth_name);
            
            $prefix = '';
            if($mth['prefix'])
                $prefix = trim($mth['prefix']) . ' ';
            $tx.= $nl;
            $tx.= '    ' . $prefix . '$' . $mth_name;
            if(isset($mth['value']))
                $tx.= ' = ' . to_source($mth['value']);
            $tx.= ';' . $nl;
        }
        
        foreach($config['methods'] as $mth){
            $prefix = '';
            if($mth['prefix'])
                $prefix = trim($mth['prefix']) . ' ';
            $mth_name = trim($mth['name']);
            if(false === strstr($mth_name, '('))
                $mth_name.= '()';
            
            $tx.= $nl;
            $tx.= '    ' . $prefix . 'function ' . $mth_name;

            if($type != 'interface'){
                $tx.= ' {' . $nl;
                $tx.= '        ' . $nl;
                $tx.= '    }' . $nl;
            }else{
                $tx.= ';' . $nl;
            }
        }
        
        $tx.= '}';
        
        Fs::write($here . '/' . $path, $tx);
    }
}