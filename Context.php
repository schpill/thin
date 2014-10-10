<?php
    namespace Thin;

    use Closure;

    class Context extends Customize
    {
        private static $instances   = array();
        public $namespace;

        public function __construct($namespace = '')
        {
            $this->namespace = $namespace;
        }

        public static function instance($ns = 'core')
        {
            $args   = func_get_args();
            $key    = sha1(serialize($args));
            $has    = Instance::has('Context', $key);

            if (true === $has) {
                return Instance::get('Context', $key);
            } else {
                return Instance::make('Context', $key, new self($ns));
            }
        }

        public function __call($event, $args)
        {
            if (substr($event, 0, 3) == 'get' && strlen($event) > 3) {
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($event, 3)));
                $key = Inflector::lower($uncamelizeMethod);

                return $this->get($key);
            } elseif(substr($event, 0, 3) == 'set' && strlen($event) > 3) {
                $value = Arrays::first($args);
                $uncamelizeMethod = Inflector::uncamelize(lcfirst(substr($event, 3)));
                $key = Inflector::lower($uncamelizeMethod);

                return $this->set($key, $value);
            }

            if (true === $this->__has($event)) {
                array_push($args, $this);
                return $this->__fire($event, $args);
            } else {
                if (method_exists($this, $event)) {
                    throw new Exception(
                        "The method $event is a native class' method. Please choose an other name."
                    );
                }
                $value = Arrays::first($args);

                if ($value instanceof Closure) {
                    $eventable = $this->__event($event, $value);
                } else {
                    $set = function () use ($value) {
                        return $value;
                    };
                    $eventable =  $this->__event($event, $set);
                }

                return $this;
            }
        }

        public function __isset($key)
        {
            return $this->__has($key);
        }

        public function __get($key)
        {
            if (true === $this->__has($key)) {
                return $this->__fire($key);
            }
            return null;
        }

        public function __set($key, $value)
        {
            if (is_callable($value)) {
                $eventable = $this->__event($key, $value);
            } else {
                $set = function () use ($value) {
                    return $value;
                };
                $eventable = $this->__event($key, $set);
            }
            return $this;
        }

        public function extend(Context $context)
        {
            $mother = $context->ns;
            $this->$mother(function () use ($context) {
                return $context;
            });
            $daughter = $this->ns;
            $daughterContext = $this;
            $context->$daughter(function () use ($daughterContext) {
                return $daughterContext;
            });
            return $this;
        }

        public function call($ns)
        {
            if (!is_string($ns)) {
                throw new Exception("It is not a valid context. Please call a valid context.");
            }
            if ($ns == $this->ns) {
                throw new Exception("Why to call yourself?");
            }
            $i = isAke(static::$instances, $ns, null);
            if (is_null($i)) {
                throw new Exception("$ns is not an instantiated context. Please call an instantiated context.");
            }
            return $i;
        }
    }
