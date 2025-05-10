<?php

namespace CliModule\Library;

class ControlMethodWriterAdmin
{
    protected static function setEdit(&$content, $config, $method, $opts, $uses)
    {
        $model = $uses[ $config['model'] ];
        $form  = $opts['form'];
        $format = $config['format'] ?? [];

        $perms = $config['perms']['prefix'];

        $object = 'object';
        $title = 'Object';
        if ($format) {
            $object = $format['name'];
            $title = preg_replace('![^a-z]!', ' ', $object);
            $title = ucwords($title);
        }

        $view = $config['view'];

        $content[] = '';
        $content[] = '$obj = (object)[];';
        $content[] = '$id = $this->req->param->id;';
        $content[] = '';
        $content[] = 'if ($id) {';
        $content[] = '    if (!$this->can_i->' . $perms . '_update) {';
        $content[] = '        return $this->show404();';
        $content[] = '    }';
        $content[] = '';
        $content[] = '    $obj = ' . $model . '::getOne([\'id\'=>$id]);';
        $content[] = '    if (!$obj) {';
        $content[] = '        return $this->show404();';
        $content[] = '    }';
        $content[] = '    $params = $this->getParams(\'Edit\');';
        $content[] = '} else {';
        $content[] = '    if (!$this->can_i->' . $perms . '_create) {';
        $content[] = '        return $this->show404();';
        $content[] = '    }';
        $content[] = '';
        $content[] = '    $params = $this->getParams(\'Create New\');';
        $content[] = '}';
        $content[] = '';

        $content[] = '$form = new Form(\'' . $form . '\');';
        $content[] = '$params[\'form\'] = $form;';
        $content[] = '';

        $content[] = 'if (!($valid = $form->validate($obj))) {';
        $content[] = '    return $this->resp(\'' . $view . '/edit\', $params);';
        $content[] = '}';
        $content[] = '';

        $content[] = 'if (!$form->csrfTest(\'t\')) {';
        $content[] = '    return $this->show404();';
        $content[] = '}';
        $content[] = '';

        $content[] = 'if ($id) {';
        $content[] = '    if (!' . $model . '::set((array)$valid, [\'id\'=>$id])) {';
        $content[] = '        deb(' . $model . '::lastError());';
        $content[] = '    }';
        $content[] = '} else {';

        // fill auto column
        if ($opts['columns']) {
            $columns = $opts['columns'];

            if (isset($columns['services'])) {
                $services = $columns['services'];

                foreach ($services as $name => $opt) {
                    $serv_property = $opt['property'];
                    $tabl_column = $opt['column'];
                    $content[] = '    $valid->'
                        . $tabl_column
                        . ' = $this->' . $name
                        . '->' . $serv_property
                        . ';';
                }
            }
        }
        // fill parent property
        foreach ($config['parents'] as $name => $opt) {
            if (isset($opt['setget'])) {
                $par = $opt['setget'];
                $content[] = '    $valid->'
                    . $par['column']
                    . ' = $' . $name . '->' . $par['property']
                    . ';';
            }
        }

        $content[] = '    if (!($id = ' . $model . '::create((array)$valid))) {';
        $content[] = '        deb(' . $model . '::lastError());';
        $content[] = '    }';

        $content[] = '}';
        $content[] = '';

        $parent = '0';
        foreach ($config['parents'] as $name => $opt) {
            if (isset($opt['setget'])) {
                $parent = '$' . $name . '->' . $opt['setget']['property'];
            }
        }
        $content[] = '// add action log';
        $content[] = '$this->addLog([';
        $content[] = '    \'user\'     => $this->user->id,';
        $content[] = '    \'object\'   => $id,';
        $content[] = '    \'parent\'   => ' . $parent . ',';
        $content[] = '    \'method\'   => $id ? 2 : 1,';
        $content[] = '    \'type\'     => \'' . $object . '\',';
        $content[] = '    \'original\' => $obj,';
        $content[] = '    \'changes\'  => $valid';
        $content[] = ']);';
        $content[] = '';

        // get index route
        $next_route = $config['methods']['index'] ?? [];
        if ($next_route) {
            $next_route = $next_route['name'];
            $content[] = '$next = $this->router->to(\'' . $next_route . '\');';
            $content[] = '$this->res->redirect($next);';
        } else {
            $content[] = 'deb(\'Success\');';
        }
    }

    protected static function setIndex(&$content, $config, $method, $opts, $uses)
    {
        $model = $uses[ $config['model'] ];
        $form  = $opts['form'];
        $format = $config['format'] ?? [];

        $object = 'object';
        $title = 'Object';
        if ($format) {
            $object = $format['name'];
            $title = preg_replace('![^a-z]!', ' ', $object);
            $title = ucwords($title);
        }

        $view = $config['view'];

        $filters = $config['filters'];
        $cond = ControlFilterProcess::getFilters($filters);
        $content[] = '$cond = [';
        ControlFilterProcess::addArrayCond($content, $cond);
        $content[] = '];';

        $content[] = '$pcond = [];';
        $content[] = '$params = $this->getParams(\'' . $title . '\');';
        $content[] = '$form = new Form(\'' . $form . '\');';
        $content[] = '';

        // query string
        if ($opts['filters']) {
            $content[] = '// query sting';
            $content[] = '$query_string = ['
                . '\'' . implode('\', \'', $opts['filters']) . '\''
                . '];';
            $content[] = '$query_cond = $this->req->getCond($query_string);';
            $content[] = 'if ($query_cond) {';
            $content[] = '    foreach ($query_cond as $key => $val) {';
            $content[] = '        $cond[$key] = $val;';
            $content[] = '    }';
            $content[] = '}';
            $content[] = '';
        }

        $content[] = 'list($page, $rpp) = $this->req->getPager(12, 25);';
        $content[] = '';

        $content[] = '$objs = ' . $model . '::get($cond, $rpp, $page, [\'id\'=>false]) ?? [];';

        if (isset($config['format'])) {
            $format = $config['format'];
            $fmt = '';
            if ($format['fields']) {
                $fmt = '\'' . implode('\', \'', $format['fields']) . '\'';
            }
            $content[] = 'if ($objs) {';
            $content[] = '    $fmt = [' . $fmt . '];';
            $content[] = '    $objs = Formatter::formatMany(\'' . $format['name'] . '\', $objs, $fmt);';
            $content[] = '}';
            $content[] = '';
        }

        $content[] = '$params[\'objects\'] = $objs;';
        $content[] = '$params[\'form\']    = $form;';
        $content[] = '$form->validate((object)$this->req->get());';
        $content[] = '';

        $curr_route_name = $config['methods']['index']['name'];

        $content[] = '// pagination';
        $content[] = '$params[\'total\'] = $total = ' . $model . '::count($cond);';
        $content[] = 'if ($total > $rpp) { ';
        $content[] = '    $params[\'pages\'] = new Paginator(';
        $content[] = '        $this->router->to(\'' . $curr_route_name . '\'),';
        $content[] = '        $total,';
        $content[] = '        $page,';
        $content[] = '        $rpp,';
        $content[] = '        10,';
        $content[] = '        $pcond';
        $content[] = '    );';
        $content[] = '}';
        $content[] = '';

        $content[] = '$this->resp(\'' . $view . '/index\', $params);';
    }

    protected static function setRemove(&$content, $config, $method, $opts, $uses)
    {
        $model = $uses[ $config['model'] ];
        $form  = $opts['form'];

        // csrf
        $content[] = '$form = new Form(\'' . $form . '\');';
        $content[] = 'if (!$form->csrfTest(\'t\')) {';
        $content[] = '    return $this->show404();';
        $content[] = '}';
        $content[] = '';

        $filters = $config['filters'];
        $req_params = '$this->req->param->id';
        $cond = ControlFilterProcess::getFilters($filters, 'id', $req_params);
        $content[] = '$cond = [';
        ControlFilterProcess::addArrayCond($content, $cond);
        $content[] = '];';
        $content[] = '';
        $content[] = '$obj = ' . $model . '::getOne($cond);';
        $content[] = 'if (!$obj) {';
        $content[] = '    return $this->show404();';
        $content[] = '}';
        $content[] = '';

        if (isset($opts['status'])) {
            $status = $opts['status'];
            if (!is_numeric($status)) {
                $status = '\'' . $status . '\'';
            }

            $content[] = '$set = [\'status\' => ' . $status . '];';
            $content[] = $model . '::set($set, [\'id\' => $obj->id]);';
        } else {
            $content[] = $model . '::remove([\'id\' => $obj->id]);';
        }
        $content[] = '';

        $object = 'object';
        if (isset($config['format'])) {
            $object = $config['format']['name'];
        }
        $parent = '0';
        foreach ($config['parents'] as $name => $opt) {
            if (isset($opt['setget'])) {
                $parent = '$' . $name . '->' . $opt['setget']['property'];
            }
        }
        $content[] = '// add action log';
        $content[] = '$this->addLog([';
        $content[] = '    \'user\'     => $this->user->id,';
        $content[] = '    \'object\'   => $obj->id,';
        $content[] = '    \'parent\'   => ' . $parent . ',';
        $content[] = '    \'method\'   => 3,';
        $content[] = '    \'type\'     => \'' . $object . '\',';
        $content[] = '    \'original\' => $obj,';
        $content[] = '    \'changes\'  => null';
        $content[] = ']);';
        $content[] = '';

        // get index route
        $next_route = $config['methods']['index'] ?? [];
        if ($next_route) {
            $next_route = $next_route['name'];
            $content[] = '$next = $this->router->to(\'' . $next_route . '\');';
            $content[] = '$this->res->redirect($next);';
        } else {
            $content[] = 'deb(\'Success\');';
        }
    }

    public static function setParamsMethod($config, &$methods, $uses)
    {
        if ($config['gate'] != 'admin') {
            return;
        }

        $menu = '';
        if (isset($config['menu'])) {
            $menu = '\'' . implode('\', \'', $config['menu']['items']) . '\'';
        }

        $ctn = [
            'return [',
            '    \'_meta\' => [',
            '        \'title\' => $title,',
            '        \'menus\' => [' . $menu . ']',
            '    ],',
            '    \'subtitle\' => $title,',
            '    \'pages\' => null',
            '];'
        ];

        $methods['getParams'] = [
            'comments' => [],
            'protected' => true,
            'return' => 'array',
            'content' => $ctn,
            'arguments' => [
                'title' => [
                    'type' => 'string'
                ]
            ]
        ];
    }

    public static function method(&$content, $config, $method, $opts, $uses)
    {
        $c_method = 'set' . ucfirst($method);
        if (!method_exists(ControlMethodWriterAdmin::class, $c_method)) {
            $content[] = '// START EDIT HERE?';
            return;
        }

        self::$c_method($content, $config, $method, $opts, $uses);
    }
}
