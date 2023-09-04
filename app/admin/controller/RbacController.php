<?php

namespace app\admin\controller;

/**
 * @title 权限管理(管理员分组)
 * @description 接口说明
 */
class RbacController extends AdminBaseController
{
    protected $data = NULL;
    public function initialize()
    {
        parent::initialize();
    }
    public function index()
    {
        $param = $this->request->param();
        $order = isset($param["order"][0]) ? trim($param["order"]) : "a.id";
        $sort = isset($param["sort"][0]) ? trim($param["sort"]) : "DESC";
        $roles = \think\Db::name("role")->alias("a")->field("a.id,a.name,a.status,a.remark,group_concat(c.user_login) as user_login")->leftJoin("role_user b", "a.id = b.role_id")->leftJoin("user c", "c.id =  b.user_id")->group("a.id")->order($order, $sort)->select()->toArray();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "roles" => $roles]);
    }
    public function addRolePage()
    {
        $auths = \think\Db::name("auth_rule")->field("id,pid,title")->where("status", 1)->select()->toArray();
        $auths_tree = $this->listToTree($auths);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "auths" => $auths_tree]);
    }
    public function addRole()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only("name,remark,status,auth");
            $auth = array_filter($data["auth"], function ($x) {
                return 0 < $x && is_numeric($x);
            });
            unset($data["auth"]);
            $rule = ["name" => "require|max:15", "remark" => "max:255", "status" => "require|in:0,1"];
            $msg = ["name.require" => "名称不能为空", "status.require" => "状态不能为空"];
            $data["auth_role"] = implode(",", $auth);
            $validate = new \think\Validate($rule, $msg);
            $validate_result = $validate->check($data);
            if (!$validate_result) {
                return jsonrule(["status" => 400, "msg" => $validate->getError()]);
            }
            if (!empty($auth) && is_array($auth)) {
                $auth = \think\Db::name("auth_rule")->whereIn("id", $auth)->select()->toArray();
                $auth = array_column($auth, "name", "id");
            }
            $res = secondVerifyResultAdmin("create_admin_group");
            if ($res["status"] != 200) {
                return jsonrule($res);
            }
            \think\Db::startTrans();
            try {
                $result = \think\Db::name("role")->insertGetId($data);
                $insert = [];
                foreach ($auth as $key => $v) {
                    $insert[] = ["role_id" => $result, "rule_name" => $v, "rule_id" => $key, "type" => "admin_url"];
                }
                if (!empty($insert)) {
                    \think\Db::name("auth_access")->insertAll($insert);
                }
                \think\Db::commit();
                active_log(sprintf($this->lang["Rabc_admin_addRole"], $result));
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
            }
            return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function editRolePage()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) ? intval($params["id"]) : "";
        $is_display = isset($params["display"]) ? intval($params["display"]) : "0,1";
        $name = isset($params["name"]) ? intval($params["name"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => "ID_ERROR", "rule" => $this->rule]);
        }
        if ($id == 1) {
            return jsonrule(["status" => 400, "msg" => "不允许的操作！", "rule" => $this->rule]);
        }
        $data = \think\Db::name("role")->where("id", $id)->find();
        if (!$data) {
            return jsonrule(["status" => 400, "msg" => "不存在的角色！", "rule" => $this->rule]);
        }
        $role = \think\Db::name("role")->field("name,remark,status")->where("id", $id)->find();
        $auth_role = \think\Db::name("auth_access")->alias("a")->field("b.id,b.pid,b.is_display")->leftJoin("auth_rule b", "a.rule_id=b.id")->where("a.role_id", $id)->where("b.is_display", "in", $is_display)->where("b.title", "like", "%" . $name . "%")->select()->toArray();
        $user = \think\Db::name("role_user")->alias("a")->field("b.id,b.user_login,b.user_nickname")->leftJoin("user b", "a.user_id=b.id")->where("a.role_id", $id)->select()->toArray();
        $auths = \think\Db::name("auth_rule")->field("id,pid,is_display,name,title")->where("is_display", "in", $is_display)->where("title", "like", "%" . $name . "%")->select()->toArray();
        $auths_tree = $this->listToTree($auths);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "role" => $role, "auths" => $auths_tree, "auth_select" => array_column($auth_role, "id") ?: [], "user" => $user]);
    }
    public function editRole()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only("id,name,remark,status,auth");
            $id = isset($data["id"]) ? intval($data["id"]) : "";
            if (!$id) {
                return jsonrule(["status" => 400, "msg" => "ID_ERROR"]);
            }
            $auth = array_filter($data["auth"], function ($x) {
                return 0 < $x && is_numeric($x);
            });
            unset($data["auth"]);
            $rule = ["name" => "require|max:15", "remark" => "max:255", "status" => "require|in:0,1"];
            $msg = ["name.require" => "名称不能为空", "status.require" => "状态不能为空"];
            $validate = new \think\Validate($rule, $msg);
            $validate_result = $validate->check($data);
            if (!$validate_result) {
                return jsonrule(["status" => 400, "msg" => $validate->getError()]);
            }
            $data["auth_role"] = implode(",", $auth);
            if (!empty($auth) && is_array($auth)) {
                $auth = \think\Db::name("auth_rule")->whereIn("id", $auth)->select()->toArray();
                $auth = array_column($auth, "name", "id");
            }
            $dec = "";
            $roles = db("role")->field("name,remark,status")->where("id", $id)->find();
            if ($data["name"] != $roles["name"]) {
                $dec .= "权限组名由“" . $roles["name"] . "”改为“" . $data["name"] . "”，";
            }
            if ($data["remark"] != $roles["remark"]) {
                $dec .= "权限组描述由“" . $roles["remark"] . "”改为“" . $data["remark"] . "”，";
            }
            if ($data["status"] != $roles["status"]) {
                if ($roles["status"] == 1) {
                    $dec .= "由“启用”改为“禁用”，";
                } else {
                    $dec .= "由“禁用”改为“启用”，";
                }
            }
            $res = secondVerifyResultAdmin("modify_admin_group");
            if ($res["status"] != 200) {
                return jsonrule($res);
            }
            $data["update_time"] = time();
            \think\Db::startTrans();
            try {
                db("role")->where("id", $id)->update($data);
                $insert = [];
                foreach ($auth as $key => $v) {
                    $insert[] = ["role_id" => $id, "rule_name" => $v, "rule_id" => $key, "type" => "admin_url"];
                }
                \think\Db::name("auth_access")->where("role_id", $id)->delete();
                if (!empty($insert)) {
                    \think\Db::name("auth_access")->insertAll($insert);
                }
                \think\Db::commit();
                active_log(sprintf($this->lang["Rabc_admin_editRole"], $id, $dec));
                unset($dec);
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => lang("UPDATE FAIL")]);
            }
            return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function delete()
    {
        $id = $this->request->param("id", 0, "intval");
        if ($id == 1) {
            return jsonrule(["status" => 400, "msg" => lang("IMPOSSIBILITY DELETE")]);
        }
        $count = \think\Db::name("RoleUser")->where("role_id", $id)->count();
        if (0 < $count) {
            return jsonrule(["status" => 400, "msg" => lang("EXIST_AMDIN")]);
        }
        $status = \think\Db::name("role")->delete($id);
        if (!empty($status)) {
            active_log(sprintf($this->lang["Rabc_admin_deleteRole"], $id));
            return jsonrule(["status" => 204, "msg" => lang("DELETE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
    }
    private function listToTree($list, $pk = "id", $pid = "pid", $display = "is_display", $child = "sublevel", $root = 0)
    {
        $tree = [];
        if (is_array($list)) {
            $refer = [];
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] =& $list[$key];
            }
            foreach ($list as $key => $data) {
                $parentId = $data[$pid];
                if ($root == $parentId) {
                    $tree[] =& $list[$key];
                } else {
                    if (isset($refer[$parentId])) {
                        $parent =& $refer[$parentId];
                        $parent[$child][$data[$pk]] =& $list[$key];
                        $parent[$child] = array_values($parent[$child]);
                    }
                }
            }
        }
        return $tree;
    }
    public function copyRole()
    {
        try {
            throwEditionError();
            $param = $this->request->param();
            if (!$param["role_id"]) {
                throw new \think\Exception("请选择要复制的分组。");
            }
            if (!$param["role_name"]) {
                throw new \think\Exception("请填写新的分组名称！");
            }
            $role = \think\Db::name("role")->field("id", true)->where("id", $param["role_id"])->find();
            if (!$role) {
                throw new \think\Exception("要复制的分组不存在！");
            }
            $role["name"] = $param["role_name"];
            $role["remark"] = $param["role_remark"];
            $role["update_time"] = time();
            $role["create_time"] = $role["update_time"];
            if (\think\Db::name("role")->where("name", $param["role_name"])->find()) {
                throw new \think\Exception("分组名称已存在");
            }
            $role_id = \think\Db::name("role")->insertGetId($role);
            $rule_list = \think\Db::name("auth_access")->field("id", true)->where("role_id", $param["role_id"])->select();
            if (!empty($rule_list)) {
                $insert = [];
                foreach ($rule_list as $val) {
                    $insert[] = ["role_id" => $role_id, "rule_name" => $val["rule_name"], "rule_id" => $val["rule_id"], "type" => "admin_url"];
                }
                \think\Db::name("auth_access")->insertAll($insert);
            }
            return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
}

?>