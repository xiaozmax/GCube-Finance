<?php


namespace app\admin\controller;

/**
 * @title 客户关怀模块
 * @description 接口说明: 客户关怀模块
 */
class ClientCareController extends AdminBaseController
{
    private $method = ["email", "message", "wechat"];
    private $validate = NULL;
    public function initialize()
    {
        parent::initialize();
        $this->validate = new \app\admin\validate\ClientCareValidate();
    }
    public function test()
    {
        return cmf_plugin_url("ClientCare://ClientCare/searchCondition", ["id" => 1], true);
    }
    public function searchCondition()
    {
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "trigger" => config("app.client_care_trigger")]);
    }
    public function careList()
    {
        $data = $this->request->param();
        $page = isset($data["page"]) && !empty($data["page"]) ? intval($data["page"]) : 1;
        $limit = isset($data["limit"]) && !empty($data["limit"]) ? intval($data["page"]) : config("app.page_size");
        $order = isset($data["order"]) && !empty($data["order"]) ? trim($data["order"]) : "care_id";
        $orderfield = ["care_id", "name", "trigger", "time", "method", "email_template", "message_template", "range_type", "status", "create_time", "update_time"];
        if (!in_array($order, $orderfield)) {
            return jsonrule(["status" => 400, "msg" => lang("OERDER_FIELD_ERROR")]);
        }
        $ordermethod = isset($data["order_method"]) && !empty($data["order_method"]) ? strtoupper(trim($data["order_method"])) : "DESC";
        $total = \think\Db::name("client_care")->count("id");
        $results = \think\Db::name("client_care")->alias("cc")->field("cc.id as care_id,cc.name,cc.trigger,cc.time,cc.method,et.name as email_template,mt.title as message_template,cc.range_type\r\n            ,cc.status,cc.create_time,cc.update_time")->leftJoin("email_templates et", "et.id = cc.email_template_id")->leftJoin("message_template mt", "mt.id = cc.message_template_id")->where(function (\think\db\Query $query) use($data) {
            if (!empty($data["name"])) {
                $name = trim($data["name"]);
                $query->where("name", "like", "%" . $name . "%");
            }
            if (!empty($data["trigger"])) {
                $trigger = trim($data["trigger"]);
                $query->where("trigger", "like", "%" . $trigger . "%");
            }
        })->limit($limit * ($page - 1), $limit)->order($order . " " . $ordermethod)->select();
        $resultsfilter = [];
        foreach ($results as $k => $result) {
            $result = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $result);
            $result["trigger"] = $this->argToLang($result["trigger"]);
            $resultsfilter[$k] = $result;
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "care_list" => $resultsfilter, "total" => $total]);
    }
    public function createCare()
    {
        $groups = \think\Db::name("product_groups")->field("id,name")->where("hidden", 0)->select();
        $groupsfilter = [];
        foreach ($groups as $key => $group) {
            $group = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $group);
            $products = \think\Db::name("products")->field("id,name")->where("gid", $group["id"])->select();
            $productsfilter = [];
            foreach ($products as $k => $product) {
                $productsfilter[$k] = array_map(function ($v) {
                    return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
                }, $product);
            }
            $group["child"] = [];
            $group["child"] = $productsfilter;
            $groupsfilter[$key] = $group;
        }
        $triggers = config("app.client_care_trigger");
        $method = $this->method;
        $emailtemplate = \think\Db::name("email_templates")->field("id,name")->where("name", "like", "%Care%")->select();
        $messagetmep = \think\Db::name("message_template")->field("id,title")->select();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "products" => $groupsfilter, "trigger" => $triggers, "method" => $method, "email_template" => $emailtemplate, "message_tmeplate" => $messagetmep]);
    }
    public function createCarePost()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $trigger = strtolower(trim($param["trigger"]));
            if (!in_array($trigger, array_column(config("app.client_care_trigger"), "name"))) {
                return jsonrule(["status" => 400, "msg" => lang("CLIENT_CARE_TIRGGER_NO_EXIST")]);
            }
            list($type) = explode("_", $trigger);
            if ($type == "product") {
                if (!$this->validate->scene("create_care_product")->check($param)) {
                    return jsonrule(["status" => 400, "msg" => $this->validate->getError()]);
                }
            } else {
                if (!$this->validate->scene("create_care_register")->check($param)) {
                    return jsonrule(["status" => 400, "msg" => $this->validate->getError()]);
                }
            }
            $care["name"] = $param["name"];
            $care["trigger"] = $trigger;
            $care["time"] = isset($param["time"]) ? intval($param["time"]) : 0;
            $method = $param["method"];
            if (!is_array($method)) {
                $method = [$method];
            }
            foreach ($method as $k => $v) {
                if (!in_array($v, $this->method)) {
                    return jsonrule(["status" => 400, "msg" => lang("CLIENT_CARE_METHOD_NO_EXIST")]);
                }
            }
            $care["method"] = implode(",", $method);
            $care["range_type"] = isset($param["range_type"]) ? intval($param["range_type"]) : 1;
            $care["email_template_id"] = isset($param["mailtemp_id"]) ? intval($param["mailtemp_id"]) : "";
            $care["message_template_id"] = isset($param["message_id"]) ? intval($param["message_id"]) : "";
            $care["status"] = intval($param["status"]);
            $care["create_time"] = time();
            $trim = array_map("trim", $care);
            $carefilter = array_map("htmlspecialchars", $trim);
            \think\Db::startTrans();
            try {
                $careid = \think\Db::name("client_care")->insertGetId($carefilter);
                $ids = $param["ids"];
                if (!is_array($ids)) {
                    $ids = [$ids];
                }
                if (!empty($ids)) {
                    foreach ($ids as $id) {
                        $links["care_id"] = $careid;
                        $links["product_id"] = $id;
                        \think\Db::name("client_care_product_links")->insert($links);
                    }
                }
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
            }
            return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    protected function argToLang($trigger)
    {
        $triggers = config("app.client_care_trigger");
        foreach ($triggers as $key => $value) {
            if ($value["name"] == $trigger) {
                $trigger = $value["name_zh"];
            }
        }
        return $trigger;
    }
    public function editCare()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) && !empty($params["id"]) ? intval($params["id"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $care = \think\Db::name("client_care")->where("id", $id)->find();
        $care = array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $care);
        $method = isset($care["method"]) ? explode(",", $care["method"]) : "";
        if ($care) {
            $care["method"] = $method;
        }
        $linkproducts = \think\Db::name("client_care_product_links")->field("product_id")->where("care_id", $id)->select();
        $link = [];
        foreach ($linkproducts as $linkproduct) {
            array_push($link, $linkproduct["product_id"]);
        }
        $groups = \think\Db::name("product_groups")->field("id,name")->where("hidden", 0)->select();
        $groupsfilter = [];
        foreach ($groups as $key => $group) {
            $group = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $group);
            $products = \think\Db::name("products")->field("id,name")->where("gid", $group["id"])->select();
            $productsfilter = [];
            foreach ($products as $k => $product) {
                $productsfilter[$k] = array_map(function ($v) {
                    return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
                }, $product);
            }
            $group["child"] = [];
            $group["child"] = $productsfilter;
            $groupsfilter[$key] = $group;
        }
        $triggers = config("app.client_care_trigger");
        $method = $this->method;
        $emailtemplate = \think\Db::name("email_templates")->field("id,name")->where("name", "like", "%Care%")->select();
        $messagetmep = \think\Db::name("message_template")->field("id,title")->select();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "care" => $care, "products" => $groupsfilter, "link_products" => $link, "triggers" => $triggers, "method" => $method, "email_template" => $emailtemplate, "message_template" => $messagetmep]);
    }
    public function editCarePost()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $id = isset($param["id"]) && !empty($param["id"]) ? $param["id"] : "";
            if (!$id) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            $trigger = strtolower(trim($param["trigger"]));
            if (!in_array($trigger, array_column(config("app.client_care_trigger"), "name"))) {
                return jsonrule(["status" => 400, "msg" => lang("CLIENT_CARE_TIRGGER_NO_EXIST")]);
            }
            list($type) = explode("_", $trigger);
            if ($type == "product") {
                if (!$this->validate->scene("create_care_product")->check($param)) {
                    return jsonrule(["status" => 400, "msg" => $this->validate->getError()]);
                }
            } else {
                if (!$this->validate->scene("create_care_register")->check($param)) {
                    return jsonrule(["status" => 400, "msg" => $this->validate->getError()]);
                }
            }
            $care["name"] = $param["name"];
            $care["trigger"] = $trigger;
            $care["time"] = isset($param["time"]) ? intval($param["time"]) : 0;
            $method = $param["method"];
            if (!is_array($method)) {
                $method = [$method];
            }
            foreach ($method as $k => $v) {
                if (!in_array($v, $this->method)) {
                    return jsonrule(["status" => 400, "msg" => lang("CLIENT_CARE_METHOD_NO_EXIST")]);
                }
            }
            $care["method"] = implode(",", $method);
            $care["range_type"] = isset($param["range_type"]) ? intval($param["range_type"]) : 1;
            $care["email_template_id"] = isset($param["mailtemp_id"]) ? intval($param["mailtemp_id"]) : "";
            $care["message_template_id"] = isset($param["message_id"]) ? intval($param["message_id"]) : "";
            $care["status"] = intval($param["status"]);
            $care["create_time"] = time();
            $trim = array_map("trim", $care);
            $carefilter = array_map("htmlspecialchars", $trim);
            \think\Db::startTrans();
            try {
                $careid = \think\Db::name("client_care")->where("id", $id)->update($carefilter);
                $ids = $param["ids"];
                if (!is_array($ids)) {
                    $ids = [$ids];
                }
                if (!empty($ids)) {
                    \think\Db::name("client_care_product_links")->where("care_id", $id)->delete();
                    foreach ($ids as $id) {
                        $links["care_id"] = $careid;
                        $links["product_id"] = $id;
                        \think\Db::name("client_care_product_links")->insert($links);
                    }
                }
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
            }
            return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function deleteCare()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) && !empty($params["id"]) ? intval($params["id"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        \think\Db::startTrans();
        try {
            \think\Db::name("client_care")->where("id", $id)->delete();
            \think\Db::name("client_care_product_links")->where("care_id", $id)->delete();
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
        }
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
}

?>