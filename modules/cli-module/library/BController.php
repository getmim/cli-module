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
    protected static function createMigration($config, $class, $here)
    {
        if ($class['gate'] == 'admin') {
            ControlMigrationAdmin::create($config, $class, $here);
        }
    }

    protected static function getCActiveMenu(&$result)
    {
        if ($result['gate'] != 'admin') {
            return;
        }

        $menus = Bash::ask([
            'text' => 'Active sidebar menu, separeted by comma'
        ]);

        if (!$menus) {
            return;
        }

        $menus = explode(',', $menus);
        $menus = array_map('trim', $menus);

        $result['menu'] = [
            'items' => $menus,
            'label' => Bash::ask([
                'text' => 'Menu label',
                'space' => 2
            ])
        ];
    }

    protected static function getCAuth(&$result)
    {
        $auth = [];
        $gate = $result['gate'];

        // App authorizer
        if ($gate == 'api') {
            $app = Bash::ask([
                'text' => 'Authorized app',
                'default' => true,
                'type' => 'bool'
            ]);
            if ($app) {
                $auth['app'] = true;
            }
        }

        if ($gate == 'admin') {
            $auth['user'] = true;
        } elseif ($gate == 'cli') {
            $auth['user'] = false;
        } else {
            $auth['user'] = Bash::ask([
                'text' => 'Require user login',
                'default' => $gate != 'site',
                'type' => 'bool'
            ]);
        }

        $result['auths'] = $auth;
    }

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

    protected static function getCModel(&$result)
    {
        if ($result['gate'] == 'cli') {
            return;
        }

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

        $format = Bash::ask([
            'text' => 'Object format name',
            'default' => $name
        ]);

        if ($format) {
            $fields = Bash::ask([
                'text' => 'Format object fields, separate by commas',
                'space' => 2
            ]);
            if ($fields) {
                $fields = explode(',', $fields);
                $fields = array_map('trim', $fields);
            } else {
                $fields = [];
            }
            $result['format'] = [
                'name' => $format,
                'fields' => $fields
            ];
        }
    }

    protected static function getCPerms(&$result)
    {
        if ($result['gate'] != 'admin') {
            return;
        }
        $default = 'object';
        $group = 'Object';
        if (isset($result['format'])) {
            $default = preg_replace('![^a-z]!', '_', $result['format']['name']);
            $group = ucwords(str_replace('_', ' ', $default));
        }

        $result['perms'] = [
            'prefix' => Bash::ask([
                'text' => 'Action permissions prefix',
                'default' => $default
            ]),
            'group' => Bash::ask([
                'text' => 'Permissions group name',
                'required' => true,
                'default' => $group,
                'space' => 2
            ])
        ];
    }

    protected static function getCDocPath(&$result)
    {
        if ($result['gate'] != 'api') {
            return;
        }

        $result['Doc.Path'] = Bash::ask([
            'text' => 'Documentator base dir'
        ]);
    }

    protected static function getCView(&$result)
    {
        if (in_array($result['gate'], ['api', 'cli'])) {
            return;
        }

        if (isset($result['format'])) {
            $pars = str_replace('-', '/', $result['format']['name']);
        } else {
            $pars = array_keys($result['parents']);
            $pars = implode('/', $pars);
            $pars.= '/object';
        }

        $result['view'] = Bash::ask([
            'text' => 'Path of the view files',
            'default' => $pars
        ]);
    }

    protected static function buildControlConfig()
    {
        $result = [];

        self::getCGate($result);
        self::getCModel($result);
        self::getCPerms($result);
        self::getCActiveMenu($result);
        self::getCDocPath($result);
        ControlRouteCollector::build($result);
        self::getCAuth($result);
        self::getCView($result);

        if ($result['gate'] != 'cli') {
            Bash::echo('Object filters', 0, true);
            $result['filters'] = [];
            ControlFilterCollector::setFilters($result['filters'], $result['parents'], 2);
        }

        ControlMethodCollector::collect($result);
        return $result;
    }

    protected static function setConfigAdminMenu(&$config, $class)
    {
        if ($class['gate'] != 'admin') {
            return;
        }

        if (!isset($class['menu']) || !$class['menu']) {
            return;
        }

        $menu = $class['menu'];

        if (!isset($config['adminUi'])) {
            $config['adminUi'] = [];
        }
        if (!isset($config['adminUi']['sidebarMenu'])) {
            $config['adminUi']['sidebarMenu'] = [];
        }
        if (!isset($config['adminUi']['sidebarMenu']['items'])) {
            $config['adminUi']['sidebarMenu']['items'] = [];
        }

        $items = $menu['items'];
        $label = $menu['label'];

        $menu_items = &$config['adminUi']['sidebarMenu']['items'];

        $f_item = $items[0];
        if (!isset($menu_items[$f_item])) {
            $menu_items[$f_item] = [];
        }

        $perms = $class['perms']['prefix'] . '_read';
        $route = $class['methods']['index']['name'];

        if (count($items) == 1) {
            $menu_items[$f_item] = [
                'label' => $label,
                'icon' => '<i class="fa fa-home" aria-hidden="true"></i>',
                'route' => [$route,[],[]],
                'priority' => 0,
                'perms' => $perms,
                'filterable' => TRUE,
                'visible' => TRUE
            ];
        } elseif(count($items) == 2) {
            $s_item = $items[1];
            if (!isset($menu_items[$f_item]['children'])) {
                $menu_items[$f_item]['children'] = [];
            }
            $menu_items[$f_item]['children'][$s_item] = [
                'label' => $label,
                'icon'  => '<i></i>',
                'route' => [$route],
                'perms' => $perms,
                'priority' => 100
            ];
        }
    }

    protected static function setConfigFormatter(&$config, $class)
    {
        if (!isset($result['format'])) {
            return;
        }

        RequireAdder::module($config, 'lib-formatter', null);
    }

    protected static function setConfigForms(&$config, $class)
    {
        $forms = [];
        $methods = ['create', 'update', 'edit'];
        if ($class['gate'] == 'admin') {
            $methods[] = 'remove';
            $methods[] = 'index';
        }

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

            if ($method == 'index' && $class['gate'] == 'admin') {
                if (isset($class['methods']['index']['filters'])) {
                    $filters = $class['methods']['index']['filters'];
                    foreach ($filters as $field) {
                        if (!isset($config['libForm']['forms'][$form][$field])) {
                            $config['libForm']['forms'][$form][$field] = [
                                'label' => $field == 'q' ? 'Search' : ucfirst($field),
                                'type' => 'text',
                                'nolabel' => true,
                                'rules' => []
                            ];
                        }
                    }
                }
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

    protected static function setConfigRouter(&$config, &$class, $ns, $name)
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
        $route_gate = $gate;
        if ($gate == 'cli') {
            $route_gate = 'tool';
        }

        $methods = $methods[$gate] ?? [];
        $format = 'object';
        if (isset($class['format'])) {
            $format = $class['format']['name'];
        }

        $ctrl_name = $ns . '\\' . preg_replace('!Controller$!', '', $name);

        if (!isset($config['routes'])) {
            $config['routes'] = [];
        }

        if (!isset($config['routes'][$route_gate])) {
            $config['routes'][$route_gate] = [];
        }

        $prefix = $gate;
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
            $route_path =  $class['route']['path'];

            if (in_array($method, $with_suffix_id)) {
                $route_path['value'] .= '/(:id)';
                $route_path['params']['id'] = 'number';
            }

            if ($method == 'remove' && $gate == 'admin') {
                $route_path['value'] .= '/remove';
            }

            if (!$route_path['params']) {
                unset($route_path['params']);
            }

            $config['routes'][$route_gate][$route_name] = [
                'path' => $route_path,
                'handler' => $ctrl_name . '::' . $method,
                'method' => $methods[$method] ?? 'GET'
            ];
            $class['methods'][$method]['name'] = $route_name;
        }
    }

    protected static function setConfigViewFile(&$config, $class, $here)
    {
        if (!isset($class['view'])) {
            return;
        }

        $view = 'theme/' . $class['gate'] . '/' . $class['view'];
        $role = $class['gate'] == 'admin'
            ? ['install', 'update', 'remove']
            : ['install', 'remove'];

        $config['__files'][$view] = $role;

        Fs::mkdir($here . '/' . $view);
    }

    static function build(string $here, string $name, array $config = []): bool{
        $mod_conf_file = glob($here . '/modules/*/config.php');
        if(!$mod_conf_file || !is_file($mod_conf_file[0]))
            Bash::error('Module config file not found');
        $mod_conf_file = $mod_conf_file[0];
        
        $mod_conf = include $mod_conf_file;
        $mod_name = $mod_conf['__name'];

        if ($config) {
            $ctrl_name = $config['name'];
            $ctrl_ns = $config['ns'];
        } else {
            $ctrl_name = to_ns($name . 'Controller');
            $ctrl_ns   = to_ns($mod_name . '\\Controller');
        }

        $ctrl_file = 'modules/' . $mod_name . '/controller/' . $ctrl_name . '.php';
        
        if(is_file($here . '/' . $ctrl_file))
            Bash::error('Controller with the same file name already exists');

        if (!$config) {
            $ctrl_config = self::buildControlConfig();
            $ctrl_config['name'] = $ctrl_name;
            $ctrl_config['ns'] = $ctrl_ns;
        } else {
            $ctrl_config = $config;
        }

        self::setConfigForms($mod_conf, $ctrl_config);
        self::setConfigFormatter($mod_conf, $ctrl_config);
        self::setConfigGate($mod_conf, $ctrl_config);
        self::setConfigPagination($mod_conf, $ctrl_config);
        self::setConfigRouter($mod_conf, $ctrl_config, $ctrl_ns, $ctrl_name);
        self::setConfigAdminMenu($mod_conf, $ctrl_config);
        self::setConfigViewFile($mod_conf, $ctrl_config, $here);

        ALAdder::classes($mod_conf, $ctrl_ns, $ctrl_name, $ctrl_file);

        ControlWriter::write($here, $mod_conf, $ctrl_config, $ctrl_file);

        self::createMigration($mod_conf, $ctrl_config, $here);
        $nl = PHP_EOL;
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($mod_conf) . ';';

        Fs::write($mod_conf_file, $tx);
        
        return true;
    }
}
