<?php

namespace CliModule\Library;

class ViewWriterAdmin
{
    protected static function getTitle($config)
    {
        return $config['perms']['group'] ?? '?TITLE?';
    }

    protected static function genContentEdit($config)
    {
        $title = self::getTitle($config);

        $ctn  = ['<form  method="POST" class="needs-validation main" novalidate>'];
        $ctn[] = '    <nav class="navbar navbar-expand-lg navbar-light bg-white navbar-shadow">';
        $ctn[] = '        <div class="navbar-multiline mr-auto">';
        $ctn[] = '            <span class="navbar-brand">' . $title . ' Editor</span>';
        $ctn[] = '            <nav aria-label="breadcrumb">';
        $ctn[] = '                <ol class="breadcrumb">';
        $ctn[] = '                    <li class="breadcrumb-item">';
        $ctn[] = '                        <a href="<?= $this->router->to(\'adminHome\') ?>">';
        $ctn[] = '                            Home';
        $ctn[] = '                        </a>';
        $ctn[] = '                    </li>';
        $ctn[] = '                    <li class="breadcrumb-item active" aria-current="page">';
        $ctn[] = '                        <?= $subtitle ?>';
        $ctn[] = '                    </li>';
        $ctn[] = '                </ol>';
        $ctn[] = '            </nav>';
        $ctn[] = '        </div>';
        $ctn[] = '        <button class="btn btn-primary">Save</button>';
        $ctn[] = '    </nav>';

        $ctn[] = '    <?= $form->csrfField(\'t\') ?>';

        $ctn[] = '    <div class="container p-3">';
        $ctn[] = '        <div class="row mb-3">';
        $ctn[] = '            <div class="col-md-12">';
        $ctn[] = '                <div class="card">';
        $ctn[] = '                    <div class="card-body">';
        $ctn[] = '                        START EDIT HERE';
        $ctn[] = '                    </div>';
        $ctn[] = '                </div>';
        $ctn[] = '            </div>';
        $ctn[] = '        </div>';
        $ctn[] = '    </div>';
        $ctn[] = '</form>';

        return implode(PHP_EOL, $ctn);

    }

    protected static function genContentIndex($config)
    {
        $perm_prefix = $config['perms']['prefix'];
        $editable = false;
        $removable = false;
        $edit_route = null;
        $title = self::getTitle($config);
        if (isset($config['methods']['edit'])) {
            $editable = true;
            $edit_route = $config['methods']['edit']['name'];
        }
        if (isset($config['methods']['remove'])) {
            $removable = true;
            $remove_route = $config['methods']['remove']['name'];
        }

        $ctn  = ['<nav class="navbar navbar-expand-lg navbar-light bg-white navbar-shadow">'];
        $ctn[] = '    <div class="navbar-multiline mr-auto">';
        $ctn[] = '        <span class="navbar-brand" href="#0">' . $title . ' Editor</span>';
        $ctn[] = '        <nav aria-label="breadcrumb">';
        $ctn[] = '            <ol class="breadcrumb">';
        $ctn[] = '                <li class="breadcrumb-item">';
        $ctn[] = '                    <a href="<?= $this->router->to(\'adminHome\') ?>">Home</a>';
        $ctn[] = '                </li>';
        $ctn[] = '                <li class="breadcrumb-item active" aria-current="page">';
        $ctn[] = '                    ' . $title;
        $ctn[] = '                </li>';
        $ctn[] = '            </ol>';
        $ctn[] = '        </nav>';
        $ctn[] = '    </div>';
        if ($editable) {
            $ctn[] = '    <?php if ($this->can_i->' . $perm_prefix . '_create): ?>';
            $ctn[] = '        <?php $next = $this->router->to(\'' . $edit_route . '\', [\'id\'=>0]); ?>';
            $ctn[] = '        <a href="<?= $next ?>" class="btn btn-primary">Create New</a>';
            $ctn[] = '    <?php endif; ?>';
        }
        $ctn[] = '</nav>';
        $ctn[] = '';

        $ctn[] = '<div class="container p-3">';
        $ctn[] = '    <div class="row">';
        $ctn[] = '        <div class="col-md-3">';
        $ctn[] = '            <div class="card mb-3">';
        $ctn[] = '                <div class="card-body">';
        $ctn[] = '                    <div>Total item: <?= number_format($total); ?></div>';
        $ctn[] = '                </div>';
        $ctn[] = '            </div>';
        $ctn[] = '        </div>';

        $ctn[] = '        <div class="col-md-9">';

        $ctn[] = '            <?php if($objects): ?>';
        $ctn[] = '                <?php $csrf = $form->csrfToken(); ?>';
        $ctn[] = '                <ul class="list-group list-group-flush card mb-3">';
        $ctn[] = '                <?php foreach($objects as $object): ?>';
        $ctn[] = '                    <li class="list-group-item">';

        $ctn[] = '                        <div class="d-flex w-100 justify-content-between">';
        $ctn[] = '                            <h5 class="mb-1">';
        $ctn[] = '                                ?OBJECT TITLE?';
        $ctn[] = '                            </h5>';
        $ctn[] = '                            <div class="btn-group btn-group-sm" role="group" aria-label="Action">';
        if ($editable) {
            $ctn[] = '                                <?php if($this->can_i->' . $perm_prefix . '_update): ?>';
            $ctn[] = '                                    <a href="<?= $this->router->to(\'' . $edit_route . '\', [\'id\'=>$object->id]) ?>" class="btn btn-secondary" title="Edit">';
            $ctn[] = '                                        <i class="fas fa-edit"></i>';
            $ctn[] = '                                    </a>';
            $ctn[] = '                                <?php endif; ?>';
        }

        $ctn[] = '                                <?php if(isset($object->page)): ?>';
        $ctn[] = '                                    <a href="<?= $object->page ?>" class="btn btn-secondary" title="View Page" target="_blank">';
        $ctn[] = '                                        <i class="fas fa-external-link-square-alt"></i>';
        $ctn[] = '                                    </a>';
        $ctn[] = '                                <?php endif; ?>';

        if ($removable) {
            $ctn[] = '                                <?php if($this->can_i->' . $perm_prefix . '_remove): ?>';
            $ctn[] = '                                    <div class="btn-group btn-group-sm" role="group">';
            $ctn[] = '                                        <button id="object-action-<?= $object->id ?>" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>';
            $ctn[] = '                                            <div class="dropdown-menu" aria-labelledby="object-action-<?= $object->id ?>">';
            $ctn[] = '                                                <a class="dropdown-item"';
            $ctn[] = '                                                    data-toggle="confirm"';
            $ctn[] = '                                                    data-text="Are you sure want to remove this item?"';
            $ctn[] = '                                                    data-btn-type="danger"';
            $ctn[] = '                                                    href="<?= $this->router->to(\'' . $remove_route . '\', [\'id\'=>$object->id], [\'t\'=>$csrf]) ?>">Delete</a>';
            $ctn[] = '                                            </div>';
            $ctn[] = '                                        </button>';
            $ctn[] = '                                    </div>';
            $ctn[] = '                                <?php endif; ?>';
        }
        $ctn[] = '                            </div>';
        $ctn[] = '                        </div>';

        $ctn[] = '                        <small>';
        $ctn[] = '                            <span title="Created">';
        $ctn[] = '                                <i class="far fa-calendar-plus"></i>';
        $ctn[] = '                                <?= $object->created->format(\'M d, Y H:i\') ?>';
        $ctn[] = '                            </span>';
        $ctn[] = '                        </small>';
        $ctn[] = '                    </li>';
        $ctn[] = '                <?php endforeach; ?>';
        $ctn[] = '            <?php endif; ?>';

        $ctn[] = '            <?php if($pages): ?>';
        $ctn[] = '                <?php';
        $ctn[] = '                    $args = [';
        $ctn[] = '                        \'pages\' => $pages,';
        $ctn[] = '                        \'align\' => \'right\'';
        $ctn[] = '                    ]';
        $ctn[] = '                ?>';
        $ctn[] = '                <?= $this->partial(\'shared/pagination\', $args); ?>';
        $ctn[] = '            <?php endif; ?>';
        $ctn[] = '        </div>';
        $ctn[] = '    </div>';
        $ctn[] = '</div>';

        return implode(PHP_EOL, $ctn);
    }

    public static function genContent(string $method, array $config)
    {
        $method = 'genContent' . ucfirst($method);
        if (!method_exists(ViewWriterAdmin::class, $method)) {
            return '<!-- START EDIT HERE -->';
        } else {
            return self::$method($config);
        }
    }
}
