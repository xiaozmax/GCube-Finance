<?php


namespace app\admin\controller;

/**
 * @title 客户等级
 * @description 接口说明：客户等级规则创建、编辑等
 */
class UserLevelController extends GetUserController
{
    public function getList()
    {
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $total = \think\Db::name("clients_level_rule")->count();
        $list = \think\Db::name("clients_level_rule")->field("id,level_name,expense,buy_num,login_times,last_login_times,renew_times,last_renew_times")->withAttr("expense", function ($value) {
            return json_decode($value, true);
        })->withAttr("expense", function ($value) {
            return json_decode($value, true);
        })->withAttr("buy_num", function ($value) {
            return json_decode($value, true);
        })->withAttr("login_times", function ($value) {
            return json_decode($value, true);
        })->withAttr("last_login_times", function ($value) {
            return json_decode($value, true);
        })->withAttr("renew_times", function ($value) {
            return json_decode($value, true);
        })->withAttr("last_renew_times", function ($value) {
            return json_decode($value, true);
        })->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        $data = ["total" => $total, "list" => $list];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getLevelPage()
    {
        $param = $this->request->param();
        $data = [];
        if (isset($param["id"])) {
            $id = intval($param["id"]);
            $tmp = \think\Db::name("clients_level_rule")->where("id", $id)->find();
            if (empty($tmp)) {
                return jsonrule(["status" => 400, "msg" => "规则不存在"]);
            }
            $tmp["expense"] = json_decode($tmp["expense"], true);
            $tmp["buy_num"] = json_decode($tmp["buy_num"], true);
            $tmp["login_times"] = json_decode($tmp["login_times"], true);
            $tmp["last_login_times"] = json_decode($tmp["last_login_times"], true);
            $tmp["renew_times"] = json_decode($tmp["renew_times"], true);
            $tmp["last_renew_times"] = json_decode($tmp["last_renew_times"], true);
        }
        $data["level_rule"] = $tmp ?: [];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postLevel()
    {
        $param = $this->request->param();
        $validate = new \app\admin\validate\UserLevelValidate();
        if (isset($param["id"])) {
            $id = intval($param["id"]);
            if (!$validate->scene("edit")->check($param)) {
                return jsonrule(["status" => 400, "msg" => $validate->getError()]);
            }
        } else {
            if (!$validate->scene("create")->check($param)) {
                return jsonrule(["status" => 400, "msg" => $validate->getError()]);
            }
        }
        $expense = ["min" => floatval($param["expense_min"]), "max" => floatval($param["expense_max"])];
        $buy_num = ["min" => intval($param["buy_num_min"]), "max" => intval($param["buy_num_max"])];
        $login_times = ["min" => intval($param["login_times_min"]), "max" => intval($param["login_times_max"])];
        $last_login_times = ["min" => intval($param["last_login_times_min"]), "max" => intval($param["last_login_times_max"]), "day" => intval($param["last_login_times_day"])];
        $renew_times = ["min" => intval($param["renew_times_min"]), "max" => intval($param["renew_times_max"])];
        $last_renew_times = ["min" => intval($param["last_renew_times_min"]), "max" => intval($param["last_renew_times_max"]), "day" => intval($param["last_renew_times_day"])];
        $insert = ["level_name" => $param["level_name"], "expense" => json_encode($expense), "buy_num" => json_encode($buy_num), "login_times" => json_encode($login_times), "last_login_times" => json_encode($last_login_times), "renew_times" => json_encode($renew_times), "last_renew_times" => json_encode($last_renew_times)];
        if ($id) {
            $insert["update_time"] = time();
            $tmp = \think\Db::name("clients_level_rule")->where("id", $id)->update($insert);
        } else {
            $insert["create_time"] = time();
            $tmp = \think\Db::name("clients_level_rule")->insertGetId($insert);
        }
        if ($tmp) {
            return jsonrule(["status" => 200, "msg" => lang("EDIT SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("EDIT FAIL")]);
    }
    public function deleteLevel()
    {
        $param = $this->request->param();
        $id = intval($param["id"]);
        $tmp = \think\Db::name("clients_level_rule")->where("id", $id)->find();
        if (empty($tmp)) {
            return jsonrule(["status" => 400, "msg" => "规则不存在"]);
        }
        \think\Db::name("clients_level_rule")->where("id", $id)->delete();
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
}

?>