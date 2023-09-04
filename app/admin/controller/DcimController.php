<?php


namespace app\admin\controller;

/**
 * @title 后台对接DCIM管理
 * @description 接口说明
 */
class DcimController extends AdminBaseController
{
    private $is_certifi = ["traffic" => 0, "kvm" => 0, "ikvm" => 0, "bmc" => 0, "reinstall" => 0, "reboot" => 0, "on" => 0, "off" => 0, "novnc" => 0, "rescue" => 0, "crack_pass" => 0, "enable_ip_custom" => 0];
    public function addServer()
    {
        $params = input("post.");
        $validate = new \app\admin\validate\DcimServerValidate();
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $insert = ["name" => $params["name"], "hostname" => $params["hostname"] ?? "", "username" => $params["username"] ?? "", "password" => aesPasswordEncode(html_entity_decode($params["password"], ENT_QUOTES)), "port" => $params["port"] ?? 0, "secure" => $params["secure"] ?? 0, "disabled" => $params["disabled"] ?? 0, "server_type" => "dcim"];
        if (!empty($params["user_prefix"])) {
            $insert["accesshash"] = "user_prefix:" . $params["user_prefix"];
        } else {
            $insert["accesshash"] = "";
        }
        $auth = ["traffic" => "off", "kvm" => "off", "ikvm" => "off", "bmc" => "on", "reinstall" => "off", "reboot" => "off", "on" => "off", "off" => "off", "novnc" => "off", "rescue" => "off", "crack_pass" => "off", "enable_ip_custom" => "off"];
        $is_certifi = $this->is_certifi;
        \think\Db::startTrans();
        try {
            $group = \think\Db::name("server_groups")->insertGetId(["name" => $params["name"], "system_type" => "dcim"]);
            $insert["gid"] = $group;
            $id = \think\Db::name("servers")->insertGetId($insert);
            if (empty($id)) {
                throw new \Exception("error");
            }
            \think\Db::name("dcim_servers")->insert(["serverid" => $id, "auth" => json_encode($auth), "area" => "", "bill_type" => "month", "flow_remind" => "", "is_certifi" => json_encode($is_certifi)]);
            \think\Db::commit();
            active_log(sprintf($this->lang["Dcim_admin_addServer"], $id));
            $result["status"] = 200;
            $result["msg"] = lang("ADD SUCCESS");
            $dcim = new \app\common\logic\Dcim($id);
            $dcim->is_admin = true;
            $dcim->createApi($insert["hostname"]);
        } catch (\Exception $e) {
            \think\Db::rollback();
            $result["status"] = 406;
            $result["msg"] = $e->getMessage();
        }
        return jsonrule($result);
    }
    public function editServer()
    {
        $params = input("post.");
        $id = $params["id"];
        $server_info = \think\Db::name("servers")->alias("a")->field("a.id,a.gid,a.name,a.hostname,a.username,a.password,a.port,a.secure,a.disabled,b.reinstall_times,b.buy_times,b.reinstall_price,b.auth,b.bill_type,b.area,b.is_certifi")->leftJoin("dcim_servers b", "a.id=b.serverid")->where("a.server_type", "dcim")->where("a.id", $id)->find();
        if (empty($server_info)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $validate = new \app\admin\validate\DcimServerValidate();
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $update_server = ["name" => $params["name"], "hostname" => $params["hostname"] ?? ""];
        $dec = "";
        if ($params["name"] != $server_info["name"]) {
            $dec .= "名称由“" . $server_info["name"] . "”改为“" . $params["name"] . "”，";
        }
        if ($params["hostname"] != $server_info["hostname"]) {
            $dec .= "主机名由“" . $server_info["hostname"] . "”改为“" . $params["hostname"] . "”，";
        }
        if (isset($params["username"])) {
            $update_server["username"] = $params["username"] ?? "";
        }
        if ($params["username"] != $server_info["username"]) {
            $dec .= "用户名由“" . $server_info["username"] . "”改为“" . $params["username"] . "”，";
        }
        if (isset($params["password"])) {
            $update_server["password"] = aesPasswordEncode(html_entity_decode($params["password"], ENT_QUOTES));
            $params["password"] = $update_server["password"];
        }
        if ($params["password"] != $server_info["password"]) {
            $dec .= "密码有修改，";
        }
        if (isset($params["port"])) {
            $update_server["port"] = $params["port"] ?? 0;
        }
        if ($params["port"] != $server_info["port"]) {
            $dec .= "端口由“" . $server_info["port"] . "”改为“" . $params["port"] . "”，";
        }
        if (isset($params["secure"])) {
            $update_server["secure"] = $params["secure"] ?? 0;
        }
        if ($params["secure"] != $server_info["secure"]) {
            if ($params["secure"] == 1) {
                $dec .= "使用SSL连接模式“关闭”改为“开启”，";
            } else {
                $dec .= "使用SSL连接模式“开启”改为“关闭”，";
            }
        }
        if (isset($params["disabled"])) {
            $update_server["disabled"] = $params["disabled"] ?? 0;
        }
        if ($params["disabled"] != $server_info["disabled"]) {
            if ($params["disabled"] == 1) {
                $dec .= "由“禁用”改为“启用”，";
            } else {
                $dec .= "由“启用”改为“禁用”，";
            }
        }
        $auth = json_decode($server_info["auth"], true);
        foreach ($auth as $k => $v) {
            if (isset($params[$k]) && $k != "enable_ip_custom") {
                $auth[$k] = $params[$k];
            }
        }
        $close_ip_custom = false;
        if (in_array($params["enable_ip_custom"], ["on", "off"])) {
            if ($auth["enable_ip_custom"] == "on" && $params["enable_ip_custom"] == "off") {
                $close_ip_custom = true;
            }
            $auth["enable_ip_custom"] = $params["enable_ip_custom"];
        }
        $update_dcim_server = [];
        $update_dcim_server["auth"] = json_encode($auth);
        $update_dcim_server["is_certifi"] = json_encode($params["is_certifi"]);
        if (isset($params["reinstall_times"])) {
            $update_dcim_server["reinstall_times"] = $params["reinstall_times"];
        }
        if ($params["reinstall_times"] != $server_info["reinstall_times"]) {
            $dec .= "每周重装次数由“" . $server_info["reinstall_times"] . "”改为“" . $params["reinstall_times"] . "”，";
        }
        if (isset($params["buy_times"])) {
            $update_dcim_server["buy_times"] = $params["buy_times"];
        }
        if ($params["buy_times"] != $server_info["buy_times"]) {
            if ($params["buy_times"] == 0) {
                $dec .= "付费重装“启用”改为“禁用”，";
            } else {
                $dec .= "付费重装“禁用”改为“启用”，";
            }
        }
        if (isset($params["reinstall_price"])) {
            $update_dcim_server["reinstall_price"] = $params["reinstall_price"];
        }
        if ($params["reinstall_price"] != $server_info["reinstall_price"]) {
            $dec .= "重装单次价格由“" . $server_info["reinstall_price"] . "”改为“" . $params["reinstall_price"] . "”，";
        }
        if (isset($params["bill_type"])) {
            $update_dcim_server["bill_type"] = $params["bill_type"];
        }
        if ($params["bill_type"] != $server_info["bill_type"]) {
            $dec .= "流量计费方式由“" . $server_info["bill_type"] . "”改为“" . $params["bill_type"] . "”，";
        }
        if (isset($params["percent"]) && isset($params["tid"])) {
            if (count($params["percent"]) != count($params["tid"])) {
                $result["status"] = 400;
                $result["msg"] = "流量提醒设置错误";
                return jsonrule($result);
            }
            $update_dcim_server["flow_remind"] = [];
            foreach ($params["percent"] as $k => $v) {
                if (is_numeric($v) && (100 < $v || $v <= 0)) {
                    $result["status"] = 400;
                    $result["msg"] = "比例只能是1-100";
                    return jsonrule($result);
                }
                $update_dcim_server["flow_remind"][] = ["percent" => $v, "tid" => $params["tid"][$k]];
            }
            $update_dcim_server["flow_remind"] = json_encode($update_dcim_server["flow_remind"]);
            if ($params["flow_remind"] != $server_info["flow_remind"]) {
                $dec .= "流量提醒设置由“" . $server_info["flow_remind"] . "”改为“" . $params["flow_remind"] . "”，";
            }
        }
        if (isset($params["ip_customid"])) {
            $update_dcim_server["ip_customid"] = (int) $params["ip_customid"];
        }
        if ($close_ip_custom) {
            $change_host = \think\Db::name("host")->field("id,dedicatedip,assignedips")->where("serverid", $id)->select()->toArray();
        }
        if (!empty($params["user_prefix"])) {
            $update_server["accesshash"] = "user_prefix:" . $params["user_prefix"];
        } else {
            $update_server["accesshash"] = "";
        }
        \think\Db::startTrans();
        try {
            \think\Db::name("server_groups")->where("id", $server_info["gid"])->update(["name" => $params["name"]]);
            \think\Db::name("servers")->where("id", $id)->update($update_server);
            \think\Db::name("dcim_servers")->where("serverid", $id)->update($update_dcim_server);
            if (!empty($change_host)) {
                foreach ($change_host as $v) {
                    if (!empty($v["assignedips"])) {
                        $v["assignedips"] = explode(",", $v["assignedips"]);
                        foreach ($v["assignedips"] as $kk => $vv) {
                            if ($vv == $v["dedicatedip"]) {
                                unset($v["assignedips"][$kk]);
                            } else {
                                if (strpos($vv, "(") !== false) {
                                    $vv = explode("(", $vv);
                                    if (strpos($vv[0], "/") === false) {
                                        $v["assignedips"][$kk] = $vv[0];
                                    }
                                }
                            }
                        }
                        $v["assignedips"] = empty($v["assignedips"]) ? "" : implode(",", $v["assignedips"]);
                        \think\Db::name("host")->where("id", $v["id"])->update(["assignedips" => $v["assignedips"]]);
                    }
                }
            }
            \think\Db::commit();
            if (empty($dec)) {
                $dec .= "没有任何修改";
            }
            active_log(sprintf($this->lang["Dcim_admin_editServer"], $id, $dec));
            unset($dec);
            $result["status"] = 200;
            $result["msg"] = lang("UPDATE SUCCESS");
            $dcim = new \app\common\logic\Dcim($id);
            $dcim->is_admin = true;
            $dcim->createApi($update_server["hostname"]);
        } catch (\Exception $e) {
            \think\Db::rollback();
            $result["status"] = 400;
            $result["msg"] = lang("UPDATE FAIL");
        }
        return jsonrule($result);
    }
    public function serverDetail($id)
    {
        $data = \think\Db::name("servers")->alias("a")->field("a.id,a.name,a.hostname,a.username,a.password,a.port,a.secure,a.disabled,a.accesshash,b.reinstall_times,b.buy_times,b.reinstall_price,b.auth,b.area,b.bill_type,b.flow_remind,b.ip_customid,b.is_certifi")->leftJoin("dcim_servers b", "a.id=b.serverid")->where("a.server_type", "dcim")->where("a.id", $id)->find();
        if (empty($data)) {
            return jsonrule(["status" => "error", "msg" => lang("ID_ERROR")]);
        }
        $data["password"] = aesPasswordDecode($data["password"]);
        $data["area"] = json_decode($data["area"], true) ?: [];
        $data["flow_remind"] = json_decode($data["flow_remind"], true) ?: [];
        $data["bill_type"] = $data["bill_type"] ?: "month";
        $auth = json_decode($data["auth"], true);
        unset($data["auth"]);
        $data = array_merge($data, $auth);
        $data["is_certifi"] = json_decode($data["is_certifi"], true) ?: $this->is_certifi;
        if (!isset($data["enable_ip_custom"])) {
            $data["enable_ip_custom"] = "off";
        }
        $data["ip_customid"] = $data["ip_customid"] ?: "";
        $accesshash = $data["accesshash"];
        unset($data["accesshash"]);
        if (!empty($accesshash)) {
            $accesshash = explode(":", trim($accesshash));
            unset($accesshash[0]);
            $data["user_prefix"] = trim(implode("", $accesshash));
        } else {
            $data["user_prefix"] = "";
        }
        $language = configuration("language");
        $config = config("language.list");
        $result["status"] = 200;
        $result["data"] = $data;
        return jsonrule($result);
    }
    public function delServer()
    {
        $id = input("post.id", 0, "intval");
        $server = \think\Db::name("host")->where("serverid", $id)->find();
        $server_group = \think\Db::name("host")->alias("a")->leftJoin("servers b", "a.serverid = b.id")->leftJoin("server_groups c", "c.id = b.gid")->where("c.system_type", "dcim")->where("b.id", $id)->find();
        if (!empty($server) || !empty($server_group)) {
            return jsonrule(["status" => 400, "msg" => lang("SERVER_USING")]);
        }
        $info = \think\Db::name("servers")->where("server_type", "dcim")->where("id", $id)->find();
        if (empty($info)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $product = \think\Db::name("products")->where("server_group", $info["gid"])->find();
        if (!empty($product)) {
            return jsonrule(["status" => 400, "msg" => lang("SERVER_USING")]);
        }
        \think\Db::startTrans();
        try {
            \think\Db::name("servers")->where("id", $id)->where("server_type", "dcim")->delete();
            \think\Db::name("server_groups")->where("id", $info["gid"])->where("system_type", "dcim")->delete();
            \think\Db::name("dcim_servers")->where("serverid", $id)->delete();
            \think\Db::commit();
            active_log(sprintf($this->lang["Dcim_admin_delServer"], $id));
            $result["status"] = 200;
            $result["msg"] = lang("DELETE SUCCESS");
        } catch (\Exception $e) {
            \think\Db::rollback();
            $result["status"] = 400;
            $result["msg"] = lang("DELETE FAIL");
        }
        return jsonrule($result);
    }
    public function serverList()
    {
        $page = input("get.page", 1, "intval");
        $limit = input("get.limit", 10, "intval");
        $orderby = input("get.orderby", "id");
        $sort = input("get.sort", "asc");
        $search = input("get.search", "");
        $page = 0 < $page ? $page : 1;
        $limit = 0 < $limit ? $limit : 10;
        if (!in_array($orderby, ["id", "name", "hostname", "server_num", "api_status"])) {
            $orderby = "id";
        }
        if (!in_array($sort, ["asc", "desc"])) {
            $sort = "asc";
        }
        $count = \think\Db::name("servers")->alias("a")->where("a.name LIKE '%" . $search . "%' OR a.hostname LIKE '%" . $search . "%'")->where("a.server_type", "dcim")->count();
        $data = \think\Db::name("servers")->alias("a")->field("a.id,a.name,a.hostname,count(DISTINCT b.id) server_num,c.api_status,count(DISTINCT d.id) product_num")->leftJoin("host b", "b.serverid=a.id AND (b.domainstatus=\"Active\" OR b.domainstatus=\"Suspended\")")->leftJoin("dcim_servers c", "c.serverid=a.id")->leftJoin("products d", "a.gid=d.server_group")->where("a.name LIKE '%" . $search . "%' OR a.hostname LIKE '%" . $search . "%'")->where("a.server_type", "dcim")->group("a.id")->order($orderby, $sort)->page($page)->limit($limit)->select()->toArray();
        $max_page = ceil($count / $limit);
        foreach ($data as $k => $v) {
            if ($v["server_num"] == 0 && $v["product_num"] == 0) {
                $data[$k]["removable"] = true;
            } else {
                $data[$k]["removable"] = false;
            }
        }
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
    public function refreshServerStatus($id)
    {
        $server_info = \think\Db::name("servers")->field("id,hostname,username,password,secure,port,accesshash")->where("id", $id)->where("server_type", "dcim")->find();
        if (empty($server_info)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $link_status = $dcim->init($server_info)->testLink();
        if (!empty($dcim->curl_error) || !empty($dcim->link_error_msg)) {
            \think\Db::name("dcim_servers")->where("serverid", $id)->update(["api_status" => 0]);
            $result["status"] = 200;
            if (!empty($dcim->curl_error)) {
                $result["msg"] = "连接失败curl错误：" . $dcim->curl_error;
            } else {
                $result["msg"] = $dcim->link_error_msg;
            }
            $result["server_status"] = 0;
        } else {
            $check_api = $dcim->checkApi($server_info["hostname"]);
            if ($check_api["status"] == 200) {
                \think\Db::name("dcim_servers")->where("serverid", $id)->update(["api_status" => 1]);
                $result["status"] = 200;
                $result["server_status"] = 1;
            } else {
                $create_api = $dcim->createApi($server_info["hostname"], false);
                if ($create_api["status"] == 200) {
                    \think\Db::name("dcim_servers")->where("serverid", $id)->update(["api_status" => 1]);
                    $result["status"] = 200;
                    $result["server_status"] = 1;
                } else {
                    \think\Db::name("dcim_servers")->where("serverid", $id)->update(["api_status" => 0]);
                    $result["status"] = 200;
                    $result["server_status"] = 0;
                    $result["msg"] = "财务系统连接DCIM成功,但同步API未成功创建";
                }
            }
        }
        return jsonrule($result);
    }
    public function refreshAllServerStatus()
    {
        $server_info = \think\Db::name("servers")->field("id,hostname,username,password,secure,port")->where("server_type", "dcim")->select()->toArray();
        if (empty($server_info)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $data = [];
        foreach ($server_info as $v) {
            $protocol = $v["secure"] == 1 ? "https://" : "http://";
            $url = $protocol . $v["hostname"];
            if (!empty($v["port"])) {
                $url .= ":" . $v["port"];
            }
            $data[$v["id"]] = ["url" => $url . "/index.php?m=api&a=getHouse", "data" => ["username" => $v["username"], "password" => aesPasswordDecode($v["password"]) ?? ""]];
        }
        $res = batch_curl_post($data, 3);
        $result["data"] = [];
        foreach ($res as $k => $v) {
            $one["id"] = $k;
            if ($v["http_code"] != 200) {
                $one["status"] = 0;
                $one["msg"] = $v["msg"] ?? "";
            } else {
                $one["status"] = 1;
                $one["msg"] = "";
            }
            $result["data"][] = $one;
        }
        $result["status"] = 200;
        return jsonrule($result);
    }
    public function listBuyRecord()
    {
        $page = input("get.page", 1, "intval");
        $limit = input("get.limit", 10, "intval");
        $orderby = input("get.orderby", "id");
        $sort = input("get.sort", "asc");
        $search = input("get.search", "");
        $getUserCtol = new GetUserController();
        $page = 0 < $page ? $page : 1;
        $limit = 0 < $limit ? $limit : 10;
        if (!in_array($orderby, ["id", "capacity", "price", "status", "sale_times"])) {
            $orderby = "id";
        }
        if (!in_array($sort, ["asc", "desc"])) {
            $sort = "asc";
        }
        $count = \think\Db::name("dcim_buy_record")->alias("a")->leftJoin("clients b", "a.uid=b.id")->whereLike("a.name|b.username|b.phonenumber|b.email", "%" . $search . "%");
        if ($getUserCtol->user["id"] != 1 && $getUserCtol->user["is_sale"]) {
            $count->whereIn("b.id", $getUserCtol->str);
        }
        $count = $count->count();
        $data = \think\Db::name("dcim_buy_record")->alias("a")->field("a.id,a.uid,a.name,a.price,a.status,a.create_time,a.pay_time,b.username,c.status as invoice_status,c.payment")->leftJoin("clients b", "a.uid=b.id")->leftJoin("invoices c", "a.invoiceid = c.id")->whereLike("a.name|b.username|b.phonenumber|b.email", "%" . $search . "%")->withAttr("payment", function ($value) {
            $gateways = gateway_list();
            foreach ($gateways as $v) {
                if ($v["name"] == $value) {
                    return $v["title"];
                }
            }
        });
        if ($getUserCtol->user["id"] != 1 && $getUserCtol->user["is_sale"]) {
            $data->whereIn("b.id", $getUserCtol->str);
        }
        $data = $data->order($orderby, $sort)->page($page)->limit($limit)->select()->toArray();
        $max_page = ceil($count / $limit);
        foreach ($data as $k => $v) {
            $data[$k]["invoice_status"] = config("invoice_payment_status")[$v["invoice_status"]];
            if ($v["status"] == 0) {
                $data[$k]["removable"] = true;
            } else {
                $data[$k]["removable"] = false;
            }
        }
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
    public function delRecord()
    {
        $id = input("post.id", 0, "intval");
        $record = \think\Db::name("dcim_buy_record")->where("id", $id)->find();
        if (empty($record)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        if ($record["status"] == 1) {
            return jsonrule(["status" => 400, "msg" => "不能删除"]);
        }
        \think\Db::startTrans();
        try {
            $r = \think\Db::name("dcim_buy_record")->where("id", $id)->where("status", 0)->delete();
            \think\Db::name("orders")->where("invoiceid", $record["invoiceid"])->delete();
            \think\Db::name("invoices")->where("id", $record["invoiceid"])->delete();
            \think\Db::name("invoice_items")->where("invoice_id", $record["invoiceid"])->delete();
            active_log(sprintf($this->lang["Dcim_admin_delRecord"], $id));
            if (empty($r)) {
                throw new \Exception("error");
            }
            \think\Db::commit();
            $result["status"] = 200;
            $result["msg"] = lang("DELETE SUCCESS");
        } catch (\Exception $e) {
            \think\Db::rollback();
            $result["status"] = 406;
            $result["msg"] = lang("DELETE FAIL");
        }
        return jsonrule($result);
    }
    public function listFlowPacket()
    {
        $page = input("get.page", 1, "intval");
        $limit = input("get.limit", 10, "intval");
        $orderby = input("get.orderby", "id");
        $sort = input("get.sort", "asc");
        $search = input("get.search", "");
        $page = 0 < $page ? $page : 1;
        $limit = 0 < $limit ? $limit : 10;
        if (!in_array($orderby, ["id", "capacity", "price", "status", "sale_times"])) {
            $orderby = "id";
        }
        if (!in_array($sort, ["asc", "desc"])) {
            $sort = "asc";
        }
        $count = \think\Db::name("dcim_flow_packet")->whereLike("name", "%" . $search . "%")->count();
        $max_page = ceil($count / $page);
        $data = \think\Db::name("dcim_flow_packet")->field("id,name,capacity,price,status,sale_times,stock,create_time")->whereLike("name", "%" . $search . "%")->order($orderby, $sort)->page($page)->limit($limit)->select()->toArray();
        $currency = \think\Db::name("currencies")->where("default", 1)->find();
        foreach ($data as $k => $v) {
            $data[$k]["capacity"] = $v["capacity"] . "GB";
            $data[$k]["price"] = $currency["prefix"] . $v["price"] . $currency["suffix"];
        }
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
    public function addFlowPacketPage()
    {
        $result["status"] = 200;
        $result["data"]["products"] = getProductList();
        return jsonrule($result);
    }
    public function editFlowPacketPage($id)
    {
        $data = \think\Db::name("dcim_flow_packet")->field("id,name,capacity,price,allow_products,status,create_time,sale_times,stock")->where("id", $id)->find();
        if (empty($data)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $data["allow_products"] = explode(",", $data["allow_products"]) ?? [];
        if (!empty($data["allow_products"])) {
            foreach ($data["allow_products"] as $k => $v) {
                $data["allow_products"][$k] = (int) $v;
            }
        }
        $result["status"] = 200;
        $result["products"] = getProductList();
        $result["flowpacket"] = $data;
        return jsonrule($result);
    }
    public function addFlowPacket()
    {
        $params = input("post.");
        $validate = new \app\admin\validate\DcimFlowPacketValidate();
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        if (!empty($params["allow_products"]) && is_array($params["allow_products"])) {
            $products = \think\Db::name("products")->whereIn("id", $params["allow_products"])->column("id");
            $params["allow_products"] = implode(",", $products);
        } else {
            $params["allow_products"] = "";
        }
        $insert = ["name" => $params["name"], "capacity" => $params["capacity"], "price" => $params["price"], "allow_products" => $params["allow_products"], "status" => $params["status"], "create_time" => time(), "stock" => $params["stock"] ?? 0];
        $id = \think\Db::name("dcim_flow_packet")->insertGetId($insert);
        active_log(sprintf($this->lang["Dcim_admin_addFlowPacket"], $id));
        $result["status"] = 200;
        $result["msg"] = lang("ADD SUCCESS");
        return jsonrule($result);
    }
    public function editFlowPacket()
    {
        $params = input("post.");
        $id = $params["id"];
        $exist = \think\Db::name("dcim_flow_packet")->where("id", $id)->find();
        if (empty($exist)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $validate = new \app\admin\validate\DcimFlowPacketValidate();
        $validate_result = $validate->scene("edit")->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $dec = "";
        $update["update_time"] = time();
        if (!empty($params["name"])) {
            $update["name"] = $params["name"];
            if ($params["name"] != $exist["name"]) {
                $dec .= " 流量包名称" . $exist["name"] . "改为" . $params["name"];
            }
        }
        if (!empty($params["capacity"])) {
            $update["capacity"] = $params["capacity"];
            if ($params["capacity"] != $exist["capacity"]) {
                $dec .= " 流量包容量(G)" . $exist["capacity"] . "改为" . $params["capacity"];
            }
        }
        if (!empty($params["price"])) {
            $update["price"] = $params["price"];
            if ($params["price"] != $exist["price"]) {
                $dec .= " 价格" . $exist["price"] . "改为" . $params["price"];
            }
        }
        if (isset($params["status"])) {
            $update["status"] = $params["status"];
            if ($params["status"] == 1) {
                $dec .= " 启用";
            } else {
                $dec .= " 禁用";
            }
        }
        if (isset($params["stock"])) {
            $update["stock"] = $params["stock"] ?? 0;
            if ($params["stock"] != $exist["stock"]) {
                $dec .= " 库存" . $exist["stock"] . "改为" . $params["stock"];
            }
        }
        if (!empty($params["allow_products"]) && is_array($params["allow_products"])) {
            $products = \think\Db::name("products")->whereIn("id", $params["allow_products"])->column("id");
            $update["allow_products"] = implode(",", $products);
        } else {
            if (isset($params["allow_products"])) {
                $update["allow_products"] = "";
            }
        }
        \think\Db::name("dcim_flow_packet")->where("id", $id)->update($update);
        active_log(sprintf($this->lang["Dcim_admin_editFlowPacket"], $id, $dec));
        unset($dec);
        $result["status"] = 200;
        $result["msg"] = lang("UPDATE SUCCESS");
        return jsonrule($result);
    }
    public function delFlowPacket()
    {
        $id = input("post.id", 0, "intval");
        $r = \think\Db::name("dcim_flow_packet")->where("id", $id)->delete();
        active_log(sprintf($this->lang["Dcim_admin_delFlowPacket"], $id));
        $result["status"] = 200;
        $result["msg"] = lang("DELETE SUCCESS");
        return jsonrule($result);
    }
    public function on()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->on($id);
        return jsonrule($result);
    }
    public function off()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->off($id);
        return jsonrule($result);
    }
    public function reboot()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->reboot($id);
        return jsonrule($result);
    }
    public function bmc()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->bmc($id);
        return jsonrule($result);
    }
    public function kvm()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->kvm($id);
        return jsonrule($result);
    }
    public function ikvm()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->ikvm($id);
        return jsonrule($result);
    }
    public function download()
    {
        $name = input("get.name");
        header("Access-Control-Expose-Headers: Content-disposition");
        $file = UPLOAD_PATH . "common/default/" . $name . ".jnlp";
        if (file_exists($file)) {
            $length = filesize($file);
            $showname = $name . ".jnlp";
            $expire = 1800;
            header("Pragma: public");
            header("Cache-control: max-age=" . $expire);
            header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expire) . "GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . "GMT");
            header("Content-Disposition: attachment; filename=" . $showname);
            header("Content-Length: " . $length);
            header("Content-type: text/x-java-source");
            header("Content-Encoding: none");
            header("Content-Transfer-Encoding: binary");
            readfile($file);
            sleep(2);
            unlink($file);
        } else {
            return \think\Response::create()->code(404);
        }
    }
    public function reinstall()
    {
        $params = input("post.");
        $id = $params["id"];
        $validate = new \app\common\validate\DcimValidate();
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $data = ["rootpass" => $params["password"], "action" => $params["action"], "mos" => $params["os"], "mcon" => $params["mcon"], "port" => $params["port"], "disk" => $params["disk"] ?? 0, "check_disk_size" => $params["check_disk_size"] ?? 0, "part_type" => $params["part_type"] ?? 0];
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->reinstall($id, $data);
        return jsonrule($result);
    }
    public function getReinstallStatus()
    {
        $id = input("get.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->reinstallStatus($id);
        return jsonrule($result);
    }
    public function rescue()
    {
        $id = input("post.id", 0, "intval");
        $system = input("post.system", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->rescue($id, $system);
        return jsonrule($result);
    }
    public function crackPass()
    {
        $params = input("post.");
        $id = $params["id"];
        $data = ["crack_password" => $params["password"], "other_user" => intval($params["other_user"]), "user" => $params["user"] ?? "", "action" => $params["action"] ?? ""];
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->crackPass($id, $data);
        return jsonrule($result);
    }
    public function getTrafficUsage()
    {
        $id = input("get.id");
        $host = \think\Db::name("host")->alias("a")->field("a.regdate")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.id", $id)->where("b.type", "dcim")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return jsonrule($result);
        }
        $end = input("get.end");
        $start = input("get.start");
        $end = strtotime($end) ? date("Y-m-d", strtotime($end)) : date("Y-m-d");
        $start = strtotime($start) ? date("Y-m-d", strtotime($start)) : date("Y-m-d", strtotime("-30 days"));
        if (str_replace("-", "", $start) < str_replace("-", "", date("Y-m-d", $host["regdate"]))) {
            $start = date("Y-m-d", $host["regdate"]);
        }
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->getTrafficUsage($id, $start, $end);
        return jsonrule($result);
    }
    public function cancelReinstall()
    {
        $id = input("post.id");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->cancelReinstall($id);
        return jsonrule($result);
    }
    public function unsuspendReload()
    {
        $id = input("post.id");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->unsuspendReload($id, input("post.disk_part"));
        return jsonrule($result);
    }
    public function traffic()
    {
        $id = input("post.id");
        $params = input("post.");
        if (empty($params["end_time"])) {
            $params["end_time"] = time() . "000";
        }
        if (empty($params["start_time"])) {
            $params["start_time"] = strtotime("-7 days") . "000";
        }
        if ($params["end_time"] < $params["start_time"]) {
            $result["status"] = 400;
            $result["msg"] = "开始时间不能晚于结束时间";
        }
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->traffic($id, $params);
        return jsonrule($result);
    }
    public function novnc()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->novnc($id);
        return jsonrule($result);
    }
    public function novncPage()
    {
        $password = input("get.password");
        $url = input("get.url");
        $url = base64_decode(urldecode($url));
        $host_token = input("get.host_token");
        $type = input("get.type");
        $id = input("get.id", 0, "intval");
        $this->assign("url", $url);
        $this->assign("password", $password);
        $this->assign("host_token", !empty($host_token) ? aesPasswordDecode($host_token) : "");
        $this->assign("id", $id);
        if (!empty($host_token)) {
            $this->assign("paste_button", "<div id=\"pastePassword\">粘贴密码</div>");
        } else {
            $this->assign("paste_button", "");
        }
        if ($type == "dcim") {
            $this->assign("restart_vnc", "<div id=\"restart_vnc\">强制刷新vnc</div>");
        } else {
            $this->assign("restart_vnc", "");
        }
        return $this->fetch("./vendor/dcim/novnc.html");
    }
    public function detail()
    {
        $id = input("post.id");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $detail = $dcim->detail($id);
        if (!empty($detail["server"])) {
            $result["status"] = 200;
            $result["data"]["switch"] = [];
            foreach ($detail["switch"] as $v) {
                $result["data"]["switch"][] = ["switch_id" => $v["id"], "name" => $v["switch_num_name"]];
            }
            $result["data"]["password"] = $detail["server"]["ospassword"];
            $result["data"]["username"] = $detail["server"]["osusername"];
            $result["data"]["os_ostype"] = $detail["server"]["os_ostype"];
            $result["data"]["os_osname"] = $detail["server"]["os_osname"];
            $result["data"]["disk_num"] = $detail["server"]["disk_num"];
        } else {
            $result["status"] = 400;
            $result["msg"] = "获取失败";
        }
        return json($result);
    }
    public function getSalesServer()
    {
        $id = input("get.id");
        $page = input("get.page", 1, "intval");
        $limit = input("get.limit", 100, "intval");
        $group = input("get.group", 0, "intval");
        $status = input("get.status", 0);
        $search = input("get.search", "");
        $page = 0 < $page ? $page : 1;
        $limit = 0 < $limit ? $limit : 100;
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $dcim->setUrlByHost($id);
        $result = $dcim->sales($page, $limit, $group, $status, $search);
        return json($result);
    }
    public function assignServer()
    {
        $id = input("post.id");
        $dcimid = input("post.dcimid");
        $host = \think\Db::name("host")->alias("a")->field("a.id,a.dcimid,b.api_type,b.zjmf_api_id")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.id", $id)->where("b.type", "dcim")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        if (!empty($host["dcimid"])) {
            $result["status"] = 400;
            $result["msg"] = "已有服务器ID不用分配";
            return json($result);
        }
        if ($host["api_type"] == "zjmf_api") {
            $result["status"] = 400;
            $result["msg"] = "代理产品不能使用分配";
            return json($result);
        }
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->assignServer($id, $dcimid);
        return json($result);
    }
    public function delete()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->free($id);
        return json($result);
    }
    public function refreshPowerStatus()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = true;
        $result = $dcim->refreshPowerStatus($id);
        return json($result);
    }
}

?>