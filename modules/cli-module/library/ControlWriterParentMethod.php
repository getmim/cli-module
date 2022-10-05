<?php

namespace CliModule\Library;

class ControlWriterParentMethod
{
    protected static function getArguments($opts)
    {
        if (!isset($opts['filters'])) {
            return [];
        }

        $filters = $opts['filters'];
        if (!isset($filters['parents'])) {
            return [];
        }

        $parents = $filters['parents'];
        $result = [];
        foreach ($parents as $name => $opt) {
            $result[$name] = [
                'type' => 'object'
            ];
        }

        return $result;
    }

    public static function setParentGetters(&$config, &$methods, $uses)
    {
        if (!isset($config['parents']) || !$config['parents']) {
            return;
        }

        $parents = &$config['parents'];

        foreach ($parents as $name => &$opts) {
            if (!isset($opts['model'])) {
                continue;
            }

            $model = $uses[ $opts['model'] ];
            $method = 'getRouter' . to_ns($name);
            $opts['method'] = $method;
            $filters = $opts['filters'] ?? [];

            $cond = ControlFilterProcess::getFilters($filters, $opts['field'], '$value');

            $ctn = [
                '$value = $this->req->param->' . $name . ';',
                'return ' . $model . '::getOne(['
            ];

            ControlFilterProcess::addArrayCond($ctn, $cond);

            $ctn[] = ']);';

            $comments = [
                '@route.param ' . $name
            ];

            $methods[$method] = [
                'comments' => $comments,
                'protected' => true,
                'return' => '?object',
                'content' => $ctn,
                'arguments' => self::getArguments($opts)
            ];

            $opts['arguments'] = $methods[$method]['arguments'];
        }
        unset($opts);
    }
}
