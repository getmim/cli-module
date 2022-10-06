<?php

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;

class ControlMigrationAdmin
{
    public static function create(&$config, $class, $here)
    {
        $mod_name = $config['__name'];
        $mig_file = $here . '/modules/' . $mod_name . '/migrate.php';

        $perms = $class['perms']['prefix'];
        $group = $class['perms']['group'];
        $format = $class['format']['name'];

        $data = [];
        foreach ($class['methods'] as $method => $opt) {
            if ($method == 'edit') {
                $data[$perms . '_update'] = [
                    'group' => $group,
                    'about' => 'Allow user to edit ' . $format
                ];
                $data[$perms . '_create'] = [
                    'group' => $group,
                    'about' => 'Allow user to create new ' . $format
                ];
            } elseif ($method == 'details' || $method == 'index') {
                $data[$perms . '_read'] = [
                    'group' => $group,
                    'about' => 'Allow user to read ' . $format
                ];
            } elseif ($method == 'remove') {
                $data[$perms . '_remove'] = [
                    'group' => $group,
                    'about' => 'Allow user to read ' . $format
                ];
            }
        }

        if (!$data) {
            return;
        }

        $migs = [
            'LibUserPerm\\Model\\UserPerm' => [
                'data' => [
                    'name' => $data
                ]
            ]
        ];

        $nl = PHP_EOL;

        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($migs) . ';';

        Fs::write($mig_file, $tx);
    }
}
