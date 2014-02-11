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

        public function __construct (array $values = array())
        {
            $this->values = $values;
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
    }

