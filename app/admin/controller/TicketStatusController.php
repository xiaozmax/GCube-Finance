<?php


namespace app\admin\controller;

/**
 * @title 后台工单状态
 * @description 接口说明
 */
class TicketStatusController extends AdminBaseController
{
    protected $default = ["Open", "Answered", "CustomerReply", "Closed"];
    public function add()
    {
        $params = input("post.");
        $rule = ["title" => "require", "order" => "number"];
        $msg = ["name.require" => "标题不能为空", "order.number" => "排序只能是数字"];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $title = trim($params["title"]);
        $exist = \think\Db::name("ticket_status")->where("title")->find();
        if (!empty($exist)) {
            $result["status"] = 200;
            $result["msg"] = "该状态已存在";
            return jsonrule($result);
        }
        $data["title"] = $title;
        $data["color"] = $params["color"] ?: "";
        $data["show_active"] = !empty($params["show_active"]) ? 1 : 0;
        $data["show_await"] = !empty($params["show_await"]) ? 1 : 0;
        $data["auto_close"] = !empty($params["auto_close"]) ? 1 : 0;
        $data["order"] = isset($params["order"]) ? intval($params["order"]) : 0;
        $r = \think\Db::name("ticket_status")->insertGetId($data);
        if ($r) {
            active_log("添加工单状态成功 - 标题:" . $data["title"] . " - ID:" . $r);
        }
        $result["status"] = 200;
        $result["msg"] = "添加成功";
        return jsonrule($result);
    }
    public function save()
    {
        $params = input("post.");
        $rule = ["title" => "require", "order" => "number"];
        $msg = ["name.require" => "标题不能为空", "order.number" => "排序只能是数字"];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $id = intval($params["id"]);
        if ($id <= 5) {
            unset($params["title"]);
            unset($params["color"]);
        }
        $info = \think\Db::name("ticket_status")->where("id", $id)->find();
        if (empty($info)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return jsonrule($result);
        }
        $data = [];
        $des = "";
        if (!empty($params["title"])) {
            $data["title"] = $params["title"] ?: "";
            if ($data["title"] != $info["title"]) {
                $des .= "状态标题由“" . $info["title"] . "”改为“" . $data["title"] . "”，";
            }
        }
        if (!empty($params["color"])) {
            $data["color"] = $params["color"] ?: "";
            if ($data["color"] != $info["color"]) {
                $des .= "状态染色由“" . $info["color"] . "”改为“" . $data["color"] . "”，";
            }
        }
        $arr = ["show_active", "show_await", "auto_close"];
        foreach ($arr as $v) {
            if (isset($params[$v])) {
                $data[$v] = !empty($params[$v]) ? 1 : 0;
            }
        }
        if (!empty($params["order"])) {
            $data["order"] = intval($params["order"]);
            if ($data["order"] != $info["order"]) {
                $des .= "排序由“" . $info["order"] . "”改为“" . $data["order"] . "”，";
            }
        }
        if (!empty($data)) {
            $r = \think\Db::name("ticket_status")->where("id", $id)->update($data);
        }
        if (empty($des)) {
            $des .= "什么都没有修改";
        }
        active_log(sprintf($this->lang["TicketDepartmentStatus_admin_save"], $id, $info["title"], $des));
        $result["status"] = 200;
        $result["msg"] = "修改成功";
        return jsonrule($result);
    }
    public function delete()
    {
        $id = intval(input("post.id"));
        if ($id <= 5) {
            $result["status"] = 401;
            $result["msg"] = lang("TICKET_STATUS_NOT_DELETE");
            return jsonrule($result);
        }
        $info = \think\Db::name("ticket_status")->field("id,title")->where("id", $id)->find();
        if (empty($info)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return jsonrule($result);
        }
        $r = \think\Db::name("ticket_status")->where("id", $id)->delete();
        if ($r) {
            \think\Db::name("ticket")->where("status", $info["id"])->update(["status" => 4]);
            active_log("删除工单状态成功 - 标题:" . $info["title"] . " - ID:" . $id);
        }
        $result["status"] = 200;
        $result["msg"] = "删除成功";
        return jsonrule($result);
    }
    public function getList()
    {
        $data = \think\Db::name("ticket_status")->order("order", "asc")->select();
        $result["status"] = 200;
        $result["data"] = $data;
        return jsonrule($result);
    }
    public function getDetail($id)
    {
        $id = intval($id);
        $data = \think\Db::name("ticket_status")->where("id", $id)->find();
        if (empty($data)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return jsonrule($result);
        }
        $result["status"] = 200;
        $result["data"] = $data;
        return jsonrule($result);
    }
}

?>