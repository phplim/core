<?php
declare (strict_types = 1);
namespace lim;

/**
 *
 */
class Api
{

    public $data, $req;

    public function __construct($req)
    {
        $this->req  = $req;
        $this->data = $req->all;
    }

    public function check()
    {
        $path = ROOT . str_replace(['\\', VERSION], ['/', VERSION . '/rule'], $this->req->class) . '.php';

        // suc([$class,$path]);
        if (is_file($path)) {
            $rules = include $path;

            $rule = $rules['methods'][$this->req->method]['rule'] ?? []; //提取专有规则

            $must = $rules['methods'][$this->req->method]['must'] ?? []; //必选规则

            $vars = $rules['methods'][$this->req->method]['vars'] ?? []; //提取合法字段

            $vars = $vars == '*' ? array_keys($rules['rules']) : $vars; //如果字段为*则提取所有规则字段

            $vars = array_unique(array_merge($vars, $must, array_keys($rule))); //合法变量

            //过滤非法变量
            foreach ($this->data as $k => $v) {
                if (!in_array($k, $vars)) {
                    unset($this->data[$k]);
                }
            }

            //规则组合
            foreach ($vars as $var) {

                //提取公共规则
                if (isset($rules['rules'][$var])) {
                    $rule[$var] = $rules['rules'][$var];
                }

                //组合选规则
                if (in_array($var, $must)) {
                    $rule[$var] = str_replace('@', '@must|', $rule[$var]);
                }

            }

            rule($rule, $this->data)->break();
        }
        
        return $this;
    }

    public function auth()
    {
        return $this;
    }

    public function before()
    {
        return $this;
    }

    public function after()
    {
        // code...
    }

    public function insert()
    {
        suc(Db::table($this->table)->insert($this->data));
    }

    public function delete()
    {
        suc(Db::table($this->table)->delete($this->data));
    }

    public function update()
    {
        suc(Db::table($this->table)->update($this->data));
    }

    public function get()
    {
        suc(Db::table($this->table)->get($this->data));
    }

    public function select()
    {
        suc(Db::table($this->table)->select($this->data));
    }

}
