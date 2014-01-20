<?php
    /**
     * Hook class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    class Hook
    {
        public function __construct()
        {
            $hooks = Utils::get('ThinHooks');
            if (null === $hooks) {
                $hooks = array();
                Utils::set('ThinHooks', $hooks);
            }
        }

        public function before($function, $action)
        {
            $hooks = Utils::get('ThinHooks');
            if (!Arrays::exists($function, $hooks)) {
                $hooks[$function] = array();
            }
            $hooks[$function]['before'] = $action;
            Utils::set('ThinHooks', $hooks);
            return $this;
        }

        public function after($function, $action)
        {
            $hooks = Utils::get('ThinHooks');
            if (!Arrays::exists($function, $hooks)) {
                $hooks[$function] = array();
            }
            $hooks[$function]['after'] = $action;
            Utils::set('after', $hooks);
            return $this;
        }

        public function run($function, array $params = array())
        {
            $hooks = Utils::get('ThinHooks');
            $res = null;
            if (Arrays::exists($function, $hooks)) {
                if (Arrays::exists('before', $hooks[$function])) {
                    $action = $hooks[$function]['before'];
                    if (is_callable($action, true, $before)) {
                        $res = $before();
                    }
                }
                if (null === $res) {
                    $res = '';
                }

                $res .= call_user_func_array($function, $params);

                if (Arrays::exists('after', $hooks[$function])) {
                    $action = $hooks[$function]['after'];
                    if (is_callable($action, true, $after)) {
                        $res .= $after();
                    }
                }

                return $res;
            } else {
                return call_user_func_array($function, $params);
            }
        }

        public function __call($method, $params)
        {
            return $this->run($method, $params);
        }
    }
