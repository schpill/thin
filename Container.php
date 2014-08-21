<?php
    /**
     * Container class
     *
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Container extends Object
    {
        protected $values = array();
        protected $_token;

        public static function instance()
        {
            return container();
        }

        public function __construct($values = array())
        {
            if (is_object($values)) {
                $values = $values->assoc();
            }

            if (count($values)) {
                $this->values = $this->make($values);
            } else {
                $this->values = $values;
            }

            $this->_token = Utils::token();
        }

        public function make(array $array)
        {
            $return = array();

            foreach ($array as $k => $v) {
                if (Arrays::is($v)) {
                    $o = new self;
                    $return[$k] = $o->populate($v);
                } else {
                    $return[$k] = $v;
                }
            }

            return $return;
        }

        public function offsetSet($id, $value)
        {
            $this->values[$id] = $value;
        }

        public function offsetGet($id)
        {
            if (!Arrays::exists($id, $this->values)) {
                throw new Exception(sprintf('Identifier "%s" is not defined.', $id));
            }

            $isFactory = is_object($this->values[$id]) && method_exists($this->values[$id], '__invoke');

            return (true === $isFactory) ? $this->values[$id]($this) : $this->values[$id];
        }

        public function offsetExists($id)
        {
            return ake($id, $this->values);
        }

        public function offsetUnset($id)
        {
            unset($this->values[$id]);
        }

        public static function share(\Closure $callable)
        {
            return function ($c) use ($callable) {
                static $object;

                if (null === $object) {
                    $object = $callable($c);
                }

                return $object;
            };
        }

        public static function protect(\Closure $callable)
        {
            return function ($c) use ($callable) {
                return $callable;
            };
        }

        public function raw($id)
        {
            if (!Arrays::exists($id, $this->values)) {
                throw new Exception(sprintf('Identifier "%s" is not defined.', $id));
            }

            return $this->values[$id];
        }

        public function extend($id, \Closure $callable)
        {
            if (!Arrays::exists($id, $this->values)) {
                throw new Exception(sprintf('Identifier "%s" is not defined.', $id));
            }

            $factory = $this->values[$id];

            if (!($factory instanceof \Closure)) {
                throw new Exception(sprintf('Identifier "%s" does not contain an object definition.', $id));
            }

            return $this->values[$id] = function ($c) use ($callable, $factory) {
                return $callable($factory($c), $c);
            };
        }

        public function keys()
        {
            return array_keys($this->values);
        }

        public function values()
        {
            return $this->values;
        }

        public function showVars()
        {
            return get_object_vars($this);
        }

        public function route(Container $route)
        {
            $routes = container()->getMapRoutes();
            $routes = empty($routes) ? array() : $routes;
            $routes[$route->getName()] = $route;
            container()->setMapRoutes($routes);

            return $this;
        }

        public function link($routeName, $params = array())
        {
            $link = URLSITE;
            $routes = container()->getMapRoutes();

            if (Arrays::isArray($routes)) {
                if (ake($routeName, $routes)) {
                    $route = $routes[$routeName];
                    $path = $route->getPath();

                    if (count($params)) {
                        foreach ($params as $key => $param) {
                            $path = strReplaceFirst('(.*)', $param, $path);
                        }
                    }

                    $link .= $path;
                }
            }

            return $link;
        }

        public function isRoute($routeName)
        {
            $routes = container()->getMapRoutes();

            if (Arrays::isArray($routes)) {
                if (ake($routeName, $routes)) {
                    $route = $routes[$routeName];
                    $actualRoute = $this->getRoute();

                    return $actualRoute === $route;
                }
            }

            return false;
        }

        public static function create($name = null, $array = array())
        {
            $name       = (null === $name) ? sha1(time()) : $name;
            $o          = o($name);

            return $o->populate($array);
        }

        public function event($id, \Closure $closure)
        {
            $this->values[sha1($id . $this->_token)] = $closure;

            return $this;
        }

        public function daol($c, $f, $args = array())
        {
            $c = strstr($c, '-') ? strrev(repl('-', '', $c)) : $c;
            $exist = isAke($this->values, sha1($f . $this->_token), null);

            if (empty($exist)) {
                $exist = isAke($this->_closures, $f, null);

                if (empty($exist)) {
                    $exist = registry('events.' . $f);

                    if (!empty($exist)) {
                        event($c, $exist);
                    } else {
                        $callable = function () use ($f) {
                            return call_user_func_array($f, func_get_args());
                        };
                        event($c, $callable);
                    }
                } else {
                    event($c, $exist);
                }
            } else {
                event($c, $exist);
            }

            return $this;
        }

        public function run($id, $args = array())
        {
            $id = sha1($id . $this->_token);

            if (Arrays::exists($id, $this->values)) {
                return call_user_func_array($this->values[$id], $args);
            }

            return null;
        }
    }

