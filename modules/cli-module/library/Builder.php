<?php
/**
 * Module builder
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Mim\Library\Fs;
use Cli\Library\Bash;
use CliModule\Library\ConfigCollector;

class Builder
{   
    static function build(string $here): bool{
        // make sure the folder is empty
        if(Fs::scan($here))
            Bash::error('Target directory is not empty');
        
        // make sure we can write here
        if(!is_writable($here))
            Bash::error('Unable to write to current directory');
        
        $config = ConfigCollector::collect($here);
        if(!$config)
            return false;
        
        $mod_name = $config['__name'];
        $mod_dir  = $here . '/modules/' . $mod_name;
        $mod_conf_file = $mod_dir . '/config.php';
        
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($config) . ';';
        
        Fs::write($mod_conf_file, $tx);
        
        // now, create readme file 
        self::readme($here, $config['__name'], $config['__git']);
        return true;
    }
    
    static function readme(string $here, string $name, string $git): void{
        $readme_file = $here . '/README.md';
        
        $repo = strstr($git, 'getmim') ? $name : $git;
        
        $nl = PHP_EOL;
        
        $tx = '# ' . $name . $nl;
        $tx.= $nl;
        $tx.= '## Instalasi' . $nl;
        $tx.= $nl;
        $tx.= 'Jalankan perintah di bawah di folder aplikasi:' . $nl;
        $tx.= $nl;
        $tx.= '```' . $nl;
        $tx.= 'mim app install ' . $repo . $nl;
        $tx.= '```' . $nl;
        
        Fs::write($readme_file, $tx);
    }
}