<?php
/**
 * Badmin
 * @package cli-module
 * @version 1.1.0
 */

namespace CliModule\Library;

use Cli\Library\Bash;

class BAdmin
{
    static function build(string $here, string $name): bool{
        Bash::error('This feature is still under development');
    }
}