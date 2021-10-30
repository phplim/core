<?
declare (strict_types = 1);

return [
    //公共规则
    'rules'   => [
        'id'                   => 'ID@string',
        'pid'                  => '平台ID@int',
        'bid'                  => '头榜ID@string',
    ],

    //方法
    'methods' => [
        'tt' => [
            'vars' => 'tb',
            'must'=>'id',
            'rule'=>['ss'=>'等待@int','other'=>'aa@any']
        ],
    ],

];
