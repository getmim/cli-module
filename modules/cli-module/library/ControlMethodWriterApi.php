<?php

namespace CliModule\Library;

class ControlMethodWriterApi
{
    protected static function setCreate(&$content, $config, $method, $opts, $uses)
    {
        $model = $uses[ $config['model'] ];

        $form = $opts['form'];
        $content[] = '$form = new Form(\'' . $form . '\');';
        $content[] = 'if (!($valid = $form->validate())) {';
        $content[] = '    return $this->resp(422, $form->getErrors());';
        $content[] = '}';
        $content[] = '';

        // fill auto column
        if ($opts['columns']) {
            $columns = $opts['columns'];

            if (isset($columns['services'])) {
                $services = $columns['services'];

                foreach ($services as $name => $opt) {
                    $serv_property = $opt['property'];
                    $tabl_column = $opt['column'];
                    $content[] = '$valid->'
                        . $tabl_column
                        . ' = $this->' . $name
                        . '->' . $serv_property
                        . ';';
                }
            }
        }

        $parents = $config['parents'];
        if ($parents) {
            foreach ($parents as $name => $opt) {
                if (isset($opt['setget'])) {
                    $setter = $opt['setget'];
                    $par_property = $setter['property'];
                    $tab_column = $setter['column'];

                    $content[] = '$valid->'
                        . $tab_column
                        . ' = $' . $name
                        . '->' . $par_property
                        . ';';
                }
            }
            $content[] = '';
        }

        $content[] = 'if(!($id = ' . $model . '::create((array)$valid))) {';
        $content[] = '    return $this->resp(500, null, ' . $model . '::lastError());';
        $content[] = '}';

        $content[] = '';
        $content[] = '$obj = ' . $model . '::getOne([\'id\' => $id]);';
        if (isset($config['format'])) {
            $format = $config['format'];
            $fmt = '';
            if ($format['fields']) {
                $fmt = '\'' . implode('\', \'', $format['fields']) . '\'';
            }
            $content[] = '$fmt = [' . $fmt . '];';
            $content[] = '$obj = Formatter::format(\'' . $format['name'] . '\', $obj, $fmt);';
            $content[] = '';
        }
        $content[] = '$this->resp(0, $obj);';
    }

    protected static function setDelete(&$content, $config, $method, $opts, $uses)
    {
        $model = $uses[ $config['model'] ];
        $filters = $config['filters'];
        $req_params = '$this->req->param->id';
        $cond = ControlFilterProcess::getFilters($filters, 'id', $req_params);
        $content[] = '$cond = [';
        ControlFilterProcess::addArrayCond($content, $cond);
        $content[] = '];';
        $content[] = '';
        $content[] = '$obj = ' . $model . '::getOne($cond);';
        $content[] = 'if (!$obj) {';
        $content[] = '    return $this->resp(404);';
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
        $content[] = 'return $this->resp(0);';
    }

    protected static function setIndex(&$content, $config, $method, $opts, $uses)
    {
        $model = $uses[ $config['model'] ];

        $filters = $config['filters'];
        $cond = ControlFilterProcess::getFilters($filters);
        $content[] = '$cond = [';
        ControlFilterProcess::addArrayCond($content, $cond);
        $content[] = '];';
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

        if ($opts['sorts']) {
            $content[] = '// sort';
            $content[] = '$sort_fields = ['
                . '\'' . implode('\', \'', $opts['sorts']) . '\''
                . '];';
            $content[] = '$sort_key = $this->req->getQuery(\'sort\', \'id\');';
            $content[] = 'if (!in_array($sort_key, $sort_fields)) {';
            $content[] = '    $sort_key = \'id\';';
            $content[] = '}';
            $content[] = '';

            $content[] = '$sort_by = $this->req->getQuery(\'by\', \'DESC\');';
            $content[] = 'if (!in_array($sort_by, [\'ASC\', \'DESC\'])) {';
            $content[] = '    $sort_by = \'DESC\';';
            $content[] = '}';
            $content[] = '';
        }

        $content[] = '// pagination';
        $content[] = 'list($page, $rpp) = $this->req->getPager(12, 24);';
        $content[] = '';

        if ($opts['sorts']) {
            $content[] = '$sort = [$sort_key => $sort_by];';
            $content[] = '$objs = ' . $model . '::get($cond, $rpp, $page, $sort) ?? [];';
        } else {
            $content[] = '$objs = ' . $model . '::get($cond, $rpp, $page) ?? [];';
        }
        if (isset($config['format'])) {
            $format = $config['format'];
            $fmt = '';
            if ($format['fields']) {
                $fmt = '\'' . implode('\', \'', $format['fields']) . '\'';
            }
            $content[] = 'if ($objs) {';
            $content[] = '    $fmt = [' . $fmt . '];';
            $content[] = '    $objs = Formatter::formatMany(\'' . $format['name'] . '\', $obj, $fmt);';
            $content[] = '}';
            $content[] = '';
        }

        $content[] = 'return $this->resp(0, $objs, null, [';
        $content[] = '    \'meta\' => [';
        $content[] = '        \'page\' => $page,';
        $content[] = '        \'rpp\' => $rpp,';
        $content[] = '        \'total\' => ' . $model . '::count($cond)';
        $content[] = '    ]';
        $content[] = ']);';
    }

    protected static function setSingle(&$content, $config, $method, $opts, $uses)
    {
        $model = $uses[ $config['model'] ];
        $filters = $config['filters'];
        $req_params = '$this->req->param->id';
        $cond = ControlFilterProcess::getFilters($filters, 'id', $req_params);
        $content[] = '$cond = [';
        ControlFilterProcess::addArrayCond($content, $cond);
        $content[] = '];';
        $content[] = '';
        $content[] = '$obj = ' . $model . '::getOne($cond);';
        $content[] = 'if (!$obj) {';
        $content[] = '    return $this->resp(404);';
        $content[] = '}';
        $content[] = '';

        if (isset($config['format'])) {
            $format = $config['format'];
            $fmt = '';
            if ($format['fields']) {
                $fmt = '\'' . implode('\', \'', $format['fields']) . '\'';
            }
            $content[] = '$fmt = [' . $fmt . '];';
            $content[] = '$obj = Formatter::format(\'' . $format['name'] . '\', $obj, $fmt);';
            $content[] = '';
        }

        $content[] = '$this->resp(0, $obj);';
    }

    protected static function setUpdate(&$content, $config, $method, $opts, $uses)
    {
        $model = $uses[ $config['model'] ];
        $filters = $config['filters'];
        $req_params = '$this->req->param->id';
        $cond = ControlFilterProcess::getFilters($filters, 'id', $req_params);
        $content[] = '$cond = [';
        ControlFilterProcess::addArrayCond($content, $cond);
        $content[] = '];';
        $content[] = '';
        $content[] = '$obj = ' . $model . '::getOne($cond);';
        $content[] = 'if (!$obj) {';
        $content[] = '    return $this->resp(404);';
        $content[] = '}';
        $content[] = '';

        $form = $opts['form'];
        $content[] = '$form = new Form(\'' . $form . '\');';
        $content[] = 'if (!($valid = $form->validate($obj))) {';
        $content[] = '    return $this->resp(422, $form->getErrors());';
        $content[] = '}';
        $content[] = '';

        $content[] = 'if(!' . $model . '::set((array)$valid, [\'id\' => $obj->id])) {';
        $content[] = '    return $this->resp(500, null, ' . $model . '::lastError());';
        $content[] = '}';

        $content[] = '';
        $content[] = '$obj = ' . $model . '::getOne([\'id\' => $obj->id]);';

        if (isset($config['format'])) {
            $format = $config['format'];
            $fmt = '';
            if ($format['fields']) {
                $fmt = '\'' . implode('\', \'', $format['fields']) . '\'';
            }
            $content[] = '$fmt = [' . $fmt . '];';
            $content[] = '$obj = Formatter::format(\'' . $format['name'] . '\', $obj, $fmt);';
            $content[] = '';
        }

        $content[] = '$this->resp(0, $obj);';
    }

    public static function method(&$content, $config, $method, $opts, $uses)
    {
        $c_method = 'set' . ucfirst($method);
        if (!method_exists(ControlMethodWriterApi::class, $c_method)){
            $content[] = '// START EDIT HERE';
            return;
        }

        self::$c_method($content, $config, $method, $opts, $uses);
    }
}
