<?php
    namespace Thin\Provider;

    use Thin\Provider;
    use Thin\Exception;
    use Thin\Arrays;
    use Thin\File;
    use Thin\Inflector;

    class Form extends Provider
    {
        public function register()
        {
            $args = func_get_args();
            if (count($args) < 1 || count($args) > 1) {
                throw new Exception("You need to provide a form to register.");
            }
            $form = reset($args);
            $this->type = 'forms';
            $this->file = APPLICATION_PATH . DS . 'forms' . DS . ucfirst(Inflector::lower($form)) . DS . ucfirst(Inflector::lower($form)) . '.php';
            $this->class = 'ThinForm\\' . ucfirst(Inflector::lower($form)) . '\\' . ucfirst(Inflector::lower($form));
            return $this->factory();
        }
    }
