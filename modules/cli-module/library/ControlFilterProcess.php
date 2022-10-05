<?php

namespace CliModule\Library;

class ControlFilterProcess
{
    static function addArrayCond(&$ctn, $cond, $space = 0)
    {
        $max_arr_len = 0;
        foreach ($cond as $key => $value) {
            $key_len = strlen($key);
            if ($key_len > $max_arr_len) {
                $max_arr_len = $key_len;
            }
        }

        $max_arr_len+= 2;

        foreach ($cond as $key => $value) {
            $ctn[] = str_repeat(' ', $space + 4)
                . str_pad('\'' . $key . '\'', $max_arr_len)
                . ' => '
                . $value
                . ',';
        }

        $last_id = count($ctn) - 1;
        $ctn[$last_id] = chop($ctn[$last_id], ',');
    }

    static function getFilters($filters, $field = null, $value = null)
    {
        $result = [];
        if ($field) {
            $result[$field] = $value;
        }

        if (!$filters) {
            return $result;
        }

        // status filter
        if (isset($filters['status'])) {
            $status = $filters['status'];
            if (!is_numeric($status)) {
                $status = '\'' . addcslashes($status) . '\'';
            }

            $result['status'] = $status;
        }

        if (isset($filters['services'])) {
            $services = $filters['services'];

            foreach ($services as $name => $opt) {
                $serv_prop = $opt['property'];
                $tabl_colm = $opt['column'];

                $result[$tabl_colm] = '$this->' . $name . '->' . $serv_prop;
            }
        }

        if (isset($filters['parents'])) {
            $parents = $filters['parents'];

            foreach ($parents as $name => $opt) {
                $parn_prop = $opt['property'];
                $tabl_colm = $opt['column'];

                $result[$tabl_colm] = '$' . $name . '->' . $parn_prop;
            }
        }

        return $result;
    }
}
