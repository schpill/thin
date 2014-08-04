<?php
    namespace Thin\Provider;

    use Thin\Provider;
    use Thin\Exception;
    use Thin\Arrays;
    use Thin\File;
    use Thin\Inflector;

    class Service extends Provider
    {
        public function register()
        {
            $args = func_get_args();
            if (count($args) < 1 || count($args) > 1) {
                throw new Exception("You need to provide a service to register.");
            }
            $service = reset($args);
            $this->type = 'services';
            $this->file = APPLICATION_PATH . DS . 'services' . DS . ucfirst(Inflector::lower($service)) . DS . ucfirst(Inflector::lower($service)) . '.php';
            $this->class = 'ThinService\\' . ucfirst(Inflector::lower($service)) . '\\' . ucfirst(Inflector::lower($service));
            return $this->factory();
        }
    }
