<?php


namespace app\admin\controller;

/**
 * @title 后台新闻
 * @description 接口说明
 */
class NewsController extends AdminBaseController
{
    protected $upload_path = "";
    protected $upload_url = "";
    protected function initialize()
    {
        parent::initialize();
        $this->upload_url = $this->request->host() . "/upload/news/";
        $this->upload_path = CMF_ROOT . "/public/upload/news/";
    }
    public function getList(\think\Request $request)
    {
        $param = $request->param();
        $orderby = $param["orderby"] ?? "push_time";
        $sorting = $param["sorting"] == "ASC" ? "ASC" : "DESC";
        $where = [];
        if (isset($param["search"][0])) {
            $where[] = ["title", "like", "%" . $param["search"] . "%"];
        }
        if (isset($param["status"])) {
            $where[] = ["status", "=", (int) $param["status"]];
        }
        if (isset($param["parent_id"]) && 0 < $param["parent_id"]) {
            $tmp = \think\Db::name("news_type")->where([["parent_id", "=", $param["parent_id"]]])->select();
            if (isset($tmp[0]["id"])) {
                $tmp_ids = array_column($tmp->toArray(), "id");
                $tmp_ids[] = $param["parent_id"];
                $where[] = ["parent_id", "in", $tmp_ids];
            } else {
                $where[] = ["parent_id", "=", $param["parent_id"]];
            }
        }
        $news_menu = \think\Db::name("news_menu")->where($where)->order($orderby, $sorting)->page($this->page, $this->limit)->select()->toArray();
        $count = \think\Db::name("news_menu")->where($where)->count();
        $news_type = \think\Db::name("news_type")->select()->toArray();
        $tmp = array_column($news_type, NULL, "id");
        foreach ($news_menu as &$v) {
            $v["parent"] = $tmp[$v["parent_id"]] ?? (object) [];
        }
        $returndata = [];
        $url = $this->upload_url;
        $returndata["list"] = array_map(function ($v) use($url) {
            $v["head_img"] = isset($v["head_img"][0]) ? $url . $v["head_img"] : "";
            return $v;
        }, $news_menu);
        $returndata["limit"] = $this->limit;
        $returndata["page"] = $this->page;
        $returndata["param"] = $param;
        $returndata["orderby"] = $orderby;
        $returndata["sorting"] = $sorting;
        $returndata["total_page"] = ceil($count / $this->limit);
        $returndata["count"] = $count;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function getCatsPage()
    {
        $param = $this->request->param();
        $where = [];
        if (isset($param["title"][0])) {
            $where[] = ["title", "like", "%" . $param["search"] . "%"];
        }
        if (isset($param["status"])) {
            $where[] = ["status", "=", (int) $param["status"]];
        }
        if (isset($param["parent_id"]) && 0 < $param["parent_id"]) {
            $where[] = ["parent_id", "=", (int) $param["parent_id"]];
        }
        $list = \think\Db::name("news_type")->where($where)->page($this->page, $this->limit)->order(["id" => "DESC"])->select();
        $count = \think\Db::name("news_type")->where($where)->count();
        return jsonrule(["status" => 200, "data" => ["list" => $list, "meta" => ["total" => $count, "page" => $this->page, "limit" => $this->limit]]]);
    }
    public function getCateList()
    {
        $param = $this->request->param();
        $where = [];
        if (isset($param["status"])) {
            $where[] = ["status", "=", (int) $param["status"]];
        }
        if (isset($param["parent_id"])) {
            $where[] = ["parent_id", "=", (int) $param["parent_id"]];
        }
        $list = \think\Db::name("news_type")->where($where)->order(["parent_id" => "ASC", "id" => "DESC"])->select()->toArray();
        $data = [];
        foreach ($list as $v) {
            if (0 < $v["parent_id"] && isset($data[$v["parent_id"]])) {
                $data[$v["parent_id"]]["list"][$v["id"]] = $v;
            } else {
                $data[$v["id"]] = $v;
                $data[$v["id"]]["list"] = [];
            }
        }
        return jsonrule(["status" => 200, "data" => array_values($data)]);
    }
    public function getCatData(\think\Request $request)
    {
        $id = $request->param("id");
        if (empty($id)) {
            return jsonrule(["status" => 406, "msg" => Lang("ID ERROR")]);
        }
        $data = \think\Db::name("news_type")->where("id", $id)->find();
        if (empty($data)) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_EXIST")]);
        }
        return jsonrule(["status" => 200, "data" => $data]);
    }
    public function postEditCat(\think\Request $request)
    {
        $param = $request->param();
        if (isset($param["id"]) && (int) $param["id"] == 0) {
            unset($param["id"]);
        }
        if (isset($param["id"]) && $param["id"] < 3) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_DELETE")]);
        }
        $validate = new \app\admin\validate\NewsTypeValidate();
        if (!$validate->check($param)) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $data = ["title" => $param["title"], "parent_id" => $param["parent_id"] ?? 0, "hidden" => $param["hidden"] ?? 0, "status" => $param["status"] ?? 1, "sort" => $param["sort"] ?? 0];
        if ($param["alias"]) {
            $alias = str_replace(["/", "\\"], "", $param["alias"]);
            $model = \think\Db::name("news_type")->where("alias", $alias);
            if ($param["id"]) {
                $model = $model->where("id", "<>", $param["id"]);
            }
            $model = $model->find();
            if ($model) {
                return jsonrule(["status" => 406, "msg" => lang("ALIAS_IS_USE_ERROR")]);
            }
            $data["alias"] = $alias;
        }
        if (!empty($param["id"])) {
            $menu_type = \think\Db::name("news_type")->where("id", $param["id"])->find();
            if (!isset($menu_type["id"])) {
                return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_EXIST")]);
            }
            \think\Db::name("news_type")->where("id", $param["id"])->update($data);
            if ($param["hidden"] == 1) {
                active_log(sprintf($this->lang["News_admin_postEditCat1"], $param["id"], $menu_type["title"], $param["title"]));
            } else {
                active_log(sprintf($this->lang["News_admin_postEditCat2"], $param["id"], $menu_type["title"], $param["title"]));
            }
        } else {
            $nt = \think\Db::name("news_type")->insertGetId($data);
            active_log(sprintf($this->lang["News_admin_postaddCat"], $nt));
        }
        return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
    }
    public function getCheckalias(\think\Request $request)
    {
        $param = $request->param();
        $alias = trim($param["alias"]);
        if (!$alias) {
            return json(["status" => 400, "msg" => "别名不能为空"]);
        }
        $model = \think\Db::name("news_type")->field("id")->where("alias", $alias);
        if ($param["id"]) {
            $model = $model->where("id", "<>", $param["id"]);
        }
        $model = $model->find();
        return json(["status" => 200, "msg" => "success", "data" => $model ? 0 : 1]);
    }
    public function deleteCat(\think\Request $request)
    {
        $id = $request->param("id");
        if (empty($id)) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_EXIST")]);
        }
        if ($id <= 2) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_DELETE")]);
        }
        $tmp = \think\Db::name("news_type")->where("id", $id)->find();
        if (!isset($tmp["id"])) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_EXIST")]);
        }
        $news_data = \think\Db::name("news_menu")->where("parent_id", $id)->select()->toArray();
        if (isset($news_data["id"])) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_DELETE_ERROR")]);
        }
        $news_type = \think\Db::name("news_type")->where("parent_id", $id)->select()->toArray();
        if (isset($news_type["id"])) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_DELETE_ERROR_1")]);
        }
        try {
            \think\Db::name("news_type")->where("id", $id)->delete();
            active_log(sprintf($this->lang["News_admin_deletecat"], $id));
        } catch (\Exception $e) {
            return jsonrule(["status" => 406, "msg" => lang("DELETE FAIL")]);
        }
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function getContent(\think\Request $request)
    {
        $id = (int) $request->param("id", 0);
        if (!$id) {
            return jsonrule(["status" => 200, "data" => []]);
        }
        $new_data = \think\Db::name("news_menu")->find($id);
        if (empty($new_data)) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_NOT_EXIST")]);
        }
        $content = \think\Db::name("news")->where("relid", $id)->find();
        $new_data["content"] = htmlspecialchars_decode($content["content"]);
        return jsonrule(["status" => 200, "data" => $new_data]);
    }
    public function postEditContent(\think\Request $request)
    {
        $param = $request->param();
        $rule = ["id" => "number", "parent_id" => "require|number", "title" => "require|length:1,30", "read" => "number", "hidden" => "in:0,1", "sort" => "number"];
        $msg = ["parent_id.require" => "分类id不能为空", "title.require" => "新闻标题不能为空", "title.length" => "新闻标题为1到30的字符"];
        $validate = new \think\Validate($rule, $msg);
        $res = $validate->check($param);
        if (!$res) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        if (!empty($param["id"])) {
            $new_data = \think\Db::name("news_menu")->where("id", $param["id"])->find();
            if (empty($new_data)) {
                return jsonrule(["status" => 406, "msg" => lang("NEW_NOT_EXIST")]);
            }
        }
        if (empty($param["parent_id"])) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_TYPE_NOT_EXIST")]);
        }
        if (isset($param["head_img"][0])) {
            $upload = new \app\common\logic\Upload();
            $ret = $upload->moveTo($param["head_img"], $this->upload_path);
            if (isset($ret["error"])) {
                return ["status" => 400, "msg" => lang("IMAGE_UPLOAD_FAILED")];
            }
        }
        $id = $param["id"];
        $content = htmlspecialchars($param["content"]);
        $news_menu_data = ["parent_id" => $param["parent_id"], "admin_id" => cmf_get_current_admin_id(), "title" => $param["title"], "label" => $param["label"], "keywords" => $param["keywords"] ?? "", "description" => $param["description"] ?? "", "read" => $param["read"] ?? 0, "head_img" => $param["head_img"] ?? "", "hidden" => $param["hidden"] ?? 0, "sort" => $param["sort"] ?? 0, "push_time" => $param["push_time"] ?? time()];
        $new_content["content"] = $content;
        \think\Db::startTrans();
        try {
            if (!empty($id)) {
                $news_menu_data["update_time"] = time();
                $news = \think\Db::name("news")->field("content")->where("relid", $id)->find();
                $newsm = \think\Db::name("news_menu")->where("id", $id)->find();
                \think\Db::name("news_menu")->where("id", $id)->update($news_menu_data);
                \think\Db::name("news")->where("relid", $id)->update($new_content);
                $dec = "";
                if (!empty($param["title"]) && $param["title"] != $newsm["title"]) {
                    $dec .= "标题由“" . $newsm["title"] . "“改为”" . $param["title"] . "”，";
                }
                if (!empty($param["parent_id"]) && $param["parent_id"] != $newsm["parent_id"]) {
                    $dec .= "分类由“" . $newsm["parent_id"] . "“改为”" . $param["parent_id"] . "”，";
                }
                if ($param["hidden"] != $newsm["hidden"]) {
                    if ($param["hidden"] == 1) {
                        $dec .= "由“显示”改为“隐藏”，";
                    } else {
                        $dec .= "由“隐藏”改为“显示”，";
                    }
                }
                if (empty($dec)) {
                    $dec .= "什么都没有修改";
                }
                active_log(sprintf($this->lang["News_admin_postEditContent"], $id, $dec));
                unset($dec);
            } else {
                $news_menu_data["create_time"] = time();
                $new_id = \think\Db::name("news_menu")->insertGetId($news_menu_data);
                $new_content["relid"] = $new_id;
                \think\Db::name("news")->insertGetId($new_content);
                active_log(sprintf($this->lang["News_admin_postaddContent"], $new_id, $param["title"]));
            }
            \think\Db::commit();
            return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        } catch (\Exception $e) {
            \think\Db::rollback();
            if (!empty($file_name) && file_exists($this->upload_path . $file_name)) {
                @unlink($this->upload_path . $file_name);
            }
            return jsonrule(["status" => 406, "msg" => lang("ADD FAIL")]);
        }
    }
    public function deleteContent(\think\Request $request)
    {
        $id = $request->param("id");
        $id = intval($id);
        if (empty($id)) {
            return jsonrule(["status" => 406, "msg" => lang("ID_ERROR")]);
        }
        $tmp = \think\Db::name("news_menu")->where("id", "=", $id)->find();
        if (!isset($tmp["id"])) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_NOT_EXIST")]);
        }
        \think\Db::name("news_menu")->delete($id);
        active_log(sprintf($this->lang["News_admin_delete"], $id, $tmp["title"]));
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function getGetCustomParam(\think\Request $request)
    {
        try {
            $model = \think\Db::name("customfields")->field("id,fieldname")->where("type", "depot");
            $count = $model->count();
            $model = $model->page($this->page, $this->limit)->order("id", "desc")->select()->toArray();
            if (!$model) {
                return jsonrule(["status" => 200, "msg" => "success", "data" => ["count" => 0, "list" => []]]);
            }
            $ids = array_column($model, "id");
            $vals_data = \think\Db::name("customfieldsvalues")->whereIn("fieldid", $ids)->select()->toArray();
            $vals = array_column($vals_data, "value", "fieldid");
            foreach ($model as $key => $val) {
                $model[$key]["value"] = $vals[$val["id"]];
            }
            return jsonrule(["status" => 200, "msg" => "success", "data" => ["count" => $count, "list" => $model]]);
        } catch (\Throwable $e) {
            echo $e->getLine();
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
    public function getAddCustomParam(\think\Request $request)
    {
        \think\Db::startTrans();
        try {
            $param = $request->param();
            $this->checkCustomParam($request);
            $data = ["type" => "depot", "relid" => 0, "fieldname" => $param["fieldname"], "fieldtype" => "text", "description" => "站务自定义字段", "create_time" => time(), "update_time" => time()];
            $fields_id = \think\Db::name("customfields")->insertGetId($data);
            \think\Db::name("customfieldsvalues")->insert(["fieldid" => $fields_id, "relid" => 0, "value" => $param["value"]]);
            \think\Db::commit();
            return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        } catch (\Throwable $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
    public function getUpdateCustomParam(\think\Request $request)
    {
        \think\Db::startTrans();
        try {
            $param = $request->param();
            if (!$request->param("id")) {
                throw new \think\Exception("id不能为空");
            }
            $this->checkCustomParam($request);
            $data = ["fieldname" => $param["fieldname"], "update_time" => time()];
            \think\Db::name("customfields")->where("type", "depot")->where("id", $param["id"])->update($data);
            \think\Db::name("customfieldsvalues")->where("fieldid", $param["id"])->update(["value" => $param["value"]]);
            \think\Db::commit();
            return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
        } catch (\Throwable $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
    public function getDelCustomParam(\think\Request $request)
    {
        \think\Db::startTrans();
        try {
            $param = $request->param();
            \think\Db::name("customfields")->where("id", $param["id"])->where("type", "depot")->delete();
            \think\Db::name("customfieldsvalues")->where("fieldid", $param["id"])->delete();
            \think\Db::commit();
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        } catch (\Throwable $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
    public function getGetCustomUpdateVal(\think\Request $request)
    {
        try {
            $param = $request->param();
            $model = \think\Db::name("customfields")->where("id", intval($param["id"]))->find();
            if (!$model) {
                throw new \think\Exception("记录不存在");
            }
            $value = \think\Db::name("customfieldsvalues")->where("fieldid", intval($param["id"]))->find();
            if (!$value) {
                throw new \think\Exception("记录值不存在");
            }
            $data = ["id" => intval($param["id"]), "field" => $model["fieldname"], "value" => htmlspecialchars_decode($value["value"])];
            return jsonrule(["status" => 200, "msg" => "success", "data" => $data]);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
    }
    private final function checkCustomParam(\think\Request $request, $e = "")
    {
        if (!trim($request->param("fieldname"))) {
            throw new \think\Exception("自定义字段不能为空");
        }
        if (!trim($request->param("value"))) {
            throw new \think\Exception("自定义字段的值不能为空");
        }
        $model = \think\Db::name("customfields")->where(["fieldname" => $request->param("fieldname"), "type" => "depot"]);
        if (intval($request->param("id"))) {
            $model = $model->where("id", "<>", $request->param("id"));
        }
        $model = $model->find();
        if ($model) {
            if ($e) {
                throw new \think\Exception($e);
            }
            throw new \think\Exception("该自定义字段已存在");
        }
    }
    private final function getCustomId()
    {
        return \think\Db::name("customfields")->field("fieldname,id")->where("type", "depot")->page($this->page, $this->limit)->select()->order("id", "desc")->toArray();
    }
}

?>