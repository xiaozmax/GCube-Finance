<?php

namespace app\admin\model;

class InterflowKeywordModel extends \think\Model
{
    protected $autoWriteTimestamp = true;
    protected $updateTime = "update_time";
    protected $createTime = "create_time";
    protected $dateFormat = "Y/m/d H:i";
    protected $readonly = ["create_time"];
    public function getAllKeyword($in = true, $param = ["type", ["general_execute", "system_execute"]])
    {
        $in ? $query = $this->whereIn($param[0], $param[1]) : ($query = $this->whereNotIn($param[0], $param[1]));
        $all_kw = $query->where("status", "on")->column("matching_text,matching_text_preg,func_id,keyword,title,system_bind", "id");
        return $all_kw ?: [];
    }
    public function getHostFromIP($ip, $accounts)
    {
        $uid = \think\Db::name("interflow_bots")->alias("a")->leftJoin("interflow_clients b", "a.i_type = b.i_type")->where("a.port", $accounts["port"])->where("b.i_account", $accounts["account"])->value("b.uid");
        if (empty($uid)) {
            return false;
        }
        $host = \think\Db::name("host")->field("id,serverid")->where("uid", $uid)->where("dedicatedip", $ip)->order("id", "desc")->find();
        return $host ?: false;
    }
    public function getModuleFromSID($server_id)
    {
        if (empty($server_id)) {
            return false;
        }
        $servers = \think\Db::name("servers")->field("type,server_type")->where("id", $server_id)->find();
        return $servers["type"] ?: $servers["server_type"];
    }
    public function getModuleFromIp($ip, $accounts)
    {
        $host = $this->getHostFromIP($ip, $accounts);
        if (!empty($host)) {
            return $this->getModuleFromSID($host["serverid"]);
        }
        return false;
    }
    public function getModuleFromFuncPool($func_pool, $intent = NULL, $module)
    {
        $query = $this->alias("a")->leftJoin("interflow_func b", "a.id = b.func_id")->field("a.id,a.keyword,b.func_assembly")->where("a.status", "on");
        empty($intent);
        empty($intent) ? NULL : $query->where("a.keyword", $intent);
        empty($func_pool);
        empty($func_pool) ? NULL : $query->whereIn("a.id", $func_pool);
        $query->withAttr("func_assembly", function ($v) {
            return strtolower($v);
        });
        $Modules = $query->select();
        $data = json_decode(json_encode($Modules), true) ?: [];
        $need_check = false;
        if (!empty($data[0]["func_assembly"])) {
            $need_check = true;
        }
        $keyword = $data[0]["keyword"];
        $intent_func_module = array_filter($data, function ($v) use($module) {
            $now_func_module = explode("::", $v["func_assembly"]);
            if (in_array($module, $now_func_module)) {
                return $v;
            }
        });
        return [$intent_func_module, $need_check, $keyword];
    }
}

?>
