<?php

namespace SchoolPalm\ModuleBridge\Core;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Facades\CreatedRegistry;
use SchoolPalm\ModuleBridge\Support\Helper;

abstract class ActionResolver
{
    /** @var string|null The current portal (e.g. 'admin', 'teacher', 'student'). */
    public $root;

    /** @var string|null The current module name (e.g. 'students', 'subjects'). */
    protected $moduleName;

    /** @var string|null The current action (e.g. 'view-list', 'edit', 'create'). */
    protected $action;

    /** @var mixed|null Optional record ID (e.g. student ID). */
    public $id;

    /** @var string|null Absolute path to the current module directory. */
    protected $modulePath;

    public $module;

    /** @var \Illuminate\Http\Request The current HTTP request instance. */
    public $request;

    public $rootDir;
    public $moduleRootPath;

    /** @var array<string, object> Loaded module action classes (e.g. StudentActions). */
    protected array $actions = [];

    protected function __construct()
    {
        $this->request = request();
        $this->rootDir = config('sdk.modules_path', 'modules');
        $this->root = Helper::getPathSegment('portal');
        $this->moduleName = Helper::getPathSegment('module');
        $this->action = Helper::getPathSegment('action');
        $this->id = Helper::getPathSegment('id');

        if (!$this->moduleName) {
            $this->moduleName = $this->root;
        }

        if (!$this->action) {
            $this->action = $this->moduleName;
        }
    }

    public function  init(array $module)
    {
        $this->module =  $module;
        $this->loadActions();
       
    }
    public function performAction()
    {
        return $this->handleActions();
    }

    public function getMethodName(string $prefix = 'run'): string
    {
        return $prefix . preg_replace(
            '/\s+/',
            '',
            Str::title(preg_replace('/\-+/', ' ', $this->action))
        );
    }

    public function handleActions()
    {
        $method = $this->getMethodName();

        if (method_exists($this, $method)) {
            return app()->call([$this, $method], ['id' => $this->id]);
        }

        foreach ($this->actions as $actionClass) {
            if (method_exists($actionClass, $method)) {
                return app()->call([$actionClass, $method], ['id' => $this->id]);
            }
        }

        return abort(403, 'No action defined.');
    }

    protected function loadActions(): void
    {
        $this->actions = [];


        $actionPath = $this->module['path'] . DIRECTORY_SEPARATOR . 'Actions';

  
        $actionNamespace = $this->module['namespace'] . '\\Actions';

        if (is_dir($actionPath)) {
            foreach (File::files($actionPath) as $file) {
                $class = $actionNamespace . '\\' . pathinfo($file, PATHINFO_FILENAME);
                if (class_exists($class)) {
                    $this->actions[$class] = new $class($this);
                }
            }
        }
    }

    public function __call($method, $args)
    {
        foreach ($this->actions as $action) {
            if (method_exists($action, $method)) {
                return app()->call([$action, $method], ['id' => $this->id]);
            }
        }

        throw new \BadMethodCallException("Method {$method} not found");
    }

    public function componentPath(): string
    {
        return Str::studly($this->action);
    }

    public function refererComponent(): string
    {
        return '';
    }

    public function moduleComponentPath(string $path = ''): string
    {
        return '';
    }
}
