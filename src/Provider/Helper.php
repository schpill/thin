<?php
    namespace Thin\Provider;

    use Thin\Provider;
    use Thin\Exception;
    use Thin\Arrays;
    use Thin\File;
    use Thin\Inflector;

    class Helper extends Provider
    {
        public function register()
        {
            $args = func_get_args();
            if (count($args) < 1 || count($args) > 1) {
                throw new Exception("You need to provide a helper to register.");
            }
            $helper = reset($args);
            $this->type = 'helpers';
            $this->file = APPLICATION_PATH . DS . 'helpers' . DS . ucfirst(Inflector::lower($helper)) . DS . ucfirst(Inflector::lower($helper)) . '.php';
            $this->class = 'ThinHelper\\' . ucfirst(Inflector::lower($helper)) . '\\' . ucfirst(Inflector::lower($helper));
            return $this->factory();
        }
    }
