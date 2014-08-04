<?php
    namespace Thin;

    abstract class Provider
    {
        protected $app;
        protected $type;
        protected $class;
        protected $file;

        public function __construct()
        {
            $this->app = app();
        }

        /**
         * Bootstrap the application events.
         *
         * @return void
         */
        public function init() {}

        /**
         * Register the service provider.
         *
         * @return void
         */
        abstract public function register();

        public function factory()
        {
            if (File::exists($this->file)) {
                require_once $this->file;
                $instance = new $this->class;
                $methods = get_class_methods($this->class);

                $tab = explode('\\', get_class($instance));
                $item = Inflector::lower(Arrays::last($tab));

                if (Arrays::in('init', $methods)) {
                    $instance->init();
                }
                $this->app->bindShared($this->type . '.' . $item, function($app) use ($instance) {
                    return $instance;
                });
                return $this;
            } else {
                throw new Exception("The file '$file' does not exist.");
            }
        }

        /**
         * Get the services provided by the provider.
         *
         * @return array
         */
        public function services()
        {
            return array();
        }
    }
