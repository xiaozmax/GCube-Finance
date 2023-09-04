<?php


namespace app\admin\controller;

/**
 * @title 后台预设回复
 * @description 接口说明
 */
class TicketPrereplyController extends AdminBaseController
{
    public function replyList()
    {
        $categories = \think\Db::name("ticket_prereply_category")->field("id,name")->select();
        $categoriesFilter = [];
        foreach ($categories as $key => $category) {
            $son = \think\Db::name("ticket_prereply")->field("id,title,content")->where("cid", $category["id"])->select();
            $categoriesFilter[$key] = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $category);
            $categoriesFilter[$key]["child"] = $son;
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "prereply" => $categoriesFilter]);
    }
    public function addCategory()
    {
        $params = input("post.");
        $rule = ["name" => "require"];
        $msg = ["name.require" => "名称不能为空"];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $data["name"] = trim($params["name"]);
        $newid = \think\Db::name("ticket_prereply_category")->insertGetId($data);
        if ($newid) {
            active_log("添加预设回复分类成功,分类名称:" . $data["name"] . ",ID:" . $newid);
        }
        $result["status"] = 200;
        $result["msg"] = "添加成功";
        $result["data"] = $newid;
        return jsonrule($result);
    }
    public function editCategoryPage()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) ? intval($params["id"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $category = \think\Db::name("ticket_prereply_category")->field("id,name")->where("id", $id)->find();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "category" => $category]);
    }
    public function editCategory()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $id = isset($params["id"]) ? intval($params["id"]) : "";
            if (!$id) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            $rule = ["name" => "require"];
            $msg = ["name.require" => "名称不能为空"];
            $validate = new \think\Validate($rule, $msg);
            $validate_result = $validate->check($params);
            if (!$validate_result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            \think\Db::name("ticket_prereply_category")->where("id", $id)->update(["name" => trim($params["name"])]);
            return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function deleteCategory()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) ? intval($params["id"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        \think\Db::startTrans();
        try {
            \think\Db::name("ticket_prereply")->where("cid", $id)->delete();
            \think\Db::name("ticket_prereply_category")->where("id", $id)->delete();
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
        }
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function addPrereplyPage()
    {
        $categories = \think\Db::name("ticket_prereply_category")->field("id,name")->select()->toArray();
        return jsonrule(["status" => 200, "mag" => lang("SUCCESS MESSAGE"), "categories" => $categories]);
    }
    public function addPrereply()
    {
        $params = input("post.");
        $rule = ["cid" => "require", "title" => "require"];
        $msg = ["cid.require" => "分类不能为空", "title.require" => "请输入标题"];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $cid = intval($params["cid"]);
        $category = \think\Db::name("ticket_prereply_category")->where("id", $cid)->find();
        if (empty($category)) {
            $result["status"] = 406;
            $result["msg"] = "分类不存在";
            return jsonrule($result);
        }
        $data["cid"] = $cid;
        $data["title"] = trim($params["title"]);
        $data["content"] = $params["content"] ?: "";
        $r = \think\Db::name("ticket_prereply")->insertGetId($data);
        if ($r) {
            active_log("添加预设回复成功,标题:" . $data["title"] . ",ID:" . $r);
        }
        $result["status"] = 200;
        $result["msg"] = "添加成功";
        $result["data"] = $r;
        return jsonrule($result);
    }
    public function savePrereplyPage()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) ? intval($params["id"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $categories = \think\Db::name("ticket_prereply_category")->field("id,name")->select()->toArray();
        $pre = \think\Db::name("ticket_prereply")->field("id,cid,title,content")->where("id", $id)->find();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "categories" => $categories, "list" => array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $pre)]);
    }
    public function savePrereply()
    {
        $params = input("post.");
        $rule = ["title" => "require"];
        $msg = ["title.require" => "请输入标题"];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $id = intval($params["id"]);
        $exist = \think\Db::name("ticket_prereply")->where("id", $id)->find();
        if (empty($exist)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return jsonrule($result);
        }
        $cid = intval($params["cid"]);
        $category = \think\Db::name("ticket_prereply_category")->where("id", $cid)->find();
        if (!empty($category)) {
            $data["cid"] = $cid;
        }
        $data["title"] = trim($params["title"]);
        if (isset($params["content"])) {
            $data["content"] = $params["content"] ?: "";
        }
        $r = \think\Db::name("ticket_prereply")->where("id", $id)->update($data);
        if ($r) {
            active_log("修改预设回复成功,ID:" . $id);
        }
        $result["status"] = 200;
        $result["msg"] = "修改成功";
        return jsonrule($result);
    }
    public function searchPrereply()
    {
        $title = input("post.title", "");
        $content = input("post.content", "");
        $data = \think\Db::name("ticket_prereply")->field("id,title,content")->whereLike("title", "%" . $title . "%")->whereLike("content", "%" . $content . "%")->select();
        $result["status"] = 200;
        $result["msg"] = "搜索成功";
        $result["data"] = $data;
        return jsonrule($result);
    }
    public function deletePrereply()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) ? intval($params["id"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        \think\Db::name("ticket_prereply")->where("id", $id)->delete();
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
}

?>