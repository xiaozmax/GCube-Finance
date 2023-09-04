<?php


namespace app\admin\controller;

/**
 * @title 对外API
 * @description 接口说明: API管理
 */
class ApiController extends AdminBaseController
{
    public function index()
    {
        $page = input("get.page", 1, "intval");
        $limit = input("get.limit", 10, "intval");
        $orderby = input("get.orderby", "id");
        $sort = input("get.sort", "asc");
        $search = input("get.search", "");
        $page = 0 < $page ? $page : 1;
        $limit = 0 < $limit ? $limit : 10;
        if (!in_array($orderby, ["id", "username", "ip"])) {
            $orderby = "id";
        }
        if (!in_array($sort, ["asc", "desc"])) {
            $sort = "desc";
        }
        $count = \think\Db::name("api")->whereLike("username|ip", "%" . $search . "%")->where("is_auto", 0)->count();
        $data = \think\Db::name("api")->field("id,username,ip,create_time")->whereLike("username|ip", "%" . $search . "%")->where("is_auto", 0)->order($orderby, $sort)->page($page)->limit($limit)->select()->toArray();
        $max_page = ceil($count / $limit);
        $result["status"] = 200;
        $result["data"]["page"] = $page;
        $result["data"]["limit"] = $limit;
        $result["data"]["sum"] = $count;
        $result["data"]["max_page"] = $max_page;
        $result["data"]["orderby"] = $orderby;
        $result["data"]["sort"] = $sort;
        $result["data"]["list"] = $data;
        return jsonrule($result);
    }
    public function add()
    {
        $params = input("post.");
        $validate = new \app\admin\validate\ApiValidate();
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $username_exist = \think\Db::name("api")->where("username", $params["username"])->find();
        if (!empty($username_exist)) {
            $result["status"] = 400;
            $result["msg"] = "用户名已存在";
            return jsonrule($result);
        }
        $insert = ["username" => $params["username"], "password" => md5($params["password"]), "ip" => $params["ip"], "create_time" => time()];
        \think\Db::name("api")->insert($insert);
        $result["status"] = 200;
        $result["msg"] = lang("ADD SUCCESS");
        return jsonrule($result);
    }
    public function delete()
    {
        $id = input("post.id", 0, "intval");
        \think\Db::name("api")->where("id", $id)->where("is_auto", 0)->delete();
        $result["status"] = 200;
        $result["msg"] = lang("DELETE SUCCESS");
        return jsonrule($result);
    }
}

?>