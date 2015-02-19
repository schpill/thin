<?php
    namespace Thin;

    set_time_limit(0);

    class Cron
    {
        private $db;

        public function __construct($db = null)
        {
            $this->db = is_null() ? rdb('core', 'cron') : $db;
        }

        public static function instance($db = null)
        {
            $key    = sha1('Cron' . date('dmY'));
            $has    = Instance::has('Cron', $key);

            if (true === $has) {
                return Instance::get('Cron', $key);
            } else {
                return Instance::make('Cron', $key, with(new self($db)));
            }
        }

        public function queue($action, $data = [], $date = 0, $controller = 'task', $module = 'cron')
        {
            $date = 1 > $date ? time() : $date;

            return $this->db
            ->create()
            ->setModule($module)
            ->setController($controller)
            ->setAction($action)
            ->setData(serialize($data))
            ->setDate($date)
            ->save();
        }

        public function unqueue($action, $date = 0, $controller = 'task', $module = 'cron')
        {
            $date = 1 > $date ? time() : $date;

            $res = $this->db
            ->where("module = $module")
            ->where("controller = $controller")
            ->where("action = $action")
            ->where("date <= $date")
            ->exec(true);

            if (0 < $res->count()) {
                $res->delete();
            }

            return $this;
        }

        public function unqueueCron($cron)
        {
            $cron->delete();

            return $this;
        }

        public function executeCron($cron, $unqueue = true)
        {
            $date = $cron->getDate();

            if ($date <= time()) {
                $_REQUEST = unserialize($cron->getData());
                context()->dispatch($cron);

                return true === $unqueue ? $this->unqueueCron($cron) : $this;
            }
        }

        public function flush($unqueue = true)
        {
            $crons = $this->db->where('date <= ' . time())->exec(true);

            if (0 < $crons->count()) {
                foreach ($crons->rows() as $cron) {
                    $this->executeCron($cron, $unqueue);
                }
            }

            return $this;
        }
    }
