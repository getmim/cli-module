<?php
/**
 * Config require module adder
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

class RequireAdder
{
    static function module(array &$config, string $module, $repo): void
    {
        $requires = $config['__dependencies']['required'] ?? [];
        foreach ($requires as $reqs) {
            foreach ($reqs as $req => $rep) {
                if ($req == $module) {
                    return;
                }
            }
        }

        $requires[] = [
            $module => $repo
        ];

        $config['__dependencies']['required'] = $requires;
    }
}
