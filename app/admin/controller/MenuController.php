<?php


namespace app\admin\controller;

/**
 * @title 菜单/导航管理
 * @description 接口说明: 菜单/导航管理
 */
class MenuController extends AdminBaseController
{
    public function managePositionPage()
    {
        $active = \think\Db::name("menu_active")->field("type,menuid")->select()->toArray();
        $active = array_column($active, "menuid", "type");
        $data["client_menu_item"] = \think\Db::name("menu")->field("id,name")->where("type", "client")->select()->toArray();
        $data["index_menu_item"] = \think\Db::name("menu")->field("id,name")->where("type", "www")->select()->toArray();
        $data["client_menu"] = $active["client"] ?? 0;
        $data["www_top_menu"] = $active["www_top"] ?? 0;
        $data["www_bottom_menu"] = $active["www_bottom"] ?? 0;
        return jsonrule(["data" => $data, "status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function menuManagePage()
    {
        $menuid = request()->param("menuid");
        $active = \think\Db::name("menu_active")->field("type,menuid")->select()->toArray();
        $active = array_column($active, "type", "menuid");
        $data["menu"] = \think\Db::name("menu")->select()->toArray();
        foreach ($data["menu"] as $k => $v) {
            if (isset($active[$v["id"]])) {
                $data["menu"][$k]["active_type"] = $active[$v["id"]];
            } else {
                $data["menu"][$k]["active_type"] = "";
            }
        }
        $menu = new \app\common\logic\Menu();
        $data["nav"] = $menu->getNav($menuid, 0, "", true);
        return jsonrule(["data" => $data, "status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function savePosition()
    {
        $menu = request()->param("menu");
        $menu_obj = new \app\common\logic\Menu();
        if (isset($menu["client"])) {
            $menu_obj->activeMenu(0, $menu["client"]);
        }
        if (isset($menu["www_top"])) {
            $menu_obj->activeMenu(0, $menu["www_top"]);
        }
        if (isset($menu["www_bottom"])) {
            $menu_obj->activeMenu(0, $menu["www_bottom"]);
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function createMenu()
    {
        $param = request()->only(["name", "type"]);
        $validate = new \app\admin\validate\MenuValidate();
        if (!$validate->check($param)) {
            return jsonrule(["status" => 400, "msg" => $validate->getError()]);
        }
        $id = \think\Db::name("menu")->insertGetId($param);
        return jsonrule(["status" => 200, "msg" => "添加成功"]);
    }
    public function editMenu()
    {
        $param = request()->only(["id", "name", "nav"]);
        $validate = new \app\admin\validate\MenuValidate();
        if (!$validate->scene("edit")->check($param)) {
            return jsonrule(["status" => 400, "msg" => $validate->getError()]);
        }
        $menu = \think\Db::name("menu")->where("id", (int) $param["id"])->find();
        if (empty($menu)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $exist = \think\Db::name("menu")->where("id", "<>", $param["id"])->where("name", $param["name"])->find();
        if (!empty($exist)) {
            return jsonrule(["status" => 400, "msg" => "名称已使用"]);
        }
        $menu_obj = new \app\common\logic\Menu();
        \think\Db::startTrans();
        try {
            $ids = [];
            $nav_id = array_column($param["nav"], "id");
            $has_top = false;
            \think\Db::name("nav")->where("menuid", $menu["id"])->delete();
            foreach ($param["nav"] as $v) {
                if (empty($v["id"]) || !isset($v["pid"])) {
                    throw new \Exception("导航参数错误:缺少ID");
                }
                if ($v["pid"] != 0 && !in_array($v["pid"], $nav_id)) {
                    throw new \Exception("导航参数错误:上级ID错误");
                }
                if ($v["pid"] == 0) {
                    $has_top = true;
                }
                $ids[$v["id"]]["fake_pid"] = $v["pid"];
                $v["pid"] = 0;
                $ids[$v["id"]]["navid"] = $menu_obj->addNav($v, $menu["id"]);
            }
            if (!$has_top) {
                throw new \Exception("导航参数错误:没有顶级导航");
            }
            foreach ($ids as $k => $v) {
                \think\Db::name("nav")->where("id", $v["navid"])->update(["pid" => (int) $ids[$ids[$v["fake_pid"]]["navid"]]]);
            }
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
        }
        return jsonrule(["status" => 200, "msg" => "修改成功"]);
    }
    public function deleteMenu()
    {
        $id = request()->param("id");
        if (empty($id)) {
            return jsonrule(["status" => 400, "msg" => "请选择要删除的菜单"]);
        }
        \think\Db::startTrans();
        try {
            \think\Db::name("menu")->where("id", $id)->delete();
            \think\Db::name("nav")->where("menuid", $id)->delete();
            \think\Db::name("menu_active")->where("menuid", $id)->update(["menuid" => 0]);
            \think\Db::commit();
            return jsonrule(["status" => 200, "msg" => "删除成功"]);
        } catch (\Exception $e) {
            return jsonrule(["status" => 200, "msg" => "删除失败"]);
        }
    }
    public function createNav()
    {
        $param = request()->only(["menuid", "name", "nav_type", "url", "fa_icon", "relid"]);
        $validate = new \app\admin\validate\NavValidate();
        if (!$validate->check($param)) {
            return jsonrule(["status" => 400, "msg" => $validate->getError()]);
        }
        $menu = \think\Db::name("menu")->where("id", (int) $param["menuid"])->find();
        if (empty($menu)) {
            return jsonrule(["status" => 400, "msg" => "菜单错误"]);
        }
        $menu_obj = new \app\common\logic\Menu();
        try {
            $menu_obj->addNav($param, $menu["id"]);
        } catch (\Exception $e) {
            return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
        }
        return jsonrule(["status" => 200, "msg" => "添加成功"]);
    }
    public function deleteNav()
    {
        $id = (int) request()->param("id");
        $nav = \think\Db::name("nav")->where("id", $id)->find();
        if (empty($nav)) {
            return jsonrule(["status" => 400, "msg" => "导航已删除"]);
        }
        \think\Db::startTrans();
        try {
            \think\Db::name("nav")->where("id", $id)->delete();
            \think\Db::name("nav")->where("pid", $id)->update(["pid" => $nav["pid"], "order" => $nav["order"]]);
            \think\Db::commit();
            return jsonrule(["status" => 200, "msg" => "删除成功"]);
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 200, "msg" => "删除失败"]);
        }
    }
}

?>