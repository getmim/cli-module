<?php

namespace CliModule\Library;

class ViewWriter
{
    public static function write($here, $config)
    {
        $with_view = ['admin', 'site'];
        if (!in_array($config['gate'], $with_view)) {
            return;
        }

        $class = 'CliModule\\Library\\ViewWriter' . ucfirst($config['gate']);
        if (!class_exists($class)) {
            return;
        }

        $view_base = $here . '/theme/' . $config['gate'] . '/' . $config['view'];
        foreach ($config['methods'] as $method => $opts) {
            if ($config['gate'] == 'admin' && $method == 'remove') {
                continue;
            }

            $view_file = $view_base . '/' . $method . '.phtml';
            if (is_file($view_file)) {
                continue;
            }

            $ctn = $class::genContent($method, $config);
            file_put_contents($view_file, $ctn);
        }
    }
}
