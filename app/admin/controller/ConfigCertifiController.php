<?php

namespace app\admin\controller;

/**
 * @title 实名认证配置
 * @description 实名认证配置
 */
class ConfigCertifiController extends AdminBaseController
{
    protected $type = NULL;
    protected $three = NULL;
    protected $alipay_biz_code = NULL;
    public function setting()
    {
        $config = ["certifi_is_upload", "certifi_is_stop", "certifi_stop_day", "certifi_open", "certifi_select", "certifi_realname", "certifi_isbindphone", "artificial_auto_send_msg", "certifi_business_btn", "certifi_business_open", "certifi_business_is_upload", "certifi_business_is_author", "certifi_business_author_path"];
        $data = configuration($config);
        if (empty($data["certifi_select"])) {
            $data["certifi_select"] = "artificial";
        } else {
            $certifi_select = explode(",", $data["certifi_select"]);
            foreach ($certifi_select as &$value) {
                if ($value == "phonethree") {
                    $value = "Phonethree";
                } else {
                    if ($value == "three") {
                        $value = "Threehc";
                    } else {
                        if ($value == "ali") {
                            $value = "Ali";
                        }
                    }
                }
            }
            $data["certifi_select"] = implode(",", $certifi_select);
        }
        $certifi_plugin = array_column(getPluginsList(), "title", "name") ?: [];
        $data["certifi_select_all"] = array_merge(["artificial" => "人工审核"], $certifi_plugin);
        $data["certifi_business_author_path_url"] = $data["certifi_business_author_path"] ? config("author_attachments_url") . $data["certifi_business_author_path"] : "";
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data, "edition" => getEdition()]);
    }
    public function settingPost()
    {
        $arr = ["certifi_is_upload", "certifi_is_stop", "certifi_stop_day", "certifi_open", "certifi_select", "certifi_realname", "certifi_isbindphone", "artificial_auto_send_msg", "certifi_business_btn", "certifi_business_open", "certifi_business_is_upload", "certifi_business_is_author", "certifi_business_author_path"];
        $param = $this->request->only($arr);
        if (!getEdition() && ($param["artificial_auto_send_msg"] == 1 || $param["certifi_business_open"] == 1)) {
            updateConfiguration("artificial_auto_send_msg", 0);
            updateConfiguration("certifi_business_open", 0);
            updateConfiguration("certifi_business_is_upload", 0);
            updateConfiguration("certifi_business_is_author", 0);
            updateConfiguration("certifi_business_author_path", "");
            return jsonrule(["status" => 400, "msg" => "该功能仅专业版可用"]);
        }
        $param["certifi_select"] = implode(",", $param["certifi_select"]);
        $tmp = configuration($arr);
        if (!getEdition()) {
            $param["artificial_auto_send_msg"] = 0;
            $param["certifi_business_open"] = 0;
            $param["certifi_business_is_upload"] = 0;
            $param["certifi_business_is_author"] = 0;
            $param["certifi_business_author_path"] = "";
        }
        if ($param["certifi_business_open"] && $param["certifi_business_is_author"] && empty($param["certifi_business_author_path"])) {
            return jsonrule(["status" => 400, "msg" => "请上传授权书模板"]);
        }
        $dec = "";
        foreach ($param as $k => $v) {
            if ($k == "certifi_is_stop") {
                if ($v != $tmp[$k]) {
                    $tmp["setting"] = "未实名暂停产品";
                    if ($tmp[$k] == 1) {
                        $dec .= $tmp["setting"] . "由“开启”改为“关闭”，";
                    } else {
                        $dec .= $tmp["setting"] . "由“关闭”改为“开启”，";
                    }
                }
            }
            updateConfiguration($k, $v);
        }
        $dec && active_log_final(sprintf($this->lang["ConfigCer_admin_update"], $dec), 0, 4);
        return jsonrule(["status" => 200, "msg" => "设置成功"]);
    }
    public function authorDown()
    {
        try {
            $auth_path = configuration("certifi_business_author_path");
            if (!$auth_path) {
                throw new \think\Exception("文件资源不存在");
            }
            return download(config("author_attachments") . $auth_path, "shouQuan");
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function authorDel()
    {
        try {
            $auth_path = configuration("certifi_business_author_path");
            if (!$auth_path) {
                throw new \think\Exception("文件资源不存在");
            }
            unlink(config("author_attachments") . $auth_path);
            updateConfiguration("certifi_business_author_path", "");
            return jsonrule(["status" => 200, "msg" => "删除成功"]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function initialize()
    {
        parent::initialize();
        $this->type = config("certi_type");
        $this->three = [["name" => "两要素", "value" => "two"], ["name" => "三要素", "value" => "three"], ["name" => "四要素", "value" => "four"]];
        $this->alipay_biz_code = [["name" => "快捷认证（无需识别）", "value" => "SMART_FACE"], ["name" => "人脸识别", "value" => "FACE"], ["name" => "身份证识别", "value" => "CERT_PHOTO"], ["name" => "人脸+身份证", "value" => "CERT_PHOTO_FACE"]];
    }
    public function detail()
    {
        $where[] = ["setting", "like", "certifi%"];
        $data = \think\Db::name("configuration")->where($where)->select()->toArray();
        if (isset($data[0])) {
            $data = array_column($data, "value", "setting");
        }
        if (empty($data["certifi_select"])) {
            $data["certifi_select"] = NULL;
            if (empty($data["certifi_select"]) || $data["certifi_select"]) {
                $data["certifi_select"] = "artificial";
            } else {
                $data["certifi_select"] = configuration("certifi_type");
            }
        }
        return jsonrule(["status" => 200, "data" => $data]);
    }
    public function alipay_biz_code()
    {
        return jsonrule(["status" => 200, "data" => $this->alipay_biz_code]);
    }
    public function alipay_three_type()
    {
        return jsonrule(["status" => 200, "data" => $this->three]);
    }
    public function type()
    {
        return jsonrule(["status" => 200, "data" => $this->type]);
    }
    public function types()
    {
        $type = $this->type;
        foreach ($this->type as $key => $value) {
            if ($type[$key]["value"] == "artificial") {
                unset($type[$key]);
            }
        }
        $type = array_merge($type);
        return jsonrule(["status" => 200, "data" => $type]);
    }
    public function update()
    {
        $param = $this->request->only(["certifi_alipay_biz_code", "certifi_alipay_public_key", "certifi_app_id", "certifi_merchant_private_key", "certifi_type", "certifi_is_upload", "certifi_is_stop", "certifi_stop_day", "certifi_open", "certifi_appcode", "certifi_three_type", "certifi_select", "certifi_realname", "certifi_isbindphone", "certifi_isrealname", "name", "certifi_phonethree_appcode"]);
        $param["certifi_select"] = implode(",", $param["certifi_select"]);
        \think\Db::startTrans();
        try {
            $dec = "";
            $arr = array_column($this->type, "name", "value");
            $arr1 = array_column($this->alipay_biz_code, "name", "value");
            $arr2 = array_column($this->three, "name", "value");
            $arrs = $this->type;
            if (!empty($param["certifi_type"]) && !empty($param["name"])) {
                foreach ($arrs as $k => $v) {
                    if ($v["value"] == $param["certifi_type"]) {
                        $arrs[$k]["name"] = $param["name"];
                    }
                }
                $param["certi_typename"] = json_encode($arrs);
            }
            foreach ($param as $k => $v) {
                $tmp = \think\Db::name("configuration")->where("setting", $k)->find();
                updateConfiguration($k, $v);
                if ($v != $tmp["value"]) {
                    if ($v == "ali") {
                        $tmp["setting"] = "认证接口";
                        $tmp["value"] = $arr[$tmp["value"]];
                        $v = $arr[$v];
                        $dec .= $tmp["setting"] . "由“" . $tmp["value"] . "”改为“" . $v . "”，";
                    } else {
                        if ($k == "certifi_alipay_biz_code") {
                            $tmp["setting"] = "认证方式";
                            $tmp["value"] = $arr1[$tmp["value"]];
                            $v = $arr1[$v];
                            $dec .= $tmp["setting"] . "由“" . $tmp["value"] . "”改为“" . $v . "”，";
                        } else {
                            if ($k == "certifi_app_id") {
                                $tmp["setting"] = "APP ID";
                                $dec .= $tmp["setting"] . "由“" . $tmp["value"] . "”改为“" . $v . "”，";
                            } else {
                                if ($k == "certifi_alipay_public_key") {
                                    $tmp["setting"] = "支付宝公钥";
                                    $dec .= $tmp["setting"] . "有修改，";
                                } else {
                                    if ($k == " certifi_merchant_private_key") {
                                        $tmp["setting"] = "商户私钥";
                                        $dec .= $tmp["setting"] . "有修改，";
                                    } else {
                                        if ($k == " certifi_appcode") {
                                            $tmp["setting"] = "三要素code";
                                            $dec .= $tmp["setting"] . "由“" . $tmp["value"] . "”改为“" . $v . "”，";
                                        } else {
                                            if ($k == " certifi_three_type") {
                                                $tmp["setting"] = "三要素类型";
                                                $dec .= $arr2[$tmp["setting"]] . "由“" . $tmp["value"] . "”改为“" . $arr2[$v] . "”，";
                                            } else {
                                                if ($k == " certifi_realname") {
                                                    $tmp["setting"] = "同步名字";
                                                    $dec .= $arr2[$tmp["setting"]] . "由“" . $tmp["value"] . "”改为“" . $arr2[$v] . "”，";
                                                } else {
                                                    if ($k == "certifi_is_stop") {
                                                        $tmp["setting"] = "未实名暂停产品";
                                                        if ($tmp["value"] == 1) {
                                                            $dec .= $tmp["setting"] . "由“开启”改为“关闭”，";
                                                        } else {
                                                            $dec .= $tmp["setting"] . "由“关闭”改为“开启”，";
                                                        }
                                                    } else {
                                                        if ($k == "certifi_stop_day") {
                                                            $tmp["setting"] = "暂停期限";
                                                            $dec .= $tmp["setting"] . "由“" . $tmp["value"] . "”改为“" . $v . "”，";
                                                        } else {
                                                            if ($k == "certifi_isbindphone") {
                                                                $tmp["setting"] = "绑定手机是否一致";
                                                                $dec .= $tmp["setting"] . "由“" . $tmp["value"] . "”改为“" . $v . "”，";
                                                            } else {
                                                                if ($k == "certifi_isrealname") {
                                                                    $tmp["setting"] = "是否实名";
                                                                    $dec .= $tmp["setting"] . "由“" . $tmp["value"] . "”改为“" . $v . "”，";
                                                                } else {
                                                                    $dec .= $tmp["setting"] . "由“" . $tmp["value"] . "”改为“" . $v . "”，";
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if (empty($dec)) {
                $dec .= "没有任何修改";
            }
            active_log_final(sprintf($this->lang["ConfigCer_admin_update"], $dec), 0, 4);
            unset($dec);
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 406, "msg" => $e->getMessage()]);
        }
        return jsonrule(["status" => 200, "data" => $param, "msg" => "设置成功"]);
    }
}

?>