<?php
/**
 * Module controller
 * @package cli-module
 * @version 0.0.1
 */

namespace CliModule\Controller;

use Cli\Library\Bash;
use CliModule\Library\Builder;
use CliModule\Library\BAdmin;
use CliModule\Library\BController;
use CliModule\Library\BGit;
use CliModule\Library\BHelper;
use CliModule\Library\BIface;
use CliModule\Library\BLibrary;
use CliModule\Library\BMiddleware;
use CliModule\Library\BModel;
use CliModule\Library\BService;

class ModuleController extends \CliModule\Controller
{
    public function adminAction(): void
    {
        $here = $this->validateModuleHere();
        if (BAdmin::build($here, $this->req->param->name)) {
            Bash::echo('Successfully create new control');
        }
    }

    public function controllerAction(): void
    {
        $here = $this->validateModuleHere();
        if (BController::build($here, $this->req->param->name)) {
            Bash::echo('Successfully create new controller');
        }
    }

    public function gitAction(): void
    {
        $here = $this->validateModuleHere();
        if (BGit::init($here)) {
            Bash::echo('Successfully initializing current module git remote origin repository');
        }
    }
    
    public function helperAction(): void
    {
        $here = $this->validateModuleHere();
        if (BHelper::build($here, $this->req->param->name)) {
            Bash::echo('Successfully create new blank helper file');
        }
    }

    public function ifaceAction(): void
    {
        if (BIface::build(getcwd(), $this->req->param->name)) {
            Bash::echo('Successfully create new empty interface');
        }
    }
    
    public function initAction(): void
    {
        if (Builder::build(getcwd())) {
            Bash::echo('Successfully create new empty module');
        }
    }
    
    public function libraryAction(): void
    {
        $here = $this->validateModuleHere();
        if (BLibrary::build($here, $this->req->param->name)) {
            Bash::echo('Successfully create new blank library');
        }
    }

    public function middlewareAction(): void
    {
        $here = $this->validateModuleHere();
        if (BMiddleware::build($here, $this->req->param->name)) {
            Bash::echo('Successfully create new middleware');
        }
    }
    
    public function modelAction(): void
    {
        $here = $this->validateModuleHere();
        if (BModel::build($here, $this->req->param->name)) {
            Bash::echo('Successfully create new blank model');
        }
    }
    
    public function serviceAction(): void
    {
        $here = $this->validateModuleHere();
        if (BService::build($here, $this->req->param->name)) {
            Bash::echo('Successfully create new blank service');
        }
    }
}
