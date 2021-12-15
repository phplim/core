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
        list($f, $m) = explode('.', $this->req->rule);

        if ($rule = Server::$extend['rule'][strtolower($f)][$m] ?? null) {
            
            $vars = array_keys($rule);
            //过滤非法参数
            foreach ($this->data as $k => $v) {
                if (!in_array($k, $vars)) {
                    unset($this->data[$k]);
                }
            }
            // suc($rule,$this->data);
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
        suc(Db::use($this->database??'default')->table($this->table)->insert($this->data));
    }

    public function delete()
    {
        suc(Db::use($this->database??'default')->table($this->table)->delete($this->data));
    }

    public function update()
    {
        suc(Db::use($this->database??'default')->table($this->table)->update($this->data));
    }

    public function get()
    {
        suc(Db::use($this->database??'default')->table($this->table)->get($this->data));
    }

    public function select()
    {
        suc(Db::use($this->database??'default')->table($this->table)->select($this->data));
    }

}
