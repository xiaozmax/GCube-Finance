<?php


namespace app\admin\controller;

/**
 * @title 系统相关
 * @description 接口描述
 */
class SystemController extends AdminBaseController
{
    private $auth_url = "https://license.soft13.idcsmart.com";
    public function initialize()
    {
        parent::initialize();
        $this->auth_url = config("auth_url");
    }
    public function getcommoninfo()
    {
        $data["license_type"] = intval(is_profession());
        return jsonrule(["status" => 200, "data" => $data]);
    }
    public function getUpdateContent()
    {
        $version = getZjmfVersion();
        $upgrade_system_logic = new \app\common\logic\UpgradeSystem();
        $last_version = $upgrade_system_logic->getHistoryVersion();
        $str = "";
        if (version_compare($last_version["last"], $version, ">=")) {
            $arr = $upgrade_system_logic->diffVersion($last_version["last"], $version);
            $arr = array_reverse($arr);
            array_shift($arr);
            $str = file_get_contents($this->auth_url . "/upgrade/" . $last_version["last"] . ".php");
            if ($arr) {
                $str .= "<h1>历史更新</h1>";
                foreach ($arr as $v) {
                    $str .= file_get_contents($this->auth_url . "/upgrade/" . $v . ".php");
                }
            }
        }
        return jsonrule(["status" => 200, "data" => mb_convert_encoding(iconv("utf-8", "gbk//IGNORE", $str), "utf-8", "GBK")]);
    }
    public function getInfo()
    {
        $mysql_version = (array) \think\Db::query("select VERSION()");
        $mysql_version = $mysql_version[0]["VERSION()"] ? str_replace("-log", "", $mysql_version[0]["VERSION()"]) : "获取数据库版本失败";
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            $auth_status = "";
            $auth_suspend_reason = "";
            $auth_app = [];
            $auth_due_time = "";
            $service_due_time = "";
        } else {
            $_strcode = _strcode($zjmf_authorize, "DECODE", "zjmf_key_strcode");
            $_strcode = explode("|zjmf|", $_strcode);
            $authkey = "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDg6DKmQVwkQCzKcFYb0BBW7N2f\nI7DqL4MaiT6vibgEzH3EUFuBCRg3cXqCplJlk13PPbKMWMYsrc5cz7+k08kgTpD4\ntevlKOMNhYeXNk5ftZ0b6MAR0u5tiyEiATAjRwTpVmhOHOOh32MMBkf+NNWrZA/n\nzcLRV8GU7+LcJ8AH/QIDAQAB\n-----END PUBLIC KEY-----";
            $pu_key = openssl_pkey_get_public($authkey);
            foreach ($_strcode as $v) {
                openssl_public_decrypt(base64_decode($v), $de, $pu_key);
                $de_str .= $de;
            }
            $auth = json_decode($de_str, true);
            $auth_status = $auth["status"];
            $auth_suspend_reason = $auth["suspend_reason"];
            $auth_app = $auth["app"];
            $service_due_time = !empty($auth["due_time"]) ? $auth["due_time"] : date("Y-m-d H:i:s", strtotime($auth["create_time"]) + 31536000);
            $auth_due_time = !empty($auth["auth_due_time"]) ? $auth["auth_due_time"] : "2039-12-31 23:59:59";
        }
        $data = ["server_ip" => configuration("authsystemip") ? de_systemip(configuration("authsystemip")) : gethostbyname($_SERVER["SERVER_NAME"]), "server_name" => $_SERVER["SERVER_NAME"], "server_port" => $_SERVER["SERVER_PORT"], "server_version" => php_uname("s") . php_uname("r"), "server_system" => php_uname(), "php_version" => PHP_VERSION, "include_path" => DEFAULT_INCLUDE_PATH, "php_sapi_name" => php_sapi_name(), "now_time" => date("Y-m-d H:i:s"), "upload_max_filesize" => get_cfg_var("upload_max_filesize"), "max_execution_time" => get_cfg_var("max_execution_time") . "秒 ", "memory_limit" => get_cfg_var("memory_limit") ? get_cfg_var("memory_limit") : "无", "processor_identifier" => ini_get("memory_limit"), "system_root" => CMF_ROOT, "http_accept_language" => $_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "", "system_token" => \think\Db::name("configuration")->where("setting", "system_token")->value("value") ?? "", "install_version" => getZjmfVersion(), "mysql_version" => $mysql_version, "system_version_type" => configuration("system_version_type") ?? "stable", "zjmf_system_version_type_last" => configuration("zjmf_system_version_type_last") ?? "stable", "system_license" => configuration("system_license") ?? "", "auth_status" => $auth_status, "auth_suspend_reason" => $auth_suspend_reason, "auth_app" => $auth_app, "auth_due_time" => $auth_due_time, "service_due_time" => $service_due_time];
        return jsonrule(["status" => 200, "data" => $data]);
    }
    public function getLastVersion()
    {
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            $getEdition = "error";
        } else {
            $_strcode = _strcode($zjmf_authorize, "DECODE", "zjmf_key_strcode");
            $_strcode = explode("|zjmf|", $_strcode);
            $authkey = "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDg6DKmQVwkQCzKcFYb0BBW7N2f\nI7DqL4MaiT6vibgEzH3EUFuBCRg3cXqCplJlk13PPbKMWMYsrc5cz7+k08kgTpD4\ntevlKOMNhYeXNk5ftZ0b6MAR0u5tiyEiATAjRwTpVmhOHOOh32MMBkf+NNWrZA/n\nzcLRV8GU7+LcJ8AH/QIDAQAB\n-----END PUBLIC KEY-----";
            $pu_key = openssl_pkey_get_public($authkey);
            foreach ($_strcode as $v) {
                openssl_public_decrypt(base64_decode($v), $de, $pu_key);
                $de_str .= $de;
            }
            $auth = json_decode($de_str, true);
            $getEdition = intval($auth["edition"]);
        }
        $upgrade_system_logic = new \app\common\logic\UpgradeSystem();
        $last_version = $upgrade_system_logic->getLastVersion();
        if ($last_version["status"] && $last_version["status"] == 400) {
            $last_version = "未检测到最新版本";
            $last_version_check = "no_response";
        }
        $data = ["last_version" => $last_version, "last_version_check" => $last_version_check ?: "", "license_type" => $getEdition];
        return jsonrule(["status" => 200, "data" => $data]);
    }
    public function getPhpInfo()
    {
        ob_start();
        phpinfo();
        $info = ob_get_contents();
        ob_end_clean();
        $info = preg_replace("%^.*<body>(.*)</body>.*\$%ms", "\$1", $info);
        ob_start();
        echo "<style type=\"text/css\">\n.e {background-color: #EFF2F9; font-weight: bold; color: #000000;}\n.v {background-color: #efefef; color: #000000;}\n.vr {background-color: #efefef; text-align: right; color: #000000;}\nhr {width: 600px; background-color: #cccccc; border: 0px; height: 1px; color: #000000;}\n</style>\n";
        echo $info;
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
    public function getDatabaseInfo()
    {
        $table_data = \think\Db::query("SHOW TABLE STATUS");
        $returndata = [];
        $report_array = [];
        $i = 0;
        $totalrows = 0;
        $size = 0;
        foreach ($table_data as $key => $val) {
            $name = $val["Name"];
            $rows = $val["Rows"];
            $datalen = $val["Data_length"];
            $indexlen = $val["Index_length"];
            $totalsize = $datalen + $indexlen;
            $totalrows += $rows;
            $size += $totalsize;
            $report_array[] = ["name" => $name, "rows" => $rows, "size" => round($totalsize / 1024, 2) . " kb"];
            $i++;
        }
        $returndata["report_array"] = $report_array;
        $returndata["total_count"] = $i;
        $returndata["total_rows"] = $totalrows;
        $returndata["total_size"] = round($size / 1024, 2) . " kb";
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function postOptimizeTables()
    {
        try {
            $table_list = $this->listTables();
            $this->optimizeTables($table_list);
            active_log($this->lang["System_admin_postOptimizeTables"]);
        } catch (\Exception $e) {
        }
        return jsonrule(["status" => 200, "msg" => "执行成功"]);
    }
    public function postDownDataBackup()
    {
        $backup_class = new \app\admin\lib\Backup(DATABASE_DOWN_PATH, config("database.database"));
        try {
            active_log($this->lang["System_admin_postDownDataBackup"]);
            $filename = $backup_class->backupAll();
            ob_clean();
            header("Access-Control-Expose-Headers: Content-disposition");
            header("File_name: " . $filename);
            if (file_exists(DATABASE_DOWN_PATH . $filename)) {
                return download(DATABASE_DOWN_PATH . $filename, $filename);
            }
            return jsonrule(["status" => "400", "msg" => "没有此文件"]);
        } catch (\Exception $e) {
            return jsonrule(["status" => "406", "msg" => $e->getMessage()]);
        }
    }
    public function postToggleVersion()
    {
        $params = $this->request->param();
        $version = $params["type"] ?? "stable";
        if (!in_array($version, ["stable", "beta"])) {
            $version = "stable";
        }
        $system_license = configuration("system_license");
        postRequest($this->auth_url . "/app/api/toggle_version", ["license" => $system_license, "type" => $version, "token" => config("auth_token")]);
        updateConfiguration("system_version_type", $version);
        return jsonrule(["status" => 200, "msg" => "版本切换成功"]);
    }
    public function getAutoUpdate()
    {
        if (!extension_loaded("ionCube Loader")) {
            return jsonrule(["status" => 400, "msg" => "请先安装ionCube扩展"]);
        }
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            compareLicense();
        }
        if (configuration("last_license_time") + 86400 < time()) {
            compareLicense();
        }
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            return jsonrule(["status" => 307, "msg" => "授权错误,请检查域名或ip"]);
        }
        $auth = de_authorize($zjmf_authorize);
        $ip = de_systemip(configuration("authsystemip"));
        if ($auth["last_license_time"] + 604800 < time() && $auth["license_error_time"] + 60 < time()) {
            compareLicense();
            $zjmf_authorize = configuration("zjmf_authorize");
            $auth = de_authorize($zjmf_authorize);
            updateConfiguration("license_error_time", time());
        }
        if ($ip != $auth["ip"] && !empty($ip)) {
            return jsonrule(["status" => 307, "msg" => "授权错误,请检查ip"]);
        }
        if ($auth["last_license_time"] + 604800 < time() || ltrim(str_replace("https://", "", str_replace("http://", "", $auth["domain"])), "www.") != ltrim(str_replace("https://", "", str_replace("http://", "", $_SERVER["HTTP_HOST"])), "www.") || $auth["installation_path"] != CMF_ROOT || $auth["license"] != configuration("system_license")) {
            return jsonrule(["status" => 307, "msg" => "授权错误,请检查域名或ip"]);
        }
        if (!empty($auth["facetoken"])) {
            return jsonrule(["status" => 307, "msg" => "您的授权已被暂停,请前往智简魔方会员中心检查授权状态"]);
        }
        if ($auth["status"] == "Suspend") {
            return jsonrule(["status" => 307, "msg" => "您的授权已被暂停,请前往智简魔方会员中心检查授权状态"]);
        }
        if (!empty($auth["due_time"]) && $auth["due_time"] < time() && $auth["edition"] == 1) {
            return jsonrule(["status" => 307, "msg" => "您的升级与支持服务已到期，无法升级"]);
        }
        ini_set("max_execution_time", 3600);
        cache("upgrade_system_start", time(), 3600);
        session_write_close();
        if (!configuration("update_dcim_only_once")) {
            $this->updateDcim();
        }
        updateConfiguration("update_dcim_only_once", 1);
        $upgrade_system_logic = new \app\common\logic\UpgradeSystem();
        $upgrade_system_logic->upload();
        updateConfiguration("zjmf_system_version_type_last", configuration("system_version_type"));
        $src = WEB_ROOT . "themes";
        $dst = WEB_ROOT . "themes/web";
        $out = ["cart", "clientarea", "web"];
        $res = recurse_copy($src, $dst, $out);
        if ($res["status"] == 200) {
            deleteDir($src, $out);
        }
        compareLicense();
    }
    public function getCheckAutoUpdate()
    {
        if (get_cfg_var("max_execution_time") < time() - cache("upgrade_system_start")) {
            return jsonrule(["status" => 400, "msg" => "请求接口超时"]);
        }
        $upgrade_system_logic = new \app\common\logic\UpgradeSystem();
        $upload_dir = $upgrade_system_logic->upload_dir;
        $data = [];
        $data["progress"] = "0%";
        $data["msg"] = "检测根目录权限";
        $data["status"] = 200;
        if (!is_readable($upload_dir) || !shd_new_is_writeable($upload_dir)) {
            $data["progress"] = "10%";
            $data["msg"] = "根目录不可读/写";
            $data["status"] = 400;
        }
        $progress_log = $upgrade_system_logic->progress_log;
        $timeout = ["http" => ["timeout" => 10]];
        $ctx = stream_context_create($timeout);
        $handle = fopen($progress_log, "r", false, $ctx);
        if (!$handle) {
            return json(["status" => 400, "msg" => "升级失败"]);
        }
        $content = "";
        while (!feof($handle)) {
            $content .= fread($handle, 8080);
        }
        fclose($handle);
        $arr = explode("\n", $content);
        $fun = function ($value) {
            if (empty($value)) {
                return false;
            }
            return true;
        };
        $arr = array_filter($arr, $fun);
        $last = array_pop($arr);
        $data = json_decode($last, true);
        if (($data["progress"] == "20%" || $data["progress"] == "50%") && $data["status"] == 200) {
            $file_name = $data["file_name"];
            $origin_size = $data["origin_size"];
            $moment_size = filesize(CMF_ROOT . $file_name);
            $moment_size = bcdiv($moment_size, 1048576, 2);
            $data["progress"] = bcmul(0 + 0 * bcdiv($moment_size, $origin_size, 2), 100, 2) . "%";
            $data["msg"] = $data["msg"] . ";已下载" . $moment_size . "MB";
            unset($data["file_name"]);
            unset($data["origin_size"]);
        }
        $upgrade_system_logic->updateUnzip();
        $upgrade_system_logic->updateCopy();
        return json($data);
    }
    private function listTables()
    {
        $tables = \think\Db::query("SHOW TABLES");
        $tableArray = [];
        foreach ($tables as $table) {
            $tableArray[] = $table[0];
        }
        return $tableArray;
    }
    private function optimizeTables($tables)
    {
        $optimisedTables = [];
        try {
            foreach ($tables as $table) {
                $statement = "OPTIMIZE TABLE `" . $table . "`;";
                \think\Db::query($statement);
                $optimisedTables[] = $table;
            }
        } catch (\Exception $e) {
            $tableList = implode(", ", $optimisedTables);
            $exceptionMessage = "Optimising table failed.";
            if ($tableList) {
                $exceptionMessage .= " Successfully optimised tables are: " . $tableList;
            }
            throw new \Exception($exceptionMessage);
        }
    }
    public function updateDcim()
    {
        $svg = ["1" => "Windows", "2" => "CentOS", "3" => "Ubuntu", "4" => "Debian", "5" => "ESXi", "6" => "XenServer", "7" => "FreeBSD", "8" => "Fedora", "9" => "其他"];
        $server = \think\Db::name("servers")->alias("a")->field("a.id,a.gid,b.area,b.os")->leftJoin("dcim_servers b", "a.id=b.serverid")->where("a.server_type", "dcim")->select()->toArray();
        if (empty($server)) {
            return false;
        }
        $group_area = [];
        $group_os = [];
        $product_area_config = [];
        $product_os_config = [];
        $price = [];
        foreach ($server as $k => $v) {
            $v["area"] = json_decode($v["area"], true);
            if (!empty($v["area"])) {
                foreach ($v["area"] as $vv) {
                    $group_area[$v["gid"]][] = ["id" => $vv["id"], "option_name" => $vv["id"] . "|" . $vv["area"] . "^" . ($vv["name"] ?: $vv["area"])];
                }
            }
            $v["os"] = json_decode($v["os"], true);
            $os_group = array_column($v["os"]["group"], "svg", "id");
            foreach ($v["os"]["os"] as $vv) {
                $group_os[$v["gid"]][] = ["id" => $vv["id"], "option_name" => $vv["id"] . "|" . $svg[$os_group[$vv["group_id"]]] . "^" . $vv["name"]];
            }
        }
        $products = \think\Db::name("products")->field("id,server_group")->where("type", "dcim")->whereIn("server_group", array_column($server, "gid"))->whereIn("api_type", ["", "normal"])->select()->toArray();
        if (empty($products)) {
            return false;
        }
        foreach ($products as $k => $v) {
            $gid = \think\Db::name("product_config_links")->where("pid", $v["id"])->value("gid");
            if (!empty($gid)) {
                $configid = \think\Db::name("product_config_options")->where("gid", $gid)->where("option_type", 12)->value("id");
                if (empty($configid)) {
                    $configid = \think\Db::name("product_config_options")->insertGetId(["gid" => $gid, "option_name" => "area|区域", "option_type" => 12, "qty_minimum" => 0, "qty_maximum" => 0, "order" => 0, "hidden" => 0, "upgrade" => 0, "upstream_id" => 0]);
                }
                $product_area_config[$v["id"]] = $configid;
                foreach ($group_area[$v["server_group"]] as $vv) {
                    $is_add = \think\Db::name("product_config_options_sub")->where("config_id", $configid)->whereLike("option_name", $vv["id"] . "|%")->value("id");
                    if (!$is_add) {
                        $sub_id = \think\Db::name("product_config_options_sub")->insertGetId(["config_id" => $configid, "qty_minimum" => 0, "qty_maximum" => 0, "option_name" => $vv["option_name"]]);
                        $pricing[] = ["type" => "configoptions", "relid" => $sub_id, "monthly" => 0];
                    }
                }
                $configid = \think\Db::name("product_config_options")->where("gid", $gid)->where("option_type", 5)->value("id");
                if (empty($configid)) {
                    $configid = \think\Db::name("product_config_options")->insertGetId(["gid" => $gid, "option_name" => "os|操作系统", "option_type" => 5, "qty_minimum" => 0, "qty_maximum" => 0, "order" => 1, "hidden" => 0, "upgrade" => 0, "upstream_id" => 0]);
                }
                $product_os_config[$v["id"]] = $configid;
                foreach ($group_os[$v["server_group"]] as $vv) {
                    $is_add = \think\Db::name("product_config_options_sub")->where("config_id", $configid)->whereLike("option_name", $vv["id"] . "|%")->value("id");
                    if (!$is_add) {
                        $sub_id = \think\Db::name("product_config_options_sub")->insertGetId(["config_id" => $configid, "qty_minimum" => 0, "qty_maximum" => 0, "option_name" => $vv["option_name"]]);
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
                \think\Db::name("pricing")->data($pricing)->limit(50)->insertAll();
            }
            unset($pricing);
        }
        $product_ids = array_column($products, "id");
        $host = \think\Db::name("host")->alias("a")->field("a.id,a.productid,a.dcim_area,a.os,b.gid")->leftJoin("servers b", "a.serverid=b.id")->whereIn("a.productid", $product_ids)->select()->toArray();
        foreach ($host as $v) {
            if (!empty($product_area_config[$v["productid"]])) {
                $sub_id = \think\Db::name("product_config_options_sub")->where("config_id", $product_area_config[$v["productid"]])->whereLike("option_name", $v["dcim_area"] . "|%")->value("id");
                if (!empty($sub_id)) {
                    $r = \think\Db::name("host_config_options")->where("relid", $v["id"])->where("configid", $product_area_config[$v["productid"]])->value("id");
                    if (!empty($r)) {
                        \think\Db::name("host_config_options")->where("id", $r)->update(["optionid" => $sub_id, "qty" => 0]);
                    } else {
                        \think\Db::name("host_config_options")->insert(["relid" => $v["id"], "configid" => $product_area_config[$v["productid"]], "optionid" => $sub_id, "qty" => 0]);
                    }
                }
            }
            if (!empty($product_os_config[$v["productid"]])) {
                $sub = \think\Db::name("product_config_options_sub")->field("id,option_name")->where("config_id", $product_os_config[$v["productid"]])->whereLike("option_name", "%" . $v["os"] . "%")->find();
                if (!empty($sub)) {
                    $r = \think\Db::name("host_config_options")->where("relid", $v["id"])->where("configid", $product_os_config[$v["productid"]])->value("id");
                    if (!empty($r)) {
                        \think\Db::name("host_config_options")->where("id", $r)->update(["optionid" => $sub["id"], "qty" => 0]);
                    } else {
                        \think\Db::name("host_config_options")->insert(["relid" => $v["id"], "configid" => $product_os_config[$v["productid"]], "optionid" => $sub["id"], "qty" => 0]);
                    }
                    $os_url = explode("^", explode($sub["option_name"], "|")[1])[0] ?: "";
                    \think\Db::name("host")->strict(false)->where("id", $v["id"])->update(["os_url" => $os_url]);
                }
            }
        }
        return true;
    }
    public function getAuthorize()
    {
        $res = compareLicense();
        if ($res === false) {
            return json(["status" => 400, "msg" => "授权获取失败, 无法连接到授权服务器, 请检查网络"]);
        }
        if ($res["status"] == 400) {
            return json(["status" => 400, "msg" => "授权获取失败, 授权码错误"]);
        }
        if ($res["status"] == 401) {
            return json(["status" => 400, "msg" => "授权获取失败, 该授权已使用, 请重置授权后重试"]);
        }
        return json(["status" => 200, "msg" => "授权获取成功"]);
    }
    public function putLicense()
    {
        $params = $this->request->param();
        $license = $params["license"] ?? "";
        if (empty($license)) {
            return json(["status" => 400, "msg" => "授权码不能为空"]);
        }
        \think\Db::startTrans();
        try {
            \think\Db::name("configuration")->where("setting", "system_license")->update(["value" => $license]);
            $res = compareLicense();
            if ($res === false) {
                throw new \Exception("授权更换失败, 无法连接到授权服务器, 请检查网络");
            }
            if ($res["status"] == 400) {
                throw new \Exception("授权更换失败, 授权码错误");
            }
            if ($res["status"] == 401) {
                throw new \Exception("授权更换失败, 该授权已使用, 请重置授权后重试");
            }
            active_log("授权码修改成功");
            \think\Db::commit();
            putLicenseAfter();
            return json(["status" => 200, "msg" => "授权更换成功"]);
        } catch (\Exception $e) {
            \think\Db::rollback();
            return json(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function getDataMigrate()
    {
        $down_url = $this->auth_url . "/tool/move.php";
        ob_clean();
        header("Access-Control-Expose-Headers: Content-disposition");
        return download($down_url, "move.php");
    }
    public function getSystemAuthRuleLanguage()
    {
        $admin_id = cmf_get_current_admin_id();
        $AdminUserModel = new \app\admin\model\AdminUserModel();
        $data = $AdminUserModel->get_rule($admin_id);
        return json(["status" => 200, "data" => $data]);
    }
}

?>