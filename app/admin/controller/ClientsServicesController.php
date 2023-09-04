<?php


namespace app\admin\controller;

/**
 * @title 后台产品页面
 * @description 接口说明
 */
class ClientsServicesController extends GetUserController
{
    public function index(\think\Request $request)
    {
        $param = $request->param();
        $uid = intval($param["uid"]);
        $hostselect = intval($param["hostselect"]);
        $returndata = [];
        if (!empty($uid)) {
            $client_data = \think\Db::name("clients")->where("id", $uid)->find();
        } else {
            $client_data = \think\Db::name("clients")->find();
        }
        if (!$this->check1($uid)) {
            return jsonrule(["status" => 400, "msg" => lang("NOT_USER_ID")]);
        }
        if (empty($client_data)) {
            return jsonrule(["status" => 400, "msg" => "客户编号未找到"]);
        }
        $uid = $client_data["id"];
        if (!empty($hostselect)) {
            $host_data = \think\Db::name("host")->where("id", $hostselect)->where("uid", $uid)->find();
        } else {
            $host_data = \think\Db::name("host")->where("uid", $uid)->order("id", "desc")->find();
        }
        if (empty($host_data)) {
            return jsonrule(["status" => 400, "msg" => "未找到客户产品"]);
        }
        $uid = $host_data["uid"];
        if (!empty($host_data["password"])) {
            $host_data["password"] = cmf_decrypt($host_data["password"]);
        }
        $host_data["assignedips"] = explode(",", $host_data["assignedips"]);
        $user_currcy = getUserCurrency($uid);
        $user_currcy_id = $user_currcy["id"];
        $productid = $param["productid"] ?? $host_data["productid"];
        $billing_cycle = config("billing_cycle");
        foreach ($billing_cycle as $k => $v) {
            $cycle_cmp["billingcycle"] = $k;
            $cycle_cmp["billingcycle_zh"] = $v;
            $cycle_filter[] = $cycle_cmp;
        }
        $returndata["billing_cycle"] = $cycle_filter;
        $billingcycle = $host_data["billingcycle"];
        if ($host_data["billingcycle"] == "ontrial") {
            $host_data["amount"] = 0;
        }
        $returndata["uid"] = $uid;
        $returndata["hostid"] = $hostid = $host_data["id"];
        $host_data["suspendreason"] = explode("-", $host_data["suspendreason"])[1] ? explode("-", $host_data["suspendreason"])[1] : "";
        $returndata["host_data"] = $host_data;
        $cancel_data = \think\Db::name("cancel_requests")->where("relid", $hostid)->find();
        if (!empty($cancel_data)) {
            $info = "注意：客户申请了取消此服务" . PHP_EOL . "注意：客户申请了取消此服务，所以不会在到期后再产生续费账单。" . PHP_EOL . "原因: " . $cancel_data["reason"];
            if ($cancel_data["type"] == "Endofbilling") {
                $host_data["auto_terminate_end_cycle"] = 1;
                $host_data["auto_terminate_reason"] = $cancel_data["reason"];
                $msg_timeing = "等待账单周期结束";
            } else {
                $host_data["auto_terminate_end_cycle"] = 0;
                $host_data["auto_terminate_reason"] = $cancel_data["reason"];
                $msg_timeing = "立即";
            }
            $msg_time = date("Y-m-d H:i", $cancel_data["create_time"]);
            $info_msg = "客户于 " . $msg_time . " 提交了删除产品请求，原因：" . $cancel_data["reason"] . "，时间：" . $msg_timeing;
        }
        $returndata["info_msg"] = $info_msg ?: "";
        $product_data = $this->getProductData($productid);
        $returndata["host_data"]["product_type"] = $product_data["type"];
        if (!empty($product_data["server_group"])) {
            $returndata["server_list"] = $this->getServerList($product_data["server_group"]);
            array_unshift($returndata["server_list"], ["id" => 0, "name" => lang("NULL")]);
        } else {
            $returndata["server_list"][] = ["id" => 0, "name" => lang("NULL")];
        }
        $host_option_config = \think\Db::name("host_config_options")->where("relid", $hostid)->select()->toArray();
        $returndata["host_option_config"] = $host_option_config;
        $config_option_logic = new \app\common\logic\ConfigOptions();
        $configInfo = $config_option_logic->getConfigInfo($productid, true);
        $config_array = $config_option_logic->configShow($configInfo, $user_currcy_id, $billingcycle);
        $returndata["config_array"] = $config_array;
        $product = \think\Db::name("products")->where("id", $host_data["productid"])->find();
        $config_array_ids = $returndata["config_array"] ? array_column($returndata["config_array"], "id") : [];
        $host_config_ids = $host_option_config ? array_column($host_option_config, "configid") : [];
        if (array_intersect($host_config_ids, $config_array_ids)) {
            foreach ($returndata["config_array"] as $key => $val) {
                if (!judgeYesNo($val["option_type"]) && !in_array($val["id"], $host_config_ids)) {
                    unset($returndata["config_array"][$key]);
                }
            }
        }
        $configOption = new \app\common\logic\ConfigOptions();
        $returndata["config_array"] = $configOption->getTree($returndata["config_array"]);
        $returndata["config_array"] = $this->handleTreeArr($returndata["config_array"]);
        $currencyid = priorityCurrency($uid);
        $cycle_product = (new \app\common\logic\Cart())->getProductCycle($host_data["productid"], $currencyid);
        $product["cycle"] = $cycle_product["cycle"] ?? [];
        $returndata["product"] = $product;
        $upstream_data = [];
        if ($product["api_type"] == "zjmf_api" && 0 < $product["upstream_pid"]) {
            $zjmf_api = \think\Db::name("zjmf_finance_api")->where("id", $product["zjmf_api_id"])->find();
            $returndata["zjmf_api"] = $zjmf_api["name"] ?? "无";
            $result = zjmfCurl($product["zjmf_api_id"], "host/header", ["host_id" => $host_data["dcimid"]], 30, "GET");
            if ($result["status"] == 200) {
                $upstream_data = $result["data"];
                unset($result);
            }
            $res = $upstream_data["host_data"];
            if (!empty($res)) {
                $returndata["upstream_host"] = ["domain" => $res["domain"], "regdate" => $res["regdate"], "nextduedate" => $res["nextduedate"], "domainstatus" => $res["domainstatus"], "domainstatus_desc" => $res["domainstatus_desc"], "firstpaymentamount" => $res["firstpaymentamount"], "firstpaymentamount_desc" => $res["firstpaymentamount_desc"], "amount" => $host_data["upstream_cost"] != "-" ? $host_data["upstream_cost"] : $res["amount"], "amount_desc" => $res["amount_desc"], "promo_code" => $res["promo_code"], "payment" => $res["payment"], "payment_zh" => $res["payment_zh"], "billingcycle" => $res["billingcycle"], "billingcycle_desc" => $res["billingcycle_desc"]];
            } else {
                $returndata["upstream_host"] = ["amount" => $host_data["upstream_cost"] ?: "-", "amount_desc" => "-", "firstpaymentamount" => "-", "firstpaymentamount_desc" => "-"];
            }
        } else {
            if ($product["api_type"] == "manual" || $product["api_type"] == "whmcs") {
                $returndata["upstream_host"] = ["amount" => $host_data["upstream_cost"] ?: "-", "amount_desc" => "-", "firstpaymentamount" => "-", "firstpaymentamount_desc" => "-"];
            } else {
                if ($product["api_type"] == "resource" && function_exists("resourceCurl")) {
                    $result = resourceCurl($productid, "/host/header", ["host_id" => $host_data["dcimid"]], 30, "GET");
                    if ($result["status"] == 200) {
                        $upstream_data = $result["data"];
                        unset($result);
                    }
                    $res = $upstream_data["host_data"];
                    if (!empty($res)) {
                        $returndata["upstream_host"] = ["domain" => $res["domain"], "regdate" => $res["regdate"], "nextduedate" => $res["nextduedate"], "domainstatus" => $res["domainstatus"], "domainstatus_desc" => $res["domainstatus_desc"], "firstpaymentamount" => $res["firstpaymentamount"], "firstpaymentamount_desc" => $res["firstpaymentamount_desc"], "amount" => $res["amount"], "amount_desc" => $res["amount_desc"], "promo_code" => $res["promo_code"], "payment" => $res["payment"], "payment_zh" => $res["payment_zh"], "billingcycle" => $res["billingcycle"], "billingcycle_desc" => $res["billingcycle_desc"]];
                    } else {
                        $returndata["upstream_host"] = [];
                    }
                }
            }
        }
        if ($product["api_type"] == "zjmf_api" || $product["api_type"] == "resource") {
            $zjmf_api = \think\Db::name("zjmf_finance_api")->where("id", $product_data["zjmf_api_id"])->find();
            $upstream_product = zjmfCurl($product_data["zjmf_api_id"], "/cart/get_product_config", ["pid" => $product_data["upstream_pid"]], 15, "GET");
            $returndata["zjmf_api"] = ["id" => $product_data["zjmf_api_id"], "name" => $zjmf_api["name"] . "-" . $upstream_product["data"]["products"]["name"]];
        } else {
            if ($product["api_type"] != "resource") {
                if ($product["api_type"] == "whmcs") {
                    $upper_reaches = \think\Db::name("zjmf_finance_api")->where("type", "whmcs")->where("id", $product_data["zjmf_api_id"])->find();
                    $returndata["manual"] = ["id" => $product_data["zjmf_api_id"], "name" => $upper_reaches["name"]];
                    $returndata["product"]["api_type"] = "manual";
                } else {
                    if ($product["api_type"] == "manual") {
                        $upper_reaches = \think\Db::name("zjmf_finance_api")->where("type", "manual")->where("id", $product_data["upper_reaches_id"])->find();
                        $returndata["manual"] = ["id" => $product_data["upper_reaches_id"], "name" => $upper_reaches["name"]];
                        $upper_reaches_res = \think\Db::name("upper_reaches_res")->where("hid", $host_data["id"])->find();
                        $returndata["host_data"]["upper_reaches_res"] = $upper_reaches_res["id"] ?? "";
                        $returndata["host_data"]["upper_reaches_control_mode"] = $upper_reaches_res["control_mode"] ?? "";
                    }
                }
            }
        }
        $returndata["reinstall_random_port"] = false;
        $Stime = microtime(true);
        $provision_logic = new \app\common\logic\Provision();
        if ($product["api_type"] == "zjmf_api" || $product["api_type"] == "resource") {
            if (!empty($host_data["dcimid"])) {
                $returndata["module_button"] = [["type" => "default", "func" => "create", "name" => "开通"], ["type" => "default", "func" => "suspend", "name" => "暂停"]];
                if ($returndata["host_data"]["domainstatus"] == "Suspended") {
                    $returndata["module_button"][] = ["type" => "default", "func" => "unsuspend", "name" => "解除暂停"];
                }
                $returndata["module_button"][] = ["type" => "default", "func" => "terminate", "name" => "删除"];
                $returndata["module_button"] = array_merge($returndata["module_button"], $upstream_data["module_button"]["control"]);
                $returndata["module_button"] = array_merge($returndata["module_button"], $upstream_data["module_button"]["console"]);
                $returndata["module_button"][] = ["type" => "default", "func" => "sync", "name" => "拉取信息"];
                $returndata["module_power_status"] = (bool) $upstream_data["module_power_status"];
                $returndata["reinstall_random_port"] = (bool) $upstream_data["reinstall_random_port"];
                $returndata["module_admin_main_area"] = $upstream_data["module_client_main_area"] ?: [];
            } else {
                $returndata["module_button"] = [["type" => "default", "func" => "create", "name" => "开通"]];
                $returndata["module_power_status"] = false;
            }
            $returndata["module_admin_area"] = [];
            $returndata["module_admin_main_area"] = $upstream_data["module_client_main_area"] ?: [];
        } else {
            if ($product["api_type"] == "manual") {
                $UpperReaches = new \app\common\logic\UpperReaches();
                $returndata["module_button"] = $UpperReaches->moduleAdminButton($hostid);
                $returndata["module_admin_area"] = [];
                $returndata["module_power_status"] = $UpperReaches->modulePowerStatus($hostid);
                $returndata["module_admin_main_area"] = [];
            } else {
                if ($product["type"] == "dcimcloud") {
                    $dcimcloud = new \app\common\logic\DcimCloud();
                    $returndata["module_button"] = $dcimcloud->moduleAdminButton($host_data["dcimid"]);
                    $returndata["module_admin_area"] = [];
                    if (0 < $host_data["serverid"] && 0 < $host_data["dcimid"]) {
                        $returndata["module_power_status"] = true;
                    } else {
                        $returndata["module_power_status"] = false;
                    }
                    $returndata["module_admin_main_area"] = [];
                    $returndata["reinstall_random_port"] = $dcimcloud->supportReinstallRandomPort($host_data["id"]);
                } else {
                    if ($product["type"] == "dcim") {
                        $returndata["module_button"] = [];
                        $returndata["module_admin_area"] = [];
                        if (0 < $host_data["serverid"] && 0 < $host_data["dcimid"]) {
                            $returndata["module_power_status"] = true;
                        } else {
                            $returndata["module_power_status"] = false;
                        }
                        $returndata["module_admin_main_area"] = [];
                        if ($product_data["config_option1"] == "bms") {
                            $dcim = new \app\common\logic\Dcim();
                            $returndata["module_button"] = $dcim->moduleAdminButton($host_data["dcimid"]);
                        }
                    } else {
                        $returndata["module_button"] = $provision_logic->adminButtonOutput($hostid);
                        $returndata["module_admin_area"] = $provision_logic->adminArea($hostid);
                        $returndata["module_power_status"] = $provision_logic->checkDefineFunc($hostid, "Status");
                        $returndata["module_admin_main_area"] = $provision_logic->adminAreaMainOutput($hostid);
                    }
                }
            }
        }
        $this->addPushInfoBtn($product, $host_data, $returndata["module_button"]);
        $returndata["module_upgrade"] = false;
        $upgrade_logic = new \app\common\logic\Upgrade();
        if ($upgrade_logic->judgeUpgradeConfig($hostid)) {
            if ($product["type"] == "dcimcloud" || $product["type"] == "dcim" || $product["api_type"] == "zjmf_api") {
                $returndata["module_upgrade"] = true;
            } else {
                $returndata["module_upgrade"] = $provision_logic->checkDefineFunc($hostid, "ChangePackage");
            }
        }
        $customfiles_logic = new \app\common\logic\Customfields();
        $custom_field = $customfiles_logic->getCustomField($productid);
        $custom_field_value = [];
        if (!empty($custom_field)) {
            $custom_field_ids = array_column($custom_field, "id");
            $user_custom_field_value = \think\Db::name("customfieldsvalues")->field("fieldid,value")->whereIn("fieldid", $custom_field_ids)->where("relid", $hostid)->select()->toArray();
            $custom_field_value = [];
            foreach ($user_custom_field_value as $key => $value) {
                $custom_field_value[$value["fieldid"]] = $value["value"];
            }
        }
        $returndata["custom_field"] = $custom_field;
        $returndata["custom_field_value"] = $custom_field_value;
        $returndata["info"] = $info ?: "";
        $returndata["domain_status_list"] = $product["type"] == "ssl" ? config("public.sslDomainStatus") : config("public.domainstatus");
        $admin_auth = ["create" => "on", "suspend" => "on", "unsuspend" => "on", "terminate" => "on", "on" => "on", "off" => "on", "reboot" => "on", "bmc" => "on", "kvm" => "on", "ikvm" => "on", "vnc" => "on", "reinstall" => "on", "rescue" => "on", "crack_pass" => "on", "sync" => "on"];
        if ($product["type"] == "dcim") {
            if ($product["api_type"] == "whmcs") {
                if ($host_data["domainstatus"] == "Active") {
                    $allow = ["on", "off", "reboot", "bmc", "vnc", "reinstall", "crack_pass", "traffic", "sync"];
                    foreach ($admin_auth as $k => $v) {
                        if (!in_array($k, $allow)) {
                            $admin_auth[$k] = "off";
                        }
                    }
                } else {
                    foreach ($admin_auth as $k => $v) {
                        $admin_auth[$k] = "off";
                    }
                }
            } else {
                if ($host_data["dcimid"] == 0) {
                    foreach ($admin_auth as $k => $v) {
                        if ($k != "create") {
                            $admin_auth[$k] = "off";
                        }
                    }
                } else {
                    if ($product_data["api_type"] == "zjmf_api") {
                        if (!empty($zjmf_api)) {
                            $returndata["dcim"]["url"] = trim($zjmf_api["hostname"], "/") . "/#/server/billing?id=" . $host_data["dcimid"];
                        } else {
                            $returndata["dcim"]["url"] = "";
                        }
                    } else {
                        $admin_auth["create"] = "off";
                        $dcim = new \app\common\logic\Dcim($host_data["serverid"]);
                        if ($product_data["config_option1"] == "bms") {
                            $returndata["dcim"]["url"] = $dcim->url . "/bare/#/business/details?id=" . $host_data["dcimid"];
                        } else {
                            $url = $dcim->url . "/index.php?a=server&m=detailed&id=" . $host_data["dcimid"];
                            $returndata["dcim"]["url"] = $url;
                        }
                    }
                }
            }
            $returndata["dcim"]["auth"] = $admin_auth;
            $returndata["host_data"]["disk_num"] = 1;
            $dcim_server_group = \think\Db::name("host_config_options")->field("a.id,a.qty,b.option_name,b.option_type,c.option_name sub_option")->alias("a")->leftJoin("product_config_options b", "a.configid=b.id")->leftJoin("product_config_options_sub c", "a.configid=c.config_id and a.optionid=c.id")->where("a.relid", $host_data["id"])->whereLike("b.option_name", "server_group|%")->find();
            list($returndata["dcim"]["server_group"]) = explode("|", $dcim_server_group["sub_option"]);
        }
        if ($product["type"] == "dcimcloud" && 0 < $host_data["dcimid"]) {
            if ($product_data["api_type"] == "zjmf_api") {
                if (!empty($zjmf_api)) {
                    $returndata["dcim"]["url"] = trim($zjmf_api["hostname"], "/") . "/#/cloud-server-info?id=" . $host_data["dcimid"];
                } else {
                    $returndata["dcim"]["url"] = "";
                }
            } else {
                $dcimcloud = new \app\common\logic\DcimCloud($host_data["serverid"]);
                $returndata["dcim"]["url"] = $dcimcloud->url . "/#/cloudsHome?id=" . $host_data["dcimid"];
            }
        }
        $show_os_svg = false;
        foreach ($returndata["config_array"] as $v) {
            if ($v["option_type"] == 5) {
                $show_os_svg = true;
                if (!$show_os_svg) {
                    $returndata["host_data"]["os_url"] = "none";
                }
                unset($returndata["host_data"]["stream_info"]);
                $credit = \think\Db::name("clients")->where("id", $uid)->value("credit");
                $returndata["credit"] = $credit;
                return jsonrule(["status" => 200, "data" => $returndata]);
            }
        }
    }
    protected function addPushInfoBtn($product, $host_data, &$btn)
    {
        if (!$host_data["stream_info"]) {
            return NULL;
        }
        $stream_info = json_decode($host_data["stream_info"], true);
        if (empty($stream_info["downstream_url"])) {
            return NULL;
        }
        $btn[] = ["type" => "default", "func" => "pushHostInfo", "name" => "推送信息"];
    }
    public function adminGetLinkAgeList()
    {
        try {
            return jsons(["status" => 200, "msg" => "success", "data" => $this->getLinkAgeList()]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => "请求失败"]);
        }
    }
    protected function getLinkAgeList()
    {
        $req = $this->request;
        $currencyid = priorityCurrency($req->uid);
        $product_model = new \app\common\model\ProductModel();
        $billingcycle = $product_model->getProductCycle($req->pid, $currencyid, "", "", "", "", "", "", 1)[0]["billingcycle"] ?: "";
        $config_logic = new \app\common\logic\ConfigOptions();
        $alloption = $config_logic->getConfigInfo($req->pid);
        $alloption = $config_logic->configShow($alloption, $currencyid, $billingcycle);
        if (!$alloption) {
            return $alloption;
        }
        $data = array_column($alloption, NULL, "id");
        $all_list = $config_logic->webGetLinkAgeList($req);
        $linkAge = $config_logic->webSetLinkAgeListDefaultVal($all_list, $req);
        $list = [];
        foreach ($linkAge as $val) {
            if (isset($data[$val["id"]])) {
                $data[$val["id"]]["checkSubId"] = $val["checkSubId"];
                $list[] = $data[$val["id"]];
            }
        }
        $list = $config_logic->getTree($list);
        return $this->handleTreeArr($list);
    }
    protected function handleTreeArr($data)
    {
        if (!$data) {
            return $data;
        }
        foreach ($data as $key => $val) {
            if (isset($val["son"]) && $val["son"]) {
                $data[$key]["son"] = changeTwoArr($val["son"]);
            } else {
                $data[$key]["son"] = [];
            }
        }
        return $data;
    }
    public function getProductList()
    {
        $data = ["product_list" => getProductList()];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postInfo(\think\Request $request)
    {
        $param = $request->param();
        $validate = new \app\admin\validate\HostValidate();
        $result = $validate->check($param);
        $hostid = $param["hostid"];
        $hostid = intval($hostid);
        $host_data = \think\Db::name("host")->where("id", $hostid)->find();
        if (empty($host_data)) {
            return jsonrule(["status" => 406, "msg" => "产品未找到"]);
        }
        $log_desc = "";
        $uid = $host_data["uid"];
        if (!$this->check1($uid)) {
            return jsonrule(["status" => 400, "msg" => lang("NOT_USER_ID")]);
        }
        $info = "";
        $udata = ["regdate" => $param["regdate"], "firstpaymentamount" => $param["firstpaymentamount"], "amount" => $param["amount"], "nextduedate" => $param["nextduedate"], "termination_date" => $param["termination_date"] ?? 0, "username" => $param["username"] ?? "", "password" => $param["password"] ? cmf_encrypt($param["password"]) : "", "billingcycle" => $param["billingcycle"], "payment" => $param["payment"], "domain" => $param["domain"], "domainstatus" => $param["domainstatus"], "promoid" => $param["promoid"] ?? 0, "dedicatedip" => $param["dedicatedip"] ?? "", "assignedips" => $param["assignedips"] ?? "", "overideautosuspend" => $param["overideautosuspend"] ?? 0, "overidesuspenduntil" => $param["overidesuspenduntil"] ?? 0, "auto_terminate_end_cycle" => $param["auto_terminate_end_cycle"] == "false" ? 0 : 1, "auto_terminate_reason" => $param["auto_terminate_reason"] ?? "", "notes" => $param["notes"] ?? "", "port" => $param["port"] ?? 0, "initiative_renew" => $param["initiative_renew"] ?? 0, "upstream_cost" => $param["upstream_cost"] ?? 0];
        $product_info = \think\Db::name("products")->where("id", $host_data["productid"])->find();
        $product_type = $product_info["type"];
        if ($product_info["api_type"] != "zjmf_api" && ($product_type == "dcimcloud" || $product_type == "dcim")) {
            $udata["dcimid"] = (int) $param["dcimid"];
            if (0 < $host_data["dcimid"] && $param["dcimid"] <= 0) {
                $udata["username"] = "";
                $udata["password"] = "";
                $udata["dedicatedip"] = "";
                $udata["assignedips"] = "";
            }
        }
        if ($product_info["api_type"] == "zjmf_api" && $param["dcimid"] != $host_data["dcimid"] && !empty($param["dcimid"])) {
            $post_data["id"] = $param["dcimid"];
            $post_data["pid"] = $product_info["upstream_pid"];
            $post_data["downstream_url"] = request()->domain() . request()->rootUrl();
            $post_data["downstream_token"] = md5(randStr(16) . time() . $id);
            $post_data["downstream_id"] = $hostid;
            $res = zjmfCurl($product_info["zjmf_api_id"], "/host/setdownstream", $post_data);
            if ($res["status"] == 200) {
                $update_data = [];
                $update_data["dcimid"] = $param["dcimid"];
                $stream_info = json_decode($host_data["stream_info"], true) ?: [];
                $stream_info["token"] = $post_data["downstream_token"];
                $update_data["stream_info"] = json_encode($stream_info);
                \think\Db::name("host")->where("id", $hostid)->update($update_data);
            } else {
                $result["status"] = 400;
                $result["msg"] = "保存失败,原因:" . $res["msg"];
                return jsonrule($result);
            }
        }
        if ($host_data["regdate"] != $param["regdate"]) {
            $log_desc .= "开通时间由“" . Date("Y-m-d H:i:s", $host_data["regdate"]) . "”改为“" . Date("Y-m-d H:i:s", $param["regdate"]) . "”，";
        }
        if ($host_data["firstpaymentamount"] != $param["firstpaymentamount"]) {
            $log_desc .= "首付金额从由“" . $host_data["firstpaymentamount"] . "”改为“" . $param["firstpaymentamount"] . "”，";
        }
        if ($host_data["amount"] != $param["amount"]) {
            $log_desc .= "续费金额由“" . $host_data["amount"] . "”改为“" . $param["amount"] . "”，";
        }
        if ($host_data["amount"] != $param["amount"]) {
            $udata["flag"] = 1;
            $udata["flag_cycle"] = $param["billingcycle"];
        }
        if (2099 < date("Y", $param["nextduedate"])) {
            $param["nextduedate"] = $host_data["nextduedate"];
        } else {
            if ($host_data["nextduedate"] != $param["nextduedate"]) {
                $log_desc .= "下次到期时间由“" . Date("Y-m-d H:i:s", $host_data["nextduedate"]) . "“改为”" . Date("Y-m-d H:i:s", $param["nextduedate"]) . "”，";
            }
        }
        if (2099 < date("Y", $param["termination_date"])) {
            $param["termination_date"] = $host_data["termination_date"];
        } else {
            if ($host_data["termination_date"] != $param["termination_date"]) {
                $log_desc .= "终止时间由“" . Date("Y-m-d H:i:s", $host_data["termination_date"]) . "”改为“" . Date("Y-m-d H:i:s", $param["termination_date"]) . "”，";
            }
        }
        if ($host_data["username"] != $param["username"]) {
            $log_desc .= "用户名由“" . $host_data["username"] . "”改为“" . $param["username"] . "”，";
        }
        if (cmf_decrypt($host_data["password"]) != $param["password"]) {
            $log_desc .= "密码发生修改，";
        }
        $arr = Config("billing_cycle");
        if ($host_data["billingcycle"] != $param["billingcycle"]) {
            $log_desc .= "付款周期由“" . $arr[$host_data["billingcycle"]] . "”改为“" . $arr[$param["billingcycle"]] . "”，";
        }
        unset($arr);
        if ($host_data["payment"] != $param["payment"]) {
            $arr = gateway_list();
            $arr = array_column($arr, "title", "name");
            $log_desc .= "支付方式由“" . $arr[$host_data["payment"]] . "”改为“" . $arr[$param["payment"]] . "”，";
        }
        if ($host_data["domain"] != $param["domain"]) {
            $log_desc .= "主机名由“" . $host_data["domain"] . "”改为“" . $param["domain"] . "”，";
        }
        if ($host_data["initiative_renew"] != $param["initiative_renew"]) {
            if ($param["initiative_renew"] == 1) {
                $log_desc .= "自动续费由“关闭”改为“开启”，";
            } else {
                $log_desc .= "自动续费由“开启”改为“关闭”，";
            }
        }
        if ($host_data["promoid"] != $param["promoid"]) {
            $new_product_data1 = \think\Db::name("promo_code")->field("code")->find($host_data["promoid"]);
            $new_product_data = \think\Db::name("promo_code")->field("code")->find($param["promoid"]);
            $log_desc .= "优惠码由“" . $new_product_data1["code"] . "”改为“" . $new_product_data["code"] . "”，";
        }
        if ($host_data["dedicatedip"] != $param["dedicatedip"]) {
            $log_desc .= "ip地址由“" . $host_data["dedicatedip"] . "”改为“" . $param["dedicatedip"] . "”，";
        }
        if ($host_data["assignedips"] != $param["assignedips"]) {
            $log_desc .= "其他ip由“" . $host_data["assignedips"] . "”改为“" . $param["assignedips"] . "”，";
        }
        if ($host_data["overideautosuspend"] != $param["overideautosuspend"]) {
            $log_desc .= "暂停时间由“" . $host_data["overideautosuspend"] . "”改为“" . $param["overideautosuspend"] . "”，";
        }
        if ($host_data["overidesuspenduntil"] != $param["overidesuspenduntil"]) {
            $log_desc .= "暂停时间戳由“" . $host_data["overidesuspenduntil"] . "”改为“" . $param["overidesuspenduntil"] . "”，";
        }
        if ($host_data["auto_terminate_end_cycle"] != $udata["auto_terminate_end_cycle"]) {
            if ($param["auto_terminate_end_cycle"] == 1) {
                $log_desc .= "到期后自动删除由“关闭”改为“开启”，";
            } else {
                $log_desc .= "到期后自动删除由“开启”改为“关闭”，";
            }
        }
        if ($host_data["auto_terminate_reason"] != $param["auto_terminate_reason"]) {
            $log_desc .= "到期自动删除原因由“" . $host_data["auto_terminate_reason"] . "”更改为“" . $param["auto_terminate_reason"] . "”，";
        }
        if ($host_data["notes"] != $param["notes"]) {
            $log_desc .= "管理员备注由“" . $host_data["notes"] . "”更改为“" . $param["notes"] . "”，";
        }
        $oldproductid = $host_data["productid"];
        $productid = $param["productid"];
        if ($oldproductid != $productid) {
            $new_product_data = \think\Db::name("products")->field("name")->find($productid);
            if (!empty($new_product_data)) {
                $new_product_data1 = \think\Db::name("products")->field("name")->find($oldproductid);
                $log_desc .= "产品/服务由“" . $new_product_data1["name"] . "”更改为“" . $new_product_data["name"] . "”，";
                $udata["productid"] = $productid;
            } else {
                return jsonrule(["status" => 400, "msg" => "更改到" . $productid . "失败，未找到该产品"]);
            }
        }
        if ($product_info["api_type"] == "zjmf_api") {
            $udata["serverid"] = 0;
        } else {
            $oldserver = $host_data["serverid"];
            $server = $param["serverid"];
            if ($oldserver != $server) {
                $server_data = \think\Db::name("servers")->field("name")->where("id=" . $server)->find();
                $server_data1 = \think\Db::name("servers")->field("name")->where("id=" . $oldserver)->find();
                $log_desc .= "服务器由“" . $server_data1["name"] . "”更改为“" . $server_data["name"] . "”，";
                $udata["serverid"] = $server;
            }
        }
        $termination_date = $param["termination_date"];
        if (!empty($termination_date)) {
            if (in_array($param["domainstatus"], ["Terminated", "Cancelled"])) {
                $udata["termination_date"] = time();
            } else {
                $info = "忽略终止日期，因为它只能对产品的取消或终止状态进行设置。";
            }
        }
        if ($param["domainstatus"] != $host_data["domainstatus"]) {
            $status = config("domainstatus");
            $log_desc .= "状态由“" . $status[$host_data["domainstatus"]] . "”更改为“" . $status[$param["domainstatus"]] . "”";
        }
        if ($product_info["api_type"] != "zjmf_api" && $product_type == "dcimcloud") {
            $old_traffic_bill_type = self::name("host_config_options")->field("a.id,a.qty,a.configid,a.optionid,b.option_name,b.option_type,b.upgrade")->alias("a")->leftJoin("product_config_options b", "a.configid=b.id")->where("a.relid", $hostid)->whereLike("b.option_name", "traffic_bill_type|%")->find();
        }
        $configoption = $param["configoption"];
        $upgrade_logic = new \app\common\logic\Upgrade();
        $log_text = $upgrade_logic->checkChangeText($hostid, $configoption);
        if ($log_text["data"]) {
            active_log_final($log_text["data"], $uid, 2, $hostid);
        }
        if (!empty($configoption) && is_array($configoption)) {
            $config_option_logic = new \app\common\logic\ConfigOptions();
            $config_option_logic->updateConfig($hostid, $configoption, $param["productid"], $product_info);
        }
        $customfield = $param["custom"];
        if (!empty($customfield) && is_array($customfield)) {
            $customfield_logic = new \app\common\logic\Customfields();
            $customfield_logic->saveCustomField($hostid, $customfield, "product", $param["productid"], $product_info);
        }
        $os = \think\Db::name("host_config_options")->field("a.id,c.option_name")->alias("a")->leftJoin("product_config_options b", "a.configid=b.id")->leftJoin("product_config_options_sub c", "a.configid=c.config_id and a.optionid=c.id")->where("a.relid", $hostid)->where("b.option_type", 5)->find();
        if (!empty($os)) {
            list($os) = explode("|", $os["option_name"]);
            if (strpos($os, "^") !== false) {
                $os_arr = explode("^", $os);
                $udata["os"] = $os_arr[1] ?? "";
                $udata["os_url"] = $os_arr[0];
            } else {
                $udata["os"] = $os;
                $udata["os_url"] = "";
            }
        }
        if (in_array($host_data["domainstatus"], ["Pending", "Active", "Suspended"])) {
            if (in_array($param["domainstatus"], ["Cancelled", "Fraud", "Deleted"])) {
                \think\Db::name("products")->where("id", $host_data["productid"])->setInc("qty", 1);
            }
        } else {
            if (in_array($host_data["domainstatus"], ["Cancelled", "Fraud", "Deleted"]) && in_array($param["domainstatus"], ["Pending", "Active", "Suspended"])) {
                $product_info = \think\Db::name("products")->where("id", $host_data["productid"])->find();
                if ($product_info["qty"] < 1) {
                    return jsonrule(["status" => 400, "msg" => "此产品库存不足，无法切换产品到此状态，请前往添加库存"]);
                }
                \think\Db::name("products")->where("id", $host_data["productid"])->setDec("qty", 1);
            }
        }
        \think\Db::name("host")->where("id", $hostid)->update($udata);
        $amount = $param["amount"] ?: 0;
        $provision_logic = new \app\common\logic\Provision();
        $provision_logic->adminSave($hostid);
        if (!empty($log_desc)) {
            if (empty($log_desc)) {
                $log_desc .= "什么也没修改";
            }
            active_log(sprintf($this->lang["ClientsServices_admin_postInfo"], $uid, $hostid, $log_desc), $uid);
        }
        if (!empty($old_traffic_bill_type) && $configoption[$old_traffic_bill_type["configid"]] != $old_traffic_bill_type["optionid"]) {
            $dcimcloud = new \app\common\logic\DcimCloud();
            $dcimcloud->updateResetFlowDay($hostid);
        }
        hook("after_admin_edit_service", ["adminid" => cmf_get_current_admin_id(), "hostid" => $hostid]);
        return jsonrule(["status" => 200, "msg" => "更改保存成功！", "info" => $info]);
    }
    public function postTransfer(\think\Request $request)
    {
        $param = $request->param();
        $transfer_uid = intval($param["transfer_uid"]);
        $hostid = intval($param["hostid"]);
        $host_data = \think\Db::name("host")->field("id,uid,productid")->find($hostid);
        $uid = $host_data["uid"];
        if (!$this->check1($uid)) {
            return jsonrule(["status" => 400, "msg" => lang("NOT_USER_ID")]);
        }
        if (empty($transfer_uid)) {
            return jsonrule(["status" => 400, "msg" => "接收用户不能为空"]);
        }
        if (empty($hostid)) {
            return jsonrule(["status" => 400, "msg" => "产品不能为空"]);
        }
        $trans_client_data = \think\Db::name("clients")->field("id,username")->find($transfer_uid);
        if (empty($trans_client_data)) {
            active_log(sprintf($this->lang["ClientsServices_admin_postTransfer_fail2"], $hostid, $uid, $transfer_uid), $uid);
            return jsonrule(["status" => 400, "msg" => "接收用户不存在"]);
        }
        if (empty($host_data)) {
            active_log(sprintf($this->lang["ClientsServices_admin_postTransfer_fail1"], $hostid, $uid, $transfer_uid), $uid);
            return jsonrule(["status" => 400, "msg" => "产品未找到"]);
        }
        if ($uid == $transfer_uid) {
            active_log(sprintf($this->lang["ClientsServices_admin_postTransfer_fail"], $hostid, $uid, $transfer_uid), $uid);
            return jsonrule(["status" => 400, "msg" => "用户相同，不能转移"]);
        }
        \think\Db::startTrans();
        try {
            $renew_logic = new \app\common\logic\Renew();
            $renew_logic->deleteRenewInvoice($hostid);
            $upgrade_logic = new \app\common\logic\Upgrade();
            $upgrade_logic->deleteUpgradeInvoices($hostid);
            $hosts = \think\Db::name("host")->alias("a")->field("c.id as invoice_id")->leftJoin("orders b", "a.orderid = b.id")->leftJoin("invoices c", "b.invoiceid = c.id")->where("a.id", $hostid)->where("c.status", "Unpaid")->select()->toArray();
            $invoice_ids = array_column($hosts, "invoice_id");
            \think\Db::name("invoices")->whereIn("id", $invoice_ids)->useSoftDelete("delete_time", time())->delete();
            \think\Db::name("host")->where("id", $hostid)->update(["uid" => $transfer_uid]);
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 400, "msg" => "转移产品失败"]);
        }
        active_log(sprintf($this->lang["ClientsServices_admin_postTransfer"], $hostid, $uid, $transfer_uid), $uid);
        active_log(sprintf($this->lang["ClientsServices_admin_postTransfer_suffer"], $hostid, $uid, $transfer_uid), $transfer_uid);
        \think\facade\Hook::listen("transfer_service", ["hostid" => $hostid, "uid" => $uid, "transfer_uid" => $transfer_uid]);
        return jsonrule(["status" => 200, "msg" => "转移产品成功"]);
    }
    public function deleteHost(\think\Request $request)
    {
        $param = $request->param();
        $hids = $param["hostid"];
        if (!is_array($hids)) {
            $hostids = [$hids];
        } else {
            $hostids = $hids;
        }
        foreach ($hostids as $hostid) {
            $host_data = \think\Db::name("host")->find($hostid);
            if (empty($host_data)) {
                return jsonrule(["status" => 406, "msg" => "未找到该主机"]);
            }
            $productid = $host_data["productid"];
            $uid = $host_data["uid"];
            if (!$this->check1($uid)) {
                return jsonrule(["status" => 400, "msg" => lang("NOT_USER_ID")]);
            }
        }
        \think\Db::startTrans();
        try {
            $host_datas = [];
            foreach ($hostids as $hostid) {
                $host_data = \think\Db::name("host")->where("id", $hostid)->find();
                $host_datas[] = $host_data;
                \think\Db::name("host")->where("id", $hostid)->delete();
                \think\Db::name("host_config_options")->where("relid", $hostid)->delete();
                $custom_data = \think\Db::name("customfields")->field("id")->where("type", "product")->where("relid", $productid)->select()->toArray();
                $custom_data_ids = array_column($custom_data, "id");
                \think\Db::name("customfieldsvalues")->whereIn("fieldid", $custom_data_ids)->where("relid", $hostid)->delete();
                $invoice_logic = new \app\common\logic\Invoices();
                $invoice_logic->cancelInvoices($hostid);
                \think\Db::name("upper_reaches_res")->where("hid", $hostid)->update(["hid" => 0]);
            }
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            foreach ($hostids as $hostid) {
                $host_data = \think\Db::name("host")->find($hostid);
                $productid = $host_data["productid"];
                $uid = $host_data["uid"];
                active_log_final(sprintf($this->lang["ClientsServices_admin_deleteHost_success"], $hostid, $uid, $productid), $uid, 2, $hostid);
            }
            return jsonrule(["status" => 406, "msg" => "删除产品失败"]);
        }
        foreach ($host_datas as $host_data) {
            $productid = $host_data["productid"];
            $uid = $host_data["uid"];
            \think\facade\Hook::listen("service_delete", ["hostid" => $hostid, "uid" => $uid]);
            active_log_final(sprintf($this->lang["ClientsServices_admin_deleteHost_success"], $hostid, $uid, $productid), $uid, 2, $hostid);
        }
        return jsonrule(["status" => 200, "msg" => "删除成功"]);
    }
    public function hostRenew()
    {
        $params = $this->request->param();
        $hid = $params["hostid"];
        if (!$this->check1(0, $hid)) {
            return jsonrule(["status" => 400, "msg" => lang("NOT_USER_ID")]);
        }
        $billingcycle = isset($params["billingcycles"]) ? $params["billingcycles"] : "";
        $renew = new \app\common\logic\Renew();
        $renew->is_admin = true;
        $res = $renew->renew($hid, $billingcycle);
        return jsonrule($res);
    }
    public function postBatchRenewPage()
    {
        $params = $this->request->param();
        $host_ids = $params["host_ids"];
        $cycles_param = isset($params["cycles"]) ? $params["cycles"] : [];
        $amount = isset($params["amount"]) ? $params["amount"] : [];
        if (empty($host_ids)) {
            return jsonrule(["status" => 400, "msg" => lang("Host_EMPTY")]);
        }
        if (!is_array($host_ids)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $uid = intval($params["uid"]);
        $client = \think\Db::name("clients")->where("id", $uid)->find();
        if (empty($client)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $currency_id = priorityCurrency($uid);
        list($currency) = (new \app\common\logic\Currencies())->getCurrencies("id,prefix,suffix,code", $currency_id);
        $hosts = \think\Db::name("host")->field("id")->where("uid", $uid)->whereIn("id", $host_ids)->select()->toArray();
        $host_ids = array_column($hosts, "id");
        $host_data = \think\Db::name("host")->alias("a")->field("a.productid,a.dedicatedip,a.uid,a.id,a.domainstatus,b.id as pid,b.name,b.pay_type,b.pay_method,a.nextduedate,a.billingcycle,a.amount,a.flag,b.groupid,a.promoid")->leftJoin("products b", "a.productid = b.id")->whereIn("a.id", $host_ids)->select()->toArray();
        $total = 0;
        $totalsale = 0;
        $host_data_filter = [];
        $billing_cycle = config("billing_cycle");
        foreach ($host_data as $k => $v) {
            if (0 <= $v["amount"] && !in_array($v["billingcycle"], ["free", "onetime"]) && $v["pay_method"] == "prepayment" && in_array($v["domainstatus"], ["Active", "Suspended"])) {
                $nav_group = \think\Db::name("nav_group")->where("id", $v["groupid"])->find();
                $v["groupn"] = $nav_group;
                $renew_logic = new \app\common\logic\Renew();
                $renew_logic->is_admin = true;
                $hid = $v["id"];
                $amounts1 = $v["amount"];
                if ($cycles_param[$hid] && $cycles_param[$hid] != $v["billingcycle"]) {
                    $billingcycle = $cycles_param[$hid];
                    $v["amount"] = $renew_logic->calculatedPrice($hid, $billingcycle);
                } else {
                    $billingcycle = $v["billingcycle"];
                }
                $pay_type = json_decode($v["pay_type"], true);
                $pid = $v["pid"];
                $product_model = new \app\common\model\ProductModel();
                $allow_billingcycle = $product_model->getProductCycle($pid, $currency_id, $hid, $billingcycle, $v["amount"], "", $v["billingcycle"], $amounts1, $v["flag"]);
                foreach ($allow_billingcycle as $kk => $vv) {
                    if ($vv["billingcycle"] == "ontrial") {
                        unset($allow_billingcycle[$kk]);
                    }
                    if (empty($cycles_param[$hid])) {
                        if ($billingcycle == $vv["billingcycle"]) {
                            $v["saleproducts"] = $vv["saleproducts"];
                            $totalsale = bcadd($totalsale, $v["saleproducts"], 2);
                            if ($billingcycle == "ontrial") {
                                $billingcycle = $allow_billingcycle[0]["billingcycle"] ?? "";
                                $v["amount"] = $renew_logic->calculatedPrice($hid, $billingcycle);
                            }
                            $cycles = [];
                            foreach ($allow_billingcycle as $kk => $vv) {
                                if (!in_array($vv["billingcycle"], ["free", "ontrial"])) {
                                    $cycles[] = $vv;
                                }
                            }
                            if ((new \app\common\logic\Renew())->unchangePrice($hid, $billingcycle, $currency_id) != -1 && round((new \app\common\logic\Renew())->calculatedPrice($hid, $billingcycle), 2) != round($v["amount"], 2) && $v["promoid"] == 0) {
                                $cycles = [];
                            }
                            if (!in_array($billingcycle, array_column($cycles, "billingcycle"))) {
                                $cycles[] = ["billingcycle" => $billingcycle, "billingcycle_zh" => $billing_cycle[$billingcycle], "setup_fee" => 0, "price" => 0, "amount" => $v["amount"], "saleproducts" => 0];
                            }
                            $allow_billingcycle = $cycles;
                            if ($billingcycle == "onetime" || $billingcycle == "free") {
                                $next_time = 0;
                            } else {
                                $next_time = getNextTime($billingcycle, $pay_type["pay_" . $billingcycle . "_cycle"], $v["nextduedate"], $pay_type["pay_ontrial_cycle_type"] ?: "day");
                            }
                            if ($amount) {
                                $v["amount"] = 0 < $amount[$hid] ? floatval($amount[$hid]) : 0;
                            }
                            $total = bcadd($total, $v["amount"], 2);
                            $v["billingcycle"] = $billingcycle;
                            $v["nextduedate_renew"] = $next_time;
                            $v["allow_billingcycle"] = $allow_billingcycle;
                            unset($v["domainstatus"]);
                            unset($v["pay_method"]);
                            unset($v["pid"]);
                            unset($v["pay_type"]);
                            $host_data_filter[] = $v;
                        }
                    } else {
                        if ($cycles_param[$hid] == $vv["billingcycle"]) {
                            $v["saleproducts"] = $vv["saleproducts"];
                            $totalsale = bcadd($totalsale, $vv["saleproducts"], 2);
                        }
                    }
                }
            }
        }
        $data = [];
        $data["currency"] = $currency;
        $data["hosts"] = $host_data_filter;
        $data["total"] = $total;
        $data["totalsale"] = $totalsale;
        $credit = \think\Db::name("clients")->where("id", $uid)->value("credit");
        $data["credit"] = 0 < $credit ? $credit : 0;
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postBatchRenew()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $uid = intval($params["uid"]);
            $host_ids = $params["host_ids"];
            $billincycles = $params["cycles"];
            $amount = $params["amount"];
            $hosts = \think\Db::name("host")->field("id")->where("uid", $uid)->whereIn("id", $host_ids)->select()->toArray();
            $host_ids = array_column($hosts, "id");
            $renew_logic = new \app\common\logic\Renew();
            $renew_logic->is_admin = true;
            $renew_logic->uid = $uid;
            $res = $renew_logic->setOtherParams($amount)->batchRenew($host_ids, $billincycles);
            if ($res["status"] == 200) {
                $payment = \think\Db::name("clients")->where("id", $uid)->value("defaultgateway");
                if (!$payment) {
                    $gateway_list = gateway_list("gateways");
                    $payment_name_list = array_column($gateway_list, "name");
                    $payment = $payment_name_list[0];
                }
                $res["data"]["payment"] = $payment;
            }
            return jsonrule($res);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function getApplyCreditPage()
    {
        $param = $this->request->param();
        $subtotal = \think\Db::name("invoices")->where("id", intval($param["invoiceid"]))->value("subtotal");
        $credit = \think\Db::name("clients")->where("id", intval($param["uid"]))->value("credit");
        $data = ["total" => 0 < $subtotal ? $subtotal : 0, "credit" => 0 < $credit ? $credit : 0];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function applyCredit(\think\Request $request)
    {
        $param = $request->param();
        $invoiceid = intval($param["invoiceid"]);
        $uid = $param["uid"];
        $use_credit = true;
        $check_res = $this->checkInvoice($uid, $invoiceid);
        if ($check_res["status"] == 200) {
            $invoice_data = $check_res["data"];
            if (!$use_credit) {
                $invoice_data = ["credit" => 0, "total" => $invoice_data["subtotal"]];
                \think\Db::name("invoices")->where("id", $invoiceid)->update($invoice_data);
                return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => ["invoiceid" => $invoiceid]]);
            }
            $invoice_credit = $invoice_data["credit"];
            $user_credit = \think\Db::name("clients")->where("id", $uid)->value("credit");
            if ($user_credit <= 0) {
                return jsons(["status" => 400, "msg" => "当前余额小于等于0,不可使用余额"]);
            }
            $invoic_subtotal = $invoice_data["subtotal"];
            if ($invoic_subtotal < $user_credit) {
                $user_credit = $invoic_subtotal;
            }
            $surplus = getSurplus($invoiceid);
            if ($surplus < $user_credit) {
                $user_credit = $surplus;
            }
            $paid_invoice_credit = $user_credit + $invoice_credit + $invoic_subtotal - $invoice_data["total"];
            $paid_invoice_total = bcsub($invoic_subtotal, $paid_invoice_credit, 2);
            $time = time();
            $insert_credit = ["uid" => $uid, "create_time" => $time, "description" => "Credit Applied to Invoice #" . $invoiceid, "amount" => $user_credit, "relid" => $invoiceid];
            if ($paid_invoice_total == 0) {
                $update_invoice = ["paid_time" => $time, "credit" => $paid_invoice_credit, "total" => $paid_invoice_total, "status" => "Paid"];
                hook("invoice_paid", ["invoice_id" => $invoiceid]);
                \think\Db::startTrans();
                try {
                    \think\Db::name("invoices")->where("id", $invoiceid)->update($update_invoice);
                    $virtual_credit = $user_credit + $invoice_data["subtotal"] - $invoice_data["total"] - $invoice_credit;
                    if (0 < $virtual_credit) {
                        $virtual = \think\Db::name("clients")->where("id", $uid)->where("credit", ">=", $virtual_credit)->setDec("credit", $virtual_credit);
                        if (empty($virtual)) {
                            active_log(sprintf($this->lang["Order_admin_clients_updatecredit_fail"], $uid), $uid);
                            throw new \Exception("余额不足");
                        }
                        credit_log(["uid" => $uid, "desc" => "Credit Applied to Invoice #" . $invoiceid, "amount" => $user_credit, "relid" => $invoiceid]);
                    }
                    \think\Db::commit();
                } catch (\Exception $e) {
                    \think\Db::rollback();
                    return jsons(["status" => 400, "msg" => "支付失败:" . $e->getMessage()]);
                }
                $invoice_logic = new \app\common\logic\Invoices();
                $invoice_logic->is_admin = true;
                $invoice_logic->processPaidInvoice($invoiceid);
                $result["status"] = 1001;
                $result["msg"] = "支付完成";
                $result["data"]["hostid"] = \think\Db::name("invoice_items")->where("invoice_id", $invoiceid)->where("type", "host")->where("delete_time", 0)->column("rel_id");
                if ((strpos($param["downstream_url"], "https://") === 0 || strpos($param["downstream_url"], "http://") === 0) && strlen($param["downstream_token"]) == 32 && is_numeric($param["downstream_id"])) {
                    $stream_info = \think\Db::name("host")->where("id", (int) $result["data"]["hostid"][0])->value("stream_info");
                    $stream_info = json_decode($stream_info, true) ?: [];
                    $stream_info["downstream_url"] = $param["downstream_url"];
                    $stream_info["downstream_token"] = $param["downstream_token"];
                    $stream_info["downstream_id"] = $param["downstream_id"];
                    \think\Db::name("host")->where("id", (int) $result["data"]["hostid"][0])->update(["stream_info" => json_encode($stream_info)]);
                }
                return jsons($result);
            }
            if (intval($param["enough"]) == 1) {
                return jsons(["status" => 400, "msg" => "余额不足"]);
            }
            \think\Db::name("invoices")->where("id", $invoiceid)->update(["total" => $paid_invoice_total]);
            return jsons(["status" => 200, "msg" => "使用余额成功", "data" => ["invoiceid" => $invoiceid]]);
        }
        return jsons($check_res);
    }
    private function checkInvoice($uid, $invoiceid)
    {
        if (empty($invoiceid)) {
            return ["status" => "406", "msg" => "未找到支付项目"];
        }
        $invoice_data = \think\Db::name("invoices")->where("id", $invoiceid)->where("uid", $uid)->find();
        if (empty($invoice_data)) {
            return ["status" => "406", "msg" => "账单未找到"];
        }
        if ($invoice_data["status"] == "Paid" || $invoice_data["total"] == 0) {
            return ["status" => "406", "msg" => "账单已支付"];
        }
        if (!empty($invoice_data["delete_time"])) {
            return ["status" => "406", "msg" => "账单已过期"];
        }
        return ["status" => 200, "data" => $invoice_data];
    }
    public function suspendPage()
    {
        $params = $this->request->param();
        $hostid = isset($params["hostid"]) ? intval($params["hostid"]) : "";
        if (!$hostid) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        if (!$this->check1(0, $hostid)) {
            return jsonrule(["status" => 400, "msg" => lang("NOT_USER_ID")]);
        }
        $host_data = \think\Db::name("host")->field("suspendreason")->where("id", $hostid)->find();
        $host_data["suspendreason_type"] = explode("-", $host_data["suspendreason"])[0] ? explode("-", $host_data["suspendreason"])[0] : "";
        $host_data["suspendreason"] = explode("-", $host_data["suspendreason"])[1] ? explode("-", $host_data["suspendreason"])[1] : "";
        $data = [];
        $data["host"] = $host_data;
        $data["reason"] = config("host_suspend");
        $data["hostid"] = $hostid;
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function suspend()
    {
        $param = $this->request->param();
        $id = intval($param["id"]);
        $reason = $param["reason"];
        $reason_type = $param["reason_type"];
        $host = \think\Db::name("host")->where("id", $id)->find();
        if (empty($host)) {
            return jsonrule(["status" => 400, "msg" => "产品不存在"]);
        }
        if ($reason_type == "other" && empty($reason)) {
            return jsonrule(["status" => 400, "msg" => "请填写暂停原因!"]);
        }
        $arr = ["flow" => "用量超额", "due" => "到期", "uncertifi" => "未实名认证", "other" => "其他"];
        if ($reason_type != "other") {
            $reason = $arr[$reason_type];
        }
        \think\Db::name("host")->where("id", $id)->update(["domainstatus" => "Suspended", "suspendreason" => $reason_type . "-" . $reason, "suspend_time" => time()]);
        return jsonrule(["status" => 200, "msg" => "请求成功"]);
    }
    public function postSearchClient(\think\Request $request)
    {
        $param = $request->param();
        $client_id = $param["client_id"] ? intval($param["client_id"]) : "";
        if (!$this->check($client_id)) {
            return jsonrule(["status" => 400, "msg" => lang("NOT_USER_ID")]);
        }
        $client_list = \think\Db::name("clients")->field("email,username,id")->where(function ($query) {
            if ($client_id) {
                $query->where("email", "LIKE", $client_id)->whereOr("id", $client_id)->whereOr("username", $client_id);
            }
        })->select()->toArray();
        $returndata = [];
        $returndata["client_list"] = $client_list;
        active_log(sprintf($this->lang["ClientsServices_admin_postSearchClient"], $client_id));
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    private function getHostList($uid)
    {
        $host_list = \think\Db::name("host")->field("h.id,p.name,h.domainstatus,h.dedicatedip,p.type")->alias("h")->leftJoin("products p", "p.id=h.productid")->withAttr("domainstatus", function ($value) {
            $domainstatus = [];
            $domainstatus["color"] = config("public.domainstatus")[$value]["color"];
            $domainstatus["name"] = $value;
            $domainstatus["name_zh"] = config("public.domainstatus")[$value]["name"];
            return $domainstatus;
        })->withAttr("type", function ($value) {
            return config("product_type")[$value];
        })->where("h.uid", $uid)->order("h.id", "desc")->select()->toArray();
        return $host_list ?: [];
    }
    private function getProductData($id)
    {
        $data = \think\Db::name("products")->where("id", $id)->find();
        return $data;
    }
    private function getServerList($id)
    {
        $server_list = \think\Db::name("servers")->field("id,name,type")->where("gid", $id)->select()->toArray();
        if ($server_list) {
            $modules = (new \app\common\logic\Provision())->getModules();
            if ($modules) {
                $modules = array_column($modules, "name", "value");
            }
        }
        foreach ($server_list as &$val) {
            $val["name"] .= "(" . ($modules[$val["type"]] ?? $val["type"]) . ")";
        }
        return $server_list;
    }
    public function upgradeConfig()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
                if (!$hid) {
                    return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                if (!$this->check1(0, $hid)) {
                    return jsonrule(["status" => 400, "msg" => lang("NOT_USER_ID")]);
                }
                $upgrade_logic = new \app\common\logic\Upgrade();
                $configoptions = $params["configoption"];
                $log_text = $upgrade_logic->checkChangeText($hid, $configoptions);
                $uid = \think\Db::name("host")->where("id", $hid)->value("uid");
                if ($log_text["data"]) {
                    active_log_final($log_text["data"], $uid, 2, $hid);
                }
                $data["hid"] = $hid;
                $data["configoptions"] = $configoptions;
                if (!empty($configoptions) && is_array($configoptions)) {
                    $re = $upgrade_logic->upgradeConfigAdmin($hid, $configoptions);
                    return jsonrule($re);
                }
                return jsonrule(["status" => 400, "msg" => lang("ILLEGAL_PARAM")]);
            }
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function getRefundPage()
    {
        $refund_method = ["day" => "按天计算", "full" => "全额退款", "custom" => "自定义"];
        $refund_type = ["addascredit" => "退款至余额", "only" => "仅标记退款"];
        $param = $this->request->param();
        $hid = intval($param["hid"]);
        $tmp = \think\Db::name("host")->where("id", $hid)->find();
        if (empty($tmp)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $item_type = config("invoice_type");
        $items = \think\Db::name("invoice_items")->alias("a")->field("a.invoice_id,a.amount")->leftJoin("invoices b", "a.invoice_id = b.id")->where("a.rel_id", $hid)->whereIn("a.type", ["host", "renew"])->where("b.status", "Paid")->select()->toArray();
        $amount = 0;
        foreach ($items as $v) {
            $amount += $v["amount"];
        }
        $invoice_ids = array_column($items, "invoice_id");
        $invoices = \think\Db::name("invoices")->field("id,subtotal,type,type as type_zh")->whereIn("id", $invoice_ids)->where("status", "Paid")->withAttr("type_zh", function ($value, $data) {
            return $item_type[$value];
        })->select()->toArray();
        $data = ["invoices" => $invoices, "currency" => getUserCurrency($tmp["uid"]), "refund_method" => $refund_method, "refund_type" => $refund_type, "refund_amount" => round($amount, 2)];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function refund()
    {
        $refund_method = ["day" => "按天计算", "full" => "全额退款", "custom" => "自定义"];
        $refund_type = ["addascredit" => "退款至余额", "only" => "仅标记退款"];
        $param = $this->request->param();
        $hid = intval($param["hid"]);
        $tmp = \think\Db::name("host")->where("id", $hid)->find();
        if (empty($tmp)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $uid = $tmp["uid"];
        $client = \think\Db::name("clients")->alias("a")->leftJoin("currencies b", "a.currency = b.id")->field("a.defaultgateway,b.code")->where("id", $uid)->find();
        $method = $param["refund_method"];
        $type = $param["refund_type"];
        if (!in_array($method, array_keys($refund_method))) {
            return jsonrule(["status" => 400, "msg" => lang("退款方案错误")]);
        }
        if (!in_array($type, array_keys($refund_type))) {
            return jsonrule(["status" => 400, "msg" => lang("退款类型错误")]);
        }
        $items = \think\Db::name("invoice_items")->alias("a")->field("a.invoice_id,a.amount")->leftJoin("invoices b", "a.invoice_id = b.id")->where("a.rel_id", $hid)->whereIn("a.type", ["host", "renew"])->where("b.status", "Paid")->select()->toArray();
        $amount = 0;
        foreach ($items as $v) {
            $amount += $v["amount"];
        }
        if ($method == "day") {
            $refund = ($tmp["nextduedate"] - time()) / 24 * 60 * 60 * $amount / (($tmp["nextduedate"] - $tmp["regdate"]) / 86400);
        } else {
            if ($method == "full") {
                $refund = $amount;
            } else {
                $refund = floatval($param["amount"]);
            }
        }
        if ($type == "addascredit") {
            $data = ["uid" => $uid, "currency" => $client["code"], "create_time" => time(), "pay_time" => time(), "description" => "产品退款", "amount_out" => $refund, "invoice_id" => 0, "refund" => 0];
            \think\Db::name("accounts")->insertGetId($data);
            $account_credit = ["uid" => $uid, "currency" => $client["code"], "create_time" => time(), "pay_time" => time(), "description" => "退款至余额入账", "amount_in" => $refund];
            \think\Db::name("accounts")->insertGetId($account_credit);
            \think\Db::name("clients")->where("id", $uid)->setInc("credit", $refund);
            credit_log(["uid" => $uid, "desc" => "Credit from Refund of", "amount" => $refund]);
        } else {
            if ($type == "only") {
                $data = ["uid" => $uid, "gateway" => $client["defaultgateway"], "currency" => $client["code"], "create_time" => time(), "pay_time" => time(), "description" => "产品退款", "amount_out" => $refund, "trans_id" => "", "invoice_id" => 0, "refund" => 0];
                \think\Db::name("accounts")->insertGetId($data);
            }
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
}

?>