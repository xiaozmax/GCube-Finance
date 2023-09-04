<?php


namespace app\admin\controller;

/**
 * @title 页面管理
 * @description 接口说明
 */
class RbacPageController extends AdminBaseController
{
    protected $data = NULL;
    public function initialize()
    {
        parent::initialize();
    }
    public function index()
    {
        $param = $this->request->param();
        $order = isset($param["order"][0]) ? trim($param["order"]) : "id";
        $sort = isset($param["sort"][0]) ? trim($param["sort"]) : "DESC";
        $roles = \think\Db::name("role_page")->field("id,name,status,remark")->order($order, $sort)->select()->toArray();
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
            $validate = new \think\Validate($rule, $msg);
            $validate_result = $validate->check($data);
            if (!$validate_result) {
                return jsonrule(["status" => 400, "msg" => $validate->getError()]);
            }
            if (!empty($auth) && is_array($auth)) {
                $auth = \think\Db::name("auth_rule")->whereIn("id", $auth)->select()->toArray();
                $auth = array_column($auth, "name");
            }
            \think\Db::startTrans();
            try {
                $result = db("role_page")->insertGetId($data);
                $insert = [];
                foreach ($auth as $v) {
                    $insert[] = ["rolepage_id" => $result, "rule_name" => $v, "type" => "admin_url"];
                }
                if (!empty($insert)) {
                    \think\Db::name("authpage_access")->insertAll($insert);
                }
                \think\Db::commit();
                active_log(sprintf($this->lang["Rabcpage_admin_addRole"], $result));
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
        $data = \think\Db::name("rolepage")->where("id", $id)->find();
        if (!$data) {
            return jsonrule(["status" => 400, "msg" => "不存在的角色！", "rule" => $this->rule]);
        }
        $role = \think\Db::name("rolepage")->field("name,remark,status")->where("id", $id)->find();
        $auth_role = \think\Db::name("authpage_access")->alias("a")->field("b.id,b.pid,b.is_display")->leftJoin("auth_rule b", "a.rule_name=b.name")->where("a.rolepage_id", $id)->where("b.is_display", "in", $is_display)->where("b.title", "like", "%" . $name . "%")->select()->toArray();
        $auths = \think\Db::name("auth_rule")->field("id,pid,is_display,name,title")->where("is_display", "in", $is_display)->where("title", "like", "%" . $name . "%")->select()->toArray();
        $auths_tree = $this->listToTree($auths);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "role" => $role, "auths" => $auths_tree, "auth_select" => array_column($auth_role, "id") ?: []]);
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
            if (!empty($auth) && is_array($auth)) {
                $auth = \think\Db::name("auth_rule")->whereIn("id", $auth)->select()->toArray();
                $auth = array_column($auth, "name");
            }
            $dec = "";
            $roles = db("rolepage")->field("name,remark,status")->where("id", $id)->find();
            if ($data["name"] != $roles["name"]) {
                $dec .= " - 权限组名" . $roles["name"] . "改为" . $data["name"];
            }
            if ($data["remark"] != $roles["remark"]) {
                $dec .= " - 权限组描述" . $roles["remark"] . "改为" . $data["remark"];
            }
            if ($data["status"] != $roles["status"]) {
                if ($roles["status"] == 1) {
                    $dec .= " - 禁用";
                } else {
                    $dec .= " - 启用";
                }
            }
            $data["update_time"] = time();
            \think\Db::startTrans();
            try {
                db("rolepage")->where("id", $id)->update($data);
                $insert = [];
                foreach ($auth as $v) {
                    $insert[] = ["role_id" => $id, "rule_name" => $v, "type" => "admin_url"];
                }
                \think\Db::name("authpage_access")->where("role_id", $id)->delete();
                if (!empty($insert)) {
                    \think\Db::name("authpage_access")->insertAll($insert);
                }
                \think\Db::commit();
                active_log(sprintf($this->lang["Rabcpage_admin_editRole"], $id, $dec));
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
            active_log(sprintf($this->lang["Rabcpage_admin_deleteRole"], $id));
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
}

?>