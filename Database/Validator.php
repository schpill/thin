<?php
    namespace Thin\Database;

    use Thin\Arrays;
    use Thin\Database;
    use Thin\Instance;

    class Validator
    {
        private $model;

        public function __construct(Database $model)
        {
            $this->model = $model;
        }

        public static function instance(Database $model)
        {
            $key = sha1($model->database . $model->table);
            $has = Instance::has('DatabaseValidator', $key);
            if (true === $has) {
                return Instance::get('DatabaseValidator', $key);
            } else {
                return Instance::make('DatabaseValidator', $key, with(new self($model)));
            }
        }

        public function unique($field, $value)
        {
            $res = $this->model->where("$field = $value")->exec();
            return count($res) > 0 ? false : true;
        }

        public function check($condition, $newValue = null)
        {
            $condition  = repl('NOT LIKE', 'NOTLIKE', $condition);
            $condition  = repl('NOT IN', 'NOTIN', $condition);
            list($val, $op, $value) = explode(' ', $condition, 3);
            $check = $this->compare($val, $op, $value);
            return false === $check
            ? null === $newValue
                ? false
                : is_callable($newValue)
                    ? call_user_func_array($newValue, array($val))
                    : $newValue
            : true;
        }

        private function compare($comp, $op, $value)
        {
            $res = false;
            if (isset($comp)) {
                $comp   = Inflector::lower($comp);
                $value  = Inflector::lower($value);
                switch ($op) {
                    case '=':
                        $res = sha1($comp) == sha1($value);
                        break;
                    case '>=':
                        $res = $comp >= $value;
                        break;
                    case '>':
                        $res = $comp > $value;
                        break;
                    case '<':
                        $res = $comp < $value;
                        break;
                    case '<=':
                        $res = $comp <= $value;
                        break;
                    case '<>':
                    case '!=':
                        $res = sha1($comp) != sha1($value);
                        break;
                    case 'LIKE':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (strstr($comp, $value)) {
                            $res = true;
                        }
                        break;
                    case 'NOTLIKE':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (!strstr($comp, $value)) {
                            $res = true;
                        }
                        break;
                    case 'LIKE START':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        $res = (substr($comp, 0, strlen($value)) === $value);
                        break;
                    case 'LIKE END':
                        $value = repl("'", '', $value);
                        $value = repl('%', '', $value);
                        if (!strlen($comp)) {
                            $res = true;
                        }
                        $res = (substr($comp, -strlen($value)) === $value);
                        break;
                    case 'IN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        $res = Arrays::in($comp, $tabValues);
                        break;
                    case 'NOTIN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        $res = !Arrays::in($comp, $tabValues);
                        break;
                }
            }
            return $res;
        }
    }
