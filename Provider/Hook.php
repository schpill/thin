<?php
    namespace Thin\Provider;

    use Thin\Provider;
    use Thin\Exception;
    use Thin\Arrays;
    use Thin\File;
    use Thin\Inflector;

    class Hook extends Provider
    {
        public function register()
        {
            $args = func_get_args();
            if (count($args) < 1 || count($args) > 1) {
                throw new Exception("You need to provide a hook to register.");
            }
            $hook = reset($args);
            $this->type = 'hooks';
            $this->file = APPLICATION_PATH . DS . 'hooks' . DS . ucfirst(Inflector::lower($hook)) . DS . ucfirst(Inflector::lower($hook)) . '.php';
            $this->class = 'ThinHook\\' . ucfirst(Inflector::lower($hook)) . '\\' . ucfirst(Inflector::lower($hook));
            return $this->factory();
        }
    }
