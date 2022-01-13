<?
declare (strict_types = 1);
namespace lim;

class DataHandle
{
    public function __construct(public $data)
    {

    }

    public function where($where = [])
    {
        $this->where = $where;
        return $this;
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

            if (isset($this->where)) {
                foreach ($this->where as $key => $value) {
                    if ($v[$key] != $value) {
                        continue 2;
                    }
                }
            }

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

    public function replace($replace = [])
    {
        if (!$replace) {
            return $this;
        }

        foreach ($this->data as $k => $v) {
            foreach ($replace as $key => $value) {
                if (isset($v[$key])) {
                    $v[$key] = $value[$v[$key]];
                }
            }
            $this->data[$k] = $v;
        }

        return $this;
    }

    public function append($append = [])
    {
        if (!$append) {
            return $this;
        }

        foreach ($this->data as $k => $v) {

            foreach ($append as $key => $value) {
                if (isset($v[$key])) {
                    $v[key($value)] = end($value)[$v[$key]];
                }
            }

            $this->data[$k] = $v;
        }

        return $this;
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

        if ($opt[2] ?? null) {
            $ret = array_values($ret??[]);
        }
    }

    public function tree($id = 0, &$tree = [])
    {
        foreach ($this->data as $key => $v) {

            if ($v['id'] == $id) {
                unset($this->data[$key]);
                $tree[$v['id']] = $v;
                // wlog($v['id'] . ' 1 ' . $v['name'] . ' ' . $id);
                continue;
            }

            if (($v['pid'] ?? null) == $id) {
                unset($this->data[$key]);
                $tree[$v['pid']]['sub'][$v['id']] = $v;
                tree($this->data, $v['id'], $tree[$v['pid']]['sub']);
                continue;
            }
        }

        $tree = array_values($tree);
    }
}
