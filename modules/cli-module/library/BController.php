<?php
/**
 * Controller information collector
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;
use CliModule\Library\AutoloadAdder as ALAdder;
use CliModule\Library\BClass;

class BController
{
    protected static function getCGate(&$result)
    {
        $gates = ['api', 'site', 'admin', 'cli', '[manual_input]'];
        $index = Bash::ask([
            'text' => 'Router gate ( api )',
            'options' => $gates,
            'default' => 0
        ]);
        $gate = $gates[$index];
        if ($gate == '[manual_input]') {
            $gate = Bash::ask([
                'text' => 'Gate name',
                'space' => 2,
                'required' => true
            ]);
        }

        $result['gate'] = $gate;

        $extend = '\\' . ucfirst($gate) . '\\Controller';
        $result['extends'] = Bash::ask([
            'text' => 'Extends class',
            'default' => $extend,
            'required' => true
        ]);
    }

    protected static function getCMethod(&$result)
    {
        $methods = [
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
            ]
        ];

        $gate = $result['gate'];
        if (!isset($methods[$gate])) {
            return;
        }

        $methods = $methods[$gate];
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
                    'text' => 'Add control method ([done])',
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

            $opts = [
                'auth' => []
            ];
            $selecteds[] = $action;

            // 1. App auth for gate API
            if ($gate === 'api') {
                $opts['auth']['app'] = Bash::ask([
                    'text' => 'Authorized app',
                    'default' => true,
                    'space' => 2,
                    'type' => 'bool'
                ]);
            }

            // 2. User auth for all gates
            $opts['auth']['user'] = Bash::ask([
                'text' => 'Require user login',
                'default' => $gate != 'site',
                'space' => 2,
                'type' => 'bool'
            ]);

            // 3. Form name for create, update, and edit action
            $with_forms = [
                'create',
                'update',
                'edit'
            ];
            if (in_array($action, $with_forms)) {
                $form_name = implode('.', [
                    $result['gate'],
                    $result['format'],
                    $action
                ]);

                $opts['form'] = Bash::ask([
                    'text' => 'Form name',
                    'default' => $form_name,
                    'space' => 2,
                    'required' => true
                ]);
            }

            // 4. Getter filters
            $with_filters = [
                'index',
                'single',
                'update',
                'delete',
                'remove',
                'edit'
            ];
            if (in_array($action, $with_filters)) {
                $opts['filters'] = [
                    'parents' => []
                ];

                // 4.a filter by status
                $opts['filters']['status'] = Bash::ask([
                    'text' => 'Filter by object status value',
                    'space' => 2,
                    'default' => null
                ]);

                // 4.b filter by logged in user
                $opts['filters']['user'] = Bash::ask([
                    'text' => 'Filter by logged in user',
                    'space' => 2,
                    'default' => true,
                    'type' => 'bool'
                ]);

                // 4.c filter by query string
                if ($action == 'index') {
                    $opts['filters']['query'] = [];
                    Bash::echo('Filterable column by query', 2, true);
                    while(true) {
                        $query = Bash::ask([
                            'text' => 'Add column',
                            'space' => 4
                        ]);
                        if (!$query) {
                            break;
                        }
                        $opts['filters']['query'][] = $query;
                    }
                }

                // 4.d filter by parents property
                if ($result['parents']) {
                    Bash::echo('Parent as condition', 2, true);
                    foreach($result['parents'] as $name => $opt) {
                        if (isset($opt['model'])) {
                            $par_prop = Bash::ask([
                                'space' => 4,
                                'text' => 'Parent `' . $name . '` property'
                            ]);
                        } else {
                            $par_prop = !!Bash::ask([
                                'space' => 4,
                                'text' => 'Use parent `' . $name . '`',
                                'type' => 'bool',
                                'default' => false
                            ]);
                        }

                         if ($par_prop) {
                            $obj_prop = Bash::ask([
                                'space' => 6,
                                'text' => 'Object column name',
                                'required' => true,
                                'default' => $name
                            ]);

                            $opts['filters']['parents'][$name] = [
                                'par_prop' => $par_prop,
                                'obj_prop' => $obj_prop
                            ];
                        }
                    }
                }
            }

            // spacial methods
            switch($action) {
                case 'index':
                    // sortable column
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
                    break;

                case 'create':
                    // columns autofill
                    $opts['columns'] = [
                        'user' => Bash::ask([
                            'text' => 'Set user property',
                            'space' => 2,
                            'default' => true,
                            'type' => 'bool'
                        ]),
                        'status' => Bash::ask([
                            'text' => 'Set status property value',
                            'space' => 2
                        ]),
                        'parents' => []
                    ];

                    // column from parent
                    if ($result['parents']) {
                        Bash::echo('Add parent on object column', 2, true);
                        foreach($result['parents'] as $name => $opt) {
                            if (isset($opt['model'])) {
                                $par_prop = Bash::ask([
                                    'space' => 4,
                                    'text' => 'Parent `' . $name . '` property'
                                ]);
                            } else {
                                $par_prop = !!Bash::ask([
                                    'space' => 4,
                                    'text' => 'Use parent `' . $name . '`',
                                    'type' => 'bool',
                                    'default' => false
                                ]);
                            }

                             if ($par_prop) {
                                $obj_prop = Bash::ask([
                                    'space' => 6,
                                    'text' => 'Object column name',
                                    'required' => true,
                                    'default' => $name
                                ]);

                                $opts['columns']['parents'][$name] = [
                                    'par_prop' => $par_prop,
                                    'obj_prop' => $obj_prop
                                ];
                            }
                        }
                    }
                    break;

                case 'delete':
                    // soft delete
                    $opts['status'] = Bash::ask([
                        'text' => 'Change object status instead to',
                        'space' => 2,
                        'default' => null
                    ]);
                    break;
            }

            $result['methods'][$action] = $opts;
        }
    }

    protected static function getCModel(&$result)
    {
        $model = Bash::ask([
            'text' => 'Handling model',
            'required' => true
        ]);
        $model = str_replace('/', '\\', $model);
        $model = '\\' . ltrim($model, '\\');
        $result['model'] = $model;

        $names = explode('\\', $model);
        $name = end($names);

        $name = preg_replace('![A-Z]!', '-$0', $name);
        $name = preg_replace('!^-!', '', $name);
        $name = strtolower($name);

        $result['format'] = Bash::ask([
            'text' => 'Object format name',
            'default' => $name
        ]);
    }

    protected static function getCRoute(&$result)
    {
        $prefix = Bash::ask([
            'text' => 'Route prefix',
            'required' => true
        ]);

        $prefix = chop($prefix, '/');

        $route = [
            'path' => [
                'value' => $prefix,
                'params' => []
            ]
        ];

        $result['route'] = &$route;
        $result['parents'] = [];

        if (!preg_match_all('!(\(\:(?<name>[a-z-]+)\))!', $prefix, $matches)) {
            return;
        }

        $names = $matches['name'];

        $param_types = [
            'any', 'slug', 'number', 'rest'
        ];

        foreach ($names as $name) {
            Bash::echo('Parameter `' . $name . '` properties', 2, true);

            $type = Bash::ask([
                'text' => 'Type ( number )',
                'default' => 'number',
                'options' => $param_types,
                'default' => 2,
                'space' => 4
            ]);
            $route['path']['params'][$name] = $param_types[$type];

            // include in property of created object
            $parent = [
                // 'increate' => Bash::ask([
                //     'text' => 'Set on main object creation, column name',
                //     'space' => 4
                // ])
            ];

            $model = Bash::ask([
                'text' => 'Model name',
                'space' => 4
            ]);
            if ($model) {
                $parent['model'] = $model;
                $parent['field'] = Bash::ask([
                    'text' => 'Where column',
                    'default' => 'id',
                    'space' => 6
                ]);
                $parent['filters'] = [
                    'user' => Bash::ask([
                        'text' => 'Filter by logged in user',
                        'type' => 'bool',
                        'space' => 6,
                        'default' => false
                    ]),
                    'status' => Bash::ask([
                        'text' => 'Filter by status',
                        'type' => 'any',
                        'space' => 6,
                    ])
                ];
            }

            $result['parents'][$name] = $parent;
        }
    }

    protected static function buildControlConfig()
    {
        $result = [];

        self::getCGate($result);
        self::getCModel($result);
        self::getCRoute($result);
        self::getCMethod($result);

        return $result;
    }

    protected static function setConfigFormatter(&$config, $class)
    {
        if (!$class['format']) {
            return;
        }

        RequireAdder::module($config, 'lib-formatter', null);
    }

    protected static function setConfigForms(&$config, $class)
    {
        $forms = [];
        $methods = ['create', 'update', 'edit'];

        $found = false;
        foreach ($methods as $method) {
            if (!isset($class['methods'][$method])) {
                continue;
            }

            $found = true;
            $form = $class['methods'][$method]['form'];

            if (!isset($config['libForm'])) {
                $config['libForm'] = [];
            }

            if (!isset($config['libForm']['forms'])) {
                $config['libForm']['forms'] = [];
            }

            if (!isset($config['libForm']['forms'][$form])) {
                $config['libForm']['forms'][$form] = [];
            }
        }

        // add required module 'lib-form'
        if ($found) {
            RequireAdder::module($config, 'lib-form', null);
        }
    }

    protected static function setConfigGate(&$config, $class)
    {
        $with_modules = ['admin', 'site', 'api', 'cli'];
        $gate = $class['gate'];

        if (in_array($gate, $with_modules)) {
            RequireAdder::module($config, $gate, null);
        }
    }

    protected static function setConfigPagination(&$config, $class)
    {
        $gates = ['admin', 'site'];
        if (!in_array($class['gate'], $gates)) {
            return;
        }

        if (!isset($class['methods']['index'])) {
            return;
        }

        RequireAdder::module($config, 'lib-pagination', null);
    }

    protected static function setConfigRouter(&$config, $class, $ns, $name)
    {
        $methods = [
            'admin' => [
                'index'     => 'GET',
                'edit'      => 'GET|POST',
                'details'   => 'GET',
                'remove'    => 'GET'
            ],
            'api' => [
                'index'     => 'GET',
                'single'    => 'GET',
                'create'    => 'POST',
                'update'    => 'PUT',
                'delete'    => 'DELETE',
            ],
            'site' => [
                'index'     => 'GET',
                'single'    => 'GET'
            ]
        ];

        $gate = $class['gate'];
        $methods = $methods[$gate] ?? [];
        $format = $class['format'];

        $ctrl_name = $ns . '\\' . preg_replace('!Controller$!', '', $name);

        if (!isset($config['routes'])) {
            $config['routes'] = [];
        }

        if (!isset($config['routes'][$gate])) {
            $config['routes'][$gate] = [];
        }

        $prefix = $gate;
        if (isset($class['parents'])) {
            foreach ($class['parents'] as $parent => $opt) {
                $prefix.= ucfirst($parent);
            }
        }

        $format_cc = preg_replace('![^a-zA-Z0-9]!', ' ', $format);
        $format_cc = ucwords($format_cc);
        $format_cc = str_replace(' ', '', $format_cc);

        $prefix.= $format_cc;

        $with_suffix_id = [
            'edit',
            'details',
            'remove',
            'single',
            'update',
            'delete'
        ];
        foreach ($class['methods'] as $method => $opts) {
            $route_name = $prefix . ucfirst($method);
            $route_path = $class['route']['path'];

            if (in_array($method, $with_suffix_id)) {
                $route_path['value'] .= '/(:id)';
                $route_path['params']['id'] = 'number';
            }

            if (!$route_path['params']) {
                unset($route_path['params']);
            }

            $config['routes'][$gate][$route_name] = [
                'path' => $route_path,
                'handler' => $ctrl_name . '::' . $method,
                'method' => $methods[$method] ?? 'GET'
            ];
        }
    }

    static function build(string $here, string $name): bool{
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        $mod_name = $mod_conf['__name'];
        
        $ctrl_name = to_ns($name . 'Controller');
        $ctrl_ns   = to_ns($mod_name . '\\Controller');
        $ctrl_file = 'modules/' . $mod_name . '/controller/' . $ctrl_name . '.php';
        
        if(is_file($ctrl_file))
            Bash::error('Controller with the same file name already exists');
        
        $ctrl_config = self::buildControlConfig();
        $ctrl_config['name'] = $ctrl_name;
        $ctrl_config['ns'] = $ctrl_ns;

        self::setConfigForms($mod_conf, $ctrl_config);
        self::setConfigFormatter($mod_conf, $ctrl_config);
        self::setConfigGate($mod_conf, $ctrl_config);
        self::setConfigPagination($mod_conf, $ctrl_config);
        self::setConfigRouter($mod_conf, $ctrl_config, $ctrl_ns, $ctrl_name);
        ALAdder::classes($mod_conf, $ctrl_ns, $ctrl_name, $ctrl_file);

        ControlWriter::write($here, $mod_conf, $ctrl_config, $ctrl_file);
        
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($mod_conf) . ';';
        
        Fs::write($mod_conf_file, $tx);
        
        return true;
    }
}
