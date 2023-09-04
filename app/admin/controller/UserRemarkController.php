<?php


namespace app\admin\controller;

class UserRemarkController extends AdminBaseController
{
    public function index()
    {
        $page = input("page") ?? config("page_num");
        $size = input("size") ?? config("page_size");
        $res = db("remark")->page($page, $size)->order("stick", "desc")->order("id", "desc")->select();
        return jsonrule(["data" => $res, "status" => 200, "msg" => "ok"]);
    }
    public function create()
    {
    }
    public function save(\think\Request $request)
    {
        $data = ["remark" => input("remark", "", "htmlspecialchars"), "stick" => input("stick/d"), "uid" => input("uid/d"), "admin_id" => cmf_get_current_admin_id(), "create_time" => time()];
        db("remark")->insert($data);
        return jsonrule(["status" => 201, "msg" => "ok"], 201);
    }
    public function read($id)
    {
        $res = db("remark")->where("id", $id)->find();
        return jsonrule(["data" => $res, "status" => 200, "msg" => "ok"]);
    }
    public function edit($id)
    {
    }
    public function update(\think\Request $request, $id)
    {
        $data = ["id" => $id, "remark" => input("remark", "", "htmlspecialchars"), "stick" => input("stick/d")];
        $res = db("remark")->update($data);
        if ($res) {
            return jsonrule(["status" => 203, "msg" => "ok"], 203);
        }
        return jsonrule(["status" => 400, "msg" => "error"], 400);
    }
    public function delete($id)
    {
        db("remark")->where("id", $id)->delete();
        return jsonrule(["status" => 204, "msg" => "ok"], 204);
    }
}

?>