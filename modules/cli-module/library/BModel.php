<?php
/**
 * Model builder
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;
use CliModule\Library\AutoloadAdder as ALAdder;
use CliModule\Library\BClass;

class BModel
{
    static function build(string $here, string $table, array $config = []): bool{
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];

        $mod_conf = include $mod_conf_file;
        $mod_name = $mod_conf['__name'];
        
        $lib_name = to_ns($table);
        $lib_ns   = to_ns($mod_name . '\\Model');
        $lib_file = 'modules/' . $mod_name . '/model/' . $lib_name . '.php';
        
        if(is_file($lib_file)){
            $ow = Bash::ask([
                'text' => 'Model with the same name already exists, Overide?',
                'type' => 'bool',
                'default' => false
            ]);
            if (!$ow) {
                return false;
            }
        }
            
        $lib_config = array_replace_recursive([
            'name' => $lib_name,
            'ns' => $lib_ns,
            'extends' => '\\Mim\\Model',
            'implements' => [],
            'methods' => [],
            'properties' => [
                [
                    'name' => 'table',
                    'prefix' => 'protected static',
                    'value' => $table
                ],
                [
                    'name' => 'chains',
                    'prefix' => 'protected static',
                    'value' => []
                ],
                [
                    'name' => 'q',
                    'prefix' => 'protected static',
                    'value' => []
                ]
            ]
        ], $config);

        $lib_name = $lib_config['name'];
        $lib_ns = $lib_config['ns'];

        RequireAdder::module($mod_conf, 'lib-model', null);

        BClass::write($here, $mod_conf, $lib_config, $lib_file);
        
        // inject autoload
        ALAdder::classes($mod_conf, $lib_ns, $lib_name, $lib_file);
        
        self::_writeConfig($mod_conf_file, $mod_conf);

        $mig_file = 'modules/' . $mod_name . '/migrate.php';

        self::_addMigrate(
            $here,
            $lib_ns,
            $lib_name,
            $mig_file,
            $table,
            $mod_conf_file,
            $mod_conf,
            $lib_config
        );
        
        return true;
    }

    private static function _addMigrate(
            string $here,
            string $ns,
            string $name,
            string $file,
            string $table,
            string $cnf_file,
            array &$config,
            array &$lib_config
        ): void{
        $nl = PHP_EOL;

        $migrates = [];
        if(is_file($file))
            $migrates = include $file;

        if (isset($lib_config['fields'])) {
            $fields = $lib_config['fields'];
        } else {
            $fields = self::_collectFields($cnf_file, $config);
            $lib_config['fields'] = $fields;
        }

        self::_writeFormatter($table, $fields, $cnf_file, $config);
        $migrates[ $ns . '\\' . $name ] = [ 'fields' => $fields ];

        ksort($migrates);
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($migrates) . ';';
        
        Fs::write($file, $tx);
    }

    private static function _fldNextId(int &$int): int
    {
        $int+= 1000;
        return $int;
    }

    private static function _collectFields($cnf_file, &$config)
    {
        $fld_index = 0;

        $fmt = Bash::ask([
            'text' => 'Do you want to generate formatter?',
            'type' => 'bool',
            'default' => true
        ]);

        if ($fmt) {
            RequireAdder::module($config, 'lib-formatter', null);
            self::_writeConfig($cnf_file, $config);
        }

        $fields = [];

        // the id field type
        $id_types = [
            1 => 'BIGINT',
            2 => 'INTEGER',
            3 => 'MEDIUMINT',
            4 => 'SMALLINT',
            5 => 'TINYINT'
        ];
        $id_type = Bash::ask([
            'text' => 'The type of ID column (INTEGER)',
            'options' => $id_types,
            'default' => 2
        ]);
        $fields['id'] = [
            'type' => $id_types[$id_type],
            'attrs' => [
                'unsigned' => true,
                'primary_key' => true,
                'auto_increment' => true
            ],
            'index' => self::_fldNextId($fld_index)
        ];
        if ($fmt) {
            $fields['id']['format'] = ['type' => 'number'];
        }

        // the user field
        $inc_user = Bash::ask([
            'text' => 'Do you want to include user field?',
            'type' => 'bool',
            'default' => true
        ]);
        if ($inc_user) {
            $fields['user'] = [
                'type' => 'INT',
                'attrs' => [
                    'unsigned' => TRUE,
                    'null' => FALSE
                ],
                'index' => self::_fldNextId($fld_index)
            ];

            if ($fmt) {
                $fields['user']['format'] = ['type' => 'user'];
            }
        }

        // OTHER FIELDS
        $def_type_opts = [
            'TEXT',
            'VARCHAR',
            'BIGINT',
            'DOUBLE',
            'INTEGER',
            'TINYINT',
            'DATE',
            'DATETIME',
            '[MANUAL_INPUT]'
        ];
        $type_with_length = [
            'VARCHAR',
            'CHAR',
            'DOUBLE'
        ];
        $type_with_unsigned = [
            'BIGINT',
            'DECIMAL',
            'DOUBLE',
            'FLOAT',
            'INTEGER',
            'MEDIUMINT',
            'SMALLINT',
            'TINYINT'
        ];
        $type_uniqable = [
            'CHAR',
            'VARCHAR'
        ];

        while(true) {
            $col = [];

            // .name
            $col_name = Bash::ask([
                'text' => 'Add more column',
                'type' => 'text'
            ]);

            if (!$col_name) {
                break;
            }

            // .type
            $col['type'] = Bash::ask([
                'text' => 'Column type (INTEGER)',
                'options' => $def_type_opts,
                'default' => 4,
                'space' => 1
            ]);

            $col['type'] = $def_type_opts[ $col['type'] ];
            if ($col['type'] == '[MANUAL_INPUT]') {
                $col['type'] = Bash::ask([
                    'text' => 'Please input column type',
                    'required' => true,
                    'space' => 4
                ]);
            }

            // .length
            if (in_array($col['type'], $type_with_length)) {
                $col['length'] = Bash::ask([
                    'text' => 'LENGTH',
                    'required' => true,
                    'space' => 4,
                    'default' => $col['type'] == 'DOUBLE' ? '12,3' : '100'
                ]);
                if ($col['type'] === 'VARCHAR') {
                    $col['length'] = (int)$col['length'];
                }
            }

            $col_attrs = [];

            if ($col['type'] != 'TEXT') {
                // .attrs.unsigned
                if (in_array($col['type'], $type_with_unsigned)) {
                    $uns_cnf = [
                        'text' => 'UNSIGNED',
                        'default' => true,
                        'space' => 4,
                        'type' => 'bool'
                    ];
                    if (Bash::ask($uns_cnf)) {
                        $col_attrs['unsigned'] = true;
                    }
                }

                // .attrs.null
                $null_cnf = [
                    'text' => 'NULLABLE',
                    'default' => false,
                    'space' => 4,
                    'type' => 'bool'
                ];
                $col_attrs['null'] = Bash::ask($null_cnf);

                // .attrs.unique
                if (in_array($col['type'], $type_uniqable)) {
                    $uni_cnf = [
                        'text' => 'UNIQUE',
                        'default' => false,
                        'space' => 4,
                        'type' => 'bool'
                    ];
                    if (Bash::ask($uni_cnf)) {
                        $col_attrs['unique'] = true;
                    }
                }

                // .attrs.default
                if (!isset($col_attrs['unique']) || !$col_attrs['unique']) {
                    $def_cnf = [
                        'text' => 'DEFAULT',
                        'space' => 4,
                    ];
                    $def_val = Bash::ask($def_cnf);
                    if ($def_val) {
                        $col_attrs['default'] = $def_val;
                    }
                }
            }

            $col['attrs'] = $col_attrs;

            $col['index'] = self::_fldNextId($fld_index);

            if ($fmt) {
                $col['format'] = self::_fldFormat($col, $cnf_file, $config);
            }

            $fields[$col_name] = $col;
        }

        // the updated/created field
        $inc_timestamp = Bash::ask([
            'text' => 'Do you want to include updated and created field?',
            'type' => 'bool',
            'default' => true
        ]);
        if ($inc_timestamp) {
            $fields['updated'] = [
                'type' => 'TIMESTAMP',
                'attrs' => [
                    'default' => 'CURRENT_TIMESTAMP',
                    'update' => 'CURRENT_TIMESTAMP'
                ],
                'index' => self::_fldNextId($fld_index)
            ];

            $fields['created'] = [
                'type' => 'TIMESTAMP',
                'attrs' => [
                    'default' => 'CURRENT_TIMESTAMP'
                ],
                'index' => self::_fldNextId($fld_index)
            ];

            if ($fmt) {
                $fields['updated']['format'] = ['type' => 'date'];
                $fields['created']['format'] = ['type' => 'date'];
            }
        }

        return $fields;
    }

    private static function _fldFormat($field, $cnf_file, &$config)
    {
        $result = [
            'type' => null
        ];
        $type_with_unsigned = [
            'BIGINT',
            'DECIMAL',
            'DOUBLE',
            'FLOAT',
            'INTEGER',
            'MEDIUMINT',
            'SMALLINT',
            'TINYINT'
        ];

        $types = [
            'number',
            'text',
            'enum',
            'date',
            'media',
            'object',
            'json',
            'user',
            '[manual_input]'
        ];
        $default = 1;
        if (in_array($field['type'], $type_with_unsigned)) {
            $default = 0;
        }
        if (in_array($field['type'], ['DATE', 'DATETIME'])) {
            $default = 3;
        }
        $type = Bash::ask([
            'text' => 'Format type (' . $types[$default] . ')',
            'options' => $types,
            'default' => $default,
            'space' => 2
        ]);
        $type = $types[$type];
        if($type == '[manual_input]') {
            $type = Bash::ask([
                'text' => 'Please input format type',
                'space' => 4
            ]);
        }

        $result['type'] = $type;

        if ($type == 'enum') {
            $enum_name = Bash::ask([
                'text' => 'Enum name',
                'space' => 6,
                'required' => true
            ]);

            $enums = [];
            while (true) {
                $enum = Bash::ask([
                    'text' => 'Add enum options (value?/label)',
                    'space' => 6
                ]);
                if (!$enum) {
                    break;
                }
                $eval = explode('/', $enum);
                if (count($eval) == 1) {
                    $enums[] = $eval[0];
                } else {
                    $enums[ $eval[0] ] = $eval[1];
                }
            }
            $config = array_replace_recursive($config, [
                'libEnum' => [
                    'enums' => [
                        $enum_name => $enums
                    ]
                ]
            ]);

            $result['enum'] = $enum_name;
            if (is_indexed_array($enums)) {
                $result['vtype'] = 'int';
            }

            RequireAdder::module($config, 'lib-enum', null);
            self::_writeConfig($cnf_file, $config);
        }

        if ($type == 'object') {
            $model_name = Bash::ask([
                'text' => 'Model name',
                'space' => 6,
                'required' => true
            ]);

            $result['type'] = $type;
            if ($model_name) {
                $result['model'] = [
                    'name' => $model_name,
                    'field' => 'id',
                    'type' => 'number'
                ];
            }

            $format_name = Bash::ask([
                'text' => 'Format name',
                'space' => 6
            ]);
            $result['format'] = $format_name;
        }

        return $result;
    }

    private static function _writeConfig($file, $config)
    {
        $nl = PHP_EOL;

        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($config) . ';';

        Fs::write($file, $tx);
    }

    private static function _writeFormatter($name, &$fields, $cnf_file, &$config)
    {
        $formats = [];
        foreach ($fields as $field => &$migs) {
            if (!isset($migs['format'])) {
                continue;
            }
            $formats[$field] = $migs['format'];
            unset($migs['format']);
        }
        unset($migs);

        if (!$formats) {
            return;
        }

        $name = str_replace('_', '-', $name);
        $config = array_replace_recursive($config, [
            'libFormatter' => [
                'formats' => []
            ]
        ]);
        $config['libFormatter']['formats'][$name] = $formats;

        self::_writeConfig($cnf_file, $config);
    }
}
