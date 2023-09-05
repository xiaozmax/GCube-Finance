<?php


namespace app\api\controller;

/**
 * @title 用户注册API
 * Class InstallController
 */
class InstallController extends \think\Controller
{
    public function initialize()
    {
        if (cmf_is_installed()) {
            exit(json_encode(["status" => 400, "msg" => "网站已经安装", "data" => ["url" => cmf_get_root() . "/"]]));
        }
        if (!is_writable(CMF_DATA)) {
            exit(json_encode(["status" => 500, "msg" => "目录" . realpath(CMF_ROOT . "data") . "无法写入！", "data" => ["url" => cmf_get_root() . "/"]]));
        }
        $langSet = request()->langset();
        \think\facade\Lang::load([dirname(__DIR__) . "/lang/" . $langSet . ".php"]);
    }
    public function sysVersion()
    {
        session("session_is_open", 1);
        return json(["status" => 200, "data" => ["version" => shd_version()]]);
    }
    public function envMonitor()
    {
        $status = session("session_is_open");
        if (!$status) {
            return json(["status" => 400, "msg" => "请开启浏览器Cookie"]);
        }
        $error = 0;
        $envs = [];
        if (!version_compare(phpversion(), "5.4.0", ">=")) {
            $error++;
            $env["status"] = 0;
        } else {
            $env["status"] = 1;
        }
        $env["name"] = "PHP版本";
        $env["suggest"] = ">5.6.x";
        $env["current"] = phpversion();
        $env["worst"] = "5.4.0";
        $envs[] = $env;
        $modules = [];
        if (extension_loaded("Zend OPcache")) {
            $error++;
            $module["status"] = 0;
            $module["current"] = "已开启";
        } else {
            $module["status"] = 1;
            $module["current"] = "未开启";
        }
        $module["name"] = "opcache";
        $module["suggest"] = "未开启";
        $module["worst"] = "未开启";
        $modules[] = $module;
        if (!function_exists("session_start")) {
            $error++;
            $module["status"] = 0;
            $module["current"] = "不支持";
        } else {
            $module["status"] = 1;
            $module["current"] = "支持";
        }
        $module["name"] = "session";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (!class_exists("pdo")) {
            $error++;
            $module["status"] = 0;
            $module["current"] = "未开启";
        } else {
            $module["status"] = 1;
            $module["current"] = "开启";
        }
        $module["name"] = "PDO";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (!extension_loaded("pdo_mysql")) {
            $error++;
            $module["status"] = 0;
            $module["current"] = "未开启";
        } else {
            $module["status"] = 1;
            $module["current"] = "开启";
        }
        $module["name"] = "PDO_MySQL";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (!extension_loaded("curl")) {
            $error++;
            $module["status"] = 0;
            $module["current"] = "未开启";
        } else {
            $module["status"] = 1;
            $module["current"] = "开启";
        }
        $module["name"] = "CURL";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (!extension_loaded("gd")) {
            $error++;
            $module["status"] = 0;
            $module["current"] = "未开启";
        } else {
            $module["status"] = 1;
            $module["current"] = "开启";
        }
        $module["name"] = "GD";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (!function_exists("imagettftext")) {
            $module["current"] .= "未开启";
            $module["status"] = 0;
            $error++;
        } else {
            $module["current"] = "开启";
            $module["status"] = 1;
        }
        $module["name"] = "FreeType Support";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (!extension_loaded("mbstring")) {
            $module["current"] = "未开启";
            $module["status"] = 0;
            $error++;
        } else {
            $module["status"] = 1;
            $module["current"] = "开启";
        }
        $module["name"] = "MBstring";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (!extension_loaded("fileinfo")) {
            $error++;
            $module["current"] = "未开启";
            $module["status"] = 0;
        } else {
            $module["current"] = "开启";
            $module["status"] = 1;
        }
        $module["name"] = "fileinfo";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (!extension_loaded("ionCube Loader")) {
            $error++;
            $module["current"] = "未开启";
            $module["status"] = 0;
        } else {
            $module["current"] = "开启";
            $module["status"] = 1;
        }
        $module["name"] = "ionCube";
        $module["suggest"] = "开启";
        $module["worst"] = "开启";
        $modules[] = $module;
        if (version_compare(phpversion(), "5.6.0", ">=") && version_compare(phpversion(), "7.0.0", "<") && ini_get("always_populate_raw_post_data") != -1) {
            $error++;
            $module["current"] = "未关闭";
            $module["status"] = 0;
        } else {
            $module["current"] = "关闭";
            $module["status"] = 1;
        }
        $module["name"] = "always_populate_raw_post_data";
        $module["suggest"] = "关闭";
        $module["worst"] = "关闭";
        $modules[] = $module;
        if (!ini_get("file_uploads")) {
            $error++;
            $module["current"] = "禁止上传";
            $module["status"] = 0;
        } else {
            $module["current"] = "不限制";
            $module["status"] = 1;
        }
        $module["name"] = "附件上传";
        $module["suggest"] = ">2M";
        $module["worst"] = "不限制";
        $modules[] = $module;
        $folders = [realpath(CMF_ROOT . "data") . DIRECTORY_SEPARATOR, realpath("./plugins") . DIRECTORY_SEPARATOR, realpath("../uploads") . DIRECTORY_SEPARATOR, realpath("./upload") . DIRECTORY_SEPARATOR, realpath("../app/config") . "/database.php"];
        $newFolders = [];
        $Install_logic = new \app\common\logic\Install();
        foreach ($folders as $k => $dir) {
            $testDir = $dir;
            if (strpos($dir, ".") === false) {
                $Install_logic->sp_dir_create($testDir);
            }
            if (!$Install_logic->new_is_writeable($testDir)) {
                $newFolders[$k]["name"] = $dir;
                $newFolders[$k]["write"] = 0;
                $newFolders[$k]["read"] = "";
                $error++;
            } else {
                $newFolders[$k]["name"] = $dir;
                $newFolders[$k]["write"] = 1;
                $newFolders[$k]["read"] = "";
            }
            if (!is_readable($testDir)) {
                $newFolders[$k]["name"] = $dir;
                $newFolders[$k]["read"] = 0;
                $newFolders[$k]["write"] = $newFolders[$k]["write"] ?: "";
                $error++;
            } else {
                $newFolders[$k]["name"] = $dir;
                $newFolders[$k]["read"] = 1;
                $newFolders[$k]["write"] = $newFolders[$k]["write"] ?: "";
            }
        }
        session("install_error", $error);
        $data["envs"] = $envs;
        $data["modules"] = $modules;
        $data["folders"] = $newFolders;
        $data["error"] = $error;
        return json(["status" => 200, "data" => $data]);
    }
    public function dbMonitor(\think\Request $request)
    {
        $error = session("install_error");
        if ($error) {
            return json(["status" => 400, "msg" => "为保证软件正常使用,请修复检测未通过项！"]);
        }
        $param = $request->param();
        $config["hostname"] = $param["hostname"];
        $config["username"] = $param["username"];
        $config["password"] = $param["password"];
        $config["hostport"] = $param["hostport"];
        $config["type"] = "mysql";
        $dbname = $param["dbname"];
        try {
            $engines = \think\Db::connect($config)->query("SHOW ENGINES;");
            foreach ($engines as $engine) {
                if ($engine["Engine"] == "InnoDB" && $engine["Support"] != "NO") {
                    $supportInnoDb = true;
                    $databases = \think\Db::connect($config)->query("SHOW DATABASES");
                    foreach ($databases as $v) {
                        if ($v["Database"] === $dbname) {
                            $supportDdname = true;
                            if (!isset($supportDdname)) {
                                \think\Db::connect($config)->query("CREATE DATABASE `" . $dbname . "`");
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return json(["status" => 400, "msg" => "数据库链接失败" . $e->getMessage()]);
        }
        if ($supportInnoDb === true) {
            session("install_db_data", $config);
            session("install_db_name", $dbname);
            return json(["status" => 200, "data" => []]);
        }
        return json(["status" => 400, "msg" => "数据库账号密码验证通过，但不支持InnoDb!"]);
    }
    public function codeMonitor(\think\Request $request)
    {
        $dbstatus = session("install_db_data");
        if (!$dbstatus) {
            return json(["status" => 400, "msg" => "请先完成数据库安装！"]);
        }
        $param = $request->param();
        $license = $param["license"];
        if (!preg_match("/^[0-9A-Z]{32}/", $license)) {
            return json(["status" => 400, "msg" => "请填写正确的授权码！"]);
        }
        try {
            $res = commonCurl("https://license.soft13.idcsmart.com/app/api/auth", ["license" => $license, "domain" => request()->domain() ?? "", "ip" => $_SERVER["SERVER_ADDR"] ?? "", "token" => config("auth_token"), "type" => "finance"], 10, "GET");
        } catch (\Exception $e) {
            return json(["status" => 400, "msg" => "请求授权服务器返回错误," . $e->getMessage()]);
        }
        if (!$res) {
            return json(["status" => 400, "msg" => "请求授权服务器超时，请检查网络！"]);
        }
        if ($res["status"] == 200) {
            session("install_license_status", 1);
            return json(["status" => 200, "msg" => $res["msg"]]);
        }
        if (isset($res["http_code"])) {
            return json(["status" => 400, "msg" => "请求授权服务器失败，请稍后再试试！错误码:" . $res["http_code"]]);
        }
        return json(["status" => 400, "msg" => $res["msg"]]);
    }
    public function envSystem(\think\Request $request)
    {
        $config = session("install_db_data");
        $dbstatus = session("install_license_status");
        if (!$dbstatus) {
            return json(["status" => 400, "msg" => "请检查许可证是否正常使用！"]);
        }
        $param = $request->param();
        $config["charset"] = "utf8mb4";
        $license = $param["license"];
        if (empty($license)) {
            return json(["status" => 400, "msg" => "授权码不能为空！"]);
        }
        $res = commonCurl("https://license.soft13.idcsmart.com/app/api/auth", ["license" => $license, "domain" => request()->domain() ?? "", "ip" => $_SERVER["SERVER_ADDR"] ?? "", "token" => config("auth_token"), "type" => "finance"]);
        if ($res["status"] != 200) {
            return json(["status" => 400, "msg" => $res["msg"]]);
        }
        $site_name = $param["sitename"];
        $domain = $param["domain"];
        $admin_application = $param["admin_application"];
        if (empty($site_name)) {
            return json(["status" => 400, "msg" => "系统名称不能为空！"]);
        }
        if (empty($domain)) {
            return json(["status" => 400, "msg" => "网站域名不能为空！"]);
        }
        if (empty($admin_application)) {
            return json(["status" => 400, "msg" => "后台路径不能为空！"]);
        }
        $user_login = $param["manager"];
        $user_pass = $param["manager_pwd"];
        $user_email = $param["manager_email"];
        if (empty($user_login)) {
            return json(["status" => 400, "msg" => "管理员帐号不可以为空！"]);
        }
        if (empty($user_pass)) {
            return json(["status" => 400, "msg" => "密码不可以为空！"]);
        }
        if (strlen($user_pass) < 6) {
            return json(["status" => 400, "msg" => "密码长度最少6位！"]);
        }
        if (32 < strlen($user_pass)) {
            return json(["status" => 400, "msg" => "密码长度最多32位！"]);
        }
        $db = \think\Db::connect($config);
        $db_name = session("install_db_name");
        $sql = "CREATE DATABASE IF NOT EXISTS `" . $db_name . "` DEFAULT CHARACTER SET " . $config["charset"];
        if ($db->execute($sql) === false) {
            return json(["status" => 400, "msg" => $db->getError()]);
        }
        $config["database"] = $db_name;
        $config["admin_application"] = $admin_application;
        $config["prefix"] = "shd_";
        session("install.license", $license);
        session("install.db_config", $config);
        $dir = realpath(CMF_ROOT . "/public/install/thinkcmf.sql");
        $sql = cmf_split_sql($dir, $config["prefix"], $config["charset"]);
        $apps = cmf_scan_dir(CMF_ROOT . "app/*", GLOB_ONLYDIR);
        foreach ($apps as $app) {
            $appDbSqlFile = CMF_ROOT . "app/" . $app . "/data/" . $app . ".sql";
            if (file_exists($appDbSqlFile)) {
                $sqlList = cmf_split_sql($appDbSqlFile, $config["prefix"], $config["charset"]);
                $sql = array_merge($sql, $sqlList);
            }
        }
        session("install.sql", $sql);
        session("install.error", 0);
        session("install.site_info", ["company_name" => $site_name, "domain" => $domain, "admin_application" => $admin_application]);
        session("install.admin_info", ["user_login" => $user_login, "user_pass" => $user_pass, "user_email" => $user_email]);
        $sql_num = ceil(count($sql) / 100);
        return json(["status" => 200, "data" => ["sql_num" => $sql_num]]);
    }
    public function install(\think\Request $request)
    {
        $Install_logic = new \app\common\logic\Install();
        $config = session("install.db_config");
        $sql = session("install.sql");
        if (empty($config) || empty($sql)) {
            return json(["status" => 400, "msg" => "非法安装！"]);
        }
        $sql_index = $request->param("sql_index", 0, "intval");
        $db = \think\Db::connect($config);
        $i = 100;
        for ($x = 0; $x < $i; $x++) {
            $index = $sql_index * $i + $x;
            if (count($sql) <= $index) {
                $install_error = session("install.error");
                return json(["status" => 200, "msg" => "安装完成！", "data" => ["done" => 1, "error" => $install_error]]);
            }
            $sql_to_exec = str_replace("shd_", $config["prefix"], $sql[$index]) . ";";
            $result = $Install_logic->sp_execute_sql($db, $sql_to_exec);
            if (!empty($result["error"])) {
                $install_error = session("install.error");
                $install_error = empty($install_error) ? 0 : $install_error;
                session("install.error", $install_error + 1);
                return json(["status" => 400, "msg" => $result["message"], "data" => ["sql" => $sql_to_exec, "exception" => $result["exception"]]]);
            }
        }
        $index = $sql_index + 1;
        return json(["status" => 200, "msg" => "[sql" . $index . "]执行成功"]);
    }
    public function setDbConfig()
    {
        $Install_logic = new \app\common\logic\Install();
        $config = session("install.db_config");
        $config["authcode"] = cmf_random_string(18);
        session("install.authcode", $config["authcode"]);
        $result = $Install_logic->sp_create_db_config($config);
        foreach ($config as $k => $v) {
            config("database." . $k, $v);
        }
        if ($result) {
            return json(["status" => 200, "msg" => "数据配置文件写入成功！"]);
        }
        return json(["status" => 400, "msg" => "数据配置文件写入失败！"]);
    }
    public function setSite()
    {
        $config = session("install.db_config");
        $authcode = session("install.authcode");
        if (empty($config)) {
            return json(["status" => 400, "msg" => "非法安装！"]);
        }
        $siteInfo = session("install.site_info");
        $admin = session("install.admin_info");
        $license = session("install.license");
        $admin["id"] = 1;
        $admin["user_pass"] = cmf_password($admin["user_pass"], $authcode);
        $admin["user_type"] = 1;
        $admin["create_time"] = time();
        $admin["user_status"] = 1;
        $admin["user_nickname"] = $admin["user_login"];
        $admin["language"] = "CN";
        try {
            \think\Db::startTrans();
            $auth = \think\Db::name("auth_rule")->field("id")->select()->toArray();
            $auth = implode(",", array_column($auth, "id"));
            \think\Db::name("role")->where("id", 1)->delete();
            $role_id = \think\Db::name("role")->insert(["id" => 1, "parent_id" => 0, "status" => 1, "create_time" => time(), "update_time" => time(), "list_order" => 0, "name" => "超级管理员", "remark" => "拥有网站最高管理员权限！", "auth_role" => $auth]);
            cmf_set_option("site_info", $siteInfo);
            \think\Db::name("user")->where("id", 1)->delete();
            $insert = \think\Db::name("user")->insertGetId($admin);
            \think\Db::name("role_user")->insert(["role_id" => $role_id, "user_id" => $insert]);
            $admin_dept = [];
            $admin_dept[] = ["admin_id" => $insert, "dptid" => 1];
            $admin_dept[] = ["admin_id" => $insert, "dptid" => 2];
            $admin_dept[] = ["admin_id" => $insert, "dptid" => 3];
            \think\Db::name("ticket_department_admin")->insertAll($admin_dept);
            $old_admin_application = configuration("admin_application") ?? "admin";
            updateConfiguration("admin_application", $siteInfo["admin_application"]);
            updateConfiguration("domain", $siteInfo["domain"]);
            updateConfiguration("company_name", $siteInfo["company_name"]);
            updateConfiguration("system_license", $license);
            create_system_token();
            \think\Db::commit();
            $data = ["token" => config("auth_token"), "license" => $license, "domain" => $siteInfo["domain"], "ip" => $_SERVER["SERVER_ADDR"], "system_token" => configuration("system_token"), "install_version" => configuration("update_last_version"), "installation_path" => CMF_ROOT];
            $ret = commonCurl("https://license.soft13.idcsmart.com/app/api/auth_update", $data);
            updateConfiguration("last_license_time", time());
            if ($ret["status"] == 200 && !empty($ret["data"])) {
                updateConfiguration("zjmf_authorize", $ret["data"]);
            }
        } catch (\Exception $e) {
            \think\Db::rollback();
            return json(["status" => 400, "msg" => "网站创建失败！" . $e->getMessage()]);
        }
        $admin_application = config("database.admin_application") ?? "admin";
        if ($admin_application != "admin") {
            rename(CMF_ROOT . "public/" . $old_admin_application, CMF_ROOT . "public/" . $admin_application);
        }
        return json(["status" => 200, "msg" => "网站创建完成！"]);
    }
    public function installAppHooks()
    {
        $apps = cmf_scan_dir(CMF_ROOT . "app/*", GLOB_ONLYDIR);
        foreach ($apps as $app) {
            \app\admin\logic\HookLogic::importHooks($app);
        }
        return json(["status" => 200, "msg" => "应用钩子导入成功！"]);
    }
    public function installAppUserActions()
    {
        $apps = cmf_scan_dir(CMF_ROOT . "app/*", GLOB_ONLYDIR);
        foreach ($apps as $app) {
            \app\user\logic\UserActionLogic::importUserActions($app);
        }
        session("install.step", 4);
        return json(["status" => 200, "msg" => "应用用户行为成功！"]);
    }
    public function stepLast()
    {
        if (session("install.step") == 4) {
            @touch(CMF_DATA . "install.lock");
            $data["admin_url"] = configuration("domain") . "/" . adminAddress();
            $data["admin_name"] = session("install.admin_info")["user_login"];
            $data["admin_pass"] = session("install.admin_info")["user_pass"];
            return json(["status" => 200, "msg" => "安装完成！", "data" => $data]);
        }
        return json(["status" => 200, "msg" => "非法安装！"]);
    }
}

?>