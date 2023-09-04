<?php


namespace app\admin\controller;

/**
 * @title 后台服务器配置
 * @description 接口说明
 */
class ConfigServersController extends AdminBaseController
{
    protected $imagesave = NULL;
    private $imageaddress = NULL;
    const MODE = ["1" => ["name" => "平均分配", "value" => 1, "desc" => "产品优先分配给产品数量最少的接口"], "2" => ["name" => "逐个分配", "value" => 2, "desc" => "按最初创建的接口开始分配，满额后切换下一接口"]];
    public function initialize()
    {
        parent::initialize();
        $this->imagesave = config("servers");
        $this->imageaddress = config("servers");
    }
    public function serverList()
    {
        $data = $this->request->param();
        $servers = \think\Db::name("servers")->where("server_type", "normal");
        if ($data["gid"]) {
            $servers->where("gid", $data["gid"]);
        }
        if ($data["search"]) {
            $servers->where("name LIKE '%" . $data["search"] . "%' OR hostname LIKE '%" . $data["search"] . "%'");
        }
        $count = $servers->count();
        $servers = $servers->order("id", "desc")->page(max(1, $data["page"]), min(50, max(1, $data["limit"])))->select()->toArray();
        $provision = (new \app\common\logic\Provision())->getModules();
        if (!empty($provision)) {
            $provision = array_column($provision, "name", "value");
        }
        $server_groups = \think\Db::name("server_groups")->where("system_type", "normal")->select()->toArray();
        if (!empty($server_groups)) {
            $server_groups = array_column($server_groups, "name", "id");
        }
        $servers = array_map(function ($v) use($server_groups, $provision) {
            $v["gname"] = $server_groups[$v["gid"]] ?? "";
            $v["type"] = $provision[$v["type"]] ?? "";
            $use_num = \think\Db::name("host")->where("serverid", $v["id"])->count();
            $v["open_num"] = $use_num . "/" . $v["max_accounts"];
            return $v;
        }, $servers);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $servers, "count" => $count]);
    }
    public function addServers()
    {
        $provision = new \app\common\logic\Provision();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => ["modules" => $provision->getModules(), "groups" => []]]);
    }
    public function getModulesGroup()
    {
        $params = $this->request->only(["modules"]);
        $gid = \think\Db::name("servers")->where("server_type", "normal")->where("type", $params["modules"])->column("gid");
        $groups = \think\Db::name("server_groups")->where("system_type", "normal")->select()->toArray();
        $modules = array_filter($groups, function ($v) use($gid) {
            if (in_array($v["id"], $gid)) {
                return true;
            }
            $is_null = \think\Db::name("servers")->where("gid", $v["id"])->find();
            if (empty($is_null)) {
                return true;
            }
            return false;
        });
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => ["groups" => $modules]]);
    }
    public function addServersPost()
    {
        if ($this->request->isPost()) {
            $params = $this->request->only(["name", "ip_address", "assigned_ips", "hostname", "noc", "status_address", "username", "password", "accesshash", "secure", "port", "disabled", "type", "max_accounts", "gid"]);
            $module = $params["type"];
            if (file_exists(WEB_ROOT . "plugins/servers/" . $module . "/" . $module . ".php")) {
                require_once WEB_ROOT . "plugins/servers/" . $module . "/" . $module . ".php";
            }
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "模块未授权";
                    return jsonrule($result);
                }
            }
            if (\think\Db::name("servers")->where("server_type", "normal")->where("name", trim($params["name"]))->find()) {
                return jsonrule(["status" => 400, "msg" => lang("该接口已存在")]);
            }
            if ($params["gid"]) {
                $modules_type = \think\Db::name("servers")->where("server_type", "normal")->where("gid", $params["gid"])->column("type");
                $modules_type[] = $params["type"];
                if (count(array_unique($modules_type)) != 1) {
                    return jsonrule(["status" => 400, "msg" => lang("同一个接口分组下的接口，服务器模块类型应保持一致！")]);
                }
            }
            $validate = new \app\admin\validate\ConfigServersValidate();
            if (!$validate->scene("create_servers")->check($params)) {
                return jsonrule(["status" => 400, "msg" => $validate->getError()]);
            }
            if ($params["gid"]) {
                $group = \think\Db::name("server_groups")->where("id", $params["gid"])->where("system_type", "normal")->find();
                if (!$group) {
                    return jsonrule(["status" => 400, "msg" => lang("服务器组不存在")]);
                }
            }
            $params["password"] = aesPasswordEncode($params["password"]);
            $data = array_map("trim", $params);
            $serverid = \think\Db::name("servers")->insertGetId($data);
            hook("server_add", ["serverid" => $serverid]);
            if ($serverid) {
                active_log(sprintf($this->lang["Configserver_admin_addServersPost"], $serverid));
                return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
            }
            return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function editServers()
    {
        $provision = new \app\common\logic\Provision();
        $params = $this->request->param();
        $serverid = intval($params["id"]);
        $server = \think\Db::name("servers")->where("id", $serverid)->find();
        $server["password"] = aesPasswordDecode($server["password"]);
        $server["noc"] = isset($server["noc"][0]) ? base64EncodeImage(config("servers") . $server["noc"]) : "";
        $server = array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $server);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "groups" => [], "modules" => $provision->getModules(), "server" => $server]);
    }
    public function editServersPost()
    {
        if ($this->request->isPost()) {
            $dec = "";
            $params = $this->request->only(["id", "name", "ip_address", "assigned_ips", "hostname", "gid", "noc", "status_address", "username", "password", "accesshash", "secure", "port", "disabled", "type", "max_accounts"]);
            $module = $params["type"];
            if (file_exists(WEB_ROOT . "plugins/servers/" . $module . "/" . $module . ".php")) {
                require_once WEB_ROOT . "plugins/servers/" . $module . "/" . $module . ".php";
            }
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "模块未授权";
                    return jsonrule($result);
                }
            }
            $serverid = intval($params["id"]);
            if (!$serverid) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            if (\think\Db::name("servers")->where("name", trim($params["name"]))->where("server_type", "normal")->where("id", "<>", $serverid)->find()) {
                return jsonrule(["status" => 400, "msg" => lang("该接口已存在")]);
            }
            if ($params["gid"]) {
                $modules_type = \think\Db::name("servers")->where("server_type", "normal")->where("gid", $params["gid"])->where("id", "<>", $serverid)->column("type");
                $modules_type[] = $params["type"];
                if (count(array_unique($modules_type)) != 1) {
                    return jsonrule(["status" => 400, "msg" => lang("同一个接口分组下的接口，服务器模块类型应保持一致！")]);
                }
            }
            $validate = new \app\admin\validate\ConfigServersValidate();
            if (!$validate->scene("create_servers")->check($params)) {
                return jsonrule(["status" => 400, "msg" => $validate->getError()]);
            }
            if ($params["gid"]) {
                $group = \think\Db::name("server_groups")->where("id", $params["gid"])->where("system_type", "normal")->find();
                if (!$group) {
                    return jsonrule(["status" => 400, "msg" => lang("服务器组不存在")]);
                }
            }
            if (isset($params["noc"][0]) && is_string($params["noc"])) {
                $upload = new \app\common\logic\Upload();
                $avatar = $upload->moveTo($params["noc"], config("servers"));
                if (isset($avatar["error"])) {
                    return jsonrule(["status" => 400, "msg" => $avatar["error"]]);
                }
            }
            $data = array_map("trim", $params);
            $data["password"] = aesPasswordEncode($data["password"]);
            hook("server_edit", ["serverid" => $serverid]);
            $noc = \think\Db::name("servers")->where("id", $serverid)->value("noc");
            $nocall = \think\Db::name("servers")->where("id", $serverid)->find();
            if (!empty($data["name"]) && $data["name"] != $nocall["name"]) {
                $dec .= "名称由“" . $nocall["name"] . "”改为“" . $data["name"] . "”，";
            }
            if (!empty($data["ip_address"]) && $data["ip_address"] != $nocall["ip_address"]) {
                $dec .= " ip地址由“" . $nocall["ip_address"] . "”改为“" . $data["ip_address"] . "”，";
            }
            if (!empty($data["assigned_ips"]) && $data["assigned_ips"] != $nocall["assigned_ips"]) {
                $dec .= " 其他ip地址由“" . $nocall["assigned_ips"] . "改为" . $data["assigned_ips"] . "”，";
            }
            if (!empty($data["hostname"]) && $data["hostname"] != $nocall["hostname"]) {
                $dec .= " 主机名由“" . $nocall["hostname"] . "”改为“" . $data["hostname"] . "”，";
            }
            if (!empty($data["gid"]) && $data["gid"] != $nocall["gid"]) {
                $result = \think\Db::name("server_groups")->field("name")->where("id", $data["gid"])->find();
                $result1 = \think\Db::name("server_groups")->field("name")->where("id", $nocall["gid"])->find();
                $dec .= " 服务器组由“" . $result1["name"] . "”改为“" . $result["name"] . "”，";
            }
            if (!empty($data["noc"]) && $data["noc"] != $nocall["noc"]) {
                $dec .= "上传由“" . $nocall["noc"] . "”改为“" . $nocall["noc"] . "”，";
            }
            if (!empty($data["status_address"]) && $data["status_address"] != $nocall["status_address"]) {
                $dec .= "服务器状态地址由“" . $nocall["status_address"] . "”改为“" . $data["status_address"] . "”，";
            }
            if (!empty($data["username"]) && $data["username"] != $nocall["username"]) {
                $dec .= "用户名由“" . $nocall["username"] . "”改为“" . $data["username"] . "”，";
            }
            if (!empty($data["password"]) && $data["password"] != $nocall["password"]) {
                $dec .= "密码有修改";
            }
            if (!empty($data["accesshash"]) && $data["accesshash"] != $nocall["accesshash"]) {
                $dec .= "访问散列值由“" . $nocall["accesshash"] . "”改为“" . $data["accesshash"] . "”，";
            }
            if ($data["secure"] != $nocall["secure"]) {
                if ($data["secure"] == 1) {
                    $dec .= "使用SSL连接模式“关闭”改为“开启”，";
                } else {
                    $dec .= "使用SSL连接模式“开启”改为“关闭”，";
                }
            }
            if (!empty($data["port"]) && $data["port"] != $nocall["port"]) {
                $dec .= "端口由“" . $nocall["port"] . "”改为“" . $data["port"] . "”";
            }
            if ($data["disabled"] != $nocall["disabled"]) {
                if ($data["disabled"] == 1) {
                    $dec .= "由“禁用”改为“启用”，";
                } else {
                    $dec .= "由“启用”改为“禁用”，";
                }
            }
            $edit = \think\Db::name("servers")->where("id", $serverid)->where("server_type", "normal")->update($data);
            if ($edit) {
                unlink($this->imageaddress . $noc);
                if (empty($dec)) {
                    $dec .= "没有任何修改";
                }
                active_log(sprintf($this->lang["Configserver_admin_editServersPost"], $serverid, $dec));
                unset($dec);
            }
            return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function deleteServers()
    {
        $data = $this->request->param();
        $id = intval($data["id"]);
        $server = \think\Db::name("host")->where("serverid", $id)->find();
        if (!empty($server)) {
            return jsonrule(["status" => 400, "msg" => lang("此接口已被使用，不能删除")]);
        }
        $serverdelete = \think\Db::name("servers")->where("id", $id)->where("server_type", "normal")->find();
        unlink($this->imageaddress . $serverdelete["noc"]);
        hook("server_delete", ["serverid" => $id]);
        $result = \think\Db::name("servers")->where("id", $id)->where("server_type", "normal")->delete();
        if ($result) {
            active_log(sprintf($this->lang["Configserver_admin_delete"], $id));
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        }
        return jsonrule(["status" => 200, "msg" => lang("DELETE FAIL")]);
    }
    public function groupsList()
    {
        try {
            $data = $this->request->param();
            $groups = \think\Db::name("server_groups")->where("system_type", "normal");
            $count = $groups->count();
            $groups = $groups->page(max(1, $data["page"]), min(50, max(1, $data["limit"])))->order("id", "desc")->select()->toArray();
            foreach ($groups as $k => $v) {
                $servers_model = \think\Db::name("servers")->where("gid", $v["id"])->select()->toArray();
                $num = 0;
                $servers_ids = [];
                if (!empty($servers_model)) {
                    $servers_ids = array_column($servers_model, "id");
                    $num = array_sum(array_column($servers_model, "max_accounts"));
                }
                $use_num = \think\Db::name("host")->whereIn("serverid", $servers_ids)->where("serverid", "<>", 0)->count();
                $groups[$k]["open_num"] = $use_num . "/" . $num;
                $groups[$k]["mode"] = self::MODE[$groups[$k]["mode"]]["name"];
            }
            return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $groups, "count" => $count]);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function createGroups()
    {
        $servers = \think\Db::name("servers")->where("gid", 0)->select()->toArray();
        $modules = (new \app\common\logic\Provision())->getModules();
        if ($modules) {
            $modules = array_column($modules, "name", "value");
        }
        foreach ($servers as &$val) {
            $val["name"] .= "(" . ($modules[$val["type"]] ?? $val["type"]) . ")";
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => ["servers" => $servers, "select_servers" => [], "mode" => array_values(self::MODE)]]);
    }
    public function createGroupsPost()
    {
        if ($this->request->isPost()) {
            try {
                return \think\Db::transaction(function () {
                    $data = $this->request->param();
                    $data = array_map(function ($v) {
                        return is_array($v) ? $v : trim($v);
                    }, $data);
                    $datafilter["name"] = $data["group_name"];
                    $datafilter["mode"] = $data["mode"];
                    if (\think\Db::name("server_groups")->where("system_type", "normal")->where("name", $data["group_name"])->find()) {
                        return jsonrule(["status" => 400, "msg" => lang("该接口组已存在!")]);
                    }
                    $sg = \think\Db::name("server_groups")->insertGetId($datafilter);
                    if ($data["sid"]) {
                        $sid = is_string($data["sid"]) ? explode(",", $data["sid"]) : $data["sid"];
                        $servers_type = \think\Db::name("servers")->whereIn("id", $sid)->column("type");
                        if (count(array_unique($servers_type)) != 1) {
                            throw new \think\Exception(lang("同一个接口分组下的接口，服务器模块类型应保持一致！"));
                        }
                        $sid && \think\Db::name("servers")->whereIn("id", $sid)->update(["gid" => $sg]);
                    }
                    active_log(sprintf($this->lang["Configserver_admin_createGroupsPost"], $sg));
                    return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
                });
            } catch (\Throwable $e) {
                return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
            }
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function editServerGroups()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $servergroup = \think\Db::name("server_groups")->where("id", $id)->find();
        $servergroup = array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $servergroup);
        $servers = \think\Db::name("servers")->whereIn("gid", [0, $id])->select()->toArray();
        $modules = (new \app\common\logic\Provision())->getModules();
        if ($modules) {
            $modules = array_column($modules, "name", "value");
        }
        foreach ($servers as &$val) {
            $val["name"] .= "(" . ($modules[$val["type"]] ?? $val["type"]) . ")";
        }
        $select_servers = array_filter($servers, function ($v) {
            if ($v["gid"]) {
                return true;
            }
            return false;
        });
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => ["server_group" => $servergroup, "servers" => $servers, "select_servers" => $select_servers ? array_column($select_servers, "id") : [], "mode" => array_values(self::MODE)]]);
    }
    public function editServerGroupsPost()
    {
        if ($this->request->isPost()) {
            try {
                return \think\Db::transaction(function () {
                    $data = $this->request->param();
                    $id = intval($data["id"]);
                    if (!$id) {
                        return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
                    }
                    $data = array_map(function ($v) {
                        return is_array($v) ? $v : trim($v);
                    }, $data);
                    if (\think\Db::name("server_groups")->where("system_type", "normal")->where("name", $data["group_name"])->where("id", "<>", $id)->find()) {
                        return jsonrule(["status" => 400, "msg" => lang("该接口组已存在!")]);
                    }
                    $result1 = \think\Db::name("server_groups")->where("id", $id)->find();
                    $dec = "";
                    $datafilter["name"] = $data["group_name"];
                    if (!empty($data["group_name"]) && $result1["group_name"] != $data["group_name"]) {
                        $dec .= "服务器组名由“" . $result1["group_name"] . "”改为“" . $data["group_name"] . "”，";
                    }
                    $datafilter["mode"] = $data["mode"];
                    if (!empty($data["mode"]) && $result1["mode"] != $data["mode"]) {
                        $dec .= "分配方式由“" . self::MODE[$result1["mode"]]["name"] . "”改为“" . self::MODE[$data["mode"]]["name"] . "”，";
                    }
                    if (isset($data["sid"])) {
                        \think\Db::name("servers")->whereIn("gid", $id)->update(["gid" => 0]);
                        $sid = is_string($data["sid"]) ? explode(",", $data["sid"]) : $data["sid"];
                        $servers_type = \think\Db::name("servers")->whereIn("id", $sid)->column("type");
                        if ($sid && count(array_unique($servers_type)) != 1) {
                            throw new \think\Exception(lang("同一个接口分组下的接口，服务器模块类型应保持一致！"));
                        }
                        $sid && \think\Db::name("servers")->whereIn("id", $sid)->update(["gid" => $id]);
                    }
                    \think\Db::name("server_groups")->where("id", $id)->update($datafilter);
                    if (empty($dec)) {
                        $dec .= "没有任何修改";
                    }
                    active_log(sprintf($this->lang["Configserver_admin_editServerGroupsPost"], $id, $dec));
                    unset($dec);
                    return jsonrule(["status" => 200, "msg" => lang("EDIT SUCCESS")]);
                });
            } catch (\Throwable $e) {
                return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
            }
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function deleteServerGroups()
    {
        $params = $this->request->param();
        $gid = intval($params["id"]);
        $servers = \think\Db::name("servers")->where("gid", $gid)->select();
        if (!empty($servers[0])) {
            return jsonrule(["status" => 400, "msg" => lang("此接口分组中已有接口，不能删除")]);
        }
        $result = \think\Db::name("server_groups")->where("system_type", "normal")->where("id", $gid)->delete();
        if ($result) {
            active_log(sprintf($this->lang["Configserver_admin_deleteServerGroups"], $gid));
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
    }
    public function testLink($id)
    {
        session_write_close();
        $data = \think\Db::name("servers")->alias("a")->field("a.*,b.name group_name,b.type module_type,b.system_type system_module_type,a.type server_module_type,a.server_type servers_module_type")->leftJoin("server_groups b", "a.gid=b.id")->where("a.id", $id)->find();
        if (empty($data)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        if ($data["system_module_type"] == "dcim") {
            unset($data["server_module_type"]);
            unset($data["servers_module_type"]);
            $dcim = new \app\common\logic\Dcim();
            $dcim->is_admin = true;
            $link_status = $dcim->init($data)->testLink();
            if (!empty($dcim->curl_error) || !empty($dcim->link_error_msg)) {
                \think\Db::name("dcim_servers")->where("serverid", $id)->update(["api_status" => 0]);
                $result["status"] = 200;
                if (!empty($dcim->curl_error)) {
                    $result["data"]["msg"] = "连接失败curl错误：" . $dcim->curl_error;
                } else {
                    $result["data"]["msg"] = $dcim->link_error_msg;
                }
                $result["data"]["server_status"] = 0;
            } else {
                \think\Db::name("dcim_servers")->where("serverid", $id)->update(["api_status" => 1]);
                $result["status"] = 200;
                $result["data"]["server_status"] = 1;
            }
        } else {
            if ($data["system_module_type"] == "dcimcloud") {
                unset($data["server_module_type"]);
                unset($data["servers_module_type"]);
                $dcimcloud = new \app\common\logic\DcimCloud($id);
                $dcimcloud->is_admin = true;
                $link_status = $dcimcloud->login(true);
                if (!empty($dcimcloud->curl_error) || !empty($dcimcloud->link_error_msg)) {
                    \think\Db::name("dcim_servers")->where("serverid", $id)->update(["api_status" => 0]);
                    $result["status"] = 200;
                    if (!empty($dcimcloud->curl_error)) {
                        $result["data"]["msg"] = "连接失败curl错误：" . $dcimcloud->curl_error;
                    } else {
                        $result["data"]["msg"] = $dcimcloud->link_error_msg;
                    }
                    $result["data"]["server_status"] = 0;
                } else {
                    \think\Db::name("dcim_servers")->where("serverid", $id)->update(["api_status" => 1]);
                    $result["status"] = 200;
                    $result["data"]["server_status"] = 1;
                }
            } else {
                if ($data["system_module_type"] == "normal" || $data["servers_module_type"] == "normal") {
                    if (!empty($data["server_module_type"])) {
                        $data["server_ip"] = $data["ip_address"];
                        $data["server_host"] = $data["hostname"];
                        $data["password"] = aesPasswordDecode($data["password"]);
                        $data["server_password"] = $data["password"];
                        $data["server_username"] = $data["username"];
                        $module = $data["server_module_type"];
                        unset($data["module_type"]);
                        unset($data["system_module_type"]);
                        unset($data["server_module_type"]);
                        unset($data["servers_module_type"]);
                        if ($data["secure"] == 1) {
                            $data["server_http_prefix"] = "https";
                        } else {
                            $data["server_http_prefix"] = "http";
                        }
                        $provision = new \app\common\logic\Provision();
                        $result = $provision->testLink($module, $data);
                        if ($result["status"] == 200) {
                            if ($result["data"]["server_status"] == 1) {
                                \think\Db::name("servers")->where("id", $id)->update(["link_status" => 1]);
                            } else {
                                \think\Db::name("servers")->where("id", $id)->update(["link_status" => 0]);
                            }
                        }
                    } else {
                        $result["status"] = 200;
                        $result["data"]["server_status"] = 0;
                        $result["data"]["msg"] = "接口没有模块";
                    }
                } else {
                    return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
            }
        }
        return jsonrule($result);
    }
}

?>