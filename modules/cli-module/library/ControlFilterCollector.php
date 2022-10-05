<?php

namespace CliModule\Library;

use Cli\Library\Bash;
use Mim\Library\Fs;

class ControlFilterCollector
{
    public static function setFilters(&$filters, $parents, $space = 2)
    {
        self::setFilterStatus($filters, $space);
        self::setFilterServices($filters, $space);
        self::setFilterParents($filters, $parents, $space);
    }

    protected static function setFilterParents(&$filters, $parents, $space = 2)
    {
        $par_filters = [];
        foreach ($parents as $name => $opts) {
            $property = Bash::ask([
                'text' => 'Filter by parent `' . $name . '`, property',
                'type' => 'any',
                'space' => $space
            ]);

            if (!$property) {
                continue;
            }

            $par_filters[$name] = [
                'property' => $property,
                'column'   => Bash::ask([
                    'text' => 'Table column name',
                    'type' => 'any',
                    'space' => $space + 2,
                    'default' => $name
                ])
            ];
        }

        if ($par_filters) {
            $filters['parents'] = $par_filters;
        }
    }

    protected static function setFilterStatus(&$filters, $space = 2)
    {
        $status = Bash::ask([
            'text' => 'Filter by status',
            'type' => 'any',
            'space' => $space
        ]);
        if ('' !== $status) {
            $filters['status'] = $status;
        }
    }

    protected static function setFilterServices(&$filters, $space = 2)
    {
        $services = [];
        while (true)
        {
            $service = Bash::ask([
                'text' => 'Filter by service, name',
                'type' => 'any',
                'space' => $space
            ]);
            if (!$service) {
                break;
            }

            $services[$service] = [
                'property' => Bash::ask([
                    'text' => 'Service property',
                    'type' => 'any',
                    'space' => $space + 2,
                    'default' => 'id'
                ]),
                'column'   => Bash::ask([
                    'text' => 'Table column name',
                    'type' => 'any',
                    'space' => $space + 2,
                    'default' => $service
                ])
            ];
        }

        if ($services) {
            $filters['services'] = $services;
        }
    }
}
