<?php

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;

class ControlMethodCollector
{
    protected static $methods = [
        'admin' => [
            'index',
            'edit',
            'details',
            'remove'
        ],
        'api' => [
            'index',
            'single',
            'create',
            'update',
            'delete',
        ],
        'site' => [
            'index',
            'single'
        ],
        'cli' => [
            'worker'
        ]
    ];

    protected static function genFormName($gate, $action, $format)
    {
        $object = $format['name'];
        $form = $gate . '.' . $object . '.';

        if ($gate == 'admin') {
            $form.= $action == 'edit' ? 'edit' : 'index';
        } else {
            $form.= $action;
        }

        return $form;
    }

    protected static function setCreateSetter(&$opts, $gate, $action)
    {
        $setable = [
            'api' => ['create'],
            'admin' => ['edit']
        ];

        if (!isset($setable[$gate])) {
            return;
        }

        if (!in_array($action, $setable[$gate])) {
            return;
        }

        $opts['columns'] = [];

        // set user property
        $user = Bash::ask([
            'text' => 'Set user property',
            'space' => 2,
            'default' => true,
            'type' => 'bool'
        ]);
        if ($user) {
            if (!isset($opts['columns']['services'])) {
                $opts['columns']['services'] = [];
            }
            $opts['columns']['services']['user'] = [
                'property' => 'id',
                'column' => 'user'
            ];
        }
    }

    protected static function setQueryFilter(&$opts, $action)
    {
        if ($action !== 'index') {
            return;
        }

        $opts['filters'] = [];
        Bash::echo('Filterable column by query', 2, true);
        while(true) {
            $query = Bash::ask([
                'text' => 'Add column',
                'space' => 4
            ]);
            if (!$query) {
                break;
            }
            $opts['filters'][] = $query;
        }
    }

    protected static function setQuerySort(&$opts, $gate, $action)
    {
        if ($action !== 'index' || $gate != 'api') {
            return;
        }

        $opts['sorts'] = [];
        Bash::echo('Sortable column by query', 2, true);
        while(true) {
            $field = Bash::ask([
                'text' => 'Add column',
                'space' => 4
            ]);
            if (!$field) {
                break;
            }
            $opts['sorts'][] = $field;
        }
    }

    protected static function setForm(&$opts, $gate, $action, $format, $methods)
    {
        $with_form = [
            'admin' => [
                'edit',
                'index',
                'remove'
            ],
            'api' => [
                'create',
                'update'
            ]
        ];

        if (!isset($with_form[$gate])) {
            return;
        }

        if (!in_array($action, $with_form[$gate])) {
            return;
        }

        $default = self::genFormName($gate, $action, $format);
        if ($gate == 'admin' && $action == 'remove') {
            if (isset($methods['index'])) {
                if (isset($methods['index']['form'])) {
                    $default = $methods['index']['form'];
                }
            }
        }

        $opts['form'] = Bash::ask([
            'text' => 'Form name',
            'default' => $default,
            'space' => 2,
            'required' => true
        ]);
    }

    protected static function setSoftDelete(&$opts, $action)
    {
        if (!in_array($action, ['delete', 'remove'])) {
            return;
        }

        $status = Bash::ask([
            'text' => 'Soft delete to change status to',
            'space' => 2,
            'default' => null
        ]);

        if ('' !== $status) {
            $opts['status'] = $status;
        }
    }

    static function collect(&$result)
    {
        $gate = $result['gate'];
        if (!isset(self::$methods[$gate])) {
            return;
        }

        $methods = self::$methods[$gate];
        $selecteds = [];
        $result['methods'] = [];

        while (true) {
            $temp_methods = [];
            foreach ($methods as $method) {
                if (!in_array($method, $selecteds)) {
                    $temp_methods[] = $method;
                }
            }

            if (!$temp_methods) {
                $action = Bash::ask([
                    'text' => 'More custom method'
                ]);

                if (!$action) {
                    break;
                }
            } else {
                array_unshift($temp_methods, '[done]');
                $temp_methods[] = '[manual_input]';
                $index = Bash::ask([
                    'text' => 'Add controller method ([done])',
                    'options' => $temp_methods,
                    'default' => 0
                ]);
                $action = $temp_methods[$index];

                if ($action == '[done]') {
                    break;
                }

                if ($action == '[manual_input]') {
                    $action = Bash::ask([
                        'text' => 'Method name',
                        'space' => 2,
                        'required' => true
                    ]);
                }
            }

            $opts = [];
            $selecteds[] = $action;
            $format = $result['format'] ?? [];

            self::setForm($opts, $gate, $action, $format, $result['methods']);
            self::setQueryFilter($opts, $action);
            self::setQuerySort($opts, $gate, $action);
            self::setCreateSetter($opts, $gate, $action);
            self::setSoftDelete($opts, $action);

            $result['methods'][$action] = $opts;
        }
    }
}
