<?php
    /**
     * Event class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Event
    {
        public static $events   = array();
        public static $queued   = array();
        public static $flushers = array();

        public static function listeners($event)
        {
            return Arrays::exists($event, static::$events);
        }

        public static function listen($event, $callback, $once = false)
        {
           return static::set($event, $callback, $once);
        }

        public static function set($event, $callback, $once = false)
        {
            static::$events[$event][] = array($callback, $once);
        }

        public static function override($event, $callback)
        {
            static::clear($event);
            static::set($event, $callback);
        }

        public static function queue($queue, $key, $data = array())
        {
            static::$queued[$queue][$key] = $data;
        }

        public static function flusher($queue, $callback)
        {
            static::$flushers[$queue][] = $callback;
        }

        public static function clear($event)
        {
            unset(static::$events[$event]);
        }

        public static function first($event, $parameters = array())
        {
            return head(static::run($event, $parameters));
        }

        public static function until($event, $parameters = array())
        {
            return static::run($event, $parameters, true);
        }

        public static function flush($queue)
        {
            foreach (static::$flushers[$queue] as $flusher) {
                if (!Arrays::exists($queue, static::$queued)) {
                    continue;
                }

                foreach (static::$queued[$queue] as $key => $payload) {
                    array_unshift($payload, $key);
                    call_user_func_array($flusher, $payload);
                }
            }
        }

        public static function fire($event, $parameters = array())
        {
            return static::run($event, $parameters);
        }

        public static function run($events, $parameters = array(), $halt = false)
        {
            $responses = array();

            $parameters = (array) $parameters;
            foreach ((array) $events as $event) {
                if (true === static::listeners($event)) {
                    foreach (static::$events[$event] as $callbackPack) {
                        list($callback, $once) = $callbackPack;
                        $response = call_user_func_array($callback, $parameters);
                        if ($halt && !is_null($response)) {
                            return $response;
                        }
                        $responses[] = $response;
                        if (true === $once) {
                            if (false !== $index = array_search($callbackPack, static::$events[$event], true)) {
                                unset(static::$events[$event][$index]);
                            }
                        }
                    }
                } else {
                    $error = (
                        strstr($event, '.init')
                        || strstr($event, '.start')
                        || strstr($event, '.done')
                        || strstr($event, '.stop')
                    ) ? false : true;
                    if (true === $error) {
                        throw new Exception("The event $event doesn't exist.");
                    }
                }
            }
            if (count($responses) == 1) {
                $responses = Arrays::first($responses);
            }
            return $halt ? null : $responses;
        }
    }
