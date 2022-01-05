<?
declare (strict_types = 1);
namespace lim;

class DataHandle
{
    public function __construct(public $data)
    {

    }

    /**
     * 转化成KV数据 如果value 包含,则说明是多value
     * @Author   Wayren
     * @DateTime 2021-12-24T14:08:36+0800
     * @param    string                   $key   [description]
     * @param    string                   $value [description]
     * @return   [type]                          [description]
     */
    public function kv($key = '', $value = '')
    {
        if (!str_contains($value, ',')) {
            return array_column($this->data, $value, $key);
        }

        $cols = explode(',', $value);
        foreach ($this->data as $k => $v) {
            foreach ($cols as $col) {
                if (!isset($v[$col]) && $v[$col] !== null) {
                    continue;
                }
                $ret[$v[$key]][$col] = $v[$col];
            }
        }

        return $ret ?? [];
    }

    public function cols($cols = '')
    {
        $cols = explode(',', $cols);
        $res  = array_diff(array_keys(array_shift($this->data)), $cols);
        foreach ($this->data as $k => $v) {
            foreach ($res as $key) {
                unset($v[$key]);
            }
            $ret[] = $v;
        }
        return $ret ?? null;
    }

    public function each($fn = null, &$e = [])
    {
        foreach ($this->data as $k => $v) {
            if ($fn) {
                $e[] = $fn($v);
            }
        }
    }

    /**
     * 获取子数据
     * @Author   Wayren
     * @DateTime 2022-01-05T12:38:09+0800
     * @param    [type]                   $where   [条件]
     * @param    [type]                   $data    [数据项]
     * @param    array                    &$ret    [返回值]
     * @param    boolean                  $toArray [是否转数组]
     * @return   [type]                            [description]
     */
    public function sub($where, $data, &$ret = [], $opt = [])
    {
        // $opt = array_merge(['pid','id',false],$opt);

        foreach ($this->data as $k => $v) {

            if ($where($v)) {
                $ret[$v[$opt[1]]] = $data($v);
                unset($this->data[$k]);
                continue;
            }

            if (in_array($v[$opt[0]], array_keys($ret ?? []))) {
                $ret[$v[$opt[1]]] = $data($v);
                unset($this->data[$k]);
                continue;
            }
        }

        if ($opt[2]??null) {
            $ret = array_values($ret);
        }
    }
}
