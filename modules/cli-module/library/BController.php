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

        if ($gate !== 'admin') {
            $auth['user'] = Bash::ask([
                'text' => 'Require user login',
                'default' => $gate != 'site',
                'type' => 'bool'
            ]);
        } else {
            $auth['user'] = true;
        }

        // TODO
        // - ask for permissions for admin gate

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
            $fields = explode(',', $fields);
            $fields = array_map('trim', $fields);
            $result['format'] = [
                'name' => $format,
                'fields' => $fields
            ];
        }
    }

    protected static function getCDocPath(&$result)
    {
        $result['Doc.Path'] = Bash::ask([
            'text' => 'Documentator base dir'
        ]);
    }

    protected static function buildControlConfig()
    {
        $result = [];

        self::getCGate($result);
        self::getCModel($result);
        self::getCDocPath($result);
        ControlRouteCollector::build($result);
        self::getCAuth($result);

        Bash::echo('Object filters', 0, true);
        $result['filters'] = [];
        ControlFilterCollector::setFilters($result['filters'], $result['parents'], 2);

        ControlMethodCollector::collect($result);

        return $result;
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
        $format = 'object';
        if (isset($class['format'])) {
            $format = $class['format']['name'];
        }

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
            $route_path =  $class['route']['path'];

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
        
        if(is_file($ctrl_file))
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
