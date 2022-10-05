<?php
/**
 * Controller information collector
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Library;

use Mim\Library\Fs;

class ControlWriter
{
    protected static function getUsesClasses(array $config)
    {
        $classes = [];

        // formatter
        if ($config['format']) {
            $classes[] = 'LibFormatter\\Library\\Formatter';
        }

        // form if only
        $with_form = ['create', 'update', 'edit'];
        foreach ($config['methods'] as $method => $opts) {
            if (isset($opts['form'])) {
                $classes[] = 'LibForm\\Library\\Form';
                break;
            }
        }

        // object model
        if ($config['model']) {
            $classes[] = $config['model'];
        }

        // parent models
        if ($config['parents']) {
            foreach ($config['parents'] as $name => $opts) {
                if (isset($opts['model'])) {
                    $classes[] = $opts['model'];
                }
            }
        }

        $result = [];
        foreach ($classes as $class) {
            $class_name = explode('\\', $class);
            $class_name = end($class_name);

            $result[$class] = $class_name;
        }

        return $result;
    }

    protected static function genActionMethods(array &$config, array &$methods, $uses)
    {
        $class = 'CliModule\\Library\\ControlMethodWriter' . ucfirst($config['gate']);
        $class_exists = class_exists($class);

        $auths = $config['auths'];
        $gate = $config['gate'];
        $parents = $config['parents'] ?? [];

        $skip_methods = [];
        foreach ($config['methods'] as $method => $opts) {
            if (in_array($method, $skip_methods)) {
                continue;
            }
            $content = [];
            self::setMethodAuth($content, $auths, $gate);
            self::setRouteParentGetter($content, $parents, $gate);

            if ($class_exists) {
                $class::method($content, $config, $method, $opts, $uses);
            } else {
                $content[] = '// START EDIT HERE';
            }

            $comments = self::getMethodComment($config, $method, $opts);
            $methods[$method . 'Action'] = [
                'protected' => false,
                'return'    => null,
                'content'   => $content,
                'comments'  => $comments,
                'arguments' => []
            ];
        }
    }

    protected static function getMethodComment($config, $method, $options) {
        $result = [];

        // with form
        if (isset($options['form'])) {
            $result[] = '@form ' . $options['form'];
        }

        // with formatter
        $with_format = ['index', 'single', 'details', 'update'];
        if ($config['gate'] == 'api') {
            $with_format[] = 'create';
        }

        if (in_array($method, $with_format)) {
            if (isset($config['format'])) {
                $format = $config['format'];
                $result[] = '@format.name ' . $format['name'];
                $result[] = '@format.fields ' . implode(', ', $format['fields']);
            }
        }

        // soft delete
        if (in_array($method, ['delete', 'remove'])) {
            if (isset($options['status'])) {
                $result[] = '@soft ' . $options['status'];
            }
        }

        if ($method == 'index') {
            // with sort
            if ($options['sorts']) {
                $result[] = '@sort ' . implode(', ', $options['sorts']);
            }

            // with query sting
            if ($options['filters']) {
                $result[] = '@filter.query ' . implode(', ', $options['filters']);
            }
        }

        return $result;
    }

    protected static function setMethodAuth(&$content, $auth, $gate)
    {
        $au_line = [];

        if (!empty($auth['app'])) {
            $au_line[] = '!$this->app->isAuthorized()';
        }

        if (!empty($auth['user'])) {
            $au_line[] = '!$this->user->isLogin()';
        }

        if ($au_line) {
            $content[] = 'if (' . implode(' || ', $au_line) . ') {';
            if ($gate === 'api') {
                $content[] = '    return $this->resp(401);';
            } else {
                $content[] = '    deb(\'Unauthorized\');';
            }
            $content[] = '}';
            $content[] = '';
        }
    }

    protected static function setRouteParentGetter(&$content, $parents, $gate)
    {
        foreach ($parents as $name => $opts) {
            if (!isset($opts['method'])) {
                continue;
            }

            $caller = '$'
                . $name
                . ' = $this->'
                . $opts['method']
                . '(';
            if ($opts['arguments']) {
                $caller.= '$' . implode(', $', array_keys($opts['arguments']));
            }
            $caller.= ');';

            $content[] = $caller;
            $content[] = 'if (!$' . $name . ') {';
            if ($gate === 'api') {
                $content[] = '    return $this->resp(404);';
            } else {
                $content[] = '    deb(\'Not found\');';
            }
            $content[] = '}';
            $content[] = '';
        }
    }

    static function write(string $here, array $module, array $config, string $file)
    {
        $nl = PHP_EOL;
        $methods = [];

        $tx = '<?php' . $nl;
        $tx.= '/**' . $nl;
        $tx.= ' * ' . $config['name'] . $nl;
        $tx.= ' * @package ' . $module['__name'] . $nl;
        $tx.= ' * @version ' . $module['__version'] . $nl;
        $tx.= ' */' . $nl;
        $tx.= $nl;
        $tx.= 'namespace ' . trim($config['ns'], '\\ ') . ';' . $nl;
        $tx.= $nl;

        $uses = self::getUsesClasses($config);
        ControlWriterParentMethod::setParentGetters($config, $methods, $uses);
        self::genActionMethods($config, $methods, $uses);

        if ($uses) {
            foreach ($uses as $class => $name) {
                $tx.= 'use ' . trim($class, '\\') . ';' . $nl;
            }
        }

        $tx.= $nl;

        if (!empty($config['Doc.Path'])) {
            $tx.= '/**' . $nl;
            $tx.= ' * @Doc.Path ' . $config['Doc.Path'] . $nl;
            $tx.= ' */' . $nl;
        }
        $tx.= implode(' ', [
            'class',
            $config['name'],
            'extends',
            $config['extends']
        ]);
        $tx.= $nl . '{' . $nl;

        $first = true;
        foreach ($methods as $method => $opts) {
            if (!$first) {
                $tx.= $nl;
            }
            if (isset($opts['comments']) && $opts['comments']) {

                $tx.= '    /**' . $nl;
                foreach ($opts['comments'] as $line) {
                    $tx.= '     * ' . $line . $nl;
                }
                $tx.= '     */' . $nl;
            }
            $tx.= '    ';
            if ($opts['protected']) {
                $tx.= 'protected ';
            }
            $tx.= 'function ';
            $tx.= $method;
            $tx.= '(';
            if ($opts['arguments']) {
                $args = [];
                foreach ($opts['arguments'] as $name => $opt) {
                    $arg_line = '';
                    if (isset($opt['type'])) {
                        $arg_line.= $opt['type'] . ' ';
                    }
                    $arg_line.= '$' . $name;
                    $args[] = $arg_line;
                }

                $tx.= implode(', ', $args);
            }
            $tx.= ')';
            if ($opts['return']) {
                $tx.= ': ' . $opts['return'];
            }
            $tx.= $nl;
            $tx.= '    {' . $nl;
            foreach ($opts['content'] as $line) {
                $tx.= '        ' . $line . $nl;
            }
            $tx.= '    }' . $nl;

            $first = false;
        }

        $tx.= '}';

        Fs::write($here . '/' . $file, $tx);
    }
}
