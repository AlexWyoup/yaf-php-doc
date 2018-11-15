<?php

namespace Yaf;

use const INTERNAL\PHP\DEFAULT_SLASH;
use ReflectionFunction;
use const YAF\ERR\AUTOLOAD_FAILED;
use const YAF\ERR\DISPATCH_FAILED;
use const YAF\ERR\NOTFOUND\CONTROLLER;
use const YAF\ERR\NOTFOUND\MODULE;
use const YAF\ERR\ROUTE_FAILED;
use const YAF\ERR\STARTUP_FAILED;
use const YAF\ERR\TYPE_ERROR;
use Yaf\View\Simple;


final class DispatcherTODOLoader
{
    /**
     * @var DispatcherTODOLoader
     */
    protected static $_instance = null;

    /**
     * @var Router
     */
    protected $_router;

    /**
     * @var View_Interface
     */
    protected $_view;

    /**
     * @var Request_Abstract
     */
    protected $_request;

    /**
     * @var array
     */
    protected $_plugins;

    /**
     * @var bool
     */
    protected $_auto_render = true;

    /**
     * @var bool
     */
    protected $_return_response = false;

    /**
     * @var bool
     */
    protected $_instantly_flush = false;

    /**
     * @var string
     */
    protected $_default_module;

    /**
     * @var string
     */
    protected $_default_controller;

    /**
     * @var string
     */
    protected $_default_action;

    private function __construct()
    {
        $this->_plugins = [];
        $this->_router = new Router();
        $this->_default_module = YAF_G('default_module');
        $this->_default_controller = YAF_G('default_controller');
        $this->_default_action = YAF_G('default_action');

        self::$_instance = new self();
    }

    /**
     * @return DispatcherTODOLoader
     */
    public static function getInstance(): DispatcherTODOLoader
    {
        $instance = self::$_instance;

        if (!is_null($instance)) {
            return $instance;
        }

        self::$_instance = new DispatcherTODOLoader();

        return self::$_instance;
    }

    public function dispatch(Request_Abstract $request)
    {
        $this->_request = $request;
        /** @var Response_Abstract $rResponse */
        $rResponse = null;

        if ($response = $this->_dispatch($rResponse)) {
            return $response;
        }

        return false;
    }

    /**
     * @return DispatcherTODOLoader
     */
    public function enableView(): DispatcherTODOLoader
    {
        $this->_auto_render = true;

        return $this;
    }

    /**
     * @return DispatcherTODOLoader
     */
    public function disableView(): DispatcherTODOLoader
    {
        $this->_auto_render = false;

        return $this;
    }

    /**
     * @param string $templates_dir
     * @param array|null $options
     * @return null|Simple|View_Interface
     * @throws \Exception
     */
    public function initView(string $templates_dir, array $options = null): ?View_Interface
    {
        $view = $this->_initView($templates_dir, $options);

        return $view;
    }

    /**
     * @param View_Interface $view
     * @return $this|bool
     */
    public function setView(View_Interface $view)
    {
        if (is_object($view) && $view instanceof View_Interface) {
            $this->_view = $view;

            return $this;
        }

        return false;
    }

    /**
     * @param Request_Abstract $request
     * @return $this|bool
     */
    public function setRequest(Request_Abstract $request)
    {
        if (!is_object($request) || !($request instanceof Request_Abstract)) {
            trigger_error(sprintf("Expects a %s instance", get_class($request)), E_WARNING);
            return false;
        }

        $this->_request = $request;
        return $this;
    }

    public function getApplication()
    {
        // TODO PHP_MN(yaf_application_app)(INTERNAL_FUNCTION_PARAM_PASSTHRU);什么意思
    }

    /**
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->_router;
    }

    /**
     * @return Request_Abstract
     */
    public function getRequest(): Request_Abstract
    {
        return $this->_request;
    }

    /**
     * @param string $module
     * @return $this|bool
     */
    public function setDefaultModule(string $module)
    {
        if (is_string($module) && !empty($module) && Application::isModuleName($module)) {
            $this->_default_module = ucfirst(strtolower($module));

            return $this;
        }

        return false;
    }

    /**
     * @param string $controller
     * @return $this|bool
     */
    public function setDefaultController(string $controller)
    {
        if (is_string($controller) && !empty($controller)) {
            $this->_default_controller = strtoupper($controller);

            return $this;
        }

        return false;
    }

    /**
     * @param string $action
     * @return $this|bool
     */
    public function setDefaultAction(string $action)
    {
        if (is_string($action) && !empty($action)) {
            $this->_default_action = $action;

            return $this;
        }

        return false;
    }

    /**
     * @param bool $auto_response
     * @return $this|int
     */
    public function returnResponse($auto_response = true)
    {
        $argc = func_get_args();

        if ($argc) {
            $this->_return_response = $auto_response;
            return $this;
        } else {
            return $this->_return_response === true ? 1 : 0;
        }
    }

    /**
     * @param bool $flag
     * @return $this|int
     */
    public function flushInstantly(bool $flag)
    {
        $argc = func_get_args();

        if ($argc) {
            $this->_instantly_flush = $flag;
            return $this;
        } else {
            return $this->_instantly_flush === true ? 1 : 0;
        }
    }

    /**
     * @param callable $callback
     * @param int $error_type
     * @return DispatcherTODOLoader
     */
    public function setErrorHandler(callable $callback, int $error_type = E_ALL | E_STRICT): DispatcherTODOLoader
    {
        try {
            set_error_handler($callback, $error_type);
        } catch (\Throwable $e) {
            trigger_error("Call to set_error_handler failed", E_WARNING);
        }

        return $this;
    }

    /**
     * @param bool $flag
     * @return $this|int
     */
    public function autoRender(bool $flag = false)
    {
        $argc = func_get_args();

        if ($argc) {
            $this->_auto_render = $flag ? true : false;
            return $this;
        } else {
            return $this->_auto_render === true ? 1 : 0;
        }
    }

    /**
     * @param bool $flag
     * @return $this|bool
     */
    public function throwException(bool $flag = false)
    {
        $argc = func_get_args();

        if ($argc) {
            YAF_G('throw_exception', $flag ? 1 : 0);
            return $this;
        } else {
            return (bool)YAF_G('throw_exception');
        }
    }

    /**
     * @param bool $flag
     * @return $this|bool
     */
    public function catchException(bool $flag = false)
    {
        $argc = func_get_args();

        if ($argc) {
            YAF_G('catch_exception', $flag ? 1 : 0);
            return $this;
        } else {
            return (bool)YAF_G('catch_exception');
        }
    }

    /**
     * @param Plugin_Abstract $plugin
     * @return $this|bool
     */
    public function registerPlugin(Plugin_Abstract $plugin)
    {
        if (!is_object($plugin) || !($plugin instanceof Plugin_Abstract)) {
            trigger_error(sprintf("Expect a %s instance", get_class($plugin)), E_WARNING);
            return false;
        }

        $this->_plugins[] = $plugin;

        return $this;
    }

    // ================================================== 内部常量 ==================================================

    /**
     * @internal
     */
    private const YAF_PLUGIN_HOOK_ROUTESTARTUP  = "routerstartup";
    private const YAF_PLUGIN_HOOK_ROUTESHUTDOWN = "routershutdown";
    private const YAF_PLUGIN_HOOK_LOOPSTARTUP   = "dispatchloopstartup";
    private const YAF_PLUGIN_HOOK_PREDISPATCH   = "predispatch";
    private const YAF_PLUGIN_HOOK_POSTDISPATCH  = "postdispatch";
    private const YAF_PLUGIN_HOOK_LOOPSHUTDOWN  = "dispatchloopshutdown";
    private const YAF_PLUGIN_HOOK_PRERESPONSE   = "preresponse";
    private const YAF_ERROR_CONTROLLER          = "Error";
    private const YAF_ERROR_ACTION              = "error";

    // ================================================== 内部方法 ==================================================

    /**
     * @internal
     * @param null|Response_Abstract $response
     * @return null
     * @throws \Exception
     */
    private function _dispatch(?Response_Abstract $response)
    {
        $nesting = YAF_G('forward_limit');

        $response = Response_Abstract::instance(php_sapi_name());
        $request = $this->_request;
        $plugins = $this->_plugins;

        if (!is_object($request)) {
            throw new \Exception(sprintf('Expect a %s instance', get_class($request)), TYPE_ERROR);
        }

        if (!$request->isRouted()) {
            $this->MACRO_YAF_PLUGIN_HANDLE($plugins, self::YAF_PLUGIN_HOOK_ROUTESTARTUP, $request, $response);
            $this->MACRO_YAF_EXCEPTION_HANDLE($request, $response); // TODO MACRO_YAF_EXCEPTION_HANDLE NEED COMPLETE
            if (!$this->_route($request)) {
                throw new \Exception("Routing request failed", ROUTE_FAILED);
            }

            $this->_fixDefault($request);
            $this->MACRO_YAF_PLUGIN_HANDLE($plugins, self::YAF_PLUGIN_HOOK_ROUTESHUTDOWN, $request, $response);
            $this->MACRO_YAF_EXCEPTION_HANDLE($request, $response); // TODO MACRO_YAF_EXCEPTION_HANDLE NEED COMPLETE
            $request->setRouted();
        } else {
            $this->_fixDefault($request);
        }

        $this->MACRO_YAF_PLUGIN_HANDLE($plugins, self::YAF_PLUGIN_HOOK_LOOPSTARTUP, $request, $response);
        $this->MACRO_YAF_EXCEPTION_HANDLE($request, $response);

        $view = $this->_initView(null, null);
        if (!$view) {
            return null;
        }

        do {
            $this->MACRO_YAF_PLUGIN_HANDLE($plugins, self::YAF_PLUGIN_HOOK_PREDISPATCH, $request, $response);
            $this->MACRO_YAF_EXCEPTION_HANDLE($request, $response);

            if ($this->_handle($request, $response, $view)) {
                // TODO
            }
        } while (--$nesting > 0 && !$request->isDispatched());
    }

    /**
     * @internal
     * @param string $tpl_dir
     * @param array $options
     * @return Simple|View_Interface
     * @throws \Exception
     */
    private function _initView(string $tpl_dir, array $options): ?View_Interface
    {
        $view = $this->_view;

        if (is_object($view) && $view instanceof View_Interface) {
            return $view;
        }

        $view = new Simple($tpl_dir, $options);
        if (empty($view)) {
            return null;
        }
        $this->_view = $view;

        return $view;
    }

    /**
     * @param Request_Abstract $request
     * @param Response_Abstract $response
     */
    private function _dispatcherExceptionHandler(Request_Abstract $request, Response_Abstract $response)
    {
        if (YAF_G('in_exception')) {
            return;
        }

        YAF_G('in_exception', 1);

        $module = $request->module;

        if (!is_string($module) || empty($module)) {
            $module = $this->_default_module;
            $request->setModuleName($module);
        }

        $controller = DispatcherTODOLoader::YAF_ERROR_CONTROLLER;
        $action = DispatcherTODOLoader::YAF_ERROR_ACTION;

        // TODO 下面有点难懂,先放着,后面再研究研究
    }

    /**
     * @param Request_Abstract $request
     * @return int
     * @throws \Exception
     */
    private function _route(Request_Abstract $request): int
    {
        $router = $this->getRouter();

        if (is_object($router)) {
            if ($router instanceof Router) {
                if ($router->route($request)) {
                    return 1;
                }
            } else {
                $result = call_user_func([$router, 'route'], $request);

                if (!$result) {
                    throw new \Exception("Routing request faild", ROUTE_FAILED);
                }
            }

            return 1;
        }

        return 0;
    }

    /**
     * @param Request_Abstract $request
     */
    private function _fixDefault(Request_Abstract $request): void
    {
        $module     = $request->getModuleName();
        $action     = $request->getActionName();
        $controller = $request->getControllerName();


        // module
        if (!is_string($module) || empty($module)) {
            $request->setModuleName($this->_default_module);
        } else {
            $request->setModuleName(ucfirst(strtolower($module)));
        }

        // controller
        if (!is_string($controller) || empty($controller)) {
            $request->setControllerName($this->_default_controller);
        } else {
            /**
             * Upper controller name
             * eg: Index_sub -> Index_Sub
             */
            $default_controller = ucfirst(strtolower($controller));
            $default_controller = preg_replace_callback('/_(\w+)/', function($match) {
                return '_' . ucfirst($match[1]);
            }, $default_controller);

            $request->setControllerName($default_controller);
        }

        // action
        if (!is_string($action) || empty($action)) {
            $request->setActionName($action);
        } else {
            $request->setActionName(ucfirst(strtolower($action)));
        }
    }

    /**
     * @param Request_Abstract $request
     * @param Response_Abstract $response
     * @param View_Interface $view
     * @return int
     * @throws \Exception
     */
    private function _handle(Request_Abstract $request, Response_Abstract $response, View_Interface $view)
    {
        $app_dir = YAF_G('directory');

        $request->setDispatched();
        if (!$app_dir) {
            $error_message = sprintf("%s requires %s(which set the application.directory) to be initialized first", get_class($this), Application::class);
            throw new \Exception($error_message, STARTUP_FAILED);
        } else {
            $is_def_module = 0;

            $module     = $request->getModuleName();
            $controller = $request->getControllerName();
            $dmodule    = $this->_default_module;

            if (!is_string($module) || empty($module)) {
                throw new \Exception("Unexcepted a empty module name");
            } else if (!Application::isModuleName($module)) {
                throw new \Exception(sprintf("There is no module %s", $module), MODULE);
            }

            if (!is_string($controller) || empty($controller)) {
                throw new \Exception("Unexcepted a empty controller name", DISPATCH_FAILED);
            }

            if ($dmodule == $module) {
                $is_def_module = 1;
            }

            /** @var Controller_Abstract $ce */
            $ce = $this->_getController($app_dir, $module, $controller, $is_def_module);
            if (empty($ce)) {
                return 0;
            } else {
                // TODO controller 入参问题
                $icontroller = new $ce($request, $response, $view);

                if (!$request->isDispatched()) {
                    return $this->_handle($request, $response, $view);
                }

                /* view template directory for application, please notice that view engine's directory has high priority */
                if ($is_def_module) {
                    $view_dir = sprintf("%s%c%s", $app_dir, DEFAULT_SLASH, "views");
                } else {
                    $view_dir = sprintf("%s%c%s%c%s%c%s", $app_dir, DEFAULT_SLASH, "modules", DEFAULT_SLASH, $module, DEFAULT_SLASH, "views"));
                }

                if (YAF_G('view_directory')) {
                    YAF_G('view_directory', 'NULL');
                }

                YAF_G('view_directory', $view_dir);
                $property = new \ReflectionProperty($icontroller, '_name');
                $property->setAccessible(true);
                $property->setValue($controller);

                $action = $request->getActionName();
                $func_name = sprintf('%s%s', strtolower($action), 'action');

                try {
                    $reflectionFunc = new ReflectionFunction($ce, $func_name);
                } catch (\ReflectionException $e) {
                    $reflectionFunc = null;
                }
                if ($reflectionFunc) {
                    if ($reflectionFunc->getNumberOfParameters()) {
                        $this->_getCallParameters();
                    } else {

                    }

                }
            }
        }
    }

    /**
     * @param string $app_dir
     * @param string $module
     * @param string $controller
     * @param int $def_module
     * @return int|string|null
     * @throws \Exception
     * @throws \ReflectionException
     */
    private function _getController(string $app_dir, string $module, string $controller, int $def_module)
    {
        if ($def_module) {
            $directory = sprintf("%s%c%s", $app_dir, DIRECTORY_SEPARATOR, Loader::YAF_CONTROLLER_DIRECTORY_NAME);
            $directory_len = strlen($directory);
        } else {
            $directory = sprintf("%s%c%s%c%s%c%s",
                $app_dir, DIRECTORY_SEPARATOR, Loader::YAF_MODULE_DIRECTORY_NAME, DIRECTORY_SEPARATOR, $module, DIRECTORY_SEPARATOR, Loader::YAF_CONTROLLER_DIRECTORY_NAME);
            $directory_len = strlen($directory);
        }

        $class = '';
        if ($directory_len) {
            if (YAF_G('name_suffix')) {
                $class = sprintf("%s%s%s", $controller, YAF_G('name_separator'), 'Controller');
            } else {
                $class = sprintf("%s%s%s", 'Controller', YAF_G('name_separator'), $controller);
            }
        }

        $class_lowercase = strtolower($class);

        if (!class_exists($class_lowercase, false)) {
            // TODO $directory是否为引用
            if (Loader::internalAutoload($controller, strlen($controller), $directory)) {
                trigger_error(sprintf("Failed opening controller script %s", $directory), CONTROLLER);
                return null;
            } else if (!class_exists($class_lowercase)) {
                trigger_error(sprintf('"Could not find class %s in controller script %s"', $class), AUTOLOAD_FAILED);
                return 0;
            } else {
                $ce = $class_lowercase;

                if (!($ce instanceof Controller_Abstract)) {
                    trigger_error(sprintf("Controller must be an instance of %s", Controller_Abstract::class), TYPE_ERROR);
                    return 0;
                }
            }

            return $ce;
        }

        return null;
    }

    private function _getCallParameters(Request_Abstract $request, ReflectionFunction $fptr, ?array &$params, int &$count)
    {
        $request_args = $request->getParams();
        $func_arg_info = $fptr->getParameters();

        foreach ($func_arg_info as $arg) {
            if (isset($request_args[$arg->getName()])) {
                $params[$arg->getName()] = $request_args[$arg->getName()];
            }
        }
    }

    // ================================================== 内部宏 ==================================================

    /**
     * 内部宏
     *
     * @internal
     * @param Plugin_Abstract[] $plugins
     * @param $hook
     * @param Request_Abstract $request
     * @param Response_Abstract $response
     */
    private function MACRO_YAF_PLUGIN_HANDLE(array $plugins, string $hook, Request_Abstract $request, Response_Abstract $response): void
    {
        if (!is_null($plugins)) {
            foreach ($plugins as $plugin) {
                if (is_callable([$plugin, $hook])) {
                    call_user_func([$plugin, $hook], $request, $response);
                }
            }
        }
    }

    /**
     * 内部方法, YAF对外无此方法
     *
     * 原宏位置在
     * @see yaf_exception.h
     *
     * @internal
     * @param Request_Abstract $request
     * @param Response_Abstract $response
     */
    private function MACRO_YAF_EXCEPTION_HANDLE(Request_Abstract $request, Response_Abstract $response): void
    {
        if (YAF_G('catch_exception')) {
            $this->_dispatcherExceptionHandler($request, $response);
        }
    }

    private function __clone()
    {
    }

    private function __sleep()
    {
    }

    private function __wakeup()
    {
    }
}