<?php


namespace app\admin\controller;

/**
 * @title 后台取消请求页面
 * @description 接口说明
 */
class CancelRequestController extends AdminBaseController
{
    public function getList()
    {
        $data = $this->request->param();
        $order = isset($data["order"]) ? trim($data["order"]) : "c.id";
        $sort = isset($data["sort"]) ? trim($data["sort"]) : "DESC";
        $data = \think\Db::name("cancel_requests")->field("c.id,c.relid,c.type,c.reason,cl.username,h.id as hostid,h.uid,h.domainstatus,h.nextduedate,\r\n                    h.auto_terminate_end_cycle, h.auto_terminate_reason,p.name as productname, \r\n                    g.name as groupname")->alias("c")->leftJoin("host h", "h.id=c.relid")->leftJoin("clients cl", "cl.id=h.uid")->leftJoin("products p", "p.id=h.productid")->leftJoin("product_groups g", "g.id=p.gid")->order($order, $sort)->select()->toArray();
        foreach ($data as $key => $val) {
            if ($val["domainstatus"] == "Cancelled") {
                unset($data[$key]);
            } else {
                $product_desc = "";
                $product_desc .= $val["groupname"] . " - " . $val["productname"];
                if ($val["type"] == "Immediate") {
                    $type_desc = "立即停用";
                } else {
                    $type_desc = "到期时停用" . PHP_EOL . "(" . date("Y-m-d", $val["nextduedate"]) . ")";
                }
                $val["product_desc"] = $product_desc;
                $val["type_desc"] = $type_desc;
            }
        }
        $data = $data ?: [];
        return jsonrule(["status" => 200, "data" => $data]);
    }
    public function deleteList(\think\Request $request)
    {
        $id = intval($request->id);
        if (!empty($id)) {
            \think\Db::name("cancel_requests")->delete($id);
            return jsonrule(["status" => 200, "msg" => "删除成功"]);
        }
    }
    public function getCancelList()
    {
        $data = \think\Db::name("cancel_requests")->field("c.id,c.relid,c.type,c.reason,cl.username,h.id as hostid,h.uid,h.domainstatus,h.nextduedate,\r\n                h.auto_terminate_end_cycle, h.auto_terminate_reason,p.name as productname, \r\n                g.name as groupname")->alias("c")->leftJoin("host h", "h.id=c.relid")->leftJoin("clients cl", "cl.id=h.uid")->leftJoin("products p", "p.id=h.productid")->leftJoin("product_groups g", "g.id=p.gid")->select()->toArray();
        foreach ($data as $key => $val) {
            if ($val["domainstatus"] != "Cancelled") {
                unset($data[$key]);
            } else {
                $product_desc = "";
                $product_desc .= $val["groupname"] . " - " . $val["productname"];
                if ($val["type"] == "Immediate") {
                    $type_desc = "立即停用";
                } else {
                    $type_desc = "到期时停用" . PHP_EOL . "(" . date("Y-m-d", $val["nextduedate"]) . ")";
                }
                $val["product_desc"] = $product_desc;
                $val["type_desc"] = $type_desc;
            }
        }
        $data = $data ?: [];
        return jsonrule(["status" => 200, "data" => $data]);
    }
}

?>