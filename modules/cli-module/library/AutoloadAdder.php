<?php
/**
 * Module config autoload adder
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

class AutoloadAdder
{
    static function classes(array &$config, string $ns, string $name, string $path): void{
        $al_classes = $config['autoload']['classes'];
        $path_dir = dirname($path);
        
        $ns_cls = $ns . '\\' . $name;
        if(isset($al_classes[$ns_cls]))
            return;
        
        $use_direct = false;
        
        // 1. $ns is not exists
        //  - add one
        // 2. $ns exists
        //  a. [base] pointing to $path_dir
        //    - skip process
        //  b. [base] pointing somewhere else
        //     i. have [children]
        //        1) [children] pointing to $path_dir
        //          - skip process
        //        2) [children] pointing to somewhere else
        //          - set $use_direct as true
        //    ii. don't have [children]
        //        - add one
        
        /* 1. */
        if(!isset($al_classes[$ns])){
            $al_classes[$ns] = [
                'type' => 'file',
                'base' => $path_dir
            ];
        
        /* 2. */
        }else{
            $ans = $al_classes[$ns];
            
            /* 2.a */
            if($ans['base'] === $path_dir){
                // - skip process
                
            /* 2.b */
            }else{
                /* 2.b.i */
                if(isset($ans['children'])){
                    /* 2.b.i.1 */
                    if($ans['children'] === $path_dir){
                        // - skip process
                        
                    /* 2.b.i.2 */
                    }else{
                        $use_direct = true;
                    }
                    
                /* 2.b.ii */
                }else{
                    $ans['children'] = $path_dir;
                }
            }
            
            $al_classes[$ns] = $ans;
        }
        
        
        if($use_direct){
            $al_classes[$ns_cls] = [
                'type' => 'file',
                'base' => $path
            ];
        }
        
        $config['autoload']['classes'] = $al_classes;
    }
    
    static function files(array &$config, $path): void{
        $al_files = $config['autoload']['files'];
        
        if(isset($al_files[$path]))
            return;
        $al_files[$path] = true;
        
        $config['autoload']['files'] = $al_files;
    }
}