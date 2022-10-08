<?php

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;

class ControlRouteCollector
{
    protected static function getParentsName($path)
    {
        $regex = '!(\(\:(?<name>[a-z-]+)\))!';
        $prefix = $path['value'];

        if (!preg_match_all($regex, $prefix, $matches)) {
            return;
        }

        return $matches['name'];
    }

    protected static function setParamType(&$params, $name)
    {
        $param_types = ['any', 'slug', 'number', 'rest'];

        $type = Bash::ask([
            'text' => 'Type ( number )',
            'default' => 'number',
            'options' => $param_types,
            'default' => 2,
            'space' => 4
        ]);

        $params[$name] = $param_types[$type];
    }

    protected static function setParentModel(&$parents, $name)
    {
        $parent = [];

        $model = Bash::ask([
            'text' => 'Model name',
            'space' => 4
        ]);

        if (!$model) {
            return;
        }

        $parent['model'] = $model;
        $parent['field'] = Bash::ask([
            'text' => 'Where column',
            'default' => 'id',
            'space' => 6
        ]);

        $parent['filters'] = [];
        ControlFilterCollector::setFilters($parent['filters'], $parents, 6);

        self::setParentModelSetter($parent, $name);
        $parents[$name] = $parent;
    }

    protected static function setParentModelSetter(&$parent, $name)
    {
        $property = Bash::ask([
            'text' => 'Set on object creation, property',
            'type' => 'any',
            'space' => 6
        ]);
        if (!$property) {
            return;
        }

        $parent['setget'] = [
            'property' => $property,
            'column' => Bash::ask([
                'text' => 'Table column name',
                'type' => 'any',
                'space' => 8,
                'default' => $name
            ])
        ];
    }

    protected static function setPrefix(&$result)
    {
        $prefix = Bash::ask([
            'text' => 'Route prefix',
            'required' => true
        ]);
        $prefix = '/' . trim($prefix, '/');

        $result['route'] = [
            'path' => [
                'value' => $prefix,
                'params' => []
            ]
        ];
    }

    static function build(&$result)
    {
        self::setPrefix($result);
        $route = &$result['route'];
        $route_path = &$route['path'];

        $result['parents'] = [];
        $parents = self::getParentsName($route_path);
        if (!$parents) {
            return;
        }

        foreach ($parents as $name) {
            Bash::echo('Parameter `' . $name . '` properties', 2, true);

            self::setParamType($route_path['params'], $name);
            self::setParentModel($result['parents'], $name);
        }
    }
}
