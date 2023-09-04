<?php


namespace app\admin\controller;

/**
 * @title 后台工单部门
 * @description 接口说明
 */
class TicketDepartmentController extends AdminBaseController
{
    protected $custom_param_type = ["dropdown" => "下拉", "password" => "密码", "text" => "文本框", "tickbox" => "选项框", "textarea" => "文本域"];
    public function addPage()
    {
        $admin_list = \think\Db::name("user")->field("id,user_login,user_nickname")->select()->toArray();
        $zjmf_finance_api = \think\Db::name("zjmf_finance_api")->field("id,name")->where("status", 1)->select()->toArray();
        return jsonrule(["status" => 200, "data" => ["admin_list" => $admin_list, "zjmf_finance_api" => $zjmf_finance_api]]);
    }
    public function add()
    {
        $params = input("post.");
        $rule = ["name" => "require", "email" => "email"];
        $msg = ["name.require" => "部门名称不能为空", "email.email" => "邮件地址格式错误"];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $data["name"] = $params["name"];
        $data["description"] = $params["description"] ?: "";
        $data["email"] = $params["email"];
        $data["only_reg_client"] = !empty($params["only_reg_client"]) ? 1 : 0;
        $data["only_client_open"] = !empty($params["only_client_open"]) ? 1 : 0;
        $data["no_auto_reply"] = !empty($params["no_auto_reply"]) ? 1 : 0;
        $data["feedback_request"] = !empty($params["feedback_request"]) ? 1 : 0;
        $data["is_certifi"] = !empty($params["is_certifi"]) ? 1 : 0;
        $data["hidden"] = !empty($params["hidden"]) ? 1 : 0;
        $data["host"] = $params["host"] ?: "";
        $data["port"] = $params["port"] ?: "";
        $data["login"] = $params["login"] ?: "";
        $data["password"] = cmf_encrypt($params["password"]);
        $data["is_product_order"] = !empty($params["is_product_order"]) ? $params["is_product_order"] : 0;
        $data["is_open_auto_reply"] = !empty($params["is_open_auto_reply"]) ? $params["is_open_auto_reply"] : 0;
        $data["minutes"] = !empty($params["minutes"]) ? $params["minutes"] : 0;
        $data["time_type"] = !empty($params["time_type"]) ? $params["time_type"] : 0;
        $data["bz"] = !empty($params["bz"]) ? $params["bz"] : 0;
        $data["is_related_upstream"] = !empty($params["is_related_upstream"]) ? $params["is_related_upstream"] : 0;
        $max_order = \think\Db::name("ticket_department")->field("order")->order("order", "desc")->find();
        $data["order"] = (int) $max_order["order"] + 1;
        $r = \think\Db::name("ticket_department")->insertGetId($data);
        if ($r) {
            if (!empty($params["admins"]) && is_array($params["admins"])) {
                $params["admins"] = array_filter($params["admins"], function ($x) {
                    return is_numeric($x) && 0 < $x;
                });
                if (!empty($params["admins"])) {
                    $admins = \think\Db::name("user")->field("id")->whereIn("id", $params["admins"])->select()->toArray();
                    $insert = [];
                    foreach ($admins as $k => $v) {
                        $insert[] = ["admin_id" => $v["id"], "dptid" => $r];
                    }
                    if (!empty($insert)) {
                        \think\Db::name("ticket_department_admin")->insertAll($insert);
                    }
                }
            }
            if (!empty($params["upstreams"]) && is_array($params["upstreams"]) && $params["is_related_upstream"] == 1) {
                $insert = [];
                foreach ($params["upstreams"] as $k => $v) {
                    $insert[] = ["api_id" => $k, "upstream_dptid" => $v, "dptid" => $r];
                }
                if (!empty($insert)) {
                    \think\Db::name("ticket_department_upstream")->insertAll($insert);
                }
            }
            active_log(sprintf($this->lang["TicketDepartment_admin_add"], $r, $data["name"]));
        }
        $result["status"] = 200;
        $result["msg"] = "添加成功";
        return jsonrule($result);
    }
    public function save()
    {
        $params = $this->request->param();
        $rule = ["name" => "require", "email" => "email"];
        $msg = ["name.require" => "部门名称不能为空", "email.email" => "邮件地址格式错误"];
        $des = "";
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $id = intval($params["id"]);
        $info = \think\Db::name("ticket_department")->where("id", $id)->find();
        if (empty($info)) {
            $result["status"] = 406;
            $result["msg"] = "工单部门id错误";
            return jsonrule($result);
        }
        $data["name"] = $params["name"];
        if (!empty($data["name"]) && $data["name"] != $info["name"]) {
            $des .= "部门名称由“" . $info["name"] . "”改为“" . $data["name"] . "”，";
        }
        $data["email"] = $params["email"];
        if (!empty($data["email"]) && $data["email"] != $info["email"]) {
            $des .= "邮箱由“" . $info["email"] . "”改为“" . $data["email"] . "”，";
        }
        if (isset($params["admins"])) {
            if (!empty($params["admins"]) && is_array($params["admins"])) {
                $params["admins"] = array_filter($params["admins"], function ($x) {
                    return is_numeric($x) && 0 < $x;
                });
                \think\Db::name("ticket_department_admin")->where("dptid", $id)->delete();
                if (!empty($params["admins"])) {
                    $admins = \think\Db::name("user")->field("id")->whereIn("id", $params["admins"])->select()->toArray();
                    $insert = [];
                    foreach ($admins as $k => $v) {
                        $insert[] = ["admin_id" => $v["id"], "dptid" => $id];
                    }
                    if (!empty($insert)) {
                        \think\Db::name("ticket_department_admin")->insertAll($insert);
                    }
                }
            } else {
                \think\Db::name("ticket_department_admin")->where("dptid", $id)->delete();
            }
        }
        if (!empty($params["upstreams"]) && is_array($params["upstreams"]) && $params["is_related_upstream"] == 1) {
            \think\Db::name("ticket_department_upstream")->where("dptid", $id)->delete();
            $insert = [];
            foreach ($params["upstreams"] as $k => $v) {
                $insert[] = ["api_id" => $k, "upstream_dptid" => $v, "dptid" => $id];
            }
            if (!empty($insert)) {
                \think\Db::name("ticket_department_upstream")->insertAll($insert);
            }
        } else {
            \think\Db::name("ticket_department_upstream")->where("dptid", $id)->delete();
        }
        $data["description"] = $params["description"];
        if (isset($params["description"]) && $data["description"] != $info["description"]) {
            $des .= "描述由“" . $info["description"] . " 改为:" . $data["description"] . "”，";
        }
        $data["is_product_order"] = !empty($params["is_product_order"]) ? $params["is_product_order"] : 0;
        if (isset($params["is_product_order"]) && $data["is_product_order"] != $info["is_product_order"]) {
            if ($data["is_product_order"] == 1) {
                $des .= " 需要激活产品由“关闭”改为“开启”";
            } else {
                $des .= " 需要激活产品由“开启”改为“关闭”";
            }
        }
        $data["is_open_auto_reply"] = !empty($params["is_open_auto_reply"]) ? $params["is_open_auto_reply"] : 0;
        if (isset($params["is_open_auto_reply"]) && $data["is_open_auto_reply"] != $info["is_open_auto_reply"]) {
            if ($data["is_open_auto_reply"] == 1) {
                $des .= "自动回复由“关闭”改为“开启”";
            } else {
                $des .= "自动回复由“开启”改为“关闭”";
            }
        }
        $data["minutes"] = !empty($params["minutes"]) ? $params["minutes"] : 0;
        if (isset($params["minutes"]) && $data["minutes"] != $info["minutes"] && $data["minutes"] != $info["minutes"]) {
            $des .= "时间由“" . $info["minutes"] . "”改为“" . $data["minutes"] . "”，";
        }
        $arr = ["秒", "分钟"];
        $data["time_type"] = !empty($params["time_type"]) ? $params["time_type"] : 0;
        if (isset($params["time_type"]) && $data["time_type"] != $info["time_type"] && $data["time_type"] != $info["time_type"]) {
            $des .= "时间类型由“" . $arr[$info["time_type"]] . "”改为“" . $arr[$data["time_type"]] . "”，";
        }
        $data["bz"] = !empty($params["bz"]) ? $params["bz"] : 0;
        if (isset($params["bz"]) && $data["bz"] != $info["bz"] && $data["bz"] != $info["bz"]) {
            $des .= "自动回复内容由“" . $info["bz"] . "”改为“" . $data["bz"] . "”，";
        }
        $data["feedback_request"] = !empty($params["feedback_request"]) ? 1 : 0;
        if (isset($params["feedback_request"]) && $data["feedback_request"] != $info["feedback_request"]) {
            if ($data["feedback_request"] == 1) {
                $des .= "工单评分由“关闭”改为“开启”";
            } else {
                $des .= "工单评分由“开启”改为“关闭”";
            }
        }
        $data["hidden"] = !empty($params["hidden"]) ? 1 : 0;
        if (isset($params["hidden"]) && $data["hidden"] != $info["hidden"]) {
            if ($data["hidden"] == 1) {
                $des .= "由“隐藏”改为“显示”";
            } else {
                $des .= "由“显示”改为“隐藏”";
            }
        }
        $data["is_related_upstream"] = !empty($params["is_related_upstream"]) ? $params["is_related_upstream"] : 0;
        if (isset($params["is_related_upstream"]) && $data["is_related_upstream"] != $info["is_related_upstream"]) {
            if ($data["is_related_upstream"] == 1) {
                $des .= "关联上游部门由“关闭”改为“开启”";
            } else {
                $des .= "关联上游部门由“开启”改为“关闭”";
            }
        }
        if (isset($params["host"])) {
            $data["host"] = $params["host"] ?: "";
        }
        if (isset($params["port"])) {
            $data["port"] = $params["port"] ?: "";
        }
        if (isset($params["login"])) {
            $data["login"] = $params["login"] ?: "";
        }
        if (isset($params["password"])) {
            $old_password = cmf_decrypt($info["password"]);
            if ($params["password"] != str_repeat("*", strlen($old_password))) {
                $data["password"] = cmf_encrypt($params["password"]);
            }
        }
        $data["is_certifi"] = !empty($params["is_certifi"]) ? 1 : 0;
        $r = \think\Db::name("ticket_department")->where("id", $id)->update($data);
        if (is_array($params["customfieldname"]) && !empty($params["customfieldname"])) {
            $customfields = model("Customfields")->getCustomfields($id, "ticket", "id");
            $old_id = array_column($customfields, "id");
            foreach ($params["customfieldname"] as $k => $v) {
                if (in_array($k, $old_id)) {
                    $update["fieldname"] = $v;
                    $update["update_time"] = time();
                    if (isset($params["customfieldtype"][$k])) {
                        $update["fieldtype"] = $params["customfieldtype"][$k] ?: "text";
                    }
                    if (isset($params["customcfdesc"][$k])) {
                        $update["description"] = $params["customcfdesc"][$k] ?: "";
                    }
                    if (isset($params["customfieldoptions"][$k])) {
                        $update["fieldoptions"] = $params["customfieldoptions"][$k] ?: "";
                    }
                    if (isset($params["customregexpr"][$k])) {
                        $update["regexpr"] = $params["customregexpr"][$k] ?: "";
                    }
                    if (isset($params["customadminonly"][$k])) {
                        $update["adminonly"] = $params["customadminonly"][$k] ?: "";
                    }
                    if (isset($params["customrequired"][$k])) {
                        $update["required"] = $params["customrequired"][$k] ?: "";
                    }
                    if (isset($params["customsortorder"][$k])) {
                        $update["sortorder"] = $params["customsortorder"][$k] ?: "";
                    }
                    \think\Db::name("customfields")->where("id", $k)->update($update);
                }
            }
        }
        if (!empty($params["addfieldname"])) {
            $add_field = ["type" => "ticket", "relid" => $id, "fieldname" => $params["addfieldname"], "fieldtype" => $params["addfieldtype"] ?: "text", "description" => $params["addcfdesc"] ?: "", "fieldoptions" => $params["addfieldoptions"] ?: "", "regexpr" => $params["addregexpr"] ?: "", "adminonly" => $params["addadminonly"] ?: "", "required" => $params["addrequired"] ?: "", "sortorder" => $params["addsortorder"] ?: 0, "create_time" => time()];
            \think\Db::name("customfields")->insert($add_field);
        }
        if (empty($desc)) {
            $des .= "没有任何修改";
        }
        active_log(sprintf($this->lang["TicketDepartment_admin_save"], $r, $des));
        $result["status"] = 200;
        $result["msg"] = "修改成功";
        return jsonrule($result);
    }
    public function delete()
    {
        $id = intval(input("post.id"));
        $info = \think\Db::name("ticket_department")->field("id,name")->where("id", $id)->find();
        if (empty($info)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return jsonrule($result);
        }
        $is_use = \think\Db::name("ticket")->where("dptid", $id)->count();
        if (!empty($is_use)) {
            $result["status"] = 406;
            $result["msg"] = "有工单使用该部门,不能删除";
            return jsonrule($result);
        }
        model("Customfields")->deleteCustomfields($id, "ticket");
        \think\Db::name("ticket_department_admin")->where("dptid", $id)->delete();
        $r = \think\Db::name("ticket_department")->where("id", $id)->delete();
        if ($r) {
            active_log("删除工单部门成功,名称:" . $info["name"] . ",ID:" . $id);
        }
        $result["status"] = 200;
        $result["msg"] = "删除成功";
        return jsonrule($result);
    }
    public function moveDown()
    {
        $id = intval(input("post.id"));
        $info = \think\Db::name("ticket_department")->field("id,order")->where("id", $id)->find();
        if (empty($info)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return jsonrule($result);
        }
        $next = \think\Db::name("ticket_department")->field("id,order")->where("order", ">", $info["order"])->order("order", "asc")->find();
        if (empty($next)) {
            $result["status"] = 200;
            $result["msg"] = "操作成功";
            return jsonrule($result);
        }
        \think\Db::startTrans();
        try {
            $r1 = \think\Db::name("ticket_department")->where("id", $id)->update(["order" => $next["order"]]);
            $r2 = \think\Db::name("ticket_department")->where("id", $next["id"])->update(["order" => $info["order"]]);
            if ($r1 && $r2) {
                \think\Db::commit();
                $result["status"] = 200;
                $result["msg"] = "操作成功";
            } else {
                \think\Db::rollback();
                $result["status"] = 200;
                $result["msg"] = "操作失败";
            }
        } catch (\Exception $e) {
            \think\Db::rollback();
            $result["status"] = 200;
            $result["msg"] = "操作失败";
        }
        return jsonrule($result);
    }
    public function moveUp()
    {
        $id = intval(input("post.id"));
        $info = \think\Db::name("ticket_department")->field("id,order")->where("id", $id)->find();
        if (empty($info)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return jsonrule($result);
        }
        $prev = \think\Db::name("ticket_department")->field("id,order")->where("order", "<", $info["order"])->order("order", "desc")->find();
        if (empty($prev)) {
            $result["status"] = 200;
            $result["msg"] = "操作成功";
            return jsonrule($result);
        }
        \think\Db::startTrans();
        try {
            $r1 = \think\Db::name("ticket_department")->where("id", $id)->update(["order" => $prev["order"]]);
            $r2 = \think\Db::name("ticket_department")->where("id", $prev["id"])->update(["order" => $info["order"]]);
            if ($r1 && $r2) {
                \think\Db::commit();
                $result["status"] = 200;
                $result["msg"] = "操作成功";
            } else {
                \think\Db::rollback();
                $result["status"] = 200;
                $result["msg"] = "操作失败";
            }
        } catch (\Exception $e) {
            \think\Db::rollback();
            $result["status"] = 200;
            $result["msg"] = "操作失败";
        }
        return jsonrule($result);
    }
    public function getList()
    {
        $data = \think\Db::name("ticket_department")->field("id,name,description,email,hidden,order,is_open_auto_reply as auto_reply")->order("order", "DESC")->select()->toArray();
        $result["status"] = 200;
        $result["data"] = $data;
        return jsonrule($result);
    }
    public function getDetail($id)
    {
        $id = intval($id);
        $data = \think\Db::name("ticket_department")->where("id", $id)->find();
        if (empty($data)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return jsonrule($result);
        }
        $department_admin = \think\Db::name("ticket_department_admin")->where("dptid", $id)->select()->toArray();
        $data["admins"] = array_column($department_admin, "admin_id") ?: [];
        unset($data["order"]);
        $data["password"] = cmf_decrypt($data["password"]);
        $ticket_department_upstream = \think\Db::name("ticket_department_upstream")->where("dptid", $id)->select()->toArray();
        $data["upstreams"] = array_column($ticket_department_upstream, "upstream_dptid", "api_id") ?: [];
        $custom_list = \think\Db::name("customfields")->where("relid", $id)->where("type", "ticket")->order("id", "desc");
        $custom_count = $custom_list->count();
        if ($custom_count) {
            $custom_list = $custom_list->page($this->page, $this->limit)->select()->toArray();
            foreach ($custom_list as $key => $val) {
                $custom_list[$key]["fieldname_zn"] = $this->custom_param_type[$val["fieldname"]] ?? "";
            }
            $data["customfields"] = ["count" => $custom_count, "list" => $custom_list];
        } else {
            $data["customfields"] = ["count" => 0, "list" => []];
        }
        $result["status"] = 200;
        $result["data"] = $data;
        return jsonrule($result);
    }
    public function getCustomParamType()
    {
        return jsonrule(["status" => 200, "data" => $this->custom_param_type]);
    }
    public function addTicketCustomParam()
    {
        try {
            $params = $this->request->param();
            $rule = ["fieldname" => "require", "fieldtype" => "require", "description" => "require", "ticketId" => "require"];
            $msg = ["fieldname.require" => "字段名称不能为空", "fieldtype.require" => "字段类型不能为空", "description.require" => "字段描述不能为空", "ticketId.require" => "工单部门ID不能为空"];
            $validate = new \think\Validate($rule, $msg);
            $validate_result = $validate->check($params);
            if (!$validate_result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $model = \think\Db::name("customfields")->where(["fieldname" => $params["fieldname"], "type" => "ticket"])->find();
            if ($model) {
                throw new \think\Exception("字段名称已存在!");
            }
            if ($params["fieldtype"] == "dropdown" && trim($params["fieldoptions"]) == "") {
                throw new \think\Exception("请输入下拉选项!");
            }
            if (!\think\Db::name("ticket_department")->find($params["ticketId"])) {
                throw new \think\Exception("该工单部门不存在!");
            }
            $data = ["type" => "ticket", "relid" => $params["ticketId"], "fieldname" => $params["fieldname"], "fieldtype" => $params["fieldtype"], "description" => $params["description"], "fieldoptions" => $params["fieldoptions"] ?: "", "regexpr" => $params["regexpr"] ?: "", "adminonly" => $params["adminonly"] ?: "", "required" => $params["required"] ?: "", "sortorder" => $params["sortorder"] ?: 0, "create_time" => time()];
            \think\Db::name("customfields")->insert($data);
            return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
    public function getTicketParamVal()
    {
        try {
            $params = $this->request->param();
            if (!$params["fieldId"]) {
                throw new \think\Exception("自定义字段id不能为空");
            }
            $model = \think\Db::name("customfields")->find($params["fieldId"]);
            if (!$model) {
                throw new \think\Exception("数据不存在!");
            }
            return jsonrule(["status" => 200, "msg" => "success", "data" => $model]);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
    public function editTicketCustomParam()
    {
        try {
            $params = $this->request->param();
            $rule = ["fieldname" => "require", "fieldtype" => "require", "description" => "require", "ticketId" => "require", "fieldId" => "require"];
            $msg = ["fieldname.require" => "字段名称不能为空", "fieldtype.require" => "字段类型不能为空", "description.require" => "字段描述不能为空", "ticketId.require" => "工单部门ID不能为空", "fieldId.require" => "自定义字段id不能为空"];
            $validate = new \think\Validate($rule, $msg);
            $validate_result = $validate->check($params);
            if (!$validate_result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $model = \think\Db::name("customfields")->where(["fieldname" => $params["fieldname"], "type" => "ticket"])->where("id", "<>", $params["fieldId"])->find();
            if ($model) {
                throw new \think\Exception("字段名称已存在!");
            }
            if ($params["fieldtype"] == "dropdown" && trim($params["fieldoptions"]) == "") {
                throw new \think\Exception("请输入下拉选项!");
            }
            if (!\think\Db::name("ticket_department")->find($params["ticketId"])) {
                throw new \think\Exception("该工单部门不存在!");
            }
            $data = ["type" => "ticket", "relid" => $params["ticketId"], "fieldname" => $params["fieldname"], "fieldtype" => $params["fieldtype"], "description" => $params["description"], "fieldoptions" => $params["fieldoptions"] ?: "", "regexpr" => $params["regexpr"] ?: "", "adminonly" => $params["adminonly"] ?: "", "required" => $params["required"] ?: "", "sortorder" => $params["sortorder"] ?: 0, "create_time" => time()];
            \think\Db::name("customfields")->where("id", $params["fieldId"])->update($data);
            return jsonrule(["status" => 200, "msg" => "success"]);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
    public function delTicketCustomParam()
    {
        try {
            $params = $this->request->param();
            if (!$params["fieldId"]) {
                throw new \think\Exception("自定义字段id不能为空");
            }
            \think\Db::startTrans();
            \think\Db::name("customfieldsvalues")->where("fieldid", $params["fieldId"])->delete();
            \think\Db::name("customfields")->delete($params["fieldId"]);
            \think\Db::commit();
            return jsonrule(["status" => 200, "msg" => "删除成功"]);
        } catch (\Throwable $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
}

?>