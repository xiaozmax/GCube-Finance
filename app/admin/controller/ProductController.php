<?php


namespace app\admin\controller;

/**
 * @title 后台产品模块
 * @description 接口说明
 */
class ProductController extends AdminBaseController
{
    public function getProuductlistPage()
    {
        $param = $this->request->param();
        $page = !empty($param["page"]) ? intval($param["page"]) : config("page");
        $limit = !empty($param["limit"]) ? intval($param["limit"]) : config("limit");
        $order = isset($param["order"][0]) ? trim($param["order"]) : "id";
        $sort = isset($param["sort"][0]) ? trim($param["sort"]) : "DESC";
        $keywords = $param["keywords"];
        $name = $param["name"] ?? "name";
        if (!empty($keywords)) {
            $where[] = ["a.name", "like", "%" . $keywords . "%"];
        }
        $group_where = function (\think\db\Query $query) {
            if (file_exists(CMF_ROOT . "app/res/common.php") && function_exists("resourceCurl")) {
                $query->where("g_type", "<>", "resource");
            }
        };
        $first_group_data = \think\Db::name("product_first_groups")->where($group_where)->order("order", "asc")->select()->toArray();
        $total = \think\Db::name("product_first_groups")->count();
        foreach ($first_group_data as $key => $value) {
            $re["data"][$key] = $value;
            $re["data"][$key]["groups"] = [];
            $group_data = \think\Db::name("product_groups")->where("gid", $value["id"])->where("order_frm_tpl", "<>", "uuid")->order("order", "asc")->select()->toArray();
            $developer_app_product_type = config("developer_app_product_type");
            foreach ($group_data as $k => $v) {
                $product_data = \think\Db::name("products")->alias("a")->field("count(h.id) as count,a.id,a.name,a.gid,a.type,a.pay_method as type_zh,a.pay_type,a.qty,a.auto_setup,a.hidden,b.name as api_name,a.zjmf_api_id,a.stock_control")->leftJoin("zjmf_finance_api b", "a.zjmf_api_id = b.id")->leftJoin("host h", "h.productid = a.id")->group("a.id")->where("a.gid", $v["id"])->whereNotIn("a.type", array_keys($developer_app_product_type))->where($where)->withAttr("type_zh", function ($value, $data) {
                    $type = config("product_type")[$data["type"]];
                    if (0 < $data["zjmf_api_id"]) {
                        return "[" . $data["api_name"] . "]" . $type;
                    }
                    return $type;
                })->withAttr("pay_type", function ($value, $data) {
                    return config("product_paytype")[json_decode($value, true)["pay_type"]] ?? "";
                })->order("a.order", "asc")->select()->toArray();
                $product_data1 = \think\Db::name("products")->alias("a")->field("count(h.id) as count_active,a.id")->leftJoin("zjmf_finance_api b", "a.zjmf_api_id = b.id")->leftJoin("host h", "h.productid = a.id")->group("a.id")->where("a.gid", $v["id"])->whereNotIn("a.type", array_keys($developer_app_product_type))->where($where)->where("h.domainstatus", "Active")->order("a.order", "asc")->select()->toArray();
                foreach ($product_data as $p1 => $v1) {
                    $f = false;
                    foreach ($product_data1 as $k1 => $v2) {
                        if ($product_data1[$k1]["id"] == $product_data[$p1]["id"]) {
                            $f = true;
                            $product_data[$p1]["count_active"] = $product_data1[$k1]["count_active"];
                            if (!$f) {
                                $product_data[$p1]["count_active"] = 0;
                            }
                        }
                    }
                }
                if (!empty($keywords)) {
                    if (!empty($product_data[0])) {
                        $re["data"][$key]["groups"][$k] = $v;
                        $re["data"][$key]["groups"][$k]["products"] = $product_data;
                    }
                } else {
                    $re["data"][$key]["groups"][$k] = $v;
                    $re["data"][$key]["groups"][$k]["products"] = $product_data;
                }
            }
            if (empty($re["data"][$key]["groups"]) && !empty($keywords)) {
                unset($re["data"][$key]);
            } else {
                $re["data"][$key]["groups"] = dealarr($re["data"][$key]["groups"]);
            }
        }
        $re["data"] = dealarr($re["data"]);
        $re["total"] = $total;
        $re["status"] = 200;
        $re["msg"] = lang("SUCCESS MESSAGE");
        return jsonrule($re);
    }
    public function updateFirstGroupsort(\think\Request $request)
    {
        if ($request->isPost()) {
            $params = $this->request->param();
            $gid = $params["gid"];
            $pre_gid = $params["pre_gid"];
            if ($pre_gid) {
                $pre_order = \think\Db::name("product_first_groups")->where("id", $pre_gid)->value("order");
                $product_first_groups = \think\Db::name("product_first_groups")->field("id")->where("order", "<=", $pre_order)->select()->toArray();
                foreach ($product_first_groups as $product_first_group) {
                    \think\Db::name("product_first_groups")->where("id", $product_first_group["id"])->setDec("order");
                }
                \think\Db::name("product_first_groups")->where("id", $gid)->update(["order" => $pre_order]);
            } else {
                $min_order = \think\Db::name("product_first_groups")->min("order");
                \think\Db::name("product_first_groups")->where("id", $gid)->update(["order" => $min_order - 1]);
            }
            $re["status"] = 200;
            $re["msg"] = lang("修改排序成功");
            return jsonrule($re);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("FAIL MESSAGE")]);
        }
    }
    public function updateGroupsort(\think\Request $request)
    {
        if ($request->isPost()) {
            $params = $this->request->param();
            $pid = $params["pid"];
            $gid = $params["gid"];
            $pre_pid = $params["pre_pid"];
            $current_gid = $params["current_gid"];
            if ($gid == $current_gid) {
                if ($pre_pid) {
                    $pre_order = \think\Db::name("product_groups")->where("id", $pre_pid)->value("order");
                    $product_groups = \think\Db::name("product_groups")->field("id")->where("gid", $gid)->where("order", "<=", $pre_order)->select()->toArray();
                    foreach ($product_groups as $product_group) {
                        \think\Db::name("product_groups")->where("id", $product_group["id"])->setDec("order");
                    }
                    \think\Db::name("product_groups")->where("id", $pid)->update(["order" => $pre_order]);
                } else {
                    $min_order = \think\Db::name("product_groups")->where("gid", $gid)->min("order");
                    \think\Db::name("product_groups")->where("id", $pid)->update(["order" => $min_order - 1]);
                }
            } else {
                if ($pre_pid) {
                    $pre_order = \think\Db::name("product_groups")->where("id", $pre_pid)->value("order");
                    $product_groups = \think\Db::name("product_groups")->field("id")->where("gid", $current_gid)->where("order", "<=", $pre_order)->select()->toArray();
                    foreach ($product_groups as $product_group) {
                        \think\Db::name("product_groups")->where("id", $product_group["id"])->setDec("order");
                    }
                    \think\Db::name("product_groups")->where("id", $pid)->update(["order" => $pre_order, "gid" => $current_gid]);
                } else {
                    $min_order = \think\Db::name("product_groups")->where("gid", $current_gid)->min("order");
                    \think\Db::name("product_groups")->where("id", $pid)->update(["order" => $min_order - 1, "gid" => $current_gid]);
                }
            }
            $re["status"] = 200;
            $re["msg"] = lang("修改排序成功");
            return jsonrule($re);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("FAIL MESSAGE")]);
        }
    }
    public function updateProductsort(\think\Request $request)
    {
        if ($request->isPost()) {
            $params = $this->request->param();
            $pid = $params["pid"];
            $gid = $params["gid"];
            $pre_pid = $params["pre_pid"];
            $current_gid = $params["current_gid"];
            if ($gid == $current_gid) {
                if ($pre_pid) {
                    $pre_order = \think\Db::name("products")->where("id", $pre_pid)->value("order");
                    $products = \think\Db::name("products")->field("id")->where("gid", $gid)->where("order", "<=", $pre_order)->select()->toArray();
                    foreach ($products as $product) {
                        \think\Db::name("products")->where("id", $product["id"])->setDec("order");
                    }
                    \think\Db::name("products")->where("id", $pid)->update(["order" => $pre_order]);
                } else {
                    $min_order = \think\Db::name("products")->where("gid", $gid)->min("order");
                    \think\Db::name("products")->where("id", $pid)->update(["order" => $min_order - 1]);
                }
            } else {
                if ($pre_pid) {
                    $pre_order = \think\Db::name("products")->where("id", $pre_pid)->value("order");
                    $products = \think\Db::name("products")->field("id")->where("gid", $current_gid)->where("order", "<=", $pre_order)->select()->toArray();
                    foreach ($products as $product) {
                        \think\Db::name("products")->where("id", $product["id"])->setDec("order");
                    }
                    \think\Db::name("products")->where("id", $pid)->update(["order" => $pre_order, "gid" => $current_gid]);
                } else {
                    $min_order = \think\Db::name("products")->where("gid", $current_gid)->min("order");
                    \think\Db::name("products")->where("id", $pid)->update(["order" => $min_order - 1, "gid" => $current_gid]);
                }
            }
            $re["status"] = 200;
            $re["msg"] = lang("修改排序成功");
            return jsonrule($re);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("FAIL MESSAGE")]);
        }
    }
    public function editGroupPage(\think\Request $request)
    {
        $param = $this->request->param();
        $where = [];
        if (isset($param["type"]) && 0 < $param["type"] && $param["type"] < 4) {
            $where[] = ["type", "=", (int) $param["type"]];
        }
        $id = $request->id;
        $gatewayPlugin = gateway_list("gateways");
        $gatewayArr = [];
        foreach ($gatewayPlugin as $key => $value) {
            $gatewayArr[$value["name"]] = $value["title"];
        }
        if (is_numeric($id) && !empty($id)) {
            $product_groups = \think\Db::name("product_groups")->field("id,name,headline,tagline,order_frm_tpl,hidden,order,disabled_gateways,type,gid,tpl_type,alias,is_upstream,zjfm_api_id")->where("id", $id)->where($where)->find();
            $product_groups["customfields"] = \think\Db::name("product_groups_customfields")->field("name,value")->where("relid", $id)->select()->toArray();
            $re["data"]["productgroup"] = $product_groups;
            $disabled_gateways = explode(",", $product_groups["disabled_gateways"]);
            foreach ($gatewayArr as $k => $v) {
                if (!in_array($k, $disabled_gateways)) {
                    $re["data"]["allgateways"][] = $k;
                }
            }
        }
        $re["data"]["activeGatewayArr"] = $gatewayArr;
        $re["data"]["firstGroups"] = \think\Db::name("product_first_groups")->select()->toArray();
        $re["data"]["default_page"] = configuration("order_page_style") ?: "default";
        $re["data"]["cart_themes"] = get_files(WEB_ROOT . "themes/cart");
        $resource = \think\Db::name("zjmf_finance_api")->where("is_resource", 1)->where("is_using", 1)->order("id", "desc")->find();
        if ($resource) {
            $re["data"]["resourceFirstGroups"] = \think\Db::name("product_first_groups")->where(["zjmf_api_id" => $resource["id"], "is_upstream" => 1])->select()->toArray();
            $re["data"]["resource"] = $resource["id"];
        }
        $re["status"] = 200;
        $re["msg"] = lang("SUCCESS MESSAGE");
        return jsonrule($re);
    }
    public function saveProductGroup(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["id" => "number", "name" => "require|max:255", "headline" => "max:255", "tagline" => "max:255", "type" => "require|number", "order_frm_tpl" => "require", "gid" => "require|number", "tpl_type" => "require|in:default,custom"];
            $msg = ["name.max" => lang("THE_NAME_CANNOT_EXCEED_CHARAC"), "headline.max" => lang("PRODUCT_GROUP_TITLE_CANNOT_EXC"), "tagline.max" => lang("PRODUCT_GROUP_SLOGAN_CANNOT_EX"), "order_frm_tpl.require" => lang("ORDER_FORM_TEMPLATE_CANNOT_BE"), "gid.require" => "一级分组不能为空"];
            $des = "";
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $group_exist = \think\Db::name("product_first_groups")->where("id", $param["gid"])->find();
            if (empty($group_exist)) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            $gateways = gateway_list();
            $gatewaysArr = array_column($gateways, "name");
            $allowGateways = $param["gateways"];
            $intersection = array_diff($gatewaysArr, $allowGateways);
            $disabled_gateways = implode(",", $intersection);
            if (!empty($param["id"])) {
                $pg = \think\Db::name("product_groups")->where("id", $param["id"])->find();
                $old_customfields = \think\Db::name("product_groups_customfields")->where("relid", $param["id"])->select()->toArray();
            }
            $data["name"] = $param["name"] ?: "";
            if (!empty($data["name"]) && $data["name"] != $pg["name"]) {
                $des .= "产品组名称由“" . $pg["name"] . "”改为“" . $data["name"] . "”,";
            }
            $data["headline"] = $param["headline"] ?: "";
            if (!empty($data["headline"]) && $data["headline"] != $pg["headline"]) {
                $des .= "产品组标题由“" . $pg["headline"] . "”改为“" . $data["headline"] . "”,";
            }
            $data["tagline"] = $param["tagline"] ?: "";
            if (!empty($data["headline"]) && $data["tagline"] != $pg["tagline"]) {
                $des .= "产品组标语由“" . $pg["tagline"] . "”改为“" . $data["tagline"] . "”,";
            }
            $data["disabled_gateways"] = $disabled_gateways ?: "";
            $data["hidden"] = isset($param["hidden"]) ? intval($param["hidden"]) : 0;
            if ($data["hidden"] != $pg["hidden"]) {
                if ($data["hidden"] == 1) {
                    $des .= "是否隐藏由“显示”改为“隐藏”，";
                } else {
                    $des .= "是否隐藏由“隐藏”改为“显示”，";
                }
            }
            $data["type"] = $param["type"];
            $data["gid"] = $param["gid"];
            if ($param["alias"]) {
                $alias = str_replace(["/", "\\"], "", $param["alias"]);
                $model = \think\Db::name("product_groups")->where("alias", $alias);
                if ($param["id"]) {
                    $model = $model->where("id", "<>", $param["id"]);
                }
                $model = $model->find();
                if ($model) {
                    return jsonrule(["status" => 400, "msg" => lang("ALIAS_IS_USE_ERROR")]);
                }
                $data["alias"] = $alias;
            }
            $data["update_time"] = time();
            $data["tpl_type"] = $param["tpl_type"];
            if ($data["tpl_type"] != "default") {
                $data["order_frm_tpl"] = $param["order_frm_tpl"] ?: "default";
                if (!empty($data["order_frm_tpl"]) && $data["order_frm_tpl"] != $pg["order_frm_tpl"]) {
                    $des .= "订购表格模板由“" . $pg["tagline"] . "”改为“" . $data["order_frm_tpl"] . "”,";
                }
            }
            $data["is_upstream"] = intval($param["is_upstream"]);
            if (!empty($param["is_resource"])) {
                $resource = \think\Db::name("zjmf_finance_api")->where("is_resource", 1)->where("is_using", 1)->order("id", "desc")->find();
                if (empty($resource)) {
                    return jsonrule(["status" => 400, "msg" => "资源池账户不存在"]);
                }
                $data["zjfm_api_id"] = intval($resource["id"]);
            } else {
                $data["zjfm_api_id"] = intval($param["zjfm_api_id"]);
            }
            if (!empty($param["customfields"])) {
                if (count(array_filter(array_unique(array_column($param["customfields"], "name")))) != count($param["customfields"])) {
                    return jsonrule(["status" => 400, "msg" => "自定义字段参数错误"]);
                }
                $customfields_change = false;
                if (!empty($old_customfields)) {
                    $old_customfields = array_column($old_customfields, "value", "name");
                    $new_customfields = array_column($param["customfields"], "value", "name");
                    if (count($old_customfields) != count($new_customfields)) {
                        $des .= "自定义字段修改，";
                        $customfields_change = true;
                    } else {
                        foreach ($old_customfields as $k => $v) {
                            if (!isset($new_customfields[$k]) || $v != $new_customfields[$k]) {
                                $des .= "自定义字段修改，";
                                $customfields_change = true;
                            }
                        }
                    }
                } else {
                    $des .= "自定义字段添加，";
                    $customfields_change = true;
                }
            } else {
                if (!empty($old_customfields)) {
                    $des .= "自定义字段移除，";
                    $customfields_change = true;
                }
            }
            if (!empty($param["id"])) {
                $id = $param["id"];
                \think\Db::name("product_groups")->where("id='" . $id . "'")->update($data);
                if (isset($customfields_change) && $customfields_change === true) {
                    \think\Db::name("product_groups_customfields")->where("relid", $id)->delete();
                    if (!empty($param["customfields"])) {
                        foreach ($param["customfields"] as $k => $v) {
                            \think\Db::name("product_groups_customfields")->insertGetId(["relid" => $id, "name" => $v["name"], "value" => $v["value"], "create_time" => time()]);
                        }
                    }
                }
                if (empty($des)) {
                    $des .= "未做任何修改";
                }
                active_log(sprintf($this->lang["Product_admin_saveProductGroup"], $id, $des));
                return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
            } else {
                $maxorder = $this->getOrderId("product_groups");
                $data["order"] = $maxorder;
                $data["create_time"] = time();
                $id = \think\Db::name("product_groups")->insertGetId($data);
                if (!empty($param["customfields"])) {
                    foreach ($param["customfields"] as $k => $v) {
                        \think\Db::name("product_groups_customfields")->insertGetId(["relid" => $id, "name" => $v["name"], "value" => $v["value"], "create_time" => time()]);
                    }
                }
                active_log(sprintf($this->lang["Product_admin_saveProductGroup_add"], $data["name"], $id));
                return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
            }
        } else {
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function checkAlias(\think\Request $request)
    {
        $param = $request->param();
        $alias = trim($param["alias"]);
        if (!$alias) {
            return json(["status" => 400, "msg" => "别名不能为空"]);
        }
        $model = \think\Db::name("product_groups")->where("alias", $alias);
        if ($param["id"]) {
            $model = $model->where("id", "<>", $param["id"]);
        }
        $model = $model->find();
        return json(["status" => 200, "msg" => "success", "data" => $model ? 0 : 1]);
    }
    public function editFirstGroupPage(\think\Request $request)
    {
        $param = $this->request->param();
        $id = $request->id;
        if (is_numeric($id) && !empty($id)) {
            $product_first_groups = \think\Db::name("product_first_groups")->field("id,name,hidden,order,is_upstream,zjmf_api_id")->where("id", $id)->find();
            $product_first_groups["customfields"] = \think\Db::name("product_first_groups_customfields")->field("name,value")->where("relid", $id)->select()->toArray();
            $re["data"]["productfirstgroup"] = $product_first_groups;
        }
        $re["status"] = 200;
        $re["msg"] = lang("SUCCESS MESSAGE");
        return jsonrule($re);
    }
    public function saveProductFirstGroup(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["id" => "number", "name" => "require|max:255"];
            $msg = ["name.max" => lang("THE_NAME_CANNOT_EXCEED_CHARAC")];
            $des = "";
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            if (!empty($param["id"])) {
                $pg = \think\Db::name("product_first_groups")->where("id", $param["id"])->find();
                $old_customfields = \think\Db::name("product_first_groups_customfields")->where("relid", $param["id"])->select()->toArray();
            }
            $data["name"] = $param["name"] ?: "";
            if (!empty($data["name"]) && $data["name"] != $pg["name"]) {
                $des .= "一级组名称由“" . $pg["name"] . "”改为“" . $data["name"] . "”,";
            }
            $data["hidden"] = isset($param["hidden"]) ? intval($param["hidden"]) : 0;
            if ($data["hidden"] != $pg["hidden"]) {
                if ($data["hidden"] == 1) {
                    $des .= "是否隐藏由“显示”改为“隐藏”，";
                } else {
                    $des .= "是否隐藏由“隐藏”改为“显示”，";
                }
            }
            $data["update_time"] = time();
            $data["is_upstream"] = intval($param["is_upstream"]);
            if (!empty($param["is_resource"])) {
                $resource = \think\Db::name("zjmf_finance_api")->where("is_resource", 1)->where("is_using", 1)->order("id", "desc")->find();
                if (empty($resource)) {
                    return jsonrule(["status" => 400, "msg" => "资源池账户不存在"]);
                }
                $data["zjmf_api_id"] = intval($resource["id"]);
            } else {
                $data["zjmf_api_id"] = intval($param["zjmf_api_id"]);
            }
            if (!empty($param["customfields"])) {
                if (count(array_filter(array_unique(array_column($param["customfields"], "name")))) != count($param["customfields"])) {
                    return jsonrule(["status" => 400, "msg" => "自定义字段参数错误"]);
                }
                $customfields_change = false;
                if (!empty($old_customfields)) {
                    $old_customfields = array_column($old_customfields, "value", "name");
                    $new_customfields = array_column($param["customfields"], "value", "name");
                    if (count($old_customfields) != count($new_customfields)) {
                        $des .= "自定义字段修改，";
                        $customfields_change = true;
                    } else {
                        foreach ($old_customfields as $k => $v) {
                            if (!isset($new_customfields[$k]) || $v != $new_customfields[$k]) {
                                $des .= "自定义字段修改，";
                                $customfields_change = true;
                            }
                        }
                    }
                } else {
                    $des .= "自定义字段添加，";
                    $customfields_change = true;
                }
            } else {
                if (!empty($old_customfields)) {
                    $des .= "自定义字段移除，";
                    $customfields_change = true;
                }
            }
            if (!empty($param["id"])) {
                $id = $param["id"];
                \think\Db::name("product_first_groups")->where("id='" . $id . "'")->update($data);
                if (isset($customfields_change) && $customfields_change === true) {
                    \think\Db::name("product_first_groups_customfields")->where("relid", $id)->delete();
                    if (!empty($param["customfields"])) {
                        foreach ($param["customfields"] as $k => $v) {
                            \think\Db::name("product_first_groups_customfields")->insertGetId(["relid" => $id, "name" => $v["name"], "value" => $v["value"], "create_time" => time()]);
                        }
                    }
                }
                if (empty($des)) {
                    $des .= "未做任何修改";
                }
                active_log(sprintf("保存一级分组#ProductFirstGroup ID:%d，%s", $id, $des));
                return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
            } else {
                $maxorder = $this->getOrderId("product_first_groups");
                $data["order"] = $maxorder;
                $data["create_time"] = time();
                $id = \think\Db::name("product_first_groups")->insertGetId($data);
                if (!empty($param["customfields"])) {
                    foreach ($param["customfields"] as $k => $v) {
                        \think\Db::name("product_first_groups_customfields")->insertGetId(["relid" => $id, "name" => $v["name"], "value" => $v["value"], "create_time" => time()]);
                    }
                }
                active_log(sprintf("添加一级分组:%s - ProductFirstGroup ID:%d", $data["name"], $id));
                return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
            }
        } else {
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function delete(\think\Request $request)
    {
        $id = $request->param("id");
        if (empty($id) || !is_numeric($id)) {
            return jsonrule(["status" => 400, "msg" => lang("PRODUCT_CANNOT_BE_EMPTY")]);
        }
        $hosts = \think\Db::name("host")->where("productid", $id)->select()->toArray();
        if (!empty($hosts)) {
            return jsonrule(["status" => 400, "msg" => lang("YOU_CANNOT_DELETE_A_PRODUCT_TH")]);
        }
        $pro = \think\Db::name("products")->where("id", $id)->find();
        $currencies = \think\Db::name("currencies")->field("id")->select()->toArray();
        \think\Db::startTrans();
        try {
            \think\Db::name("products")->where("id", $id)->delete();
            foreach ($currencies as $currency) {
                \think\Db::name("pricing")->where("type", "product")->where("currency", $currency["id"])->where("relid", $id)->delete();
            }
            $links = \think\Db::name("product_config_links")->field("gid")->where("pid", $id)->select()->toArray();
            foreach ($links as $v) {
                $count = \think\Db::name("product_config_links")->where("gid", $v["gid"])->count();
                if ($count <= 1) {
                    $cids = \think\Db::name("product_config_options")->where("gid", $v["gid"])->column("id");
                    $subids = \think\Db::name("product_config_options_sub")->whereIn("config_id", $cids)->column("id");
                    \think\Db::name("pricing")->where("type", "configoptions")->whereIn("relid", $subids)->delete();
                    \think\Db::name("product_config_options_sub")->whereIn("config_id", $cids)->delete();
                    \think\Db::name("product_config_options")->where("gid", $v["gid"])->delete();
                    \think\Db::name("product_config_links")->where("gid", $v["gid"])->delete();
                    \think\Db::name("product_config_groups")->where("id", $v["gid"])->delete();
                }
            }
            $evaluation = \think\Db::name("evaluation")->field("id")->where("rid", $id)->where("type", "products")->select()->toArray();
            foreach ($links as $v) {
                \think\Db::name("evaluation_like")->where("eid", $v["id"])->delete();
            }
            \think\Db::name("evaluation")->where("rid", $id)->where("type", "products")->delete();
            \think\Db::name("app_version")->where("pid", $id)->delete();
            \think\Db::name("app_favorite")->where("pid", $id)->delete();
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
        }
        active_log(sprintf($this->lang["Product_admin_delete"], $pro["name"], $id));
        hook("product_delete", ["pid" => $id]);
        $logic = new \app\common\logic\Product();
        $logic->deleteDetailCache([$id]);
        $logic->updateInfoCache();
        $logic->updateListCache([$id], true);
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function deleteGroup(\think\Request $request)
    {
        $id = $request->param("id");
        if (empty($id) || !is_numeric($id)) {
            return jsonrule(["status" => 400, "msg" => lang("PRODUCT_CANNOT_BE_EMPTY")]);
        }
        $hosts = \think\Db::name("products")->where("gid", $id)->select()->toArray();
        if (!empty($hosts)) {
            return jsonrule(["status" => 400, "msg" => lang("THERE_ARE_PRODUCTS_UNDER_THIS")]);
        }
        $groups = \think\Db::name("product_groups")->where("id", $id)->find();
        \think\Db::name("product_groups")->where("id", $id)->delete();
        \think\Db::name("product_groups_customfields")->where("relid", $id)->delete();
        active_log(sprintf($this->lang["Product_admin_deleteGroup"], $groups["name"], $id));
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function deleteFirstGroup(\think\Request $request)
    {
        $id = $request->param("id");
        if (empty($id) || !is_numeric($id)) {
            return jsonrule(["status" => 400, "msg" => lang("PRODUCT_CANNOT_BE_EMPTY")]);
        }
        if ($id == 1) {
            return jsonrule(["status" => 400, "msg" => "不能删除id为1的分组"]);
        }
        $product_first_groups = \think\Db::name("product_first_groups")->where("id", $id)->find();
        if (empty($product_first_groups)) {
            return jsonrule(["status" => 401, "msg" => "未找到一级分组"]);
        }
        $groups = \think\Db::name("product_groups")->where("gid", $id)->select()->toArray();
        if (!empty($groups)) {
            return jsonrule(["status" => 400, "msg" => "此一级分组下还有产品分组，无法删除"]);
        }
        $groups = \think\Db::name("product_first_groups")->where("id", $id)->find();
        \think\Db::name("product_first_groups")->where("id", $id)->delete();
        \think\Db::name("product_first_groups_customfields")->where("relid", $id)->delete();
        active_log(sprintf("删除一级分组:%s - ProductFirstGroup ID:%d", $product_first_groups["name"], $id));
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function duplicatePage()
    {
        return jsonrule(["status" => 200, "data" => []]);
    }
    public function duplicate()
    {
        $existingproduct = $this->request->param("existingproduct");
        $newproductname = $this->request->param("newproductname");
        if (empty($existingproduct)) {
            return jsonrule(["status" => 406, "msg" => lang("ORIGINAL_PRODUCT_ID_ERROR")]);
        }
        if (empty($newproductname)) {
            return jsonrule(["status" => 406, "msg" => lang("NEW_PRODUCT_NAME_CANNOT_BE_EMP")]);
        }
        $existingproduct = intval($existingproduct);
        $newproductname = strval($newproductname);
        $product_data = \think\Db::name("products")->field("id,name,create_time,update_time,order", true)->where("id", $existingproduct)->find();
        if (empty($product_data)) {
            return jsonrule(["status" => 406, "msg" => lang("PRODUCT_DATA_TO_COPY_NOT_FOUND")]);
        }
        \think\Db::startTrans();
        try {
            $time = time();
            $product_data["name"] = $newproductname;
            $maxsort = $this->getOrderId("products");
            $product_data["order"] = $maxsort;
            $product_data["create_time"] = $time;
            unset($product_data["resource_pid"]);
            $newpid = \think\Db::name("products")->insertGetId($product_data);
            $gids = \think\Db::name("product_config_links")->alias("a")->leftJoin("product_config_groups b", "a.gid = b.id")->where("a.pid", $existingproduct)->where("b.global", 1)->column("a.gid");
            $links = [];
            if (!empty($gids)) {
                foreach ($gids as $v) {
                    $link = [];
                    $link["gid"] = $v;
                    $link["pid"] = $newpid;
                    $links[] = $link;
                }
                \think\Db::name("product_config_links")->insertAll($links);
            }
            $groups = \think\Db::name("product_config_links")->alias("a")->field("b.id,b.name,b.description,b.upstream_id")->leftJoin("product_config_groups b", "a.gid = b.id")->where("a.pid", $existingproduct)->where("b.global", 0)->order("a.id", "asc")->select()->toArray();
            foreach ($groups as $group) {
                $new_gid = \think\Db::name("product_config_groups")->insertGetId(["name" => $newproductname . "配置项组", "description" => $group["description"] ?? "配置项组描述", "upstream_id" => $group["upstream_id"] ?? 0]);
                \think\Db::name("product_config_links")->insertGetId(["gid" => $new_gid, "pid" => $newpid]);
                $options = \think\Db::name("product_config_options")->whereIn("gid", array_column($groups, "id"))->select()->toArray();
                $lingAgeArr = [];
                $map_link = [];
                foreach ($options as $ov) {
                    $oid = $ov["id"];
                    unset($ov["id"]);
                    $ov["gid"] = $new_gid;
                    $ov["copy_id"] = $oid;
                    $new_oid = \think\Db::name("product_config_options")->insertGetId($ov);
                    $lingAgeArr[] = $new_oid;
                    $sub_options = \think\Db::name("product_config_options_sub")->where("config_id", $oid)->select()->toArray();
                    $map = [];
                    foreach ($sub_options as $sv) {
                        $sub_id = $sv["id"];
                        unset($sv["id"]);
                        $sv["config_id"] = $new_oid;
                        $sv["copy_id"] = $sub_id;
                        $new_sub_id = \think\Db::name("product_config_options_sub")->insertGetId($sv);
                        $map[$sub_id] = $new_sub_id;
                        $pricings = \think\Db::name("pricing")->where("type", "configoptions")->where("relid", $sub_id)->select()->toArray();
                        $new_pricings = [];
                        foreach ($pricings as $pv) {
                            unset($pv["id"]);
                            $pv["relid"] = $new_sub_id;
                            $new_pricings[] = $pv;
                        }
                        \think\Db::name("pricing")->insertAll($new_pricings);
                    }
                    $advance_links = \think\Db::name("product_config_options_links")->where("config_id", $oid)->order("id", "asc")->select()->toArray();
                    foreach ($advance_links as $advance_link) {
                        $advance_sub_ids = json_decode($advance_link["sub_id"], true);
                        $new_advance_sub_ids = [];
                        foreach ($advance_sub_ids as $k => $advance_sub_id) {
                            $new_advance_sub_ids[$map[$k]] = $advance_sub_id;
                        }
                        $link_id = \think\Db::name("product_config_options_links")->insertGetId(["config_id" => $new_oid, "sub_id" => json_encode($new_advance_sub_ids), "relation" => $advance_link["relation"], "type" => $advance_link["type"], "relation_id" => 0, "upstream_id" => 0]);
                        $map_link[$advance_link["id"]] = $link_id;
                    }
                }
                foreach ($map_link as $k1 => $v1) {
                    $tmp = \think\Db::name("product_config_options_links")->where("id", $k1)->find();
                    if ($tmp["type"] == "result") {
                        $old_relation_id = $tmp["relation_id"];
                        $new_relation_id = $map_link[$old_relation_id];
                        \think\Db::name("product_config_options_links")->where("id", $v1)->where("type", "result")->update(["relation_id" => $new_relation_id]);
                    }
                }
                (new \app\common\model\ProductModel())->setLinkAge("copy")->handleLingAge($lingAgeArr);
                $pricing = \think\Db::name("pricing")->field("id,relid", true)->where("relid", $existingproduct)->where("type", "product")->select()->toArray();
                if (!empty($pricing)) {
                    foreach ($pricing as $key => $value) {
                        $pricing[$key]["relid"] = $newpid;
                    }
                    \think\Db::name("pricing")->insertAll($pricing);
                }
                $customfields = \think\Db::name("customfields")->field("id,relid,create_time,update_time", true)->where("relid", $existingproduct)->where("type", "product")->select()->toArray();
                if (!empty($customfields)) {
                    foreach ($customfields as $key => $value) {
                        $customfields[$key]["relid"] = $newpid;
                        $customfields[$key]["create_time"] = $time;
                    }
                    \think\Db::name("customfields")->insertAll($customfields);
                }
                $upgrade_products = \think\Db::name("product_upgrade_products")->field("product_id, upgrade_product_id")->where("product_id", $existingproduct)->select()->toArray();
                if (!empty($upgrade_products)) {
                    foreach ($upgrade_products as $key => $value) {
                        $upgrade_products[$key]["product_id"] = $newpid;
                        $upgrade_products[$key]["create_time"] = $time;
                    }
                    \think\Db::name("product_upgrade_products")->insertAll($upgrade_products);
                }
                $download = \think\Db::name("product_downloads")->field("product_id,download_id")->where("product_id", $existingproduct)->select()->toArray();
                if (!empty($download)) {
                    foreach ($download as $key => $value) {
                        $download[$key]["product_id"] = $newpid;
                    }
                    \think\Db::name("product_downloads")->insertAll($download);
                }
                active_log(sprintf($this->lang["Product_admin_duplicate"], $existingproduct, $newproductname));
                \think\Db::commit();
            }
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 406, "msg" => lang("DUPLICATE FAIL") . $e->getMessage()]);
        }
        (new \app\common\logic\Product())->updateCache([$newpid]);
        return jsonrule(["status" => 200, "msg" => lang("DUPLICATE SUCCESS"), "pid" => $newpid]);
    }
    public function addPage()
    {
        $param = $this->request->param();
        $where = [];
        $groupdata = \think\Db::name("product_groups")->field("id,name")->where($where)->order("order", "asc")->select();
        $re["status"] = 200;
        $re["msg"] = lang("SUCCESS MESSAGE");
        $re["data"]["groupdata"] = $groupdata;
        $re["data"]["type"] = config("product_type");
        $re["data"]["ptype"] = $this->getProductType();
        return jsonrule($re);
    }
    private function getProductType($pid = 0)
    {
        $list = (new \app\common\logic\Menu())->getOneNavs("client", NULL);
        $p_list = array_filter($list, function ($v) {
            return $v["nav_type"] == 2;
        });
        if ($pid) {
            foreach ($p_list as $key => $val) {
                $p_list[$key]["is_active"] = 0;
                if (in_array($pid, explode(",", $val["relid"]))) {
                    $p_list[$key]["is_active"] = 1;
                }
            }
        }
        return array_values($p_list);
    }
    private function getOrderId($table = "")
    {
        $ordermax = \think\Db::name((string) $table)->max("order");
        if ($ordermax) {
            return $ordermax + 1;
        }
        return 1;
    }
    public function create(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["type" => "require|in:" . getProductTypeString(), "gid" => "require|number", "productname" => "require|max:255"];
            $msg = ["type.require" => lang("PRODUCT_TYPE_CANNOT_BE_EMPTY"), "type.in" => lang("WRONG_PRODUCT_TYPE"), "gid.require" => lang("PRODUCT_GROUP_CANNOT_BE_EMPTY"), "gid.number" => lang("PRODUCT_GROUP_MUST_BE_A_NUMBER"), "productname.require" => lang("PRODUCT_NAME_CANNOT_BE_EMPTY"), "productname.max" => lang("PRODUCT_NAME_CANNOT_EXCEED_CH")];
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            if (!$param["ptype"]) {
                return jsonrule(["status" => 406, "msg" => "请选择前台导航页面"]);
            }
            $data["type"] = $param["type"];
            $group_exist = \think\Db::name("product_groups")->where("id", $param["gid"])->find();
            if (empty($group_exist)) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            $data["gid"] = $param["gid"];
            $data["name"] = $param["productname"];
            $maxsort = $this->getOrderId("products");
            $data["order"] = $maxsort;
            $data["create_time"] = time();
            $data["affiliate_pay_type"] = "default";
            $pay_type = ["pay_type" => "free", "pay_hour_cycle" => 0, "pay_day_cycle" => 0, "pay_ontrial_status" => 0, "pay_ontrial_cycle" => 0, "pay_ontrial_condition" => [], "pay_ontrial_cycle_type" => "day"];
            $data["pay_type"] = json_encode($pay_type);
            \think\Db::startTrans();
            try {
                $id = \think\Db::name("products")->insertGetId($data);
                $data1["product_shopping_url"] = request()->domain() . "/cart?action=configureproduct&pid=" . $id;
                $first_gid = \think\Db::name("product_groups")->where("id", $data["gid"])->value("gid");
                $data1["product_group_url"] = request()->domain() . "/cart?gid=" . $data["gid"] . "&fid=" . $first_gid;
                $data1["groupid"] = 0;
                $this->setProToNav($param, $id);
                \think\Db::name("products")->where("id", $id)->update($data1);
                \app\common\logic\Pricing::add($id, "product");
                if (isset($param["upstream_price_value"])) {
                    \think\Db::name("products")->where("id", $id)->update(["upstream_price_value" => $param["upstream_price_value"]]);
                }
                \think\Db::commit();
                active_log(sprintf($this->lang["Product_admin_create"], $data["name"], $id));
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
            }
            hook("product_create", ["pid" => $id]);
            (new \app\common\logic\Product())->updateCache([$id]);
            return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS"), "id" => $id]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    private function setProToNav($param, $id)
    {
        $menu = new \app\common\logic\Menu();
        $menu_list = $menu->getOneNavs("client", NULL);
        if (!$param["ptype"] || !isset($menu_list[$param["ptype"]])) {
            throw new \Exception("前台导航页面不存在！");
        }
        foreach ($menu_list as $key => $val) {
            if ($val["nav_type"] == 2) {
                $relid = explode(",", $val["relid"]);
                $is_exits = array_search($id, $relid);
                if ($is_exits !== false) {
                    unset($relid[$is_exits]);
                    $menu_list[$key]["relid"] = implode(",", $relid);
                }
            }
        }
        $p_relid = explode(",", $menu_list[$param["ptype"]]["relid"]);
        $menu_list[$param["ptype"]]["relid"] = implode(",", array_merge($p_relid, [$id]));
        return $menu->editDefaultNav($menu_list);
    }
    public function getUpstreamProducts()
    {
        $params = $this->request->param();
        if (isset($params["id"])) {
            $id = $params["id"] ?? "";
        } else {
            $id = \think\Db::name("zjmf_finance_api")->where("is_resource", 1)->order("id", "desc")->value("id") ?: 0;
        }
        $list = getZjmfUpstreamProducts($id);
        $currency = \think\Db::name("currencies")->where("default", 1)->value("code");
        $upstream_currency = $list["currency"];
        $arr = getRate("json");
        if ($currency == $upstream_currency) {
            $rate = 1;
        } else {
            $rate = bcdiv($arr[$currency], $arr[$upstream_currency], 2);
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $list["products"] ?? [], "upstream_currency" => $upstream_currency ?? "", "currency" => $currency ?? "", "rate" => $rate, "product_type" => config("product_type")]);
    }
    public function syncProductInfo()
    {
        $param = $this->request->param();
        $id = intval($param["pid"]);
        $product = \think\Db::name("products")->where("id", $id)->find();
        if (empty($product)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $api_type = isset($param["api_type"]) ? $param["api_type"] : "";
        if (empty($api_type)) {
            return jsonrule(["status" => 400, "msg" => lang("接口类型非空")]);
        }
        if ($api_type != "zjmf_api") {
            return jsonrule(["status" => 400, "msg" => lang("接口类型错误")]);
        }
        $zjmf_finance_api_id = intval($param["zjmf_finance_api_id"]);
        $upstream_pid = intval($param["upstream_pid"]);
        if (empty($zjmf_finance_api_id) || empty($upstream_pid)) {
            return jsonrule(["status" => 400, "msg" => lang("缺少接口ID或上游产品ID")]);
        }
        $api = \think\Db::name("zjmf_finance_api")->where("id", $zjmf_finance_api_id)->find();
        if (empty($api)) {
            return jsonrule(["status" => 400, "msg" => lang("接口不存在")]);
        }
        $res = (new \app\common\logic\Product())->syncProduct($param);
        return jsonrule($res);
    }
    public function editPage()
    {
        $id = $this->request->param("id", 0, "intval");
        $type_view = $this->request->param("type_view", 1, "intval");
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => "ID_ERROR"]);
        }
        $re = [];
        if ($type_view == 1) {
            $re["data"]["type"] = config("product_type");
        }
        $product = \think\Db::name("products")->field("id,type,gid,groupid,name,description,hidden,welcome_email,stock_control,qty,tax\r\n            ,host,password,is_domain,is_bind_phone\r\n            ,pay_type,pay_method,allow_qty,auto_setup\r\n            ,api_type,upstream_price_type,upstream_price_value,zjmf_api_id,upstream_pid\r\n            ,server_group,auto_terminate_email\r\n            ,config_options_upgrade,upgrade_email,affiliateonetime,affiliate_pay_type\r\n            ,affiliate_pay_amount,order,retired,is_featured,config_option1,config_option2\r\n            ,config_option3,config_option4,config_option5,config_option6,config_option7\r\n            ,config_option8,config_option9,config_option10,config_option11,config_option12\r\n            ,config_option13,config_option14,config_option15,config_option16,config_option17\r\n            ,config_option18,config_option19,config_option20,config_option21,config_option22\r\n            ,config_option23,config_option24,is_truename,clientscount,product_shopping_url,product_group_url,upper_reaches_id,cancel_control,upstream_auto_setup,upstream_ontrial_status")->where("id", $id)->find();
        if (!is_profession()) {
            $product["cancel_control"] = 1;
        }
        if (empty($product)) {
            $re["status"] = 406;
            $re["msg"] = lang("PRODUCT_CAN_NOT_FIND");
            return jsonrule($re);
        }
        if ($product["type"] == "dcimcloud" && empty($product["config_option1"])) {
            $product["config_option1"] = "month";
        }
        $product["is_resource"] = 0;
        if (!empty($product["zjmf_api_id"])) {
            $product["is_resource"] = \think\Db::name("zjmf_finance_api")->where("id", $product["zjmf_api_id"])->value("is_resource");
        }
        $where = [];
        $product["product_shopping_url"] = request()->domain() . "/cart?action=configureproduct&pid=" . $id;
        $first_gid = \think\Db::name("product_groups")->where("id", $product["gid"])->value("gid");
        $product["first_gid"] = $first_gid;
        $product["product_group_url"] = request()->domain() . "/cart?gid=" . $product["gid"] . "&fid=" . $first_gid;
        $product["host"] = json_decode($product["host"], true);
        $password = json_decode($product["password"], true);
        $password["rule"]["upper"] = $password["rule"]["upper"] ?? 1;
        $password["rule"]["lower"] = $password["rule"]["lower"] ?? 1;
        $password["rule"]["num"] = $password["rule"]["num"] ?? 1;
        $password["rule"]["special"] = $password["rule"]["special"] ?? 0;
        $product["password"] = $password;
        $product["server_group"] = !empty($product["server_group"]) ? $product["server_group"] : 0;
        $product["welcome_email"] = empty($product["welcome_email"]) ? "" : $product["welcome_email"];
        $product["auto_terminate_email"] = empty($product["auto_terminate_email"]) ? "" : $product["auto_terminate_email"];
        $product["upgrade_email"] = empty($product["upgrade_email"]) ? "" : $product["upgrade_email"];
        $re["data"]["product_paytype"] = config("product_paytype");
        $product = array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $product);
        $re["data"]["product"] = $product;
        $re["data"]["product_group"] = \think\Db::name("product_groups")->field("id,name")->where($where)->select();
        $pay_type = $product["pay_type"];
        $re["data"]["product"]["pay_type"] = [];
        if (!empty($pay_type)) {
            $pay_type = json_decode($pay_type, true);
            $re["data"]["product"]["pay_type"] = ["pay_type" => !empty($pay_type["pay_type"]) ? $pay_type["pay_type"] : "free", "pay_hour_cycle" => !empty($pay_type["pay_hour_cycle"]) ? $pay_type["pay_hour_cycle"] : 720, "pay_day_cycle" => !empty($pay_type["pay_day_cycle"]) ? $pay_type["pay_day_cycle"] : 30, "pay_ontrial_status" => !empty($pay_type["pay_ontrial_status"]) ? 1 : 0, "pay_ontrial_cycle" => !empty($pay_type["pay_ontrial_cycle"]) ? $pay_type["pay_ontrial_cycle"] : 0, "pay_ontrial_num" => !empty($pay_type["pay_ontrial_num"]) ? $pay_type["pay_ontrial_num"] : 1, "pay_ontrial_condition" => $pay_type["pay_ontrial_condition"] ?? [], "pay_ontrial_cycle_type" => $pay_type["pay_ontrial_cycle_type"] ?: "day", "pay_ontrial_num_rule" => getEdition() ? $pay_type["pay_ontrial_num_rule"] ?: 0 : 0, "clientscount_rule" => getEdition() ? $pay_type["clientscount_rule"] ?: 0 : 0];
        } else {
            $re["data"]["product"]["pay_type"] = ["pay_type" => "free", "pay_hour_cycle" => 720, "pay_day_cycle" => 30, "pay_ontrial_status" => 0, "pay_ontrial_cycle" => 0, "pay_ontrial_condition" => [], "pay_ontrial_num" => 1, "pay_ontrial_cycle_type" => "day", "pay_ontrial_num_rule" => 0, "clientscount_rule" => 0];
        }
        $provision = new \app\common\logic\Provision();
        $modules = $provision->getModules();
        $re["data"]["modules"] = $modules;
        $list = [];
        $list["zjmf_api"] = getAddressByApiType("zjmf_api", $id);
        $list["normal"] = getAddressByApiType("normal", $id);
        $re["data"]["server_group"] = $list;
        $re["data"]["currencies"] = (new \app\common\logic\Currencies())->getCurrencies("id,code");
        $pricing_data = \think\Db::name("pricing")->where("relid", $id)->where("type", "product")->select()->toArray();
        $diffs = array_diff(array_column($re["data"]["currencies"], "id"), array_column($pricing_data, "currency"));
        foreach ($diffs as $diff) {
            $data["type"] = "product";
            $data["currency"] = $diff;
            $data["relid"] = $id;
            $data["osetupfee"] = 0;
            $data["hsetupfee"] = 0;
            $data["dsetupfee"] = 0;
            $data["ontrialfee"] = 0;
            $data["msetupfee"] = 0;
            $data["qsetupfee"] = 0;
            $data["ssetupfee"] = 0;
            $data["asetupfee"] = 0;
            $data["bsetupfee"] = 0;
            $data["tsetupfee"] = 0;
            $data["foursetupfee"] = 0;
            $data["fivesetupfee"] = 0;
            $data["sixsetupfee"] = 0;
            $data["sevensetupfee"] = 0;
            $data["eightsetupfee"] = 0;
            $data["ninesetupfee"] = 0;
            $data["tensetupfee"] = 0;
            $data["onetime"] = 0;
            $data["hour"] = 0;
            $data["day"] = 0;
            $data["ontrial"] = 0;
            $data["monthly"] = 0;
            $data["quarterly"] = 0;
            $data["semiannually"] = 0;
            $data["annually"] = 0;
            $data["biennially"] = 0;
            $data["triennially"] = 0;
            $data["fourly"] = 0;
            $data["fively"] = 0;
            $data["sixly"] = 0;
            $data["sevenly"] = 0;
            $data["eightly"] = 0;
            $data["ninely"] = 0;
            $data["tenly"] = 0;
            array_push($pricing_data, $data);
        }
        $re["data"]["pricing"] = $pricing_data;
        $download = new \app\common\logic\Download();
        $re["data"]["download_files"] = \app\common\model\DownloadsModel::getAssociatedfiles($id);
        $re["data"]["hierarchy_cats"] = $download->getHierarchyCats();
        $re["data"]["custom_brokerage"] = config("custom_brokerage");
        $re["data"]["customfields_type"] = config("customfields");
        $customfields = \think\Db::name("customfields")->field("type,relid,create_time,update_time", true)->where("type", "product")->where("relid", $id)->select()->toArray();
        foreach ($customfields as $k => $customfield) {
            $customfields[$k] = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $customfield);
        }
        $re["data"]["customfields"] = $customfields;
        $re["data"]["config_groups"] = \think\Db::name("product_config_groups")->field(["id", "concat(name, \" - \", description)" => "name_desc"])->select()->toArray();
        $config_links_data = \think\Db::name("product_config_links")->where("pid", $id)->select()->toArray();
        $re["data"]["config_links"] = array_column($config_links_data, "gid");
        $groups = get_product_groups();
        foreach ($groups as $k => $v) {
            $groupid = $v["id"];
            $groups[$k]["product"] = db("products")->field("id,name,type")->withAttr("type", function ($value) {
                return config("product_type")[$value];
            })->where("id", "<>", $id)->where("gid", $groupid)->where("retired", 0)->order("order", "asc")->select()->toArray();
        }
        $re["data"]["all_product_data"] = $groups;
        $upgrade_product_data = \think\Db::name("product_upgrade_products")->field("upgrade_product_id")->where("product_id", $id)->select()->toArray();
        $rows = \think\Db::name("affiliates_products_setting")->where("pid", $id)->find();
        $re["data"]["upgrade_product_ids"] = array_column($upgrade_product_data, "upgrade_product_id");
        $re["data"]["pay_ontrial_condition"] = config("pay_ontrial_condition");
        $re["rows"] = $rows;
        $pgrouplist = $this->getProductType($id);
        $ptype_arr = array_filter($pgrouplist, function ($v) {
            return $v["is_active"] == 1 ? true : false;
        });
        $re["pgrouplist"] = $pgrouplist;
        $re["ptype"] = empty($ptype_arr) ? "" : array_values($ptype_arr)[0]["id"];
        $re["data"]["upstream_product_pricings"] = [];
        $re["data"]["upstream_pay_ontrial_condition"] = [];
        if ($product["api_type"] == "zjmf_api" && 0 < $product["upstream_pid"]) {
            $res = getZjmfUpstreamProductConfig($product["zjmf_api_id"], $product["upstream_pid"]);
            if ($res["status"] == 200) {
                $upstream_product = $res["data"]["products"];
                $upstream_product_pricings = $res["data"]["product_pricings"];
                $price_type = config("price_type");
                if ($upstream_product["api_type"] == "zjmf_api" && 0 < $upstream_product["upstream_pid"] && $upstream_product["upstream_price_type"] == "percent") {
                    foreach ($upstream_product_pricings as $k => $v) {
                        foreach ($price_type as $kk => $vv) {
                            $upstream_product_pricings[$k][$vv[0]] = $v[$vv[0]] * $upstream_product["upstream_price_value"] / 100;
                            $upstream_product_pricings[$k][$vv[1]] = $v[$vv[1]] * $upstream_product["upstream_price_value"] / 100;
                        }
                    }
                }
                $re["data"]["upstream_product_pricings"] = $upstream_product_pricings;
                $re["data"]["upstream_pay_ontrial_condition"] = json_decode($res["data"]["products"]["pay_type"], true)["pay_ontrial_condition"];
            }
        }
        $re["data"]["api_type"] = config("allow_api_type");
        $re["status"] = 200;
        $re["msg"] = lang("SUCCESS MESSAGE");
        return jsonrule($re);
    }
    public function edit(\think\Request $request)
    {
        if ($request->isPost()) {
            $validate = new \app\admin\validate\ProductsValidate();
            $param = $request->param();
            if (empty($param["pay_type"])) {
                return jsonrule(["status" => 406, "msg" => lang("THERE_MUST_BE_A_VALID_PERIOD")]);
            }
            if (empty($param["password_show"]) && isset($param["password_rule_len_num"])) {
                unset($param["password_rule_len_num"]);
            }
            if (empty($param["host_show"]) && isset($param["host_rule_len_num"])) {
                unset($param["host_rule_len_num"]);
            }
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $result = [];
            $id = $param["id"];
            $product = \think\Db::name("products")->where("id", $id)->find();
            if (empty($product)) {
                return jsonrule(["status" => 406, "msg" => lang("ID_ERROR")]);
            }
            if ($product["resource_pid"]) {
                return $this->editResProduct($request);
            }
            $rate = $param["rate"] ? floatval($param["rate"]) : $product["rate"];
            $api_type = isset($param["api_type"]) ? $param["api_type"] : "";
            if (empty($api_type)) {
                return jsonrule(["status" => 400, "msg" => lang("接口类型非空")]);
            }
            $allow_api_type = config("allow_api_type");
            array_push($allow_api_type, ["name" => "manual", "name_zh" => "手动资源"]);
            array_push($allow_api_type, ["name" => "whmcs", "name_zh" => "WHMCS"]);
            if (!in_array($api_type, array_column($allow_api_type, "name"))) {
                return jsonrule(["status" => 400, "msg" => lang("接口类型错误")]);
            }
            if ($api_type != "zjmf_api" && $api_type != "whmcs") {
                $auto_create_config_options = 0;
                if ($product["auto_create_config_options"] == 0 && $param["type"] == "dcim") {
                    if (!empty($param["server_group"])) {
                        $server_group = \think\Db::name("server_groups")->where("id", $param["server_group"])->where("system_type", "dcim")->find();
                        if (empty($server_group)) {
                            $result["status"] = 406;
                            $result["msg"] = "产品类型和服务器不一致";
                            return jsonrule($result);
                        }
                        if ($server_group["system_type"] == "dcim") {
                            $auto_create_config_options = 1;
                        }
                    }
                } else {
                    if ($product["auto_create_config_options"] == 0 && $param["type"] == "dcimcloud") {
                        if (!empty($param["server_group"])) {
                            $server_group = \think\Db::name("server_groups")->where("id", $param["server_group"])->where("system_type", "dcimcloud")->find();
                            if (empty($server_group)) {
                                $result["status"] = 406;
                                $result["msg"] = "产品类型和服务器不一致";
                                return jsonrule($result);
                            }
                            if ($server_group["system_type"] == "dcimcloud") {
                                $auto_create_config_options = 1;
                            }
                        }
                    } else {
                        if ($product["auto_create_config_options"] == 0 && $param["type"] == "cloud" && !empty($param["server_group"])) {
                            $server_group = \think\Db::name("server_groups")->where("id", $param["server_group"])->find();
                            if ($server_group["type"] == "nokvm") {
                                $auto_create_config_options = 2;
                            }
                        }
                    }
                }
            }
            if ($api_type == "zjmf_api") {
                $combine_id = $param["server_group"];
                $combine = \think\Db::name("zjmf_finance_api")->where("id", $combine_id)->find();
                if ($combine["type"] == "manual") {
                    $api_type = "manual";
                    $param["upper_reaches_id"] = $param["server_group"];
                }
            }
            if (0 < $param["upper_reaches_id"]) {
                $upper_reaches = \think\Db::name("zjmf_finance_api")->whereIn("type", ["manual", "whmcs"])->where("id", (int) $param["upper_reaches_id"])->find();
                if (empty($upper_reaches)) {
                    return jsonrule(["status" => 400, "msg" => lang("供应商不存在")]);
                }
            }
            $dec = "";
            $basedata = [];
            $basedata["type"] = $param["type"] ?: "other";
            if (!empty($basedata["type"]) && $basedata["type"] != $product["type"]) {
                $dec .= "产品类型由“" . $product["type"] . "”改为“" . $param["type"] . "”，";
            }
            $basedata["gid"] = $param["gid"];
            if (!empty($basedata["gid"]) && $basedata["gid"] != $product["gid"]) {
                $dec .= "产品组名由“" . $product["gid"] . "”改为“" . $param["gid"] . "”，";
            }
            $basedata["name"] = $param["name"];
            if (!empty($basedata["name"]) && $basedata["name"] != $product["name"]) {
                $dec .= "产品名由“" . $product["name"] . "”改为“" . $param["name"] . "”，";
            }
            $basedata["description"] = $param["description"];
            if (!empty($basedata["description"]) && $basedata["description"] != $product["description"]) {
                $dec .= "产品描述由“" . $product["description"] . "”改为“" . $param["description"] . "”，";
            }
            $basedata["welcome_email"] = $param["welcome_email"];
            if (!empty($basedata["welcome_email"]) && $basedata["welcome_email"] != $product["welcome_email"]) {
                $dec .= "产品开通邮箱由“" . $product["welcome_email"] . "”改为“" . $param["welcome_email"] . "”，";
            }
            $basedata["clientscount"] = $param["clientscount"];
            if (!empty($basedata["clientscount"]) && $basedata["clientscount"] != $product["clientscount"]) {
                $dec .= "单个客户购买此产品的数量由“" . $product["clientscount"] . "”改为“" . $param["clientscount"] . "”，";
            }
            $basedata["product_shopping_url"] = $param["product_shopping_url"] ?: request()->domain() . "/cart?action=configureproduct&pid=" . $id;
            if (!empty($basedata["product_shopping_url"]) && $basedata["product_shopping_url"] != $product["product_shopping_url"]) {
                $dec .= "快速订购连接由“" . $product["product_shopping_url"] . "”改为“" . $param["product_shopping_url"] . "”，";
            }
            $first_gid = \think\Db::name("product_groups")->where("id", $basedata["gid"])->value("gid");
            $basedata["product_group_url"] = $param["product_group_url"] ?: request()->domain() . "/cart?gid=" . $basedata["gid"] . "&fid=" . $first_gid;
            if (!empty($basedata["product_group_url"]) && $basedata["product_group_url"] != $product["product_group_url"]) {
                $dec .= "产品组连接由“" . $product["product_group_url"] . "”改为“" . $param["product_group_url"] . "”，";
            }
            $basedata["is_truename"] = $param["is_truename"];
            if ($basedata["is_truename"] != $product["is_truename"] && !empty($basedata["is_truename"])) {
                if ($basedata["is_truename"] == 0) {
                    $dec .= "产品实名认证由“开启”改为“关闭”，";
                } else {
                    $dec .= "产品实名认证由“关闭”改为“开启”，";
                }
            }
            $basedata["is_bind_phone"] = $param["is_bind_phone"];
            if ($basedata["is_bind_phone"] != $product["is_bind_phone"] && !empty($basedata["is_bind_phone"])) {
                if ($basedata["is_bind_phone"] == 0) {
                    $dec .= "产品绑定手机由“开启”改为“关闭”，";
                } else {
                    $dec .= "产品绑定手机由“关闭”改为“开启”，";
                }
            }
            $basedata["stock_control"] = !empty($param["stock_control"]) ? 1 : 0;
            if ($basedata["stock_control"] != $product["stock_control"]) {
                if ($basedata["stock_control"] == 1) {
                    $dec .= "库存控制由“禁用”改为“启用”，";
                } else {
                    $dec .= "库存控制由“启用”改为“禁用”，";
                }
            }
            $basedata["qty"] = intval($param["qty"]);
            if ($basedata["qty"] != $product["qty"]) {
                $dec .= "库存由“" . $product["qty"] . "”改为“" . $param["qty"] . "”，";
            }
            $basedata["groupid"] = $param["groupid"];
            if (empty($param["groupid"])) {
                if ($param["type"] == "dcim") {
                    $basedata["groupid"] = 2;
                } else {
                    if ($param["type"] == "clound" || $param["type"] == "dcimcloud") {
                        $basedata["groupid"] = 1;
                    } else {
                        $basedata["groupid"] = 3;
                    }
                }
            }
            try {
                $this->setProToNav($param, $id);
            } catch (\Throwable $e) {
                return jsons(["status" => 400, "msg" => $e->getMessage()]);
            }
            $payArr = [];
            $payArr["pay_type"] = $param["pay_type"];
            $payArr["pay_hour_cycle"] = $param["pay_hour_cycle"] ?? 720;
            $payArr["pay_day_cycle"] = $param["pay_day_cycle"] ?? 30;
            $payArr["pay_ontrial_status"] = !empty($param["pay_ontrial_status"]) ? 1 : 0;
            $payArr["pay_ontrial_cycle"] = $param["pay_ontrial_cycle"] ?? 0;
            $payArr["pay_ontrial_num"] = $param["pay_ontrial_num"] ?? 1;
            $payArr["pay_ontrial_condition"] = $param["pay_ontrial_condition"] ?? [];
            $payArr["pay_ontrial_cycle_type"] = $param["pay_ontrial_cycle_type"] ?: "day";
            if (!getEdition() && 1 <= $param["pay_ontrial_num_rule"]) {
                return jsonrule(["status" => 400, "msg" => "试用数量计算规则专业版可用，免费版不可修改"]);
            }
            $payArr["pay_ontrial_num_rule"] = $param["pay_ontrial_num_rule"] ?? 0;
            if (!getEdition() && 1 <= $param["clientscount_rule"]) {
                return jsonrule(["status" => 400, "msg" => "商品数量计算规则专业版可用，免费版不可修改"]);
            }
            $payArr["clientscount_rule"] = $param["clientscount_rule"] ?? 0;
            $payArrToJson = json_encode($payArr);
            $basedata["pay_type"] = $payArrToJson;
            $basedata["pay_method"] = $param["pay_method"] ?: "prepayment";
            if ($basedata["pay_method"] != $product["pay_method"] && !empty($basedata["pay_method"])) {
                if ($basedata["pay_method"] == 1) {
                    $dec .= "付款方式由“后付费”改为“预付费”，";
                } else {
                    $dec .= "付款方式由“预付费”改为“后付费”，";
                }
            }
            $basedata["allow_qty"] = !empty($param["allow_qty"]) ? 1 : 0;
            if ($basedata["allow_qty"] != $product["allow_qty"] && !empty($basedata["allow_qty"])) {
                if ($param["allow_qty"] == 1) {
                    $dec .= "允许购买多个由“关闭”改为“开启”，";
                } else {
                    $dec .= "允许购买多个由“开启”改为“关闭”，";
                }
            }
            if (!empty($basedata["pay_type"]) && $basedata["pay_type"] != $product["pay_type"]) {
                $pay_type = json_decode($product["pay_type"], true);
                if ($pay_type["pay_type"] != $payArr["pay_type"]) {
                    $arr = config("pay_type");
                    $dec .= "付款类型由“" . $arr[$pay_type["pay_type"]] . "”改为“" . $arr[$payArr["pay_type"]];
                }
                if ($pay_type["pay_ontrial_status"] != $payArr["pay_ontrial_status"]) {
                    if ($payArr["pay_ontrial_status"] == 1) {
                        $dec .= "试用由“关闭”改为“开启”，";
                    } else {
                        $dec .= "试用由“开启”改为“关闭”，";
                    }
                }
                if ($pay_type["pay_hour_cycle"] != $payArr["pay_hour_cycle"]) {
                    $dec .= "收费周期小时由“" . $pay_type["pay_hour_cycle"] . "”改为“" . $payArr["pay_hour_cycle"] . "”，";
                }
                if ($pay_type["pay_day_cycle"] != $payArr["pay_day_cycle"]) {
                    $dec .= "收费周期天由“" . $pay_type["pay_day_cycle"] . "”改为“" . $payArr["pay_day_cycle"] . "”，";
                }
                if ($pay_type["pay_ontrial_cycle"] != $payArr["pay_ontrial_cycle"]) {
                    $dec .= "试用的时间由“" . $pay_type["pay_ontrial_cycle"] . "”改为“" . $payArr["pay_ontrial_cycle"] . "”，";
                }
                if ($pay_type["pay_ontrial_condition"] != $payArr["pay_ontrial_condition"]) {
                    $arr = config("pay_ontrial_condition_cn_new");
                    $pay_type_pay_ontrial_condition = array_flip($pay_type["pay_ontrial_condition"]);
                    $pay_type_text = implode(",", array_intersect_key($arr, $pay_type_pay_ontrial_condition));
                    $payArr_pay_ontrial_condition = array_flip($payArr["pay_ontrial_condition"]);
                    $payArr_text = implode(",", array_intersect_key($arr, $payArr_pay_ontrial_condition));
                    $dec .= "试用条件由“" . $pay_type_text . "”改为“" . $payArr_text . "”，";
                }
            }
            $basedata["auto_setup"] = $param["auto_setup"] ?: "";
            if (!empty($basedata["auto_setup"]) && $basedata["auto_setup"] != $product["auto_setup"]) {
                $dec .= "购买后动作设置由“" . $product["auto_setup"] . "”改为“" . $param["auto_setup"] . "”，";
            }
            $basedata["server_type"] = $param["server_type"] ?: "";
            if (!empty($basedata["server_type"]) && $basedata["server_type"] != $product["server_type"]) {
                $dec .= "服务器模块类型由“" . $product["server_type"] . "”改为“" . $param["server_type"] . "”，";
            }
            $basedata["server_group"] = $param["server_group"];
            if (!empty($basedata["server_group"]) && $basedata["server_group"] != $product["server_group"]) {
                $server_data = \think\Db::name("server_groups")->field("name")->where("id=" . $basedata["server_group"])->find();
                $server_data1 = \think\Db::name("server_groups")->field("name")->where("id=" . $product["server_group"])->find();
                $dec .= "服务器组由“" . $server_data["name"] . "”改为“" . $server_data1["name"] . "”，";
            }
            $basedata["config_option1"] = $param["packageconfigoption"][1];
            if ($param["type"] == "dcim") {
                $basedata["config_option1"] = $basedata["config_option1"] ?: "rent";
            }
            $basedata["config_option2"] = $param["packageconfigoption"][2];
            $basedata["config_option3"] = $param["packageconfigoption"][3];
            $basedata["config_option4"] = $param["packageconfigoption"][4];
            $basedata["config_option5"] = $param["packageconfigoption"][5];
            $basedata["config_option6"] = $param["packageconfigoption"][6];
            $basedata["config_option7"] = $param["packageconfigoption"][7];
            $basedata["config_option8"] = $param["packageconfigoption"][8];
            $basedata["config_option9"] = $param["packageconfigoption"][9];
            $basedata["config_option10"] = $param["packageconfigoption"][10];
            $basedata["config_option11"] = $param["packageconfigoption"][11];
            $basedata["config_option12"] = $param["packageconfigoption"][12];
            $basedata["config_option13"] = $param["packageconfigoption"][13];
            $basedata["config_option14"] = $param["packageconfigoption"][14];
            $basedata["config_option15"] = $param["packageconfigoption"][15];
            $basedata["config_option16"] = $param["packageconfigoption"][16];
            $basedata["config_option17"] = $param["packageconfigoption"][17];
            $basedata["config_option18"] = $param["packageconfigoption"][18];
            $basedata["config_option19"] = $param["packageconfigoption"][19];
            $basedata["config_option20"] = $param["packageconfigoption"][20];
            $basedata["config_option21"] = $param["packageconfigoption"][21];
            $basedata["config_option22"] = $param["packageconfigoption"][22];
            $basedata["config_option23"] = $param["packageconfigoption"][23];
            $basedata["config_option24"] = $param["packageconfigoption"][24];
            $basedata["recurring_cycles"] = $param["recurring_cycles"];
            if (!empty($basedata["recurring_cycles"]) && $basedata["recurring_cycles"] != $product["recurring_cycles"]) {
                $dec .= "循环周期由“" . $product["recurring_cycles"] . "”改为“" . $param["recurring_cycles"] . "”，";
            }
            $basedata["auto_terminate_days"] = intval($param["auto_terminate_days"]);
            if (!empty($basedata["auto_terminate_days"]) && $basedata["auto_terminate_days"] != $product["auto_terminate_days"]) {
                $dec .= "自动删除/固定周期由“" . $product["auto_terminate_days"] . "”改为“" . $param["auto_terminate_days"] . "”，";
            }
            $basedata["auto_terminate_email"] = intval($param["auto_terminate_email"]);
            if (!empty($basedata["auto_terminate_email"]) && $basedata["auto_terminate_email"] != $product["auto_terminate_email"]) {
                $dec .= "自动终止邮件ID由“" . $product["auto_terminate_email"] . "”改为“" . $param["auto_terminate_email"] . "”，";
            }
            $basedata["config_options_upgrade"] = !empty($param["config_options_upgrade"]) ? 1 : 0;
            if (!empty($basedata["config_options_upgrade"]) && $basedata["config_options_upgrade"] != $product["config_options_upgrade"]) {
                if ($basedata["config_options_upgrade"] == 1) {
                    $dec .= "升级可配置选项由“关闭”改为“开启”，";
                } else {
                    $dec .= "升级可配置选项由“开启”改为“关闭”，";
                }
            }
            $basedata["upgrade_email"] = intval($param["upgrade_email"]);
            if (!empty($basedata["upgrade_email"]) && $basedata["upgrade_email"] != $product["upgrade_email"]) {
                $dec .= "升级邮件由“" . $product["upgrade_email"] . "”改为“" . $param["upgrade_email"] . "”，";
            }
            $basedata["retired"] = !empty($param["retired"]) ? 1 : 0;
            if (!empty($basedata["retired"]) && $basedata["retired"] != $product["retired"]) {
                if ($basedata["retired"] == 1) {
                    $dec .= "商品由“上架”改为“下架”，";
                } else {
                    $dec .= "商品由“下架”改为“上架”，";
                }
            }
            $basedata["is_featured"] = !empty($param["is_featured"]) ? 1 : 0;
            if (!empty($basedata["is_featured"]) && $basedata["is_featured"] != $product["is_featured"]) {
                if ($basedata["is_featured"] == 1) {
                    $dec .= "商品特性由“不添加”改为“添加”，";
                } else {
                    $dec .= "商品特性由“添加”改为“不添加”，";
                }
            }
            $basedata["hidden"] = !empty($param["hidden"]) ? 1 : 0;
            if (!empty($basedata["hidden"]) && $basedata["hidden"] != $product["hidden"]) {
                if ($basedata["hidden"] == 1) {
                    $dec .= "商品由“显示”改为“隐藏”，";
                } else {
                    $dec .= "商品由“隐藏”改为“显示”，";
                }
            }
            $basedata["cancel_control"] = !empty($param["cancel_control"]) ? 1 : 0;
            if (!is_profession()) {
                $basedata["cancel_control"] = 1;
            }
            if (!empty($basedata["cancel_control"]) && $basedata["cancel_control"] != $product["cancel_control"] && is_profession()) {
                if ($basedata["cancel_control"] == 1) {
                    $dec .= "商品取消停用由“显示”改为“隐藏”，";
                } else {
                    $dec .= "商品取消停用由“隐藏”改为“显示”，";
                }
            }
            $basedata["affiliate_pay_type"] = $param["affiliate_pay_type"] ?: "default";
            if (!empty($basedata["affiliate_pay_type"]) && $basedata["affiliate_pay_type"] != $product["affiliate_pay_type"]) {
                $dec .= "自定义佣金设置由“" . $product["affiliate_pay_type"] . "”改为“" . $param["affiliate_pay_type"] . "”，";
            }
            $basedata["affiliate_pay_amount"] = $param["affiliate_pay_amount"] ?: 0;
            if (!empty($basedata["affiliate_pay_amount"]) && $basedata["affiliate_pay_amount"] != $product["affiliate_pay_amount"]) {
                $dec .= "金额百分比由“" . $product["affiliate_pay_amount"] . "”改为“" . $param["affiliate_pay_amount"] . "”，";
            }
            $basedata["affiliateonetime"] = !empty($param["affiliateonetime"]) ? 1 : 0;
            if (!empty($basedata["affiliateonetime"]) && $basedata["affiliateonetime"] != $product["affiliateonetime"]) {
                $dec .= "一次性支付由“" . $product["affiliateonetime"] . "”改为“" . $param["affiliateonetime"] . "”，";
            }
            $basedata["update_time"] = time();
            $host_rule = $host = [];
            $host["show"] = $param["host_show"] ?? 0;
            $host["modify"] = $param["host_modify"] ?? 0;
            $host["prefix"] = $param["host_prefix"] ?? "cloud";
            if ($host["show"] && empty($param["host_rule_upper"]) && empty($param["host_rule_lower"]) && empty($param["host_rule_num"])) {
                return jsonrule(["status" => 406, "msg" => "主机名规则数字、大小写字母至少选择一样"]);
            }
            $host_rule["upper"] = $param["host_rule_upper"] ?? 0;
            $host_rule["lower"] = $param["host_rule_lower"] ?? 0;
            $host_rule["num"] = $param["host_rule_num"] ?? 0;
            $host_rule["len_num"] = $param["host_rule_len_num"] ?? 12;
            $host["rule"] = $host_rule;
            $basedata["host"] = json_encode($host);
            $password_rule = $password = [];
            $password["show"] = $param["password_show"] ?? 0;
            $password["modify"] = $param["password_modify"] ?? 0;
            $password_rule["len_num"] = $param["password_rule_len_num"] ?? 12;
            $password_rule["upper"] = $param["password_rule_upper"] ?? 1;
            $password_rule["lower"] = $param["password_rule_lower"] ?? 1;
            $password_rule["num"] = $param["password_rule_num"] ?? 1;
            $password_rule["special"] = $param["password_rule_special"] ?? 0;
            if ($password_rule["special"] == 1 && $basedata["type"] == "dcim") {
                return jsonrule(["status" => 406, "msg" => "魔方DCIM密码不能包含特殊字符！"]);
            }
            $password["rule"] = $password_rule;
            $basedata["password"] = json_encode($password);
            $basedata["is_domain"] = $param["is_domain"] ? intval($param["is_domain"]) : 0;
            if ($api_type != "zjmf_api") {
                $basedata["upstream_version"] = 0;
                $basedata["zjmf_api_id"] = 0;
                $basedata["upstream_pid"] = 0;
            }
            if ($api_type == "manual") {
                $basedata["upper_reaches_id"] = $param["upper_reaches_id"] ? intval($param["upper_reaches_id"]) : intval($param["server_group"]);
            } else {
                $basedata["upper_reaches_id"] = 0;
            }
            if ($api_type == "whmcs") {
                $basedata["zjmf_api_id"] = intval($param["server_group"]);
            }
            $basedata["api_type"] = $api_type;
            $basedata["location_version"] = $product["location_version"] + 1;
            $pricingData = $param["currency"];
            $zjmf_finance_api_id = intval($param["server_group"]);
            if ($api_type == "zjmf_api" && (empty($zjmf_finance_api_id) || empty($param["upstream_pid"]))) {
                return jsonrule(["status" => 400, "msg" => lang("缺少接口ID或上游产品ID")]);
            }
            $upstream_price_type = isset($param["upstream_price_type"]) ? $param["upstream_price_type"] : "percent";
            $upstream_price_value = isset($param["upstream_price_value"]) ? floatval($param["upstream_price_value"]) : 120;
            $basedata["upstream_price_value"] = $upstream_price_value;
            if ($api_type == "zjmf_api") {
                $sync_param = ["pid" => $id, "zjmf_finance_api_id" => $zjmf_finance_api_id, "upstream_pid" => $param["upstream_pid"], "page_type" => "edit_product", "upstream_price_type" => $upstream_price_type, "upstream_price_value" => $upstream_price_value];
                \think\Db::name("products")->where("id", $id)->update($basedata);
                $res = (new \app\common\logic\Product())->syncProduct($sync_param);
                \think\Db::name("products")->where("id", $id)->update(["upstream_price_type" => $upstream_price_type]);
                if ($res["status"] != 200) {
                    return jsonrule($res);
                }
                $pay_type_new = \think\Db::name("products")->where("id", $id)->value("pay_type");
                $pay_type_new = json_decode($pay_type_new, true);
                $pay_type_new["clientscount_rule"] = intval($param["clientscount_rule"]);
                $pay_type_new = json_encode($pay_type_new);
                \think\Db::name("products")->where("id", $id)->update(["name" => $param["name"] ?: "", "gid" => intval($param["gid"]), "description" => $param["description"] ?: "", "is_truename" => intval($param["is_truename"]), "is_bind_phone" => intval($param["is_bind_phone"]), "stock_control" => 0, "qty" => 0, "pay_type" => $pay_type_new, "upstream_pid" => intval($param["upstream_pid"])]);
                if ($upstream_price_type == "custom") {
                    $res = getZjmfUpstreamProductsDetail($zjmf_finance_api_id, [$param["upstream_pid"]]);
                    $upstream_data = $res["data"]["detail"][$param["upstream_pid"]];
                    $upstream_product_pricings = $upstream_data["product_pricings"];
                    $product_pricings = \think\Db::name("pricing")->alias("a")->field("b.upstream_pid,c.code,a.currency")->leftJoin("products b", "a.relid = b.id")->leftJoin("currencies c", "a.currency = c.id")->where("a.type", "product")->where("a.relid", $id)->select()->toArray();
                    $billingcycles = config("price_type");
                    foreach ($upstream_product_pricings as $v) {
                        foreach ($product_pricings as $vv) {
                            if ($v["relid"] == $vv["upstream_pid"] && $v["code"] == $vv["code"]) {
                                foreach ($billingcycles as $billingcycle) {
                                    if ($v[$billingcycle[0]] < 0) {
                                        $pricingData[$vv["currency"]][$billingcycle[0]] = -1;
                                        $pricingData[$vv["currency"]][$billingcycle[1]] = 0;
                                    }
                                }
                            }
                        }
                    }
                    $pricing = new \app\common\logic\Pricing();
                    $pricing->save($id, "product", $pricingData);
                }
            } else {
                \think\Db::startTrans();
                try {
                    foreach ($basedata as $k => $v) {
                        if ($v == NULL || $v == NULL) {
                            $basedata[$k] = "";
                        }
                    }
                    \think\Db::name("products")->where("id", $id)->update($basedata);
                    if ($api_type == "whmcs") {
                        $field_exist = \think\Db::name("customfields")->where("type", "product")->where("relid", $id)->where("fieldname", "hostid")->find();
                        if (empty($field_exist)) {
                            $add_field = ["type" => "product", "relid" => $id, "fieldname" => "hostid", "fieldtype" => "text", "description" => "产品ID", "fieldoptions" => "", "regexpr" => "", "adminonly" => 1, "required" => 0, "showorder" => 0, "sortorder" => 0, "create_time" => time(), "showdetail" => 0];
                            \think\Db::name("customfields")->insertGetId($add_field);
                        }
                    }
                    $pricing = new \app\common\logic\Pricing();
                    $pricing->save($id, "product", $pricingData);
                    $p = \think\Db::name("products")->where("id", $id)->find();
                    $upgradepackages = $param["upgradepackages"];
                    $upgradepackagesArr = [];
                    \think\Db::name("product_upgrade_products")->where("product_id", $id)->delete();
                    foreach ($upgradepackages as $key => $value) {
                        $upgradepackagesArr[$key]["product_id"] = $id;
                        $upgradepackagesArr[$key]["upgrade_product_id"] = $value;
                    }
                    \think\Db::name("product_upgrade_products")->insertAll($upgradepackagesArr);
                    if ($api_type != "zjmf_api") {
                        $custom = new \app\common\logic\Customfields();
                        $re = $custom->add($id, "product", $param);
                        $dec .= $re["dec"];
                        if ($re["status"] == "error") {
                            return jsonrule(["status" => 406, "msg" => $re["msg"]]);
                        }
                        $re = $custom->edit($id, "product", $param);
                        $dec .= $re["dec"];
                        if ($re["status"] == "error") {
                            return jsonrule(["status" => 406, "msg" => $re["msg"]]);
                        }
                        $configoptionlinks = $param["configoptionlinks"];
                        $configoptionlinksArr = [];
                        \think\Db::name("product_config_links")->where("pid", $id)->find();
                        \think\Db::name("product_config_links")->where("pid", $id)->delete();
                        foreach ($configoptionlinks as $key => $value) {
                            $configoptionlinksArr[$key]["gid"] = $value;
                            $configoptionlinksArr[$key]["pid"] = $id;
                        }
                        \think\Db::name("product_config_links")->insertAll($configoptionlinksArr);
                        if ($auto_create_config_options == 1 && $param["type"] == "dcim") {
                            $server_info = \think\Db::name("servers")->field("id,hostname,username,password,secure,port,accesshash")->where("gid", $param["server_group"])->where("server_type", "dcim")->find();
                            $dcim = new \app\common\logic\Dcim();
                            $dcim->init($server_info);
                            $config_group_name = "裸金属-" . $param["name"];
                            $pricing = [];
                            if ($basedata["config_option1"] == "rent") {
                                $config_group_id = \think\Db::name("product_config_groups")->insertGetId(["name" => $config_group_name, "description" => $config_group_name]);
                                \think\Db::name("product_config_links")->insert(["gid" => $config_group_id, "pid" => $id]);
                                $config_options = [["option_name" => "area|区域", "option_type" => 12, "upgrade" => 0, "option" => []], ["option_name" => "os|操作系统", "option_type" => 5, "upgrade" => 0, "option" => []], ["option_name" => "server_group|硬件配置", "option_type" => 1, "upgrade" => 0, "option" => []], ["option_name" => "ip_num|IP数量", "option_type" => 1, "upgrade" => 1, "option" => [["name" => "1|1个", "monthly" => 10], ["name" => "3|3个", "monthly" => 30], ["name" => "5|5个", "monthly" => 50], ["name" => "NO_CHANGE|不变更", "monthly" => 0]]], ["option_name" => "bw|带宽", "option_type" => 1, "upgrade" => 1, "option" => [["name" => "10,10|10Mbps", "monthly" => 100], ["name" => "20,20|20Mbps", "monthly" => 200], ["name" => "50,50|50Mbps", "monthly" => 500], ["name" => "NO_CHANGE|不变更", "monthly" => 0]]]];
                                $dcim_area = $dcim->getArea();
                                foreach ($dcim_area as $v) {
                                    $config_options[0]["option"][] = ["name" => $v["id"] . "|" . $v["area"] . "^" . $v["area"]];
                                }
                                $dcim_os = $dcim->getFormatOs();
                                foreach ($dcim_os as $v) {
                                    $config_options[1]["option"][] = ["name" => $v];
                                }
                                $dcim_server_group = $dcim->getSaleGroup();
                                foreach ($dcim_server_group as $v) {
                                    $config_options[2]["option"][] = ["name" => $v["id"] . "|" . $v["name"]];
                                }
                                $i = 0;
                                foreach ($config_options as $v) {
                                    $i++;
                                    $d = ["gid" => $config_group_id, "option_name" => $v["option_name"], "option_type" => $v["option_type"], "order" => $i, "upgrade" => $v["upgrade"]];
                                    $config_options_id = \think\Db::name("product_config_options")->insertGetId($d);
                                    if (!empty($v["option"])) {
                                        foreach ($v["option"] as $vv) {
                                            $config_option_sub = ["config_id" => $config_options_id, "option_name" => $vv["name"]];
                                            $sub_id = \think\Db::name("product_config_options_sub")->insertGetId($config_option_sub);
                                            if (0 < $vv["monthly"]) {
                                                $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => $vv["monthly"]];
                                            } else {
                                                $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => 0];
                                            }
                                        }
                                    }
                                }
                                if (!empty($pricing)) {
                                    $currency = \think\Db::name("currencies")->column("id");
                                    foreach ($currency as $v) {
                                        foreach ($pricing as $kk => $vv) {
                                            $pricing[$kk]["currency"] = $v;
                                        }
                                        \think\Db::name("pricing")->insertAll($pricing);
                                    }
                                }
                                \think\Db::name("products")->where("id", $id)->update(["auto_create_config_options" => 1]);
                            } else {
                                if ($basedata["config_option1"] == "bms") {
                                    $config_group_id = \think\Db::name("product_config_groups")->insertGetId(["name" => $config_group_name, "description" => $config_group_name]);
                                    \think\Db::name("product_config_links")->insert(["gid" => $config_group_id, "pid" => $id]);
                                    $config_options = [["name" => "group_id|计算节点分组", "option_type" => 1, "upgrade" => 0, "option" => []], ["name" => "os|操作系统", "option_type" => 5, "upgrade" => 0, "option" => []], ["name" => "ip_num|IP数量", "option_type" => 4, "hidden" => 0, "qty_minimum" => 1, "qty_maximum" => 20, "upgrade" => 1, "option" => [["name" => "IP数量", "qty_minimum" => 1, "qty_maximum" => 20]]], ["name" => "system_disk_size|系统盘", "option_type" => 13, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "50|50G"]]], ["name" => "data_disk_size|数据盘", "option_type" => 14, "hidden" => 1, "qty_minimum" => 0, "qty_maximum" => 1000, "upgrade" => 1, "option" => [["name" => "数据盘", "qty_minimum" => 0, "qty_maximum" => 1000, "monthly" => 1]]], ["name" => "snap_num|快照数量", "option_type" => 1, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "2|2个"]]], ["name" => "backup_num|备份数量", "option_type" => 1, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "2|2个"]]]];
                                    $config_options[0]["option"] = $dcim->bmsLcGroup();
                                    $config_options[1]["option"] = $dcim->bmsOs();
                                    $i = 0;
                                    foreach ($config_options as $v) {
                                        $i++;
                                        $d = ["gid" => $config_group_id, "option_name" => $v["name"], "option_type" => $v["option_type"], "hidden" => (int) $v["hidden"], "order" => $i, "upgrade" => $v["upgrade"]];
                                        if (judgeQuantity($v["option_type"])) {
                                            $d["qty_minimum"] = $v["qty_minimum"];
                                            $d["qty_maximum"] = $v["qty_maximum"];
                                        }
                                        $config_options_id = \think\Db::name("product_config_options")->insertGetId($d);
                                        if (!empty($v["option"])) {
                                            foreach ($v["option"] as $vv) {
                                                $config_option_sub = ["config_id" => $config_options_id, "option_name" => $vv["name"], "qty_minimum" => $vv["qty_minimum"] ?: 0, "qty_maximum" => $vv["qty_maximum"] ?: 0];
                                                $sub_id = \think\Db::name("product_config_options_sub")->insertGetId($config_option_sub);
                                                if (0 < $vv["monthly"]) {
                                                    $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => $vv["monthly"]];
                                                } else {
                                                    $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => 0];
                                                }
                                            }
                                        }
                                    }
                                    if (!empty($pricing)) {
                                        $currency = \think\Db::name("currencies")->column("id");
                                        foreach ($currency as $v) {
                                            foreach ($pricing as $kk => $vv) {
                                                $pricing[$kk]["currency"] = $v;
                                            }
                                            \think\Db::name("pricing")->insertAll($pricing);
                                        }
                                    }
                                    \think\Db::name("products")->where("id", $id)->update(["auto_create_config_options" => 1]);
                                }
                            }
                        }
                        if ($auto_create_config_options == 1 && $param["type"] == "dcimcloud") {
                            $config_group_name = "魔方云-" . $param["name"];
                            $pricing = [];
                            $config_group_id = \think\Db::name("product_config_groups")->insertGetId(["name" => $config_group_name, "description" => $config_group_name]);
                            \think\Db::name("product_config_links")->insert(["gid" => $config_group_id, "pid" => $id]);
                            $config_options = [["name" => "area|区域", "option_type" => 12, "hidden" => 0, "option" => [], "upgrade" => 0], ["name" => "os|操作系统", "option_type" => 5, "hidden" => 0, "option" => [], "upgrade" => 0], ["name" => "cpu|CPU", "option_type" => 6, "hidden" => 0, "upgrade" => 1, "option" => [["name" => "1|1核"], ["name" => "2|2核"], ["name" => "4|4核"], ["name" => "8|8核"], ["name" => "12|12核"], ["name" => "16|16核"], ["name" => "24|24核"], ["name" => "32|32核"]]], ["name" => "memory|内存", "option_type" => 8, "hidden" => 0, "upgrade" => 1, "option" => [["name" => "1024|1G", "monthly" => 1], ["name" => "2048|2G", "monthly" => 2], ["name" => "4096|4G", "monthly" => 4], ["name" => "6144|6G", "monthly" => 6], ["name" => "8192|8G", "monthly" => 8], ["name" => "12288|12G", "monthly" => 12], ["name" => "24576|24G", "monthly" => 24], ["name" => "32768|32G", "monthly" => 32], ["name" => "65536|64G", "monthly" => 64], ["name" => "131072|128G", "monthly" => 128]]], ["name" => "system_disk_size|系统盘", "option_type" => 13, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "lin:30,win:50|Lin30G,Win50G"]]], ["name" => "network_type|网络类型", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "vpc|VPC网络"], ["name" => "normal|经典网络"]]], ["name" => "bw|带宽", "option_type" => 11, "hidden" => 0, "qty_minimum" => 1, "qty_maximum" => 100, "upgrade" => 1, "option" => [["name" => "带宽", "qty_minimum" => 1, "qty_maximum" => 100, "monthly" => 1]]], ["name" => "in_bw|流入带宽", "option_type" => 10, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "100|100Mbps"]]], ["name" => "ip_num|IP数量", "option_type" => 4, "hidden" => 0, "qty_minimum" => 1, "qty_maximum" => 20, "upgrade" => 1, "option" => [["name" => "IP数量", "qty_minimum" => 1, "qty_maximum" => 20]]], ["name" => "data_disk_size|数据盘", "option_type" => 14, "hidden" => 0, "qty_minimum" => 0, "qty_maximum" => 1000, "upgrade" => 0, "option" => [["name" => "数据盘", "qty_minimum" => 0, "qty_maximum" => 1000, "monthly" => 1]]], ["name" => "snap_num|快照数量", "option_type" => 1, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "2|2个"]]], ["name" => "backup_num|备份数量", "option_type" => 1, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "2|2个"]]], ["name" => "nat_acl_limit|NAT转发", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "-1|不支持"], ["name" => "0|不限制"], ["name" => "10|10个"]]], ["name" => "nat_web_limit|共享建站", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "-1|不支持"], ["name" => "0|不限制"], ["name" => "10|10个"]]], ["name" => "system_disk_io_limit|系统盘性能", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "0,0,0,0|不限制性能"], ["name" => "500,500,2000,2000|500持续2000IOPS"]]], ["name" => "data_disk_io_limit|数据盘性能", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "0,0,0,0|不限制性能"], ["name" => "500,500,2000,2000|500持续2000IOPS"]]], ["name" => "traffic_bill_type|流量计费方式", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "month|自然月"], ["name" => "last_30days|订购日至下月"]]]];
                            $server_info = \think\Db::name("servers")->field("id")->where("gid", $param["server_group"])->where("server_type", "dcimcloud")->find();
                            $dcimcloud = new \app\common\logic\DcimCloud();
                            $dcimcloud->setUrl($server_info["id"]);
                            $config_options[0]["option"] = $dcimcloud->getArea();
                            $config_options[1]["option"] = $dcimcloud->getOs();
                            $pricing = [];
                            $i = 0;
                            foreach ($config_options as $v) {
                                $i++;
                                $d = ["gid" => $config_group_id, "option_name" => $v["name"], "option_type" => $v["option_type"], "hidden" => $v["hidden"], "order" => $i, "upgrade" => $v["upgrade"]];
                                if (judgeQuantity($v["option_type"])) {
                                    $d["qty_minimum"] = $v["qty_minimum"];
                                    $d["qty_maximum"] = $v["qty_maximum"];
                                }
                                $config_options_id = \think\Db::name("product_config_options")->insertGetId($d);
                                if (!empty($v["option"])) {
                                    foreach ($v["option"] as $vv) {
                                        $config_option_sub = ["config_id" => $config_options_id, "option_name" => $vv["name"], "qty_minimum" => $vv["qty_minimum"] ?: 0, "qty_maximum" => $vv["qty_maximum"] ?: 0];
                                        $sub_id = \think\Db::name("product_config_options_sub")->insertGetId($config_option_sub);
                                        if (0 < $vv["monthly"]) {
                                            $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => $vv["monthly"]];
                                        } else {
                                            $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => 0];
                                        }
                                    }
                                }
                            }
                            if (!empty($pricing)) {
                                $currency = \think\Db::name("currencies")->column("id");
                                foreach ($currency as $v) {
                                    foreach ($pricing as $kk => $vv) {
                                        $pricing[$kk]["currency"] = $v;
                                    }
                                    \think\Db::name("pricing")->insertAll($pricing);
                                }
                            }
                            \think\Db::name("products")->where("id", $id)->update(["auto_create_config_options" => 1]);
                        }
                        if ($auto_create_config_options == 2) {
                            $config_group_name = "nokvm-" . $param["name"];
                            $pricing = [];
                            $config_group_id = \think\Db::name("product_config_groups")->insertGetId(["name" => $config_group_name, "description" => $config_group_name]);
                            \think\Db::name("product_config_links")->insert(["gid" => $config_group_id, "pid" => $id]);
                            $config_options = [["name" => "os|操作系统", "option_type" => 5, "upgrade" => 0, "option" => ["1|centos^CentOS 7.6 64位"]], ["name" => "Location|数据中心", "option_type" => 12, "upgrade" => 0, "option" => ["1|CN^香港"]], ["name" => "CPU|处理器核心", "option_type" => 6, "upgrade" => 1, "option" => ["4|4核"]], ["name" => "Memory|内存", "option_type" => 8, "upgrade" => 1, "option" => ["2048|2G"]], ["name" => "Disk Space|数据盘", "option_type" => 13, "upgrade" => 0, "option" => ["40|40G"]], ["name" => "Network Speed|带宽", "option_type" => 10, "upgrade" => 1, "option" => ["1280|10M"]], ["name" => "Snapshot|快照数量", "option_type" => 1, "upgrade" => 1, "option" => ["10|10份"]], ["name" => "Backups|备份数量", "option_type" => 1, "upgrade" => 1, "option" => ["10|10份"]], ["name" => "Extra IP Address|额外IP数量", "option_type" => 1, "upgrade" => 1, "option" => ["1|1 IP"]], ["name" => "nat_acl_limit|NAT转发", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => ["0|不支持", "10|10个"]], ["name" => "nat_web_limit|共享建站", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => ["0|不支持", "10|10个"]]];
                            $pricing = [];
                            $i = 0;
                            foreach ($config_options as $v) {
                                $i++;
                                $d = ["gid" => $config_group_id, "option_name" => $v["name"], "option_type" => $v["option_type"], "upgrade" => $v["upgrade"], "hidden" => (int) $v["hidden"], "order" => $i];
                                $config_options_id = \think\Db::name("product_config_options")->insertGetId($d);
                                foreach ($v["option"] as $vv) {
                                    $config_option_sub = ["config_id" => $config_options_id, "option_name" => $vv];
                                    $sub_id = \think\Db::name("product_config_options_sub")->insertGetId($config_option_sub);
                                    $pricing[] = ["type" => "configoptions", "relid" => $sub_id];
                                }
                            }
                            if (!empty($pricing)) {
                                $currency = \think\Db::name("currencies")->column("id");
                                foreach ($currency as $v) {
                                    foreach ($pricing as $kk => $vv) {
                                        $pricing[$kk]["currency"] = $v;
                                    }
                                    \think\Db::name("pricing")->insertAll($pricing);
                                }
                            }
                            \think\Db::name("products")->where("id", $id)->update(["auto_create_config_options" => 1]);
                        }
                    }
                    if (empty($this->request->afpid)) {
                        $param = $this->request->param();
                        $data["pid"] = isset($param["id"]) ? floatval($param["id"]) : 0;
                        $data["affiliate_enabled"] = isset($param["affiliate_enabled"]) ? floatval($param["affiliate_enabled"]) : 0;
                        $data["affiliate_bates"] = isset($param["affiliate_bates"]) ? floatval($param["affiliate_bates"]) : 0;
                        $data["affiliate_type"] = isset($param["affiliate_type"]) ? intval($param["affiliate_type"]) : 0;
                        $data["affiliate_is_reorder"] = isset($param["affiliate_is_reorder"]) ? intval($param["affiliate_is_reorder"]) : 0;
                        $data["affiliate_reorder"] = isset($param["affiliate_reorder"]) ? intval($param["affiliate_reorder"]) : 0;
                        $data["affiliate_reorder_type"] = isset($param["affiliate_reorder_type"]) ? intval($param["affiliate_reorder_type"]) : 0;
                        $data["affiliate_is_renew"] = isset($param["affiliate_is_renew"]) ? intval($param["affiliate_is_renew"]) : 0;
                        $data["affiliate_renew"] = isset($param["affiliate_renew"]) ? intval($param["affiliate_renew"]) : 0;
                        $data["affiliate_renew_type"] = isset($param["affiliate_renew_type"]) ? intval($param["affiliate_renew_type"]) : 0;
                        $data["create_time"] = time();
                        $res = \think\Db::name("affiliates_products_setting")->insertGetId($data);
                        active_log(sprintf($this->lang["Aff_admin_productaffiAdd"], $p["name"], $res));
                    } else {
                        $spg = \think\Db::name("affiliates_products_setting")->where("id", $this->request->afpid)->find();
                        $param = $this->request->param();
                        $data["affiliate_enabled"] = isset($param["affiliate_enabled"]) ? floatval($param["affiliate_enabled"]) : 0;
                        if ($data["affiliate_enabled"] == 1) {
                            $data["affiliate_bates"] = isset($param["affiliate_bates"]) ? floatval($param["affiliate_bates"]) : 0;
                            $data["affiliate_type"] = isset($param["affiliate_type"]) ? intval($param["affiliate_type"]) : 0;
                            $data["affiliate_is_reorder"] = isset($param["affiliate_is_reorder"]) ? intval($param["affiliate_is_reorder"]) : 0;
                            $data["affiliate_reorder"] = isset($param["affiliate_reorder"]) ? intval($param["affiliate_reorder"]) : 0;
                            $data["affiliate_reorder_type"] = isset($param["affiliate_reorder_type"]) ? intval($param["affiliate_reorder_type"]) : 0;
                            $data["affiliate_is_renew"] = isset($param["affiliate_is_renew"]) ? intval($param["affiliate_is_renew"]) : 0;
                            $data["affiliate_renew"] = isset($param["affiliate_renew"]) ? intval($param["affiliate_renew"]) : 0;
                            $data["affiliate_renew_type"] = isset($param["affiliate_renew_type"]) ? intval($param["affiliate_renew_type"]) : 0;
                            $desc = "";
                            if ($spg["affiliate_bates"] != $param["affiliate_bates"]) {
                                $desc .= " 产品推介计划比例" . $spg["affiliate_bates"] . "改为" . $param["affiliate_bates"];
                            }
                            if ($spg["affiliate_type"] != $param["affiliate_type"]) {
                                if ($spg["affiliate_type"] == 1) {
                                    $dec .= "产品比例类型由“百分比”改为“金额”，";
                                } else {
                                    $dec .= "产品比例类型由“金额”改为“百分比”，";
                                }
                            }
                            if ($spg["affiliate_is_reorder"] != $param["affiliate_is_reorder"]) {
                                if ($spg["affiliate_is_reorder"] == 1) {
                                    $dec .= "产品二次订购由“关闭”改为“开启”，";
                                } else {
                                    $dec .= "产品二次订购由“开启”改为“关闭”，";
                                }
                            }
                            if ($spg["affiliate_reorder"] != $param["affiliate_reorder"]) {
                                $desc .= "二次订购比例由“" . $spg["affiliate_reorder"] . "”改为“" . $param["affiliate_reorder"] . "”，";
                            }
                            if ($spg["affiliate_is_renew"] != $param["affiliate_is_renew"]) {
                                if ($spg["affiliate_is_renew"] == 1) {
                                    $dec .= " 产品续费由“关闭”改为“开启”，";
                                } else {
                                    $dec .= " 产品续费由“开启”改为“关闭”，";
                                }
                            }
                            if ($spg["affiliate_renew"] != $param["affiliate_renew"]) {
                                $desc .= "续费比例由“" . $spg["affiliate_renew"] . "”改为“" . $param["affiliate_renew"] . "”，";
                            }
                        }
                        if ($spg["affiliate_enabled"] != $param["affiliate_enabled"]) {
                            $str = "";
                            if ($spg["affiliate_enabled"] == 1) {
                                $str = "自定义";
                            } else {
                                if ($spg["affiliate_enabled"] == 0) {
                                    $str = "跟随系统";
                                } else {
                                    $str = "关闭";
                                }
                            }
                            if ($param["affiliate_enabled"] == 1) {
                                $dec .= " 产品推介计划由“" . $str . "”改为“自定义”，";
                            } else {
                                if ($param["affiliate_enabled"] == 0) {
                                    $dec .= " 产品推介计划由“" . $str . "”改为“跟随系统”，";
                                } else {
                                    $dec .= " 产品推介计划由“" . $str . "”改为“关闭”，";
                                }
                            }
                        }
                        \think\Db::name("affiliates_products_setting")->where("id", $this->request->afpid)->update($data);
                        if (empty($desc)) {
                            $dec .= "什么都没修改";
                        }
                    }
                    \think\Db::commit();
                } catch (\Exception $e) {
                    \think\Db::rollback();
                    return jsonrule(["status" => 406, "msg" => lang("UPDATE FAIL")]);
                }
            }
            if (empty($dec)) {
                $dec .= "什么都没修改";
            }
            active_log(sprintf($this->lang["Product_admin_edit"], $id, $dec));
            unset($dec);
            hook("product_edit", ["pid" => $id]);
            (new \app\common\logic\Product())->updateCache([$id]);
            return jsonrule(["status" => 200, "msg" => lang("EDIT SUCCESS")]);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function editResProduct(\think\Request $request)
    {
        $validate = new \app\admin\validate\ProductsValidate();
        $param = $request->param();
        if (empty($param["pay_type"])) {
            return jsonrule(["status" => 406, "msg" => lang("THERE_MUST_BE_A_VALID_PERIOD")]);
        }
        if (empty($param["password_show"]) && isset($param["password_rule_len_num"])) {
            unset($param["password_rule_len_num"]);
        }
        if (empty($param["host_show"]) && isset($param["host_rule_len_num"])) {
            unset($param["host_rule_len_num"]);
        }
        $result = $validate->check($param);
        if (!$result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $result = [];
        $id = $param["id"];
        $product = \think\Db::name("products")->where("id", $id)->find();
        if (empty($product)) {
            return jsonrule(["status" => 406, "msg" => lang("ID_ERROR")]);
        }
        $rate = $param["rate"] ? floatval($param["rate"]) : $product["rate"];
        $api_type = isset($param["api_type"]) ? $param["api_type"] : "";
        if (empty($api_type)) {
            return jsonrule(["status" => 400, "msg" => lang("接口类型非空")]);
        }
        if (!in_array($api_type, array_column(config("allow_api_type"), "name"))) {
            return jsonrule(["status" => 400, "msg" => lang("接口类型错误")]);
        }
        if ($api_type != "zjmf_api") {
            $auto_create_config_options = 0;
            if ($product["auto_create_config_options"] == 0 && $param["type"] == "dcim") {
                if (!empty($param["server_group"])) {
                    $server_group = \think\Db::name("server_groups")->where("id", $param["server_group"])->where("system_type", "dcim")->find();
                    if (empty($server_group)) {
                        $result["status"] = 406;
                        $result["msg"] = "产品类型和服务器不一致";
                        return jsonrule($result);
                    }
                    if ($server_group["system_type"] == "dcim") {
                        $auto_create_config_options = 1;
                    }
                }
            } else {
                if ($product["auto_create_config_options"] == 0 && $param["type"] == "dcimcloud") {
                    if (!empty($param["server_group"])) {
                        $server_group = \think\Db::name("server_groups")->where("id", $param["server_group"])->where("system_type", "dcimcloud")->find();
                        if (empty($server_group)) {
                            $result["status"] = 406;
                            $result["msg"] = "产品类型和服务器不一致";
                            return jsonrule($result);
                        }
                        if ($server_group["system_type"] == "dcimcloud") {
                            $auto_create_config_options = 1;
                        }
                    }
                } else {
                    if ($product["auto_create_config_options"] == 0 && $param["type"] == "cloud" && !empty($param["server_group"])) {
                        $server_group = \think\Db::name("server_groups")->where("id", $param["server_group"])->find();
                        if ($server_group["type"] == "nokvm") {
                            $auto_create_config_options = 2;
                        }
                    }
                }
            }
        }
        if ($api_type == "zjmf_api") {
            $combine_id = $param["server_group"];
            $combine = \think\Db::name("zjmf_finance_api")->where("id", $combine_id)->find();
            if ($combine["type"] == "manual") {
                $api_type = "manual";
                $param["upper_reaches_id"] = $param["server_group"];
            }
        }
        if (0 < $param["upper_reaches_id"]) {
            $upper_reaches = \think\Db::name("zjmf_finance_api")->where("type", "manual")->where("id", (int) $param["upper_reaches_id"])->find();
            if (empty($upper_reaches)) {
                return jsonrule(["status" => 400, "msg" => lang("供应商不存在")]);
            }
        }
        $dec = "";
        $basedata = [];
        $basedata["type"] = $param["type"] ?: "other";
        if (!empty($basedata["type"]) && $basedata["type"] != $product["type"]) {
            $dec .= "产品类型由“" . $product["type"] . "”改为“" . $param["type"] . "”，";
        }
        $basedata["gid"] = $param["gid"];
        if (!empty($basedata["gid"]) && $basedata["gid"] != $product["gid"]) {
            $dec .= "产品组名由“" . $product["gid"] . "”改为“" . $param["gid"] . "”，";
        }
        $basedata["name"] = $param["name"];
        if (!empty($basedata["name"]) && $basedata["name"] != $product["name"]) {
            $dec .= "产品名由“" . $product["name"] . "”改为“" . $param["name"] . "”，";
        }
        $basedata["description"] = $param["description"];
        if (!empty($basedata["description"]) && $basedata["description"] != $product["description"]) {
            $dec .= "产品描述由“" . $product["description"] . "”改为“" . $param["description"] . "”，";
        }
        $basedata["welcome_email"] = $param["welcome_email"];
        if (!empty($basedata["welcome_email"]) && $basedata["welcome_email"] != $product["welcome_email"]) {
            $dec .= "产品开通邮箱由“" . $product["welcome_email"] . "”改为“" . $param["welcome_email"] . "”，";
        }
        $basedata["clientscount"] = $param["clientscount"];
        if (!empty($basedata["clientscount"]) && $basedata["clientscount"] != $product["clientscount"]) {
            $dec .= "单个客户购买此产品的数量由“" . $product["clientscount"] . "”改为“" . $param["clientscount"] . "”，";
        }
        $basedata["product_shopping_url"] = $param["product_shopping_url"] ?: request()->domain() . "/cart?action=configureproduct&pid=" . $id;
        if (!empty($basedata["product_shopping_url"]) && $basedata["product_shopping_url"] != $product["product_shopping_url"]) {
            $dec .= "快速订购连接由“" . $product["product_shopping_url"] . "”改为“" . $param["product_shopping_url"] . "”，";
        }
        $first_gid = \think\Db::name("product_groups")->where("id", $basedata["gid"])->value("gid");
        $basedata["product_group_url"] = $param["product_group_url"] ?: request()->domain() . "/cart?gid=" . $basedata["gid"] . "&fid=" . $first_gid;
        if (!empty($basedata["product_group_url"]) && $basedata["product_group_url"] != $product["product_group_url"]) {
            $dec .= "产品组连接由“" . $product["product_group_url"] . "”改为“" . $param["product_group_url"] . "”，";
        }
        $basedata["is_truename"] = $param["is_truename"];
        if ($basedata["is_truename"] != $product["is_truename"] && !empty($basedata["is_truename"])) {
            if ($basedata["is_truename"] == 0) {
                $dec .= "产品实名认证由“开启”改为“关闭”，";
            } else {
                $dec .= "产品实名认证由“关闭”改为“开启”，";
            }
        }
        $basedata["is_bind_phone"] = $param["is_bind_phone"];
        if ($basedata["is_bind_phone"] != $product["is_bind_phone"] && !empty($basedata["is_bind_phone"])) {
            if ($basedata["is_bind_phone"] == 0) {
                $dec .= "产品绑定手机由“开启”改为“关闭”，";
            } else {
                $dec .= "产品绑定手机由“关闭”改为“开启”，";
            }
        }
        $basedata["stock_control"] = !empty($param["stock_control"]) ? 1 : 0;
        if ($basedata["stock_control"] != $product["stock_control"]) {
            if ($basedata["stock_control"] == 1) {
                $dec .= "库存控制由“禁用”改为“启用”，";
            } else {
                $dec .= "库存控制由“启用”改为“禁用”，";
            }
        }
        $basedata["qty"] = intval($param["qty"]);
        if (!empty($basedata["qty"]) && $basedata["qty"] != $product["qty"]) {
            $dec .= "库存由“" . $product["qty"] . "”改为“" . $param["qty"] . "”，";
        }
        $basedata["groupid"] = $param["groupid"];
        if (empty($param["groupid"])) {
            if ($param["type"] == "dcim") {
                $basedata["groupid"] = 2;
            } else {
                if ($param["type"] == "clound" || $param["type"] == "dcimcloud") {
                    $basedata["groupid"] = 1;
                } else {
                    $basedata["groupid"] = 3;
                }
            }
        }
        try {
            $this->setProToNav($param, $id);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
        }
        $payArr = [];
        $payArr["pay_type"] = $param["pay_type"];
        $payArr["pay_hour_cycle"] = $param["pay_hour_cycle"] ?? 720;
        $payArr["pay_day_cycle"] = $param["pay_day_cycle"] ?? 30;
        $payArr["pay_ontrial_status"] = !empty($param["pay_ontrial_status"]) ? 1 : 0;
        $payArr["pay_ontrial_cycle"] = $param["pay_ontrial_cycle"] ?? 0;
        $payArr["pay_ontrial_num"] = $param["pay_ontrial_num"] ?? 1;
        $payArr["pay_ontrial_condition"] = $param["pay_ontrial_condition"] ?? [];
        $payArr["pay_ontrial_cycle_type"] = $param["pay_ontrial_cycle_type"] ?: "day";
        if (!getEdition() && 1 <= $param["pay_ontrial_num_rule"]) {
            return jsonrule(["status" => 400, "msg" => "试用数量计算规则专业版可用，免费版不可修改"]);
        }
        $payArr["pay_ontrial_num_rule"] = $param["pay_ontrial_num_rule"] ?? 0;
        if (!getEdition() && 1 <= $param["clientscount_rule"]) {
            return jsonrule(["status" => 400, "msg" => "商品数量计算规则专业版可用，免费版不可修改"]);
        }
        $payArr["clientscount_rule"] = $param["clientscount_rule"] ?? 0;
        $payArrToJson = json_encode($payArr);
        $basedata["pay_type"] = $payArrToJson;
        $basedata["pay_method"] = $param["pay_method"] ?: "prepayment";
        if ($basedata["pay_method"] != $product["pay_method"] && !empty($basedata["pay_method"])) {
            if ($basedata["pay_method"] == 1) {
                $dec .= "付款方式由“后付费”改为“预付费”，";
            } else {
                $dec .= "付款方式由“预付费”改为“后付费”，";
            }
        }
        $basedata["allow_qty"] = !empty($param["allow_qty"]) ? 1 : 0;
        if ($basedata["allow_qty"] != $product["allow_qty"] && !empty($basedata["allow_qty"])) {
            if ($param["allow_qty"] == 1) {
                $dec .= "允许购买多个由“关闭”改为“开启”，";
            } else {
                $dec .= "允许购买多个由“开启”改为“关闭”，";
            }
        }
        if (!empty($basedata["pay_type"]) && $basedata["pay_type"] != $product["pay_type"]) {
            $pay_type = json_decode($product["pay_type"], true);
            if ($pay_type["pay_type"] != $payArr["pay_type"]) {
                $arr = config("pay_type");
                $dec .= "付款类型由“" . $arr[$pay_type["pay_type"]] . "”改为“" . $arr[$payArr["pay_type"]];
            }
            if ($pay_type["pay_ontrial_status"] != $payArr["pay_ontrial_status"]) {
                if ($payArr["pay_ontrial_status"] == 1) {
                    $dec .= "试用由“关闭”改为“开启”，";
                } else {
                    $dec .= "试用由“开启”改为“关闭”，";
                }
            }
            if ($pay_type["pay_hour_cycle"] != $payArr["pay_hour_cycle"]) {
                $dec .= "收费周期小时由“" . $pay_type["pay_hour_cycle"] . "”改为“" . $payArr["pay_hour_cycle"] . "”，";
            }
            if ($pay_type["pay_day_cycle"] != $payArr["pay_day_cycle"]) {
                $dec .= "收费周期天由“" . $pay_type["pay_day_cycle"] . "”改为“" . $payArr["pay_day_cycle"] . "”，";
            }
            if ($pay_type["pay_ontrial_cycle"] != $payArr["pay_ontrial_cycle"]) {
                $dec .= "试用的时间由“" . $pay_type["pay_ontrial_cycle"] . "”改为“" . $payArr["pay_ontrial_cycle"] . "”，";
            }
            if ($pay_type["pay_ontrial_condition"] != $payArr["pay_ontrial_condition"]) {
                $arr = config("pay_ontrial_condition_cn_new");
                $pay_type_pay_ontrial_condition = array_flip($pay_type["pay_ontrial_condition"]);
                $pay_type_text = implode(",", array_intersect_key($arr, $pay_type_pay_ontrial_condition));
                $payArr_pay_ontrial_condition = array_flip($payArr["pay_ontrial_condition"]);
                $payArr_text = implode(",", array_intersect_key($arr, $payArr_pay_ontrial_condition));
                $dec .= "试用条件由“" . $pay_type_text . "”改为“" . $payArr_text . "”，";
            }
        }
        $basedata["auto_setup"] = $param["auto_setup"] ?: "";
        if (!empty($basedata["auto_setup"]) && $basedata["auto_setup"] != $product["auto_setup"]) {
            $dec .= "购买后动作设置由“" . $product["auto_setup"] . "”改为“" . $param["auto_setup"] . "”，";
        }
        $basedata["server_type"] = $param["server_type"] ?: "";
        if (!empty($basedata["server_type"]) && $basedata["server_type"] != $product["server_type"]) {
            $dec .= "服务器模块类型由“" . $product["server_type"] . "”改为“" . $param["server_type"] . "”，";
        }
        $basedata["server_group"] = $param["server_group"];
        if (!empty($basedata["server_group"]) && $basedata["server_group"] != $product["server_group"]) {
            $server_data = \think\Db::name("server_groups")->field("name")->where("id=" . $basedata["server_group"])->find();
            $server_data1 = \think\Db::name("server_groups")->field("name")->where("id=" . $product["server_group"])->find();
            $dec .= "服务器组由“" . $server_data["name"] . "”改为“" . $server_data1["name"] . "”，";
        }
        $basedata["config_option1"] = $param["packageconfigoption"][1];
        if ($param["type"] == "dcim") {
            $basedata["config_option1"] = $basedata["config_option1"] ?: "rent";
        }
        $basedata["config_option2"] = $param["packageconfigoption"][2];
        $basedata["config_option3"] = $param["packageconfigoption"][3];
        $basedata["config_option4"] = $param["packageconfigoption"][4];
        $basedata["config_option5"] = $param["packageconfigoption"][5];
        $basedata["config_option6"] = $param["packageconfigoption"][6];
        $basedata["config_option7"] = $param["packageconfigoption"][7];
        $basedata["config_option8"] = $param["packageconfigoption"][8];
        $basedata["config_option9"] = $param["packageconfigoption"][9];
        $basedata["config_option10"] = $param["packageconfigoption"][10];
        $basedata["config_option11"] = $param["packageconfigoption"][11];
        $basedata["config_option12"] = $param["packageconfigoption"][12];
        $basedata["config_option13"] = $param["packageconfigoption"][13];
        $basedata["config_option14"] = $param["packageconfigoption"][14];
        $basedata["config_option15"] = $param["packageconfigoption"][15];
        $basedata["config_option16"] = $param["packageconfigoption"][16];
        $basedata["config_option17"] = $param["packageconfigoption"][17];
        $basedata["config_option18"] = $param["packageconfigoption"][18];
        $basedata["config_option19"] = $param["packageconfigoption"][19];
        $basedata["config_option20"] = $param["packageconfigoption"][20];
        $basedata["config_option21"] = $param["packageconfigoption"][21];
        $basedata["config_option22"] = $param["packageconfigoption"][22];
        $basedata["config_option23"] = $param["packageconfigoption"][23];
        $basedata["config_option24"] = $param["packageconfigoption"][24];
        $basedata["recurring_cycles"] = $param["recurring_cycles"];
        if (!empty($basedata["recurring_cycles"]) && $basedata["recurring_cycles"] != $product["recurring_cycles"]) {
            $dec .= "循环周期由“" . $product["recurring_cycles"] . "”改为“" . $param["recurring_cycles"] . "”，";
        }
        $basedata["auto_terminate_days"] = intval($param["auto_terminate_days"]);
        if (!empty($basedata["auto_terminate_days"]) && $basedata["auto_terminate_days"] != $product["auto_terminate_days"]) {
            $dec .= "自动删除/固定周期由“" . $product["auto_terminate_days"] . "”改为“" . $param["auto_terminate_days"] . "”，";
        }
        $basedata["auto_terminate_email"] = intval($param["auto_terminate_email"]);
        if (!empty($basedata["auto_terminate_email"]) && $basedata["auto_terminate_email"] != $product["auto_terminate_email"]) {
            $dec .= "自动终止邮件ID由“" . $product["auto_terminate_email"] . "”改为“" . $param["auto_terminate_email"] . "”，";
        }
        $basedata["config_options_upgrade"] = !empty($param["config_options_upgrade"]) ? 1 : 0;
        if (!empty($basedata["config_options_upgrade"]) && $basedata["config_options_upgrade"] != $product["config_options_upgrade"]) {
            if ($basedata["config_options_upgrade"] == 1) {
                $dec .= "升级可配置选项由“关闭”改为“开启”，";
            } else {
                $dec .= "升级可配置选项由“开启”改为“关闭”，";
            }
        }
        $basedata["upgrade_email"] = intval($param["upgrade_email"]);
        if (!empty($basedata["upgrade_email"]) && $basedata["upgrade_email"] != $product["upgrade_email"]) {
            $dec .= "升级邮件由“" . $product["upgrade_email"] . "”改为“" . $param["upgrade_email"] . "”，";
        }
        $basedata["retired"] = !empty($param["retired"]) ? 1 : 0;
        if (!empty($basedata["retired"]) && $basedata["retired"] != $product["retired"]) {
            if ($basedata["retired"] == 1) {
                $dec .= "商品由“上架”改为“下架”，";
            } else {
                $dec .= "商品由“下架”改为“上架”，";
            }
        }
        $basedata["is_featured"] = !empty($param["is_featured"]) ? 1 : 0;
        if (!empty($basedata["is_featured"]) && $basedata["is_featured"] != $product["is_featured"]) {
            if ($basedata["is_featured"] == 1) {
                $dec .= "商品特性由“不添加”改为“添加”，";
            } else {
                $dec .= "商品特性由“添加”改为“不添加”，";
            }
        }
        $basedata["hidden"] = !empty($param["hidden"]) ? 1 : 0;
        if (!empty($basedata["hidden"]) && $basedata["hidden"] != $product["hidden"]) {
            if ($basedata["hidden"] == 1) {
                $dec .= "商品由“显示”改为“隐藏”，";
            } else {
                $dec .= "商品由“隐藏”改为“显示”，";
            }
        }
        $basedata["cancel_control"] = !empty($param["cancel_control"]) ? 1 : 0;
        if (!is_profession()) {
            $basedata["cancel_control"] = 1;
        }
        if (!empty($basedata["cancel_control"]) && $basedata["cancel_control"] != $product["cancel_control"] && is_profession()) {
            if ($basedata["cancel_control"] == 1) {
                $dec .= "商品取消停用由“显示”改为“隐藏”，";
            } else {
                $dec .= "商品取消停用由“隐藏”改为“显示”，";
            }
        }
        $basedata["affiliate_pay_type"] = $param["affiliate_pay_type"] ?: "default";
        if (!empty($basedata["affiliate_pay_type"]) && $basedata["affiliate_pay_type"] != $product["affiliate_pay_type"]) {
            $dec .= "自定义佣金设置由“" . $product["affiliate_pay_type"] . "”改为“" . $param["affiliate_pay_type"] . "”，";
        }
        $basedata["affiliate_pay_amount"] = $param["affiliate_pay_amount"] ?: 0;
        if (!empty($basedata["affiliate_pay_amount"]) && $basedata["affiliate_pay_amount"] != $product["affiliate_pay_amount"]) {
            $dec .= "金额百分比由“" . $product["affiliate_pay_amount"] . "”改为“" . $param["affiliate_pay_amount"] . "”，";
        }
        $basedata["affiliateonetime"] = !empty($param["affiliateonetime"]) ? 1 : 0;
        if (!empty($basedata["affiliateonetime"]) && $basedata["affiliateonetime"] != $product["affiliateonetime"]) {
            $dec .= "一次性支付由“" . $product["affiliateonetime"] . "”改为“" . $param["affiliateonetime"] . "”，";
        }
        $basedata["update_time"] = time();
        $host_rule = $host = [];
        $host["show"] = $param["host_show"] ?? 0;
        $host["modify"] = $param["host_modify"] ?? 0;
        $host["prefix"] = $param["host_prefix"] ?? "cloud";
        if ($host["show"] && empty($param["host_rule_upper"]) && empty($param["host_rule_lower"]) && empty($param["host_rule_num"])) {
            return jsonrule(["status" => 406, "msg" => "主机名规则数字、大小写字母至少选择一样"]);
        }
        $host_rule["upper"] = $param["host_rule_upper"] ?? 0;
        $host_rule["lower"] = $param["host_rule_lower"] ?? 0;
        $host_rule["num"] = $param["host_rule_num"] ?? 0;
        $host_rule["len_num"] = $param["host_rule_len_num"] ?? 12;
        $host["rule"] = $host_rule;
        $basedata["host"] = json_encode($host);
        $password_rule = $password = [];
        $password["show"] = $param["password_show"] ?? 0;
        $password["modify"] = $param["password_modify"] ?? 0;
        $password_rule["len_num"] = $param["password_rule_len_num"] ?? 12;
        $password_rule["upper"] = $param["password_rule_upper"] ?? 1;
        $password_rule["lower"] = $param["password_rule_lower"] ?? 1;
        $password_rule["num"] = $param["password_rule_num"] ?? 1;
        $password_rule["special"] = $param["password_rule_special"] ?? 0;
        if ($password_rule["special"] == 1 && $basedata["type"] == "dcim") {
            return jsonrule(["status" => 406, "msg" => "魔方DCIM密码不能包含特殊字符！"]);
        }
        $password["rule"] = $password_rule;
        $basedata["password"] = json_encode($password);
        $basedata["is_domain"] = $param["is_domain"] ? intval($param["is_domain"]) : 0;
        if ($api_type != "zjmf_api") {
            $basedata["upstream_version"] = 0;
            $basedata["zjmf_api_id"] = 0;
            $basedata["upstream_pid"] = 0;
        }
        if ($api_type != "manual") {
            $basedata["upper_reaches_id"] = 0;
        } else {
            $basedata["upper_reaches_id"] = $param["upper_reaches_id"] ? intval($param["upper_reaches_id"]) : 0;
        }
        $basedata["upstream_pid"] = $param["upstream_pid"] ?? intval($param["upstream_pid"]);
        $basedata["api_type"] = $api_type;
        $basedata["location_version"] = $product["location_version"] + 1;
        $pricingData = $param["currency"];
        $zjmf_finance_api_id = intval($param["server_group"]);
        if ($api_type == "zjmf_api" && (empty($zjmf_finance_api_id) || empty($basedata["upstream_pid"]))) {
            return jsonrule(["status" => 400, "msg" => lang("缺少接口ID或上游产品ID")]);
        }
        $upstream_price_type = isset($param["upstream_price_type"]) ? $param["upstream_price_type"] : "percent";
        if ($api_type == "zjmf_api" && 0 < $product["upstream_version"]) {
            if (!in_array($upstream_price_type, ["percent", "custom"])) {
                return jsonrule(["status" => 400, "msg" => lang("产品价格方案只能为百分比或自定义")]);
            }
            $upstream_price_value = isset($param["upstream_price_value"]) ? floatval($param["upstream_price_value"]) : 120;
            $basedata["upstream_price_type"] = $upstream_price_type;
            $basedata["upstream_price_value"] = $upstream_price_value;
            $upstream_res = getZjmfUpstreamProductConfig($zjmf_finance_api_id, $basedata["upstream_pid"]);
            if ($upstream_res["status"] == 200) {
                $upstream_product = $upstream_res["data"]["products"] ?? [];
                if (!empty($upstream_product)) {
                    if ($upstream_product["allow_qty"] == 0) {
                        $basedata["allow_qty"] = 0;
                    }
                    $basedata["stock_control"] = $upstream_product["stock_control"];
                    $basedata["qty"] = $upstream_product["qty"];
                    $basedata["password"] = $upstream_product["password"];
                    $upstream_paytype = json_decode($upstream_product["pay_type"], true);
                    if (array_diff($upstream_paytype["pay_ontrial_condition"], $param["pay_ontrial_condition"] ?? [])) {
                        $pay_type = json_decode($upstream_product["pay_type"], true);
                        if ($pay_type["pay_type"] == "day" || $pay_type["pay_type"] == "hour") {
                            $pay_type["pay_type"] = "recurring";
                        }
                        $basedata["pay_type"] = json_encode($pay_type);
                    }
                    $basedata["auto_terminate_days"] = $upstream_product["auto_terminate_days"];
                    $basedata["config_options_upgrade"] = $upstream_product["config_options_upgrade"];
                    $basedata["down_configoption_refund"] = $upstream_product["down_configoption_refund"];
                    $basedata["retired"] = $upstream_product["retired"];
                    $basedata["is_featured"] = $upstream_product["is_featured"];
                }
                if (!empty($upstream_res["data"]["product_pricings"][0]) && $upstream_price_type == "custom") {
                    $upstream_product_pricings = $upstream_res["data"]["product_pricings"];
                    $product_pricings = \think\Db::name("pricing")->alias("a")->field("b.upstream_pid,c.code,a.currency")->leftJoin("products b", "a.relid = b.id")->leftJoin("currencies c", "a.currency = c.id")->where("a.type", "product")->where("a.relid", $id)->select()->toArray();
                    $billingcycles = config("price_type");
                    foreach ($upstream_product_pricings as $v) {
                        foreach ($product_pricings as $vv) {
                            if ($v["relid"] == $vv["upstream_pid"] && $v["code"] == $vv["code"]) {
                                foreach ($billingcycles as $billingcycle) {
                                    if ($v[$billingcycle[0]] < 0) {
                                        $pricingData[$vv["currency"]][$billingcycle[0]] = -1;
                                        $pricingData[$vv["currency"]][$billingcycle[1]] = 0;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                return jsonrule(["status" => 400, "msg" => lang("同步信息失败,请检查接口是否可用")]);
            }
        }
        \think\Db::startTrans();
        try {
            if ($api_type == "zjmf_api" && 0 < $product["upstream_version"] && !empty($upstream_res["data"]["product_pricings"][0]) && $upstream_price_type && $upstream_price_type == "percent" && $upstream_price_type != $product["upstream_price_type"]) {
                $product_model = new \app\common\model\ProductModel();
                $res_upstream = $product_model->syncProductInfoToResource($id, $api_type, $zjmf_finance_api_id, $product["upstream_pid"], true, false, false, $rate);
                if ($res_upstream["status"] != 200) {
                    throw new \think\Exception($res_upstream["msg"]);
                }
            }
            foreach ($basedata as $k => $v) {
                if ($v == NULL || $v == NULL) {
                    $basedata[$k] = "";
                }
            }
            \think\Db::name("products")->where("id", $id)->update($basedata);
            $p = \think\Db::name("products")->where("id", $id)->find();
            if (empty($this->request->afpid)) {
                $param = $this->request->param();
                $data["pid"] = isset($param["id"]) ? floatval($param["id"]) : 0;
                $data["affiliate_enabled"] = isset($param["affiliate_enabled"]) ? floatval($param["affiliate_enabled"]) : 0;
                $data["affiliate_bates"] = isset($param["affiliate_bates"]) ? floatval($param["affiliate_bates"]) : 0;
                $data["affiliate_type"] = isset($param["affiliate_type"]) ? intval($param["affiliate_type"]) : 0;
                $data["affiliate_is_reorder"] = isset($param["affiliate_is_reorder"]) ? intval($param["affiliate_is_reorder"]) : 0;
                $data["affiliate_reorder"] = isset($param["affiliate_reorder"]) ? intval($param["affiliate_reorder"]) : 0;
                $data["affiliate_reorder_type"] = isset($param["affiliate_reorder_type"]) ? intval($param["affiliate_reorder_type"]) : 0;
                $data["affiliate_is_renew"] = isset($param["affiliate_is_renew"]) ? intval($param["affiliate_is_renew"]) : 0;
                $data["affiliate_renew"] = isset($param["affiliate_renew"]) ? intval($param["affiliate_renew"]) : 0;
                $data["affiliate_renew_type"] = isset($param["affiliate_renew_type"]) ? intval($param["affiliate_renew_type"]) : 0;
                $data["create_time"] = time();
                $res = \think\Db::name("affiliates_products_setting")->insertGetId($data);
                active_log(sprintf($this->lang["Aff_admin_productaffiAdd"], $p["name"], $res));
            } else {
                $spg = \think\Db::name("affiliates_products_setting")->where("id", $this->request->afpid)->find();
                $param = $this->request->param();
                $data["affiliate_enabled"] = isset($param["affiliate_enabled"]) ? floatval($param["affiliate_enabled"]) : 0;
                if ($data["affiliate_enabled"] == 1) {
                    $data["affiliate_bates"] = isset($param["affiliate_bates"]) ? floatval($param["affiliate_bates"]) : 0;
                    $data["affiliate_type"] = isset($param["affiliate_type"]) ? intval($param["affiliate_type"]) : 0;
                    $data["affiliate_is_reorder"] = isset($param["affiliate_is_reorder"]) ? intval($param["affiliate_is_reorder"]) : 0;
                    $data["affiliate_reorder"] = isset($param["affiliate_reorder"]) ? intval($param["affiliate_reorder"]) : 0;
                    $data["affiliate_reorder_type"] = isset($param["affiliate_reorder_type"]) ? intval($param["affiliate_reorder_type"]) : 0;
                    $data["affiliate_is_renew"] = isset($param["affiliate_is_renew"]) ? intval($param["affiliate_is_renew"]) : 0;
                    $data["affiliate_renew"] = isset($param["affiliate_renew"]) ? intval($param["affiliate_renew"]) : 0;
                    $data["affiliate_renew_type"] = isset($param["affiliate_renew_type"]) ? intval($param["affiliate_renew_type"]) : 0;
                    $desc = "";
                    if ($spg["affiliate_bates"] != $param["affiliate_bates"]) {
                        $desc .= " 产品推介计划比例" . $spg["affiliate_bates"] . "改为" . $param["affiliate_bates"];
                    }
                    if ($spg["affiliate_type"] != $param["affiliate_type"]) {
                        if ($spg["affiliate_type"] == 1) {
                            $dec .= "产品比例类型由“百分比”改为“金额”，";
                        } else {
                            $dec .= "产品比例类型由“金额”改为“百分比”，";
                        }
                    }
                    if ($spg["affiliate_is_reorder"] != $param["affiliate_is_reorder"]) {
                        if ($spg["affiliate_is_reorder"] == 1) {
                            $dec .= "产品二次订购由“关闭”改为“开启”，";
                        } else {
                            $dec .= "产品二次订购由“开启”改为“关闭”，";
                        }
                    }
                    if ($spg["affiliate_reorder"] != $param["affiliate_reorder"]) {
                        $desc .= "二次订购比例由“" . $spg["affiliate_reorder"] . "”改为“" . $param["affiliate_reorder"] . "”，";
                    }
                    if ($spg["affiliate_is_renew"] != $param["affiliate_is_renew"]) {
                        if ($spg["affiliate_is_renew"] == 1) {
                            $dec .= " 产品续费由“关闭”改为“开启”，";
                        } else {
                            $dec .= " 产品续费由“开启”改为“关闭”，";
                        }
                    }
                    if ($spg["affiliate_renew"] != $param["affiliate_renew"]) {
                        $desc .= "续费比例由“" . $spg["affiliate_renew"] . "”改为“" . $param["affiliate_renew"] . "”，";
                    }
                }
                if ($spg["affiliate_enabled"] != $param["affiliate_enabled"]) {
                    $str = "";
                    if ($spg["affiliate_enabled"] == 1) {
                        $str = "自定义";
                    } else {
                        if ($spg["affiliate_enabled"] == 0) {
                            $str = "跟随系统";
                        } else {
                            $str = "关闭";
                        }
                    }
                    if ($param["affiliate_enabled"] == 1) {
                        $dec .= " 产品推介计划由“" . $str . "”改为“自定义”，";
                    } else {
                        if ($param["affiliate_enabled"] == 0) {
                            $dec .= " 产品推介计划由“" . $str . "”改为“跟随系统”，";
                        } else {
                            $dec .= " 产品推介计划由“" . $str . "”改为“关闭”，";
                        }
                    }
                }
                \think\Db::name("affiliates_products_setting")->where("id", $this->request->afpid)->update($data);
                if (empty($desc)) {
                    $dec .= "什么都没修改";
                }
            }
            if ($api_type == "zjmf_api" && $product["upstream_version"] == 0) {
                $product_model = new \app\common\model\ProductModel();
                $res_upstream = $product_model->syncProductInfoToResource($id, $api_type, $zjmf_finance_api_id, $basedata["upstream_pid"], false, false, false, "");
                if ($res_upstream["status"] != 200) {
                    throw new \think\Exception($res_upstream["msg"]);
                }
            }
            if ($upstream_price_type != "percent" || $api_type != "zjmf_api") {
                $pricing = new \app\common\logic\Pricing();
                $pricing->save($id, "product", $pricingData);
            }
            if ($api_type == "zjmf_api" && 0 < $product["zjmf_api_id"] && $product["zjmf_api_id"] == $zjmf_finance_api_id && 0 < $product["upstream_pid"] && $product["upstream_pid"] != $basedata["upstream_pid"] || $api_type == "zjmf_api" && 0 < $product["zjmf_api_id"] && $product["zjmf_api_id"] != $zjmf_finance_api_id) {
                $product_model = new \app\common\model\ProductModel();
                $res_upstream = $product_model->syncProductInfoToResource($id, $api_type, $zjmf_finance_api_id, $basedata["upstream_pid"], true, true, false, $rate);
                if ($res_upstream["status"] != 200) {
                    throw new \think\Exception($res_upstream["msg"]);
                }
            }
            $upgradepackages = $param["upgradepackages"];
            $upgradepackagesArr = [];
            \think\Db::name("product_upgrade_products")->where("product_id", $id)->delete();
            foreach ($upgradepackages as $key => $value) {
                $upgradepackagesArr[$key]["product_id"] = $id;
                $upgradepackagesArr[$key]["upgrade_product_id"] = $value;
            }
            \think\Db::name("product_upgrade_products")->insertAll($upgradepackagesArr);
            if ($api_type != "zjmf_api") {
                $custom = new \app\common\logic\Customfields();
                $re = $custom->add($id, "product", $param);
                $dec .= $re["dec"];
                if ($re["status"] == "error") {
                    return jsonrule(["status" => 406, "msg" => $re["msg"]]);
                }
                $re = $custom->edit($id, "product", $param);
                $dec .= $re["dec"];
                if ($re["status"] == "error") {
                    return jsonrule(["status" => 406, "msg" => $re["msg"]]);
                }
                $configoptionlinks = $param["configoptionlinks"];
                $configoptionlinksArr = [];
                \think\Db::name("product_config_links")->where("pid", $id)->find();
                \think\Db::name("product_config_links")->where("pid", $id)->delete();
                foreach ($configoptionlinks as $key => $value) {
                    $configoptionlinksArr[$key]["gid"] = $value;
                    $configoptionlinksArr[$key]["pid"] = $id;
                }
                \think\Db::name("product_config_links")->insertAll($configoptionlinksArr);
                if ($auto_create_config_options == 1 && $param["type"] == "dcim") {
                    $server_info = \think\Db::name("servers")->field("id,hostname,username,password,secure,port,accesshash")->where("gid", $param["server_group"])->where("server_type", "dcim")->find();
                    $dcim = new \app\common\logic\Dcim();
                    $dcim->init($server_info);
                    $config_group_name = "裸金属-" . $param["name"];
                    $pricing = [];
                    if ($basedata["config_option1"] == "rent") {
                        $config_group_id = \think\Db::name("product_config_groups")->insertGetId(["name" => $config_group_name, "description" => $config_group_name]);
                        \think\Db::name("product_config_links")->insert(["gid" => $config_group_id, "pid" => $id]);
                        $config_options = [["option_name" => "area|区域", "option_type" => 12, "upgrade" => 0, "option" => []], ["option_name" => "os|操作系统", "option_type" => 5, "upgrade" => 0, "option" => []], ["option_name" => "server_group|硬件配置", "option_type" => 1, "upgrade" => 0, "option" => []], ["option_name" => "ip_num|IP数量", "option_type" => 1, "upgrade" => 1, "option" => [["name" => "1|1个", "monthly" => 10], ["name" => "3|3个", "monthly" => 30], ["name" => "5|5个", "monthly" => 50], ["name" => "NO_CHANGE|不变更", "monthly" => 0]]], ["option_name" => "bw|带宽", "option_type" => 1, "upgrade" => 1, "option" => [["name" => "10,10|10Mbps", "monthly" => 100], ["name" => "20,20|20Mbps", "monthly" => 200], ["name" => "50,50|50Mbps", "monthly" => 500], ["name" => "NO_CHANGE|不变更", "monthly" => 0]]]];
                        $dcim_area = $dcim->getArea();
                        foreach ($dcim_area as $v) {
                            $config_options[0]["option"][] = ["name" => $v["id"] . "|" . $v["area"] . "^" . $v["area"]];
                        }
                        $dcim_os = $dcim->getFormatOs();
                        foreach ($dcim_os as $v) {
                            $config_options[1]["option"][] = ["name" => $v];
                        }
                        $dcim_server_group = $dcim->getSaleGroup();
                        foreach ($dcim_server_group as $v) {
                            $config_options[2]["option"][] = ["name" => $v["id"] . "|" . $v["name"]];
                        }
                        $i = 0;
                        foreach ($config_options as $v) {
                            $i++;
                            $d = ["gid" => $config_group_id, "option_name" => $v["option_name"], "option_type" => $v["option_type"], "order" => $i, "upgrade" => $v["upgrade"]];
                            $config_options_id = \think\Db::name("product_config_options")->insertGetId($d);
                            if (!empty($v["option"])) {
                                foreach ($v["option"] as $vv) {
                                    $config_option_sub = ["config_id" => $config_options_id, "option_name" => $vv["name"]];
                                    $sub_id = \think\Db::name("product_config_options_sub")->insertGetId($config_option_sub);
                                    if (0 < $vv["monthly"]) {
                                        $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => $vv["monthly"]];
                                    } else {
                                        $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => 0];
                                    }
                                }
                            }
                        }
                        if (!empty($pricing)) {
                            $currency = \think\Db::name("currencies")->column("id");
                            foreach ($currency as $v) {
                                foreach ($pricing as $kk => $vv) {
                                    $pricing[$kk]["currency"] = $v;
                                }
                                \think\Db::name("pricing")->insertAll($pricing);
                            }
                        }
                        \think\Db::name("products")->where("id", $id)->update(["auto_create_config_options" => 1]);
                    } else {
                        if ($basedata["config_option1"] == "bms") {
                            $config_group_id = \think\Db::name("product_config_groups")->insertGetId(["name" => $config_group_name, "description" => $config_group_name]);
                            \think\Db::name("product_config_links")->insert(["gid" => $config_group_id, "pid" => $id]);
                            $config_options = [["name" => "group_id|计算节点分组", "option_type" => 1, "upgrade" => 0, "option" => []], ["name" => "os|操作系统", "option_type" => 5, "upgrade" => 0, "option" => []], ["name" => "ip_num|IP数量", "option_type" => 4, "hidden" => 0, "qty_minimum" => 1, "qty_maximum" => 20, "upgrade" => 1, "option" => [["name" => "IP数量", "qty_minimum" => 1, "qty_maximum" => 20]]], ["name" => "system_disk_size|系统盘", "option_type" => 13, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "50|50G"]]], ["name" => "data_disk_size|数据盘", "option_type" => 14, "hidden" => 1, "qty_minimum" => 0, "qty_maximum" => 1000, "upgrade" => 1, "option" => [["name" => "数据盘", "qty_minimum" => 0, "qty_maximum" => 1000, "monthly" => 1]]], ["name" => "snap_num|快照数量", "option_type" => 1, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "2|2个"]]], ["name" => "backup_num|备份数量", "option_type" => 1, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "2|2个"]]]];
                            $config_options[0]["option"] = $dcim->bmsLcGroup();
                            $config_options[1]["option"] = $dcim->bmsOs();
                            $i = 0;
                            foreach ($config_options as $v) {
                                $i++;
                                $d = ["gid" => $config_group_id, "option_name" => $v["name"], "option_type" => $v["option_type"], "hidden" => (int) $v["hidden"], "order" => $i, "upgrade" => $v["upgrade"]];
                                if (judgeQuantity($v["option_type"])) {
                                    $d["qty_minimum"] = $v["qty_minimum"];
                                    $d["qty_maximum"] = $v["qty_maximum"];
                                }
                                $config_options_id = \think\Db::name("product_config_options")->insertGetId($d);
                                if (!empty($v["option"])) {
                                    foreach ($v["option"] as $vv) {
                                        $config_option_sub = ["config_id" => $config_options_id, "option_name" => $vv["name"], "qty_minimum" => $vv["qty_minimum"] ?: 0, "qty_maximum" => $vv["qty_maximum"] ?: 0];
                                        $sub_id = \think\Db::name("product_config_options_sub")->insertGetId($config_option_sub);
                                        if (0 < $vv["monthly"]) {
                                            $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => $vv["monthly"]];
                                        } else {
                                            $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => 0];
                                        }
                                    }
                                }
                            }
                            if (!empty($pricing)) {
                                $currency = \think\Db::name("currencies")->column("id");
                                foreach ($currency as $v) {
                                    foreach ($pricing as $kk => $vv) {
                                        $pricing[$kk]["currency"] = $v;
                                    }
                                    \think\Db::name("pricing")->insertAll($pricing);
                                }
                            }
                            \think\Db::name("products")->where("id", $id)->update(["auto_create_config_options" => 1]);
                        }
                    }
                }
                if ($auto_create_config_options == 1 && $param["type"] == "dcimcloud") {
                    $config_group_name = "魔方云-" . $param["name"];
                    $pricing = [];
                    $config_group_id = \think\Db::name("product_config_groups")->insertGetId(["name" => $config_group_name, "description" => $config_group_name]);
                    \think\Db::name("product_config_links")->insert(["gid" => $config_group_id, "pid" => $id]);
                    $config_options = [["name" => "area|区域", "option_type" => 12, "hidden" => 0, "option" => [], "upgrade" => 0], ["name" => "os|操作系统", "option_type" => 5, "hidden" => 0, "option" => [], "upgrade" => 0], ["name" => "cpu|CPU", "option_type" => 6, "hidden" => 0, "upgrade" => 1, "option" => [["name" => "1|1核"], ["name" => "2|2核"], ["name" => "4|4核"], ["name" => "8|8核"], ["name" => "12|12核"], ["name" => "16|16核"], ["name" => "24|24核"], ["name" => "32|32核"]]], ["name" => "memory|内存", "option_type" => 8, "hidden" => 0, "upgrade" => 1, "option" => [["name" => "1024|1G", "monthly" => 1], ["name" => "2048|2G", "monthly" => 2], ["name" => "4096|4G", "monthly" => 4], ["name" => "6144|6G", "monthly" => 6], ["name" => "8192|8G", "monthly" => 8], ["name" => "12288|12G", "monthly" => 12], ["name" => "24576|24G", "monthly" => 24], ["name" => "32768|32G", "monthly" => 32], ["name" => "65536|64G", "monthly" => 64], ["name" => "131072|128G", "monthly" => 128]]], ["name" => "system_disk_size|系统盘", "option_type" => 13, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "lin:30,win:50|Lin30G,Win50G"]]], ["name" => "network_type|网络类型", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "vpc|VPC网络"], ["name" => "normal|经典网络"]]], ["name" => "bw|带宽", "option_type" => 11, "hidden" => 0, "qty_minimum" => 1, "qty_maximum" => 100, "upgrade" => 1, "option" => [["name" => "带宽", "qty_minimum" => 1, "qty_maximum" => 100, "monthly" => 1]]], ["name" => "in_bw|流入带宽", "option_type" => 10, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "100|100Mbps"]]], ["name" => "ip_num|IP数量", "option_type" => 4, "hidden" => 0, "qty_minimum" => 1, "qty_maximum" => 20, "upgrade" => 1, "option" => [["name" => "IP数量", "qty_minimum" => 1, "qty_maximum" => 20]]], ["name" => "data_disk_size|数据盘", "option_type" => 14, "hidden" => 0, "qty_minimum" => 0, "qty_maximum" => 1000, "upgrade" => 0, "option" => [["name" => "数据盘", "qty_minimum" => 0, "qty_maximum" => 1000, "monthly" => 1]]], ["name" => "snap_num|快照数量", "option_type" => 1, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "2|2个"]]], ["name" => "backup_num|备份数量", "option_type" => 1, "hidden" => 1, "upgrade" => 1, "option" => [["name" => "2|2个"]]], ["name" => "nat_acl_limit|NAT转发", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "-1|不支持"], ["name" => "0|不限制"], ["name" => "10|10个"]]], ["name" => "nat_web_limit|共享建站", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "-1|不支持"], ["name" => "0|不限制"], ["name" => "10|10个"]]], ["name" => "system_disk_io_limit|系统盘性能", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "0,0,0,0|不限制性能"], ["name" => "500,500,2000,2000|500持续2000IOPS"]]], ["name" => "data_disk_io_limit|数据盘性能", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "0,0,0,0|不限制性能"], ["name" => "500,500,2000,2000|500持续2000IOPS"]]], ["name" => "traffic_bill_type|流量计费方式", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => [["name" => "month|自然月"], ["name" => "last_30days|订购日至下月"]]]];
                    $server_info = \think\Db::name("servers")->field("id")->where("gid", $param["server_group"])->where("server_type", "dcimcloud")->find();
                    $dcimcloud = new \app\common\logic\DcimCloud();
                    $dcimcloud->setUrl($server_info["id"]);
                    $config_options[0]["option"] = $dcimcloud->getArea();
                    $config_options[1]["option"] = $dcimcloud->getOs();
                    $pricing = [];
                    $i = 0;
                    foreach ($config_options as $v) {
                        $i++;
                        $d = ["gid" => $config_group_id, "option_name" => $v["name"], "option_type" => $v["option_type"], "hidden" => $v["hidden"], "order" => $i, "upgrade" => $v["upgrade"]];
                        if (judgeQuantity($v["option_type"])) {
                            $d["qty_minimum"] = $v["qty_minimum"];
                            $d["qty_maximum"] = $v["qty_maximum"];
                        }
                        $config_options_id = \think\Db::name("product_config_options")->insertGetId($d);
                        if (!empty($v["option"])) {
                            foreach ($v["option"] as $vv) {
                                $config_option_sub = ["config_id" => $config_options_id, "option_name" => $vv["name"], "qty_minimum" => $vv["qty_minimum"] ?: 0, "qty_maximum" => $vv["qty_maximum"] ?: 0];
                                $sub_id = \think\Db::name("product_config_options_sub")->insertGetId($config_option_sub);
                                if (0 < $vv["monthly"]) {
                                    $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => $vv["monthly"]];
                                } else {
                                    $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => 0];
                                }
                            }
                        }
                    }
                    if (!empty($pricing)) {
                        $currency = \think\Db::name("currencies")->column("id");
                        foreach ($currency as $v) {
                            foreach ($pricing as $kk => $vv) {
                                $pricing[$kk]["currency"] = $v;
                            }
                            \think\Db::name("pricing")->insertAll($pricing);
                        }
                    }
                    \think\Db::name("products")->where("id", $id)->update(["auto_create_config_options" => 1]);
                }
                if ($auto_create_config_options == 2) {
                    $config_group_name = "nokvm-" . $param["name"];
                    $pricing = [];
                    $config_group_id = \think\Db::name("product_config_groups")->insertGetId(["name" => $config_group_name, "description" => $config_group_name]);
                    \think\Db::name("product_config_links")->insert(["gid" => $config_group_id, "pid" => $id]);
                    $config_options = [["name" => "os|操作系统", "option_type" => 5, "upgrade" => 0, "option" => ["1|centos^CentOS 7.6 64位"]], ["name" => "Location|数据中心", "option_type" => 12, "upgrade" => 0, "option" => ["1|CN^香港"]], ["name" => "CPU|处理器核心", "option_type" => 6, "upgrade" => 1, "option" => ["4|4核"]], ["name" => "Memory|内存", "option_type" => 8, "upgrade" => 1, "option" => ["2048|2G"]], ["name" => "Disk Space|数据盘", "option_type" => 13, "upgrade" => 0, "option" => ["40|40G"]], ["name" => "Network Speed|带宽", "option_type" => 10, "upgrade" => 1, "option" => ["1280|10M"]], ["name" => "Snapshot|快照数量", "option_type" => 1, "upgrade" => 1, "option" => ["10|10份"]], ["name" => "Backups|备份数量", "option_type" => 1, "upgrade" => 1, "option" => ["10|10份"]], ["name" => "Extra IP Address|额外IP数量", "option_type" => 1, "upgrade" => 1, "option" => ["1|1 IP"]], ["name" => "nat_acl_limit|NAT转发", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => ["0|不支持", "10|10个"]], ["name" => "nat_web_limit|共享建站", "option_type" => 1, "hidden" => 1, "upgrade" => 0, "option" => ["0|不支持", "10|10个"]]];
                    $pricing = [];
                    $i = 0;
                    foreach ($config_options as $v) {
                        $i++;
                        $d = ["gid" => $config_group_id, "option_name" => $v["name"], "option_type" => $v["option_type"], "upgrade" => $v["upgrade"], "hidden" => (int) $v["hidden"], "order" => $i];
                        $config_options_id = \think\Db::name("product_config_options")->insertGetId($d);
                        foreach ($v["option"] as $vv) {
                            $config_option_sub = ["config_id" => $config_options_id, "option_name" => $vv];
                            $sub_id = \think\Db::name("product_config_options_sub")->insertGetId($config_option_sub);
                            $pricing[] = ["type" => "configoptions", "relid" => $sub_id];
                        }
                    }
                    if (!empty($pricing)) {
                        $currency = \think\Db::name("currencies")->column("id");
                        foreach ($currency as $v) {
                            foreach ($pricing as $kk => $vv) {
                                $pricing[$kk]["currency"] = $v;
                            }
                            \think\Db::name("pricing")->insertAll($pricing);
                        }
                    }
                    \think\Db::name("products")->where("id", $id)->update(["auto_create_config_options" => 1]);
                }
            }
            if (empty($dec)) {
                $dec .= "什么都没修改";
            }
            active_log(sprintf($this->lang["Product_admin_edit"], $id, $dec));
            unset($dec);
            $res_array = hook("product_edit", ["pid" => $id]);
            foreach ($res_array as $res) {
                if ($res["is_resource"] && $res["status"] != 200) {
                    throw new \think\Exception("商品同步至资源池失败,失败原因:" . $res["msg"]);
                }
            }
            \think\Db::commit();
            (new \app\common\logic\Product())->updateCache([$id]);
            return jsonrule(["status" => 200, "msg" => "编辑成功"]);
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function editStock(\think\Request $request)
    {
        $param = $request->param();
        $id = intval($param["id"]);
        $product = \think\Db::name("products")->field("api_type,upstream_pid")->where("id", $id)->find();
        if (empty($product)) {
            return jsonrule(["status" => 400, "msg" => "产品不存在"]);
        }
        if ($product["api_type"] == "zjmf_api" && 0 < $product["upstream_pid"]) {
            return jsonrule(["status" => 400, "msg" => "不可修改库存"]);
        }
        $re = \think\Db::name("products")->where("id", $id)->update(["qty" => $param["qty"]]);
        (new \app\common\logic\Product())->updateCache([$id]);
        return jsonrule(["status" => 200, "msg" => "修改库存成功"]);
    }
    public function getUpstreamPrice()
    {
        $data = [];
        $params = $this->request->param();
        $pid = intval($params["pid"]);
        $product = \think\Db::name("products")->where("id", $pid)->find();
        $zjmf_api_id = $product["zjmf_api_id"] ?? 0;
        $upstream_pid = $product["upstream_pid"] ?? 0;
        $res = getZjmfUpstreamProductConfig($zjmf_api_id, $upstream_pid);
        if ($res["status"] == 200) {
            $upstream_product = $res["data"]["products"];
            $flag = $res["data"]["flag"];
            $upstream_product_pricings = $res["data"]["product_pricings"];
            if ($upstream_product["api_type"] == "zjmf_api" && 0 < $upstream_product["upstream_pid"] && $upstream_product["upstream_price_type"] == "percent") {
                $price_type = config("price_type");
                foreach ($upstream_product_pricings as $k => $v) {
                    foreach ($price_type as $kk => $vv) {
                        $upstream_product_pricings[$k][$vv[0]] = $v[$vv[0]] * $upstream_product["upstream_price_value"] / 100;
                        $upstream_product_pricings[$k][$vv[1]] = $v[$vv[1]] * $upstream_product["upstream_price_value"] / 100;
                        $new = upstreamBates($flag, $upstream_product_pricings[$k][$vv[0]], $upstream_product_pricings[$k][$vv[1]]);
                        list($upstream_product_pricings[$k][$vv[0]], $upstream_product_pricings[$k][$vv[1]]) = $new;
                    }
                }
            } else {
                $price_type = config("price_type");
                foreach ($upstream_product_pricings as $k => $v) {
                    foreach ($price_type as $kk => $vv) {
                        $new = upstreamBates($flag, $upstream_product_pricings[$k][$vv[0]], $upstream_product_pricings[$k][$vv[1]]);
                        list($upstream_product_pricings[$k][$vv[0]], $upstream_product_pricings[$k][$vv[1]]) = $new;
                    }
                }
            }
        }
        $data["flag"] = $flag ? $flag : [];
        $data["product_pricing"] = $upstream_product_pricings ?? [];
        $data["config_groups"] = $res["data"]["config_groups"] ?? [];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function selectType(\think\Request $request)
    {
        $type = $request->param("type");
        if ($type == "dcim" || $type == "server") {
            $basedata["groupid"] = 1;
        } else {
            if ($type == "clound" || $type == "dcimcloud") {
                $basedata["groupid"] = 2;
            } else {
                $basedata["groupid"] = 3;
            }
        }
        $d1 = \think\Db::name("nav_group")->where("id", $basedata["groupid"])->find();
        return jsonrule(["status" => 200, "msg" => "成功", "data" => $d1]);
    }
    public function delCustomField(\think\Request $request)
    {
        $id = $request->param("id");
        $id = intval($id);
        if (empty($id)) {
            return jsonrule(["status" => 406, "msg" => lang("ID_ERROR")]);
        }
        $pid = \think\Db::name("customfields")->where("type", "product")->where("id", $id)->value("relid");
        \think\Db::name("customfields")->where("type", "product")->where("id", $id)->delete();
        \think\Db::name("products")->where("id", $pid)->setInc("location_version");
        (new \app\common\logic\Product())->updateCache([$pid]);
        return jsonrule(["status" => 200, "msg" => lang("DELETE_CUSTOM_FIELD_SUCCEEDED")]);
    }
    public function managedownloads(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["id" => "require|number", "remdl" => "number", "adddl" => "number"];
            $msg = ["id.require" => lang("PRODUCT_ID_CANNOT_BE_EMPTY"), "id.number" => lang("PRODUCT_ID_MUST_BE_A_NUMBER"), "remdl.number" => lang("DISASSOCIATION_ID_MUST_BE_A_NU"), "adddl.number" => lang("ASSOCIATION_ID_MUST_BE_A_NUMBE")];
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $id = $param["id"];
            $remdl = $param["remdl"];
            $adddl = $param["adddl"];
            $data = [];
            if ($adddl) {
                $data["product_id"] = $id;
                $data["download_id"] = $adddl;
                $data["create_time"] = time();
                $file_exists = \think\Db::name("product_downloads")->where("product_id", $id)->where("download_id", $adddl)->find();
                if (empty($file_exists)) {
                    \think\Db::name("product_downloads")->insert($data);
                }
                return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
            }
            if ($remdl) {
                $data["product_id"] = $id;
                $data["download_id"] = $remdl;
                \think\Db::name("product_downloads")->where($data)->delete();
                return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
            }
        }
    }
    public function selectcates(\think\Request $request)
    {
        $param = $request->param();
        $productid = $param["productid"] ? intval($param["productid"]) : 0;
        $logic_download = new \app\common\logic\Download();
        $cats_data["selectno"] = $logic_download->getCatsProduct($productid);
        $cats_data["select"] = $logic_download->getCatsProductselect($productid);
        return jsonrule(["status" => 200, "data" => $cats_data]);
    }
    public function downloadcates(\think\Request $request)
    {
        $param = $request->param();
        $productid = $param["productid"] ? intval($param["productid"]) : 0;
        $download_data = [];
        $download_data = \think\Db::name("downloads")->field("d.id,d.location,d.title,d.clientsonly,d.hidden,d.productdownload")->alias("d")->leftJoin("product_downloads p", "p.download_id=d.id")->where("p.product_id", $productid)->where("d.productdownload", 1)->select()->toArray();
        foreach ($download_data as $key => $val) {
            $download_data[$key]["down_link"] = "download/product_file?id=" . $val["id"];
        }
        return jsonrule(["status" => 200, "data" => $download_data]);
    }
    public function addDownloadcats(\think\Request $request)
    {
        $rule = ["catid" => "number", "title" => "require|max:255", "description" => "max:255"];
        $msg = ["catid.number" => lang("GROUP_ID_MUST_BE_A_NUMBER"), "title.require" => lang("GROUP_NAME_CANNOT_BE_EMPTY"), "title.max" => lang("THE_TITLE_CANNOT_EXCEED_CHARA"), "title.description" => lang("DESCRIPTION_CANNOT_EXCEED__CHA")];
        $param = $request->param();
        $validate = new \think\Validate($rule, $msg);
        $result = $validate->check($param);
        if (!$result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $data = [];
        $data["parentid"] = $param["catid"];
        $data["name"] = $param["title"];
        $data["description"] = $param["description"] ?? "";
        $data["create_time"] = time();
        \think\Db::name("downloadcats")->insert($data);
        return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
    }
    public function addDownloadFlie(\think\Request $request)
    {
        $rule = ["id" => "require|number", "catid" => "require|number", "title" => "require|max:255", "description" => "max:255"];
        $msg = ["id.require" => lang("PRODUCT_ID_CANNOT_BE_EMPTY"), "id.number" => lang("PRODUCT_ID_MUST_BE_A_NUMBER"), "catid.require" => lang("GROUP_NAME_CANNOT_BE_EMPTY"), "catid.number" => lang("GROUP_ID_MUST_BE_A_NUMBER"), "title.require" => lang("THE_TITLE_NOT_EMPTY"), "title.max" => lang("THE_TITLE_CANNOT_EXCEED_CHARA"), "title.description" => lang("DESCRIPTION_CANNOT_EXCEED__CHA")];
        $param = $request->param();
        $validate = new \think\Validate($rule, $msg);
        $result = $validate->check($param);
        if (!$result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $catid = $param["catid"];
        if (empty($catid)) {
            return jsonrule(["status" => 406, "msg" => "不能将文件添加到顶级分类，请先添加分类"]);
        }
        $logic_download = new \app\common\logic\Download();
        $res = $logic_download->upload();
        if ($res["status"] != 200) {
            return jsonrule($res);
        }
        $filename = $res["data"]["filename"];
        \think\Db::startTrans();
        try {
            $pid = $param["id"];
            $idata = ["category" => $param["catid"], "type" => "zip", "title" => $param["title"], "description" => $param["description"] ?: "", "location" => $filename ?: "", "clientsonly" => 1, "hidden" => 0, "productdownload" => 1, "create_time" => time()];
            $did = \think\Db::name("downloads")->insertGetId($idata);
            $pdata = [];
            $pdata["product_id"] = $pid;
            $pdata["download_id"] = $did;
            $pdata["create_time"] = time();
            \think\Db::name("product_downloads")->insert($pdata);
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            $file_arr[] = $filename;
            $logic_download->deleteFile($file_arr);
            return jsonrule(["status" => 406, "msg" => lang("UPLOAD FAIL")]);
        }
        return jsonrule(["status" => 200, "msg" => lang("UPLOAD SUCCESS")]);
    }
    public function groupList()
    {
        $params = $data = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "ASC";
        $data["group_name"] = !empty($params["group_name"]) ? trim($params["group_name"]) : "";
        $total = \think\Db::name("user_product_groups")->where(function (\think\db\Query $query) use($data) {
            if (!empty($data["group_name"])) {
                $query->where("group_name", "like", "%" . trim($data["group_name"]) . "%");
            }
        })->count();
        $list = \think\Db::name("user_product_groups")->where(function (\think\db\Query $query) use($data) {
            if (!empty($data["group_name"])) {
                $query->where("group_name", "like", "%" . trim($data["group_name"]) . "%");
            }
        })->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "total" => $total, "list" => $list]);
    }
    public function addProductgroupPage()
    {
        $groups = getuserProductLists();
        $data = ["group" => $groups];
        return jsonrule(["status" => 200, "data" => $data, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function addProductgroup()
    {
        if ($this->request->isPost()) {
            $param = $this->request->only(["group_name", "pids"]);
            $pids = $param["pids"];
            $param["pids"] = implode(",", $param["pids"]);
            $cid = \think\Db::name("user_product_groups")->insertGetId($param);
            foreach ($pids as $item) {
                $data = ["pid" => $item, "gid" => $cid];
                \think\Db::name("user_products")->insertGetId($data);
            }
            if (!$cid) {
                return json(["status" => 400, "msg" => lang("ADD FAIL")]);
            }
            active_log(sprintf($this->lang["Product_admin_addProductgroup"], $param["group_name"], $cid));
            return json(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        } else {
            return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function editProductgroupPage()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        $groups = getUserProductListss($id);
        $spg = \think\Db::name("user_product_groups")->where("id", $id)->find();
        $spg["pids"] = explode(",", $spg["pids"]);
        foreach ($spg["pids"] as $key => $val) {
            $spg["pids"][$key] = (int) $val;
        }
        $data = ["group" => $groups, "spg" => $spg];
        return jsonrule(["status" => 200, "data" => $data, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function editProductgroup()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            if (empty($param["id"])) {
                return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
            }
            $desc = "";
            $spg = \think\Db::name("user_product_groups")->where("id", $param["id"])->find();
            $data["group_name"] = $param["group_name"];
            if ($spg["group_name"] != $param["group_name"]) {
                $desc .= "分组名由“" . $spg["group_name"] . "”改为“" . $param["group_name"] . "”";
            }
            $data["pids"] = implode(",", $param["pids"]);
            \think\Db::startTrans();
            try {
                \think\Db::name("user_products")->where("gid", $param["id"])->delete();
                \think\Db::name("user_product_groups")->where("id", $param["id"])->update($data);
                if ($spg["pids"] != $data["pids"]) {
                    $desc .= "产品选择:";
                }
                foreach ($param["pids"] as $item) {
                    $datas = ["pid" => $item, "gid" => $param["id"]];
                    \think\Db::name("user_products")->insertGetId($datas);
                    if ($spg["pids"] != $data["pids"]) {
                        $p = \think\Db::name("products")->where("id", $item)->find();
                        $desc .= "“" . $p["name"] . "”，";
                    }
                }
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
            }
            if (empty($desc)) {
                $desc .= "未做任何修改";
            }
            (new \app\common\logic\Product())->updateListCache($param["pids"]);
            active_log(sprintf($this->lang["Product_admin_editProductgroup"], $param["id"], $desc));
            return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function delProductgroup()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        $res = \think\Db::name("user_product_groups")->where("id", $id)->find();
        if (!empty($res)) {
            \think\Db::name("user_products")->where("gid", $id)->delete();
            \think\Db::name("user_product_groups")->where("id", $id)->delete();
            \think\Db::name("user_product_bates")->where("products", $id)->delete();
            active_log(sprintf($this->lang["Product_admin_delProductgroup"], $res["group_name"], $id));
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
    }
    public function zklistPage()
    {
        $upg = \think\Db::name("user_product_groups")->field("id as products,group_name")->select()->toArray();
        $cg = \think\Db::name("client_groups")->field("id as user,group_name")->select()->toArray();
        foreach ($upg as $key => $value) {
            foreach ($cg as $k => $v) {
                $spg = \think\Db::name("user_product_bates")->alias("u")->join("client_groups c", "c.id=u.user")->field("u.id,u.user,u.type,u.bates,c.group_name")->where("u.products", $value["products"])->where("u.user", $v["user"])->find();
                $cg[$k]["id"] = $spg["id"];
                $cg[$k]["type"] = $spg["type"];
                $cg[$k]["bates"] = $spg["bates"];
            }
            $upg[$key]["child"] = $cg;
        }
        return jsonrule(["status" => 200, "clientsgroup" => $upg, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function editUserProductgroup()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            if (empty($param["id"])) {
                $data["type"] = $param["type"];
                $data["bates"] = $param["bates"];
                if ($data["type"] < 0 || $data["bates"] <= 0) {
                    return json(["status" => 400, "msg" => "参数不能小于0"]);
                }
                if ($data["type"] == 1 && 100 < $data["bates"]) {
                    return json(["status" => 400, "msg" => "折扣参数不能大于100"]);
                }
                $data["products"] = $param["products"];
                $data["user"] = $param["user"];
                \think\Db::startTrans();
                try {
                    if ($data["type"] != 0) {
                        $res = \think\Db::name("user_product_bates")->insertGetId($data);
                    }
                    \think\Db::commit();
                } catch (\Exception $e) {
                    \think\Db::rollback();
                    return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
                }
                active_log(sprintf($this->lang["Product_admin_UserProductgroup"], $res, $data["user"], $data["products"]));
                return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
            }
            $desc = "";
            $spg = \think\Db::name("user_product_bates")->where("id", $param["id"])->find();
            $data["type"] = $param["type"];
            if ($data["type"] == 0) {
                \think\Db::startTrans();
                try {
                    $desc = "删除配置";
                    \think\Db::name("user_product_bates")->where("id", $param["id"])->delete();
                    \think\Db::commit();
                    active_log(sprintf($this->lang["Product_admin_UserProductgroupedit"], $param["id"], $desc, $data["user"], $data["products"]));
                    return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
                } catch (\Exception $e) {
                    \think\Db::rollback();
                    return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
                }
            }
            if ($spg["type"] != $param["type"]) {
                $str = "";
                if ($spg["type"] == 1) {
                    $str .= "百分比";
                } else {
                    if ($spg["type"] == 2) {
                        $str .= "固定金额";
                    } else {
                        $str .= "优惠";
                    }
                }
                if ($param["type"] == 1) {
                    $desc .= "折扣类型由“" . $str . "”改为“百分比”";
                } else {
                    if ($param["type"] == 2) {
                        $desc .= "折扣类型由“" . $str . "”改为“固定金额”";
                    } else {
                        $desc .= "折扣类型由“" . $str . "”改为“优惠”";
                    }
                }
            }
            $data["bates"] = $param["bates"];
            if ($spg["bates"] != $param["bates"]) {
                if ($spg["type"] == 1) {
                    $desc .= "折扣百分比";
                } else {
                    if ($spg["type"] == 2) {
                        $desc .= "折扣固定金额";
                    } else {
                        $desc .= "折扣优惠";
                    }
                }
                $desc .= "由“" . $spg["bates"] . "”改为“" . $param["bates"] . "”";
            }
            if ($data["type"] < 0 || $data["bates"] <= 0) {
                return json(["status" => 400, "msg" => "参数不能小于0"]);
            }
            if ($data["type"] == 1 && 100 < $data["bates"]) {
                return json(["status" => 400, "msg" => "折扣参数不能大于100"]);
            }
            $data["products"] = $param["products"];
            $data["user"] = $param["user"];
            \think\Db::startTrans();
            try {
                \think\Db::name("user_product_bates")->where("id", $param["id"])->update($data);
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
            }
            $pids = \think\Db::name("user_products")->where("gid", $param["products"])->column("pid");
            (new \app\common\logic\Product())->updateListCache($pids);
            active_log(sprintf($this->lang["Product_admin_UserProductgroupedit"], $param["id"], $desc, $data["user"], $data["products"]));
            return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
}

?>