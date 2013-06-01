<?php
    /**
    * Proxy class
    *
    * @package     FTV
    * @author      Gerald Plusquellec

    * $fooBar = new \Thin\Proxy('Foo');
    *
    * // Call private method
    * echo $fooBar->privateMethod(); // 'foo';
    *
    * // Echo protected variable
    * echo $fooBar->protectedProperty; // 'baz';
    *
    * // Set and echo protected variable
    * echo $fooBar->privateProperty; // 'bar';
    * $fooBar->privateProperty = 'test';
    * echo $fooBar->privateProperty; // test
    * </code>
    *
    */

    namespace Thin;
    class Proxy
    {
        protected $instance;
        protected $reflection;

        /**
        * The constructor
        *
        * With class name
        * <code>
        * $fooBar = new \Thin\Proxy('Foo');
        * </code>
        *
        * With class name and args
        * <code>
        * $fooBar = new \Thin\Proxy('Foo', array('arg1', 'arg2'));
        * </code>
        *
        * With object
        * <code>
        * $foo = new Foo('arg1', 'arg2');
        * $fooBar = new \Thin\Proxy($foo);
        * </code>
        *
        * @param string|object $class The object, or the name of the object you want proxy to use.
        * @param array $args The optional arguments to be used in creating your class.
        */
        public function __construct($class, $args = array())
        {
            if(!is_object($class)) {
                $class = new \ReflectionClass($class);
                $class = $class->newInstanceArgs($args);
            }
            $this->instance = $class;
            $this->reflection = new \ReflectionObject($this->instance);
        }

        /**
        * Magic method __call
        *
        * Allows you call any method on the class provided to the constructor.
        *
        * <code>
        * $fooBar = new \Thin\Proxy('Foo');
        * echo $fooBar->privateMethod(); // 'foo';
        * </code>
        *
        * @link http://www.php.net/manual/en/language.oop5.overloading.php#object.call
        * @param string $methodName
        * @param array $args
        * @return mixed
        */
        public function __call($methodName, $args)
        {
            $method = new \ReflectionMethod($this->instance, $methodName);
            $method->setAccessible(true);
            array_unshift($args, $this->instance); // make instance first
            return call_user_func_array(array($method, 'invoke'), $args);
        }

        /**
        * Magic method __get
        *
        * Allows you to retrieve the value of any property inside of the proxied class.
        *
        * <code>
        * $fooBar = new \Thin\Proxy('Foo');
        * echo $fooBar->protectedProperty; // 'baz';
        * </code>
        *
        * @param string $name
        * @return mixed
        */
        public function __get($name)
        {
            $prop = $this->reflection->getProperty($name);
            $prop->setAccessible(true);
            return $prop->getValue($this->instance);
        }

        /**
        * Magic method __set
        *
        * Allows you to set the value of any property inside of the proxied class.
        *
        * <code>
        * $fooBar = new \Thin\Proxy('Foo');
        * echo $fooBar->privateProperty; // 'bar';
        * $fooBar->privateProperty = 'test2';
        * echo $fooBar->privateProperty; // test2
        * </code>
        *
        * @param type $name
        * @param type $value
        * @return type
        */
        public function __set($name, $value)
        {
            $prop = $this->reflection->getProperty($name);
            $prop->setAccessible(true);
            return $prop->setValue($this->instance, $value);
        }
    }
