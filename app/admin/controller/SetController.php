<?php


namespace app\admin\controller;

/**
 * @title 后台设置
 * @description 接口说明
 */
class SetController extends AdminBaseController
{
    public function site()
    {
        $content = hook_one("admin_setting_site_view");
        if (!empty($content)) {
            return $content;
        }
        $noNeedDirs = [".", "..", ".svn", "fonts"];
        $adminThemesDir = WEB_ROOT . config("template.cmf_admin_theme_path") . config("template.cmf_admin_default_theme") . "/public/assets/themes/";
        $adminStyles = cmf_scan_dir($adminThemesDir . "*", GLOB_ONLYDIR);
        $adminStyles = array_diff($adminStyles, $noNeedDirs);
        $cdnSettings = cmf_get_option("cdn_settings");
        $cmfSettings = cmf_get_option("cmf_settings");
        $adminSettings = cmf_get_option("admin_settings");
        $adminThemes = [];
        $themes = cmf_scan_dir(WEB_ROOT . config("template.cmf_admin_theme_path") . "/*", GLOB_ONLYDIR);
        foreach ($themes as $theme) {
            if (strpos($theme, "admin_") === 0) {
                array_push($adminThemes, $theme);
            }
        }
        if (APP_DEBUG && false) {
            $apps = cmf_scan_dir(APP_PATH . "*", GLOB_ONLYDIR);
            $apps = array_diff($apps, $noNeedDirs);
            $this->assign("apps", $apps);
        }
        $this->assign("site_info", cmf_get_option("site_info"));
        $this->assign("admin_styles", $adminStyles);
        $this->assign("templates", []);
        $this->assign("admin_themes", $adminThemes);
        $this->assign("cdn_settings", $cdnSettings);
        $this->assign("admin_settings", $adminSettings);
        $this->assign("cmf_settings", $cmfSettings);
        return $this->fetch();
    }
    public function sitePost()
    {
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), "SettingSite");
            if ($result !== true) {
                $this->error($result);
            }
            $options = $this->request->param("options/a");
            cmf_set_option("site_info", $options);
            $cmfSettings = $this->request->param("cmf_settings/a");
            $bannedUsernames = preg_replace("/[^0-9A-Za-z_\\x{4e00}-\\x{9fa5}-]/u", ",", $cmfSettings["banned_usernames"]);
            $cmfSettings["banned_usernames"] = $bannedUsernames;
            cmf_set_option("cmf_settings", $cmfSettings);
            $cdnSettings = $this->request->param("cdn_settings/a");
            cmf_set_option("cdn_settings", $cdnSettings);
            $adminSettings = $this->request->param("admin_settings/a");
            $routeModel = new \app\admin\model\RouteModel();
            if (!empty($adminSettings["admin_password"])) {
                $routeModel->setRoute($adminSettings["admin_password"] . "\$", "admin/Index/index", [], 2, 5000);
            } else {
                $routeModel->deleteRoute("admin/Index/index", []);
            }
            $routeModel->getRoutes(true);
            if (!empty($adminSettings["admin_theme"])) {
                $result = cmf_set_dynamic_config(["template" => ["cmf_admin_default_theme" => $adminSettings["admin_theme"]]]);
                if ($result === false) {
                    $this->error("配置写入失败!");
                }
            }
            cmf_set_option("admin_settings", $adminSettings);
            $this->success("保存成功！", "");
        }
    }
    public function password()
    {
        return $this->fetch();
    }
    public function passwordPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            if (empty($data["old_password"])) {
                return jsonrule(["status" => 406, "msg" => "原始密码不能为空"]);
            }
            if (empty($data["password"])) {
                return jsonrule(["status" => 406, "msg" => "新密码不能为空"]);
            }
            $userId = cmf_get_current_admin_id();
            $admin = \think\Db::name("user")->where("id", $userId)->find();
            $oldPassword = $data["old_password"];
            $password = $data["password"];
            $rePassword = $data["re_password"];
            if (cmf_compare_password($oldPassword, $admin["user_pass"])) {
                if ($password == $rePassword) {
                    if (cmf_compare_password($password, $admin["user_pass"])) {
                        return jsonrule(["status" => 406, "msg" => "新密码不能和原始密码相同！"]);
                    }
                    \think\Db::name("user")->where("id", $userId)->update(["user_pass" => cmf_password($password)]);
                    return jsonrule(["status" => 200, "msg" => "密码修改成功！"]);
                }
                return jsonrule(["status" => 401, "msg" => "两次密码不同！"]);
            }
            return jsonrule(["status" => 406, "msg" => "原始密码不正确"]);
        }
        return jsonrule(["status" => 400, "msg" => "请求错误！"]);
    }
    public function upload()
    {
        $uploadSetting = cmf_get_upload_setting();
        $this->assign("upload_setting", $uploadSetting);
        return $this->fetch();
    }
    public function uploadPost()
    {
        if ($this->request->isPost()) {
            $uploadSetting = $this->request->post();
            cmf_set_option("upload_setting", $uploadSetting);
            $this->success("保存成功！");
        }
    }
    public function clearCache()
    {
        cmf_clear_cache();
        return jsonrule(["status" => 200, "msg" => "清除缓存成功！"]);
    }
    public function getCustomFields()
    {
        $customfields = \think\Db::name("customfields")->where("type", "client")->order("sortorder asc")->select()->toArray();
        $returndata = [];
        $returndata["type_list"] = config("customfields");
        $returndata["customfields"] = $customfields;
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $returndata]);
    }
    public function postCustomFields(\think\Request $request)
    {
        $param = $request->param();
        $custom_logic = new \app\common\logic\Customfields();
        $re = $custom_logic->add(0, "client", $param);
        if ($re["status"] == "error") {
            return jsonrule(["status" => 406, "msg" => $re["msg"]]);
        }
        if (!empty($re["dec"])) {
            active_log(sprintf($this->lang["Set_admin_postCustomFields_add"], $re["dec"]));
        }
        $re = $custom_logic->edit(0, "client", $param);
        if ($re["status"] == "error") {
            return jsonrule(["status" => 406, "msg" => $re["msg"]]);
        }
        if (!empty($re["dec"])) {
            active_log(sprintf($this->lang["Set_admin_postCustomFields_edit"], $re["dec"]));
        }
        return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
    }
    public function delCustomFields(\think\Request $request)
    {
        $param = $request->param();
        $id = $param["id"];
        $type = $param["type"];
        if (empty($id) || empty($type)) {
            return jsonrule(["status" => 406, "msg" => lang("ID_OR_TYPE_CAN_NOT_EMPTY")]);
        }
        $custom_data = \think\Db::name("customfields")->where("type", $type)->where("id", $id)->find();
        if (empty($custom_data)) {
            return jsonrule(["status" => 406, "msg" => lang("UN_FIND_CUSTOM_FIELDS")]);
        }
        \think\Db::startTrans();
        try {
            \think\Db::name("customfields")->where("id", $id)->delete();
            \think\Db::name("customfieldsvalues")->where("fieldid", $id)->delete();
            \think\Db::commit();
            active_log(sprintf($this->lang["Set_admin_postCustomFields_delete"], $custom_data["fieldname"], $id));
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 406, "msg" => lang("DELETE FAIL")]);
        }
    }
    public function databaseBackups()
    {
        $keys = ["daily_email_backup_status", "daily_email_backup", "daily_ftp_backup_status", "ftp_backup_hostname", "ftp_backup_port", "ftp_backup_username\n            ", "ftp_backup_password", "ftp_backup_destination", "ftp_secure_mode", "ftp_passive_mode"];
        $config_data = getConfig($keys);
        if ($config_data["ftp_backup_password"]) {
            $config_data["ftp_backup_password"] = str_pad("", strlen(cmf_decrypt($config_data["ftp_backup_password"])), "*");
        }
        $returndata = [];
        $returndata["config_data"] = $config_data;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function backupDatabaseFtp(\think\Request $request)
    {
        $param = $request->param();
        $rule = ["ftp_backup_hostname" => "require", "ftp_backup_port" => "require|number", "ftp_backup_username" => "require", "ftp_backup_password" => "require", "ftp_backup_destination" => "require", "ftp_secure_mode" => "in:0,1", "ftp_passive_mode" => "in:0,1", "type" => "in:test,save"];
        $msg = ["ftp_backup_hostname.require" => "FTP主机名不能为空", "ftp_backup_port.require" => "FTP端口号不能为空", "ftp_backup_port.number" => "FTP端口号必须为数字", "ftp_backup_username.require" => "FTP用户名不能为空", "ftp_backup_password.require" => "FTP密码不能为空", "ftp_backup_destination.require" => "FTP路径不能为空"];
        $validate = new \think\Validate($rule, $msg);
        $result = $validate->check($param);
        if (!$result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $password = $param["ftp_backup_password"];
        if (str_pad("", strlen($password), "*") == $password) {
            $password = getConfig("ftp_backup_password");
            $password = cmf_decrypt($password);
        }
        if ($param["ftp_secure_mode"]) {
            $ftp_resource = ftp_ssl_connect($param["ftp_backup_hostname"], $param["ftp_backup_port"], 5);
        } else {
            if ($param["ftp_passive_mode"]) {
                $ftp_resource = ftp_connect($param["ftp_backup_hostname"], $param["ftp_backup_port"], 5);
            } else {
                return jsonrule(["status" => 406, "msg" => "请选择连接模式"]);
            }
        }
        if (!$ftp_resource) {
            return jsonrule(["status" => 406, "msg" => "FTP服务器连接失败"]);
        }
        if (@ftp_login($ftp_resource, $param["ftp_backup_username"], $param["ftp_backup_password"])) {
            if (ftp_chdir($ftp_resource, $param["ftp_backup_destination"])) {
                ftp_close($conn_id);
                if ($param["type"] == "save") {
                    updateConfiguration("daily_ftp_backup_status", 1);
                    updateConfiguration("ftp_backup_hostname", $param["ftp_backup_hostname"]);
                    updateConfiguration("ftp_backup_port", $param["ftp_backup_port"]);
                    updateConfiguration("ftp_backup_username", $param["ftp_backup_username"]);
                    updateConfiguration("ftp_backup_password", cmf_encrypt($password));
                    updateConfiguration("ftp_backup_destination", $param["ftp_backup_destination"]);
                    updateConfiguration("ftp_secure_mode", $param["ftp_secure_mode"]);
                    updateConfiguration("ftp_passive_mode", $param["ftp_passive_mode"]);
                    return jsonrule(["status" => 200, "msg" => "保存成功"]);
                }
                return jsonrule(["status" => 200, "msg" => "连接FTP服务器成功"]);
            }
            return jsonrule(["status" => 406, "msg" => "切换到目录失败"]);
        }
        return jsonrule(["status" => 406, "msg" => "FTP服务器登录失败"]);
    }
    public function deactivateFtp()
    {
        updateConfiguration("daily_ftp_backup_status", 0);
        return jsonrule(["status" => 200, "msg" => "执行成功"]);
    }
    public function backupEmail(\think\Request $request)
    {
        $param = $request->param();
        $daily_email_backup = strval(trim($param["daily_email_backup"]));
        if (empty($daily_email_backup)) {
            return jsonrule(["status" => 406, "msg" => "邮箱不能为空"]);
        }
        $reg = "/^([a-zA-Z0-9_\\-\\+]+)@([a-zA-Z0-9_\\-\\+]+)\\.([a-zA-Z]{0,5})\$/";
        if (!preg_match($reg, $daily_email_backup)) {
            return jsonrule(["status" => 406, "msg" => "邮箱格式错误"]);
        }
        updateConfiguration("daily_email_backup", $param["daily_email_backup"]);
        updateConfiguration("daily_email_backup_status", 1);
        return jsonrule(["status" => 200, "msg" => "保存成功"]);
    }
    public function deactivateEmail(\think\Request $request)
    {
        updateConfiguration("daily_email_backup_status", 0);
        return jsonrule(["status" => 200, "msg" => "执行成功"]);
    }
}

?>