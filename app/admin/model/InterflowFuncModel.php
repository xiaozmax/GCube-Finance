<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

namespace app\admin\model;

class InterflowFuncModel extends \think\Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = "create_time";
    protected $dateFormat = "Y/m/d H:i";
    protected $readonly = ["create_time"];
    public function getAllFunc()
    {
        $all_func = $this->column("keyword_id", "func_id");
        return $all_func ?: [];
    }
}

?>