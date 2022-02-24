<?php

/**
 * @Author: Administrator
 * @Date:   2021-11-03 16:19:54
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-02-24 15:10:51
 */
declare (strict_types = 1);
namespace lim;

use \lim\Dbs as Db;

/**
 *数据库表处理类
 */
class Table
{
    /**
     * 创建数据表
     * @Author   Wayren
     * @DateTime 2022-02-24T13:11:11+0800
     * @return   [type]                   [description]
     */
    public function create()
    {
        $this->dir(function ($table, $data) {

            if ($has = Db::query("SHOW TABLES LIKE '{$table}'")->fetch()) {
                wlog('已有数据表:' . $table);
                return;
            }

            $data = $data['create'];

            $sql = "CREATE TABLE `$table` ( ";

            $AUTO_INCREMENT = '';

            foreach ($data['field'] as $col => $type) {
                $sql .= "`$col` $type ,";
                if (str_contains(strtoupper($type), 'AUTO_INCREMENT')) {
                    $AUTO_INCREMENT = "AUTO_INCREMENT=1";
                    // wlog('存在自增');
                }
            }

            if (isset($data['key'])) {
                foreach ($data['key'] as $col => $index) {
                    $sql .= " $index ,";
                }
            }

            $engine  = $data['engine'] ?? 'InnoDB';
            $charset = $data['charset'] ?? 'utf8mb4';
            $collate = $data['collate'] ?? 'utf8mb4_bin';
            $comment = $data['comment'] ?? '';

            $sql = substr($sql, 0, -1) . " ) ENGINE={$engine} $AUTO_INCREMENT DEFAULT CHARSET={$charset} COLLATE={$collate} COMMENT='{$comment}';";

            Db::pdo('default')->exec($sql);
            wlog('新增数据表:' . $table);

        });
    }

    /**
     * 检查数据表
     * @Author   Wayren
     * @DateTime 2022-02-24T13:11:49+0800
     * @return   [type]                   [description]
     */
    public function check()
    {
        $this->dir(function ($table, $data) {
            $oldTable = Db::query("SHOW  COLUMNS FROM {$table}")->fetchAll();
            $oldCols  = array_column($oldTable, 'Field');
            foreach ($data['create']['field'] as $col => $type) {
                if (!in_array($col, $oldCols) && !in_array($col,array_keys($data['change']??[]))) {
                    $sql = "alter table $table add `$col` $type;";
                    Db::pdo('default')->exec($sql);
                    wlog('新增字段:' . $table . '->' . $col);
                }
            }
        });
    }

    public function change()
    {
        $this->dir(function ($table, $data) {
            if (isset($data['change'])) {
                foreach ($data['change'] as $old => $res) {

                    if (!$has = Db::query("SHOW  COLUMNS FROM {$table} WHERE field = '{$old}'")->fetch()) {
                        wlog('字段:' . $old . '不存在');
                        continue;
                    }

                    $len = count($res);

                    if ($len==1) {
                        $new = array_shift($res);
                        $type = $data['create']['field'][$old] ?? null;
                    } else {
                        list($new,$type) = $res;
                    }

                    if (!$type) {
                        wlog('不存在对应的字段属性');
                        continue;
                    }

                    $sql = "ALTER TABLE $table CHANGE `$old` `$new` $type";
                    Db::pdo('default')->exec($sql);
                    wlog('修改字段:' . $table . ' [ ' . $old . ' ] => [ ' . $new . ' ]');
                }
            }
        });
    }

    public function dir($fn)
    {
        $dir = ROOT . 'app/database';
        if (is_dir($dir) && $handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                $path = $dir . '/' . $file;
                if (($file == ".") || ($file == "..") || is_dir($path)) {
                    continue;
                }
                list($table, $ext) = explode('.', $file);
                $data              = include $path;
                $fn($table, $data);
            }
            closedir($handle);
        }
    }

    public function _modifyCols($table, $col, $type)
    {
        $res = Db::query("SHOW  COLUMNS FROM $table WHERE Field = '{$col}'")->fetch();
        print_r($res);
        wlog($type);

    }

    public function _addCols($table, $col, $type)
    {
        if ($has = Db::query("SHOW  COLUMNS FROM {$table} WHERE field = '{$col}'")->fetch()) {
            wlog('已有字段:' . $table . '->' . $col);
            return;
        }

        $sql = "alter table $table add $col $type;";

        Db::pdo('default')->exec($sql);
        wlog('新增字段:' . $table . '->' . $col);
    }
}
