<?php
/**
 * Autocomplete provider
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

class Autocomplete extends \Cli\Autocomplete
{
    static function command(array $args): string{
        $farg = $args[1] ?? null;
        $result = ['init', 'controller', 'helper', 'library', 'model', 'service', 'watch', 'sync'];

        if(!$farg)
            return trim(implode(' ', $result));

        return parent::lastArg($farg, $result);
    }
}