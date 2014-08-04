<?php
    namespace Thin\Provider;

    use Thin\Provider;
    use Thin\Exception;
    use Thin\Arrays;
    use Thin\File;
    use Thin\Inflector;

    class Plugin extends Provider
    {
        public function register()
        {
            $args = func_get_args();
            if (count($args) < 1 || count($args) > 1) {
                throw new Exception("You need to provide a plugin to register.");
            }
            $plugin = reset($args);
            $this->type = 'plugins';
            $this->file = APPLICATION_PATH . DS . 'plugins' . DS . ucfirst(Inflector::lower($plugin)) . DS . ucfirst(Inflector::lower($plugin)) . '.php';
            $this->class = 'ThinPlugin\\' . ucfirst(Inflector::lower($plugin)) . '\\' . ucfirst(Inflector::lower($plugin));
            return $this->factory();
        }
    }
