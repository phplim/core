<?php
declare (strict_types = 1);

namespace lim;

const LIM_MSG = [
    'must'   => ' 是必须的|300',
    'int'    => ' 必须为整数|301',
    'string' => ' 必须为字符串|302',
    'bool'   => ' 必须为布尔值|303',
    'unique' => ' 重复|304',
    'time'   => ' 不是有效的时间|305',
    'date'   => '不是有效的日期|306',
    'has'    => '不存在|307',
    'eq'     => '错误|308',
    'in'     => '非法|309',
    'object' => '必须为对象|310',
    'array'  => '必须为数组或对象|311',
    'len'    => '长度错误|312',
];

class Rule
{
    public $code = 200, $msg = '请求成功', $data;

    public function __construct($rule, $data)
    {
        if (!isset($rule)) {
            return;
        }

        $rule = is_string($rule) ? [$rule] : $rule;
        $data = $this->data = is_string($data) ? [$data] : $data;

        foreach ($rule as $key => $value) {
            $ruler = $this->parseRule($value);
            foreach ($ruler['rule'] as $k => $v) {

                if ($k != 'must' && !isset($data[$key])) {
                    continue;
                }

                $this->check($key, $ruler['name'], $k, $v, $data[$key] ?? null);

                if ($this->code !== 200) {
                    break;
                }
            }
        }
    }

    public function check($key, $name, $act, $opt, $value)
    {

        $ret = match($act) {
            'string'         => is_string($value),
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN) != null,
            'int', 'integer' => is_numeric($value),
            'has', 'must'    => isset($value),
            'unique' => db::unique($key, $value, $opt),
            'time'   => strtotime($value),
            'date'   => strtotime(date('Y-m-d H:i:s', (int) strtotime($value))) === strtotime($value),
            'eq'     => $value == $opt,
            'in'     => in_array($value, explode(',', $opt)),
            'object' => substr(json_encode($value), 0, 1) == '{',
            'array'  => is_array($value),
            'len'    => $this->len($value, $opt),
            'float'  => filter_var($value, FILTER_VALIDATE_FLOAT),
            default  => true
        };

        if (!$ret) {
            list($msg, $code) = explode('|', LIM_MSG[$act]);
            switch ($act) {
                case 'in':
                    $msg = '只能为{' . $opt . '}中一个';
                    break;
            }
            $this->code = (int) $code;
            $this->msg  = (empty($name) ? $key : $name) . $msg;
        }
    }


    public function len($value = '', $opt = '')
    {
        list($min, $max) = explode(',', $opt);
        $len             = strlen((string) $value);
        return ($len >= $min && $len <= $max);
    }

    public function parseRule($rule)
    {
        list($name, $rule) = explode('@', $rule);
        $rule              = explode('|', $rule);
        foreach ($rule as $k => $value) {
            $t           = explode(':', $value);
            $rule[$t[0]] = $t[1] ?? null;
            unset($rule[$k]);
        }
        return ['name' => $name, 'rule' => $rule];
        array_push($rules, ['key' => $key, 'name' => $name, 'rule' => $rule]);
    }

    function break () {
        if ($this->code !== 200) {
            unset($this->data);
            exit(json_encode($this, 256));
        }
    }

    public function code()
    {
        return $this->code;
    }

    public static function m($value = '', $code = 300)
    {
        exit(json_encode(['code' => (int) $code, 'msg' => $value], 256));
    }

}
