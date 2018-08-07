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
    static function getConfig(string $ns, string $name, string $extends=null): array{
        $result = [
            'name' => $name,
            'ns' => $ns,
            'extends' => $extends,
            'implements' => [],
            'methods' => [],
            'properties' => []
        ];
        
        Bash::echo('We\'re going to create class `' . $ns . '\\' . $name . '`');
        
        $extends_conf = [
            'text'      => 'Class extends, incude ns',
            'space'     => 2
        ];
        if($extends)
            $extends_conf['default'] = $extends;
        
        $extends = Bash::ask($extends_conf);
        
        if($extends)
            $result['extends'] = $extends;
        
        while(true){
            $space = $result['implements'] ? 3 : 2;
            $text  = $result['implements']
                ? 'Add more class implements interface, include ns'
                : 'Add class implements interface, include ns';
            
            $imp = Bash::ask([
                'text'  => $text,
                'space' => $space
            ]);
            if(!$imp)
                break;
            $result['implements'][] = $imp;
        }
        
        $last_prefix = 'public';
        while(true){
            $space = $result['properties'] ? 3 : 2;
            $text  = $result['properties']
                ? 'Add more class property'
                : 'Add class property';
            
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

            if(false === strstr($mth, '=')){
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
        
        while(true){
            $space = $result['methods'] ? 3 : 2;
            $text  = $result['methods']
                ? 'Add more class method'
                : 'Add class method';
            
            $mth = Bash::ask([
                'text'  => $text,
                'space' => $space
            ]);
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
        
        return $result;
    }
    
    static function write(string $here, array $module, array $config, string $path): void{
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
        $tx.= 'class ' . $config['name'];
        
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
            $tx.= '    ' . $prefix . 'function ' . $mth_name . ' {' . $nl;
            $tx.= '        ' . $nl;
            $tx.= '    }' . $nl;
        }
        
        $tx.= '}';
        
        Fs::write($here . '/' . $path, $tx);
    }
}