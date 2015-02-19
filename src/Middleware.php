<?php
    namespace Thin;

    abstract class Middleware
    {
        /**
         * @var \Thin\Container Reference to the primary application instance
         */
        protected $app;

        /**
         * @var mixed Reference to the next downstream middleware
         */
        protected $next;

        /**
         * Set application
         *
         * This method injects the primary Thin\Container instance into
         * this middleware.
         *
         * @param  \Thin\Container $application
         */
        final public function setApplication($application)
        {
            $this->app = $application;
        }

        /**
         * Get application
         *
         * This method retrieves the application previously injected
         * into this middleware.
         *
         * @return \Thin\Container
         */
        final public function getApplication()
        {
            return $this->app;
        }

        /**
         * Set next middleware
         *
         * This method injects the next downstream middleware into
         * this middleware so that it may optionally be called
         * when appropriate.
         *
         * @param \Thin\Middleware
         */
        final public function setNextMiddleware($nextMiddleware)
        {
            $this->next = $nextMiddleware;
        }

        /**
         * Get next middleware
         *
         * This method retrieves the next downstream middleware
         * previously injected into this middleware.
         *
         * @return \Thin\\Middleware
         */
        final public function getNextMiddleware()
        {
            return $this->next;
        }

        /**
         * Call
         *
         * Perform actions specific to this middleware and optionally
         * call the next downstream middleware.
         */
        abstract public function call();
    }
