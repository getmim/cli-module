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
    protected static function getMethodAuth(&$result, $auth)
    {
        if (!empty($auth['app'])) {
            $result[] = 'if (!$this->app->isAuthorized()) {';
            $result[] = '    return $this->resp(401);';
            $result[] = '}';
        }

        if (!empty($auth['user'])) {
            $result[] = 'if (!$this->use->isLogin()) {';
            $result[] = '    return $this->resp(401);';
            $result[] = '}';
        }

        $result[] = '';
    }

    protected static function getMethodParents(&$result, $parents)
    {
        foreach ($parents as $par => $opts) {
            if (!isset($opts['method'])) {
                continue;
            }
            $result[] = '$' . $par . ' = $this->' . $opts['method'] . '();';
            $result[] = 'if (!$' . $par . ') {';
            $result[] = '    return $this->resp(404);';
            $result[] = '}';
            $result[] = '';
        }
    }

    protected static function getMethodStdFilter($filters, $parents)
    {
        $result = [];

        if (!empty($filters['status'])) {
            $result['status'] = '\'' . $filters['status'] . '\'';
        }

        if (!empty($filters['user'])) {
            $result['user'] = '$this->user->id';
        }

        if ($filters['parents']) {
            foreach ($filters['parents'] as $name => $opts) {
                if ($opts['par_prop'] === true) {
                    $result[$opts['obj_prop']] = '$this->req->param->' . $name;
                } else {
                    $result[$opts['obj_prop']] = '$' . $name . '->' . $opts['par_prop'];
                }
            }
        }

        return $result;
    }

    protected static function getMethodApiCreate($method, $opts, $config, $uses)
    {
        $model = $config['model'];
        $model = $uses[$model];
        $format = $config['format'];
        $form  = $opts['form'];
        $result = [];

        self::getMethodAuth($result, $opts['auth'] ?? []);
        self::getMethodParents($result, $config['parents']);

        $result[] = '$form = new Form(\'' . $form . '\');';
        $result[] = 'if (!($valid = $form->validate($obj)) {';
        $result[] = '    return $this->resp(422, $form->getErrors());';
        $result[] = '}';

        // fill auto column
        $columns = $opts['columns'];
        if (!empty($columns['user'])) {
            $result[] = '$valid->user = $this->user->id;';
        }
        if (isset($columns['status']) && !is_null($columns['status'])) {
            $result[] = '$valid->status = \'' . $columns['status'] . '\';';
        }


        $result[] = '';
        $result[] = 'if(!($id = ' . $model . '::create((array)$valid))) {';
        $result[] = '    return $this->resp(500, null, ' . $model . '::lastError());';
        $result[] = '}';

        // deb($columns);

        $result[] = '';
        $result[] = '$obj = ' . $model . '::getOne([\'id\' => $id]);';
        $result[] = '$fmt = [];';
        $result[] = '$obj = Formatter::format(\'' . $format . '\', $obj, $fmt);';
        $result[] = '';
        $result[] = '$this->resp(0, $obj);';

        return $result;
    }

    protected static function getMethodApiDelete($method, $opts, $config, $uses)
    {
        $model = $config['model'];
        $model = $uses[$model];
        $result = [];

        $filters = self::getMethodStdFilter($opts['filters'] ?? [], $config['parents']);

        self::getMethodAuth($result, $opts['auth'] ?? []);
        self::getMethodParents($result, $config['parents']);

        $result[] = '$cond = [';
        $result[] = '    \'id\' => $this->req->param->id,';
        foreach ($filters as $key => $value) {
            $result[] = '    \'' . $key . '\' => ' . $value . ',';
        }
        $result[] = '];';

        $result[] = '$obj = ' . $model . '::getOne($cond);';
        $result[] = 'if (!$obj) {';
        $result[] = '    return $this->resp(404);';
        $result[] = '}';

        $result[] = '';

        // soft delete
        if ($opts['status'] !== '') {
            $result[] = '$set = [\'status\' => \'' . $opts['status'] . '\'];';
            $result[] = $model . '::set($set, [\'id\' => $obj->id]);';
        } else {
            $result[] = $model . '::remove([\'id\' => $obj->id]);';
        }

        $result[] = '';
        $result[] = 'return $this->resp(0);';
        return $result;
    }

    protected static function getMethodApiIndex($method, $opts, $config, $uses)
    {
        $model = $config['model'];
        $model = $uses[$model];
        $format = $config['format'];
        $result = [];

        $filters = self::getMethodStdFilter($opts['filters'] ?? [], $config['parents']);

        self::getMethodAuth($result, $opts['auth'] ?? []);
        self::getMethodParents($result, $config['parents']);

        // sort
        $result[] = '// SORT BY';
        $result[] = '$sort = \'created\';';
        if ($opts['sorts']) {
            $result[] = '$sort_by = [';
            foreach ($opts['sorts'] as $sort) {
                $result[] = '    \'' . $sort . '\',';
            }
            $result[] = '];';
            $result[] = '$sort = $this->req->getQuery(\'sort\', \'created\');';
            $result[] = 'if (!in_array($sort, $sort_by)) {';
            $result[] = '    $sort = \'created\';';
            $result[] = '}';
        }

        $result[] = '$by = $this->req->getQuery(\'by\', \'DESC\');';
        $result[] = 'if (!in_array($by, [\'ASC\', \'DESC\'])) {';
        $result[] = '    $by = \'DESC\';';
        $result[] = '}';

        // pagination
        $result[] = '';
        $result[] = '// PAGINATION';
        $result[] = 'list($page, $rpp) = $this->req->getPager(12, 24);';
        $result[] = '';

        // cond from query string
        if (!empty($opts['filters']['query'])) {
            $result[] = '// QUERY FILTER';
            $result[] = '$qry_filter = [';
            foreach ($opts['filters']['query'] as $query) {
                $result[] = '    \'' . $query . '\',';
            }
            $result[] = '];';
            $result[] = '$cond = $this->req->getCond($qry_filter);';
        } else {
            $result[] = '$cond = [];';
        }

        if ($filters) {
            $result[] = '';
            $result[] = '// AUTO FILTER';
            foreach ($filters as $key => $value) {
                $result[] = '$cond[\'' . $key . '\'] = ' . $value . ';';
            }
        }

        $result[] = '';
        $result[] = '$objs = ' . $model . '::get($cond, $rpp, $page, [$sort => $by]) ?? [];';
        $result[] = 'if ($objs) {';
        $result[] = '    $fmt = [];';
        $result[] = '    $objs = Formatter::formatMany(\'' . $format . '\', $objs, $fmt);';
        $result[] = '}';

        $result[] = '';
        $result[] = 'return $this->resp(0, $objs, null, [';
        $result[] = '    \'page\' => $page,';
        $result[] = '    \'rpp\' => $rpp,';
        $result[] = '    \'total\' => ' . $model . '::count($cond)';
        $result[] = '];';


        return $result;
    }

    protected static function getMethodApiSingle($method, $opts, $config, $uses)
    {
        $model = $config['model'];
        $model = $uses[$model];
        $format = $config['format'];
        $result = [];

        $filters = self::getMethodStdFilter($opts['filters'] ?? [], $config['parents']);

        self::getMethodAuth($result, $opts['auth'] ?? []);
        self::getMethodParents($result, $config['parents']);

        $result[] = '$cond = [';
        $result[] = '    \'id\' => $this->req->param->id,';
        foreach ($filters as $key => $value) {
            $result[] = '    \'' . $key . '\' => ' . $value . ',';
        }
        $result[] = '];';

        $result[] = '$obj = ' . $model . '::getOne($cond);';
        $result[] = 'if (!$obj) {';
        $result[] = '    return $this->resp(404);';
        $result[] = '}';

        $result[] = '';

        $result[] = '$fmt = [];';
        $result[] = '$obj = Formatter::format(\'' . $format . '\', $obj, $fmt);';
        $result[] = '';
        $result[] = '$this->resp(0, $obj);';

        return $result;
    }

    protected static function getMethodApiUpdate($method, $opts, $config, $uses)
    {
        $model = $config['model'];
        $model = $uses[$model];
        $format = $config['format'];
        $form  = $opts['form'];
        $result = [];

        $filters = self::getMethodStdFilter($opts['filters'] ?? [], $config['parents']);

        self::getMethodAuth($result, $opts['auth'] ?? []);
        self::getMethodParents($result, $config['parents']);

        $result[] = '$cond = [';
        $result[] = '    \'id\' => $this->req->param->id,';
        foreach ($filters as $key => $value) {
            $result[] = '    \'' . $key . '\' => ' . $value . ',';
        }
        $result[] = '];';

        $result[] = '$obj = ' . $model . '::getOne($cond);';
        $result[] = 'if (!$obj) {';
        $result[] = '    return $this->resp(404);';
        $result[] = '}';

        $result[] = '';

        $result[] = '$form = new Form(\'' . $form . '\');';
        $result[] = 'if (!($valid = $form->validate($obj)) {';
        $result[] = '    return $this->resp(422, $form->getErrors());';
        $result[] = '}';

        $result[] = '';
        $result[] = 'if(!' . $model . '::set((array)$valid, [\'id\' => $obj->id])) {';
        $result[] = '    return $this->resp(500, null, ' . $model . '::lastError());';
        $result[] = '}';

        $result[] = '';
        $result[] = '$obj = ' . $model . '::getOne([\'id\' => $obj->id]);';
        $result[] = '$fmt = [];';
        $result[] = '$obj = Formatter::format(\'' . $format . '\', $obj, $fmt);';
        $result[] = '';
        $result[] = '$this->resp(0, $obj);';

        return $result;
    }

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
        foreach ($config['methods'] as $method => $opts) {
            $ctn = 'getMethod'
                . ucfirst($config['gate'])
                . ucfirst($method);

            $content = [];
            if (method_exists(ControlWriter::class, $ctn)) {
                $content = self::$ctn($method, $opts, $config, $uses);
            } else {
                $content = ['// No content'];
            }
            $methods[$method . 'Action'] = [
                'protected' => false,
                'return' => null,
                'content' => $content
            ];
        }
    }

    protected static function genParentGetterMethods(array &$config, array &$methods, $uses)
    {
        if (!$config['parents']) {
            return;
        }

        $tx = '';
        foreach ($config['parents'] as $name => &$opts) {
            if (!isset($opts['model'])) {
                continue;
            }

            $model = $opts['model'];
            $mod_use = $uses[$model];
            $method = 'getRouter' . ucfirst($name);
            $opts['method'] = $method;

            $ctn = [
                '$value = $this->req->param->' . $name . ';',
                'return ' . $mod_use . '::getOne([',
                '    \'' . $opts['field'] . '\' => $value,'
            ];

            // filters
            $filters = $opts['filters'] ?? [];

            if (!empty($filters['user'])) {
                $ctn[] = '    \'user\' => $this->user->id,';
            }

            if (!empty($filters['status'])) {
                $ctn[] = '    \'status\' => \'' . $filters['status'] . '\'';
            }

            $ctn[] = ']);';

            $methods[$method] = [
                'protected' => true,
                'return' => '?object',
                'content' => $ctn
            ];
        }
        unset($opts);
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
        self::genParentGetterMethods($config, $methods, $uses);
        self::genActionMethods($config, $methods, $uses);

        if ($uses) {
            foreach ($uses as $class => $name) {
                $tx.= implode(' ', [
                    'use',
                    ltrim($class, '\\'),
                    'as',
                    $name . ';' . $nl
                ]);
            }
        }

        $tx.= $nl;
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
            $tx.= '    ';
            if ($opts['protected']) {
                $tx.= 'protected ';
            }
            $tx.= 'function ';
            $tx.= $method;
            $tx.= '()';
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
