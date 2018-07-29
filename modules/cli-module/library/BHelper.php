<?php
/**
 * Blank helper file builder
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;
use CliModule\Library\AutoloadAdder as ALAdder;

class BHelper
{
    static function build(string $here, string $name): bool{
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        $mod_name = $mod_conf['__name'];
        
        $helper_file = 'modules/' . $mod_name . '/helper/' . $name . '.php';
        
        if(is_file($helper_file))
            Bash::error('Helper with the same name already exists');
        
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= '// ' . $mod_name . ' ' . $name . ' helper' . $nl;
        Fs::write($here . '/' . $helper_file, $tx);
        
        ALAdder::files($mod_conf, $helper_file);
        
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($mod_conf) . ';';
        
        Fs::write($mod_conf_file, $tx);
        
        return true;
    }
}