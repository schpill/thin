<?php
    namespace Thin;

    use Closure;
    use Dbjson\Dbjson as Db;
    use Jeremeamia\SuperClosure\SerializableClosure;

    class Task
    {
        public static function push(Closure $closure, $args = [], $when = 0)
        {
            $closure = serialize(new SerializableClosure($closure));

            $db = Db::instance('system', 'task');

            $new = $db->create([
                'closure'   => $closure,
                'args'      => $args,
                'when'      => $when
            ])->save();
        }

        public static function bulk(array $closures, $args = [], $when = 0)
        {
            if (count($closures)) {
                foreach ($closures as $closure) {
                    if ($closure instanceof Closure) {
                        static::push($closure, $args, $when);
                    }
                }
            }
        }

        public static function later($timestamp, Closure $closure, $args = [])
        {
            static::push($closure, $args, $timestamp);
        }

        public static function pushMethod($method, $args = [], $when = 0)
        {
            $db = Db::instance('system', 'task');

            list($class, $action) = explode('@', $method, 2);

            $new = $db->create([
                'class'     => $class,
                'action'    => $action,
                'args'      => $args,
                'when'      => $when
            ])->save();
        }

        public static function bulkMethod(array $methods, $args = [], $when = 0)
        {
            if (count($mvcs)) {
                foreach ($methods as $method) {
                    if (strstr($method, '@')) {
                        static::pushMethod($method, $args, $when);
                    }
                }
            }
        }

        public static function laterMethod($timestamp, $method, $args = [])
        {
            static::pushMethod($method, $args, $timestamp);
        }

        public static function listen()
        {
            set_time_limit(0);

            $db = Db::instance('system', 'task');

            $tasks = $db->where('when < ' . time())->exec();

            if (count($tasks)) {
                foreach ($tasks as $task) {
                    $isClosure = isAke($task, 'closure', false);

                    if (false === $isClosure) {
                        static::runMethod($task);
                    } else {
                        static::runClosure($task);
                    }
                }
            }
        }

        private static function runMethod($task)
        {
            $dbTask         = Db::instance('system', 'task');
            $dbTaskInstance = Db::instance('system', 'taskinstance');
            $dbTaskOver     = Db::instance('system', 'taskover');

            $inInstance     = $dbTaskInstance->where('task_id = ' . $task['id'])->first(true);

            if (empty($inInstance)) {
                $inInstance = $dbTaskInstance->create(['task_id' => $task['id']])->save();

                $class  = isAke($task, 'class', false);
                $action = isAke($task, 'action', false);
                $args   = isAke($task, 'args', []);

                if (false !== $class && false !== $action) {
                    $results = call_user_func_array([$class, $action], $args);
                    $inInstance->delete();

                    $dbTaskOver->create([
                        'task_id' => $task['id'],
                        'results' => $results
                    ])->save();

                    $dbTask->find($task['id'])->delete();
                }
            }
        }

        private static function runClosure($task)
        {
            $dbTask         = Db::instance('system', 'task');
            $dbTaskInstance = Db::instance('system', 'taskinstance');
            $dbTaskOver     = Db::instance('system', 'taskover');

            $inInstance     = $dbTaskInstance->where('task_id = ' . $task['id'])->first(true);

            if (empty($inInstance)) {
                $inInstance = $dbTaskInstance->create(['task_id' => $task['id']])->save();

                $closure    = isAke($task, 'closure', false);
                $args       = isAke($task, 'args', []);

                if (false !== $closure) {
                    $results = call_user_func_array(unserialize($closure), $args);
                    $inInstance->delete();

                    $dbTaskOver->create([
                        'task_id' => $task['id'],
                        'results' => $results
                    ])->save();

                    $dbTask->find($task['id'])->delete();
                }
            }
        }
    }
