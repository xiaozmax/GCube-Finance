<?php


namespace app\admin\controller;

/**
 * @title 短信模块及模板配置
 * @description 接口说明:短信模块及模板配置
 */
class ConfigMessageController extends AdminBaseController
{
    private $validate = NULL;
    public function initialize()
    {
        parent::initialize();
        $this->validate = new \app\admin\validate\ConfigMessageValidate();
    }
    private function sms_operator_cn1()
    {
        $pluginModel = new \app\admin\model\PluginModel();
        $plugins = $pluginModel->getList("sms");
        $pluginsFilter = [];
        foreach ($plugins as $k => $v) {
            if ($v["status"] == 1) {
                $pluginsnOne = [];
                $pluginsnOne["label"] = strtolower($v["name"]);
                $pluginsnOne["value"] = $v["title"];
                $pluginsnOne["sms_type"] = $v["sms_type"];
                $pluginsFilter[] = $pluginsnOne;
            }
        }
        return $pluginsFilter;
    }
    public function configMobile()
    {
        $res = [];
        $res["status"] = 200;
        $res["msg"] = lang("SUCCESS MESSAGE");
        $data = [];
        $data["shd_allow_sms_send"] = configuration("shd_allow_sms_send") ? 1 : 0;
        $data["shd_allow_sms_send_global"] = configuration("shd_allow_sms_send_global") ? 1 : 0;
        $data["sms_operator"] = configuration("sms_operator");
        $data["sms_operator_global"] = configuration("sms_operator_global");
        $data["sms_operator_list"] = $this->sms_operator_cn1();
        $res["msg_config"] = $data;
        return jsonrule($res);
    }
    public function configMobilePost()
    {
        if ($this->request->isPost()) {
            return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function templateList()
    {
        $data = $this->request->param();
        $page = isset($data["page"]) && !empty($data["page"]) ? intval($data["page"]) : 1;
        $limit = isset($data["limit"]) && !empty($data["limit"]) ? intval($data["limit"]) : config("limit");
        $order = isset($data["order"]) && !empty($data["order"]) ? strtolower(trim($data["order"])) : "id";
        $allowed_order = ["id", "template_id", "type", "title", "content", "status"];
        if (!in_array($order, $allowed_order)) {
            return jsonrule(["status" => 400, "msg" => lang("OERDER_FIELD_ERROR")]);
        }
        $order_method = isset($data["order_method"]) && !empty($data["order_method"]) ? strtolower(trim($data["order_method"])) : "asc";
        $sms_operator = \think\Db::name("configuration")->where("setting", "sms_operator")->value("value");
        $smsoperator = isset($data["sms_operator"]) && !empty($data["sms_operator"]) ? strtolower(trim($data["sms_operator"])) : $sms_operator;
        $count = \think\Db::name("message_template")->where("sms_operator", $smsoperator)->where(function (\think\db\Query $query) use($data) {
            if (!empty($data["template_id"])) {
                $template_id = strtolower(trim($data["template_id"]));
                $query->where("template_id", "like", "%" . $template_id . "%");
            }
            if (!empty($data["title"])) {
                $keyword = strtolower(trim($data["title"]));
                $query->where("title", "like", "%" . $keyword . "%");
            }
        })->count();
        $templates = \think\Db::name("message_template")->where("sms_operator", $smsoperator)->where(function (\think\db\Query $query) use($data) {
            if (!empty($data["template_id"])) {
                $template_id = strtolower(trim($data["template_id"]));
                $query->where("template_id", $template_id);
            }
            if (!empty($data["title"])) {
                $keyword = strtolower(trim($data["title"]));
                $query->where("title", "like", "%" . $keyword . "%");
            }
        })->limit($limit * ($page - 1), $limit)->order($order . " " . $order_method)->select();
        $templatesfilter = [];
        foreach ($templates as $key => $template) {
            $templatesfilter[$key] = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $template);
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "total" => $count, "templates" => $templatesfilter, "data" => $this->sms_operator_cn1()]);
    }
    public function updateTemStatus()
    {
        $data = $this->request->param();
        $smsoperator = isset($data["sms_operator"]) && !empty($data["sms_operator"]) ? strtolower(trim($data["sms_operator"])) : configuration("sms_operator");
        $result = $this->updateStatus($smsoperator);
        if ($result) {
            return json(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
        }
        return json(["status" => 400, "msg" => lang("UPDATE FAIL")]);
    }
    protected function updateStatus($smsoperator)
    {
        $message_template = \think\Db::name("message_template")->field("template_id,range_type,status")->where("sms_operator", $smsoperator)->select();
        $sms["config"] = pluginConfig(ucfirst($smsoperator), "sms");
        foreach ($message_template as $template) {
            if (!(empty($template["template_id"]) || $template["status"] != 1)) {
                if ($template["range_type"] == 0) {
                    $getTemplate = "getCnTemplate";
                } else {
                    if ($template["range_type"] == 1) {
                        $getTemplate = "getGlobalTemplate";
                    } else {
                        if ($template["range_type"] == 2) {
                            $getTemplate = "getCnProTemplate";
                        }
                    }
                }
                $sms["template_id"] = $template["template_id"];
                $result = zjmfhook(ucfirst($smsoperator), "sms", $sms, $getTemplate);
                if ($result["status"] == "success") {
                    if ($result["template"]["template_status"] == 1) {
                        $statuss = "正在审核";
                        $status = 1;
                    } else {
                        if ($result["template"]["template_status"] == 2) {
                            $statuss = "审核通过";
                            $status = 2;
                        } else {
                            if ($result["template"]["template_status"] == 3) {
                                $statuss = "未通过审核（" . $result["template"]["msg"] . "）";
                                $status = 3;
                            }
                        }
                    }
                    \think\Db::name("message_template")->where("sms_operator", $smsoperator)->where("template_id", $template["template_id"])->update(["status" => $status]);
                    active_log(sprintf($this->lang["ConfigMessage_admin_update"], $template["template_id"], $statuss));
                }
            }
        }
        return true;
    }
    public function createTemplatePage()
    {
        $emailarg = new \app\common\logic\Sms();
        $emailarg->is_admin = true;
        $argsbase = $emailarg->getBaseArgphone();
        $argsarray["general"] = $emailarg->getReplaceArgphone("general");
        $argsarray["product"] = $emailarg->getReplaceArgphone("product");
        $argsarray["support"] = $emailarg->getReplaceArgphone("support");
        $argsarray["notification"] = $emailarg->getReplaceArgphone("notification");
        $argsarray["admin"] = $emailarg->getReplaceArgphone("admin");
        return jsonrule(["status" => 200, "base_args" => $argsbase, "combine" => $argsarray, "msg" => lang("SUCCESS MESSAGE"), "data" => config("message_template_type"), "allowedSmsOperator" => $this->sms_operator_cn1()]);
    }
    public function getTemplateDesc()
    {
        $params = $this->request->param();
        $description = zjmfhook(ucfirst($params["name"]), "sms", "", "description");
        $descriptionTemplate = zjmfhook(ucfirst($params["name"]), "sms", "", "descriptionTemplate");
        return jsonrule(["status" => 200, "description" => $description ?: "", "descriptionTemplate" => $descriptionTemplate ?: ["cn" => "", "global" => "", "cnpro" => ""], "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function createTemplate()
    {
        if ($this->request->isPost()) {
            $param = $this->request->only(["range_type", "sms_operator", "title", "content", "remark"]);
            $param = array_map("trim", $param);
            if (!$this->validate->scene("template_message")->check($param)) {
                return jsonrule(["status" => 400, "msg" => $this->validate->getError()]);
            }
            $temp = [];
            $sms_operator = $param["sms_operator"];
            $param["template_id"] = "";
            $param["status"] = 0;
            $param["create_time"] = time();
            $temp_id = \think\Db::name("message_template")->insertGetId($param);
            if ($temp_id) {
                active_log(sprintf($this->lang["ConfigMessage_admin_create"], $temp_id));
                return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
            }
            return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function updateTemplate()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) ? intval($params["id"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $temp = \think\Db::name("message_template")->field("id,template_id,range_type,sms_operator,title,content,remark,status")->where("id", $id)->find();
        if (isset($temp["status"]) && $temp["status"] == 1) {
            return jsonrule(["status" => 400, "msg" => lang("MESSAGE_TEMPLATE_CHECK_ING")]);
        }
        $status = [["id" => 0, "name" => "未提交审核"], ["id" => 1, "name" => "正在审核"], ["id" => 2, "name" => "审核通过"], ["id" => 3, "name" => "未通过审核"]];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "temp" => $temp, "status" => $status]);
    }
    public function updateTemplatePost()
    {
        if ($this->request->isPost()) {
            $param = $this->request->only(["id", "template_id", "range_type", "sms_operator", "title", "content", "remark", "status"]);
            $param = array_map("trim", $param);
            $id = isset($param["id"]) ? intval($param["id"]) : "";
            if (!$id) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            if (!$this->validate->scene("template_message")->check($param)) {
                return jsonrule(["status" => 400, "msg" => $this->validate->getError()]);
            }
            $template = \think\Db::name("message_template")->where("id", $id)->find();
            $temp_id = $template["template_id"];
            $type = $template["sms_operator"];
            $sms["config"] = pluginConfig(ucfirst($type), "sms");
            $sms["template_id"] = $param["template_id"];
            $sms["title"] = $param["title"];
            $sms["content"] = $param["content"];
            $sms["remark"] = $param["remark"];
            if (!empty($temp_id) || !empty($param["template_id"])) {
                if ($param["range_type"] == 0) {
                    $putTemplate = "putCnTemplate";
                } else {
                    if ($param["range_type"] == 1) {
                        $putTemplate = "putGlobalTemplate";
                    } else {
                        if ($param["range_type"] == 2) {
                            $putTemplate = "putCnProTemplate";
                        }
                    }
                }
                $result = zjmfhook(ucfirst($type), "sms", $sms, $putTemplate);
                if ($result["status"] == "success") {
                    $param["update_time"] = time();
                    $param["status"] = $result["template"]["template_status"] ?: 1;
                    $res = \think\Db::name("message_template")->where("id", $id)->update($param);
                    if ($res) {
                        active_log(sprintf($this->lang["ConfigMessage_admin_edit"], $temp_id));
                        return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
                    }
                    return jsonrule(["status" => 400, "msg" => lang("UPDATE FAIL")]);
                }
                return jsonrule(["status" => 400, "msg" => lang("UPDATE FAIL") . ":" . $result["msg"]]);
            }
            $param["update_time"] = time();
            $param["status"] = 0;
            $res = \think\Db::name("message_template")->where("id", $id)->update($param);
            if ($res) {
                active_log(sprintf($this->lang["ConfigMessage_admin_edit"], $temp_id));
                return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
            }
            return jsonrule(["status" => 400, "msg" => lang("UPDATE FAIL")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function checkPost()
    {
        $data = $this->request->param();
        session_write_close();
        $allmsg = [];
        if (isset($data["ids"]) && !empty($data["ids"])) {
            $ids = $data["ids"];
            if (is_string($ids) && !is_array($ids)) {
                $ids = [$ids];
            }
            $type = strtolower(trim($data["type"]));
            $sms = [];
            $template = [];
            $message_template = \think\Db::name("message_template")->whereIn("id", $ids)->where("sms_operator", $type)->select()->toArray();
            $sms["config"] = pluginConfig(ucfirst($type), "sms");
            foreach ($message_template as $template) {
                if (!empty($template)) {
                    $sms["title"] = $template["title"];
                    $sms["content"] = $template["content"];
                    $sms["remark"] = $template["remark"];
                    if ($template["range_type"] == 0) {
                        $createTemplate = "createCnTemplate";
                    } else {
                        if ($template["range_type"] == 1) {
                            $createTemplate = "createGlobalTemplate";
                        } else {
                            if ($template["range_type"] == 2) {
                                $createTemplate = "createCnProTemplate";
                            }
                        }
                    }
                    $result = zjmfhook(ucfirst($type), "sms", $sms, $createTemplate);
                    if ($result["status"] == "success") {
                        $template["template_id"] = $result["template"]["template_id"];
                        if ($template["template_id"] && empty($result["template"]["template_status"])) {
                            $template["status"] = 1;
                        } else {
                            $template["status"] = $result["template"]["template_status"] ? $result["template"]["template_status"] : 0;
                        }
                        $template["update_time"] = time();
                        \think\Db::name("message_template")->where("id", $template["id"])->where("sms_operator", $type)->update($template);
                        $msg = lang("MESSAGE_TEMPLATE_CHECK_SUCCESS", ["id" => $template["id"]]);
                    } else {
                        $msg = lang("MESSAGE_TEMPLATE_CHECK_FAIL", ["id" => $template["id"]]) . $result["msg"];
                    }
                    array_push($allmsg, $msg);
                }
            }
            return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "checkmsg" => implode("<br>", $allmsg)]);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("MOBILE_SUBMAIL_BATCH_CHECK")]);
        }
    }
    public function deleteTemplate()
    {
        $param = $this->request->param();
        $type = isset($param["type"]) ? strtolower(trim($param["type"])) : "aliyun";
        if (isset($param["ids"]) && !empty($param["ids"])) {
            $ids = $param["ids"];
            if (is_string($ids) && !is_array($ids)) {
                $ids = [$ids];
            }
            $sms["config"] = pluginConfig(ucfirst($type), "sms");
            foreach ($ids as $id) {
                $temp = \think\Db::name("message_template")->field("template_id,range_type")->where("id", $id)->where("sms_operator", $type)->find();
                if (!empty($temp)) {
                    try {
                        \think\Db::name("message_template")->where("id", $id)->where("sms_operator", $type)->delete();
                        \think\Db::name("message_template_link")->where("sms_temp_id", $id)->where("sms_operator", $type)->delete();
                        active_log(sprintf($this->lang["ConfigMessage_admin_delete"], $id));
                        $sms["template_id"] = $temp["template_id"];
                        if ($temp["range_type"] == 0) {
                            $deleteTemplate = "deleteCnTemplate";
                        } else {
                            if ($temp["range_type"] == 1) {
                                $deleteTemplate = "deleteGlobalTemplate";
                            } else {
                                if ($temp["range_type"] == 2) {
                                    $deleteTemplate = "deleteCnProTemplate";
                                }
                            }
                        }
                        zjmfhook(ucfirst($type), "sms", $sms, $deleteTemplate);
                    } catch (\Exception $e) {
                    }
                }
            }
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("MOBILE_SUBMAIL_BATCH_CHECK")]);
        }
    }
    public function SetSmsTemplate()
    {
        $param = $this->request->param();
        $range_type = isset($param["range_type"]) ? intval($param["range_type"]) : 0;
        if ($range_type == 0) {
            $data["shd_allow_sms_send"] = configuration("shd_allow_sms_send") ? 1 : 0;
            $sms_operator = !empty($param["sms_operator"]) ? strtolower(trim($param["sms_operator"])) : configuration("sms_operator");
            $data["sms_operator"] = $sms_operator;
        } else {
            if ($range_type == 1) {
                $data["shd_allow_sms_send_global"] = configuration("shd_allow_sms_send_global") ? 1 : 0;
                $sms_operator = !empty($param["sms_operator"]) ? strtolower(trim($param["sms_operator"])) : configuration("sms_operator_global");
                $data["sms_operator_global"] = $sms_operator;
            }
        }
        $sms_operator_cn1 = $this->sms_operator_cn1();
        foreach ($sms_operator_cn1 as $sms_type) {
            $sms_type_name = array_column($sms_type["sms_type"], "name");
            unset($sms_type["sms_type"]);
            if (in_array("global", $sms_type_name) && $range_type == 1) {
                $sms_operator_cn[] = $sms_type;
            } else {
                if (in_array("cn", $sms_type_name) && $range_type == 0) {
                    $sms_operator_cn[] = $sms_type;
                }
            }
        }
        $data["allowedSmsOperator"] = $sms_operator_cn;
        if (empty($sms_operator)) {
            $sms_operator = $sms_operator_cn[0]["label"];
        }
        $select_temps = \think\Db::name("message_template_link")->where("sms_operator", $sms_operator)->where("range_type", $range_type)->select()->toArray();
        $select_temps_filter = [];
        foreach ($select_temps as $k => $select_temp) {
            switch ($select_temp["type"]) {
                case 1:
                    $select_temp["type"] = "generated_invoice";
                    break;
                case 2:
                    $select_temp["type"] = "invoice_pay";
                    break;
                case 3:
                    $select_temp["type"] = "invoice_overdue_pay";
                    break;
                case 4:
                    $select_temp["type"] = "submit_ticket";
                    break;
                case 5:
                    $select_temp["type"] = "ticket_reply";
                    break;
                case 6:
                    $select_temp["type"] = "host_suspend";
                    break;
                case 7:
                    $select_temp["type"] = "unpay_invoice";
                    break;
                case 8:
                    $select_temp["type"] = "send_code";
                    break;
                case 9:
                    $select_temp["type"] = "login_sms_remind";
                    break;
                case 10:
                    $select_temp["type"] = "order_refund";
                    break;
                case 11:
                    $select_temp["type"] = "admin_order_paid";
                    break;
                case 12:
                    $select_temp["type"] = "admin_order";
                    break;
                case 13:
                    $select_temp["type"] = "invoice_payment_confirmation";
                    break;
                case 14:
                    $select_temp["type"] = "second_renew_product_reminder";
                    break;
                case 15:
                    $select_temp["type"] = "renew_product_reminder";
                    break;
                case 16:
                    $select_temp["type"] = "third_invoice_payment_reminder";
                    break;
                case 17:
                    $select_temp["type"] = "second_invoice_payment_reminder";
                    break;
                case 18:
                    $select_temp["type"] = "first_invoice_payment_reminder";
                    break;
                case 19:
                    $select_temp["type"] = "new_order_notice";
                    break;
                case 20:
                    $select_temp["type"] = "admin_product_suspension_faild";
                    break;
                case 21:
                    $select_temp["type"] = "admin_login_success";
                    break;
                case 22:
                    $select_temp["type"] = "admin_new_ticket_reply";
                    break;
                case 23:
                    $select_temp["type"] = "admin_new_ticket";
                    break;
                case 24:
                    $select_temp["type"] = "default_product_welcome";
                    break;
                case 25:
                    $select_temp["type"] = "zjmf_dcim_rebuild_system_success";
                    break;
                case 26:
                    $select_temp["type"] = "service_termination_notification";
                    break;
                case 27:
                    $select_temp["type"] = "service_unsuspension_notification";
                    break;
                case 28:
                    $select_temp["type"] = "uncertify_reminder";
                    break;
                case 29:
                    $select_temp["type"] = "zjmf_dcim_product_welcome";
                    break;
                case 30:
                    $select_temp["type"] = "support_ticket_auto_close_notification";
                    break;
                case 31:
                    $select_temp["type"] = "support_ticket_opened";
                    break;
                case 32:
                    $select_temp["type"] = "email_bond_notice";
                    break;
                case 33:
                    $select_temp["type"] = "registration_success";
                    break;
                case 34:
                    $select_temp["type"] = "credit_limit_invoice_notice";
                    break;
                case 35:
                    $select_temp["type"] = "credit_limit_invoice_payment_reminder";
                    break;
                case 36:
                    $select_temp["type"] = "credit_limit_invoice_payment_reminder_host_suspend";
                    break;
                case 37:
                    $select_temp["type"] = "resume_use";
                    break;
                case 38:
                    $select_temp["type"] = "realname_pass_remind";
                    break;
                case 39:
                    $select_temp["type"] = "binding_remind";
                    break;
                default:
                    $select_temps_filter[$k] = $select_temp;
            }
        }
        $templates = \think\Db::name("message_template")->field("id,title as temp")->where("sms_operator", $sms_operator)->where("range_type", $range_type)->select()->toArray();
        foreach ($templates as $key => $template) {
            $templatesfilter[$key] = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $template);
        }
        $message_template_lists = \think\facade\Config::get("message_template_lists");
        return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "message_template_lists" => $message_template_lists, "range_type" => $range_type, "templates" => $templatesfilter, "select_temp" => $select_temps_filter, "sms_setting" => $data]);
    }
    public function SetSmsTemplatePost()
    {
        if ($this->request->isPost()) {
            $dec = "";
            $param = $this->request->param();
            $range_type = isset($param["range_type"]) ? intval($param["range_type"]) : 0;
            if ($range_type == 0) {
                if (empty($param["sms_operator"])) {
                    return jsonrule(["status" => 400, "msg" => "请选择国内短信供应商"]);
                }
                $sms_operator = strtolower(trim($param["sms_operator"]));
                if (isset($param["shd_allow_sms_send"])) {
                    updateConfiguration("shd_allow_sms_send", intval($param["shd_allow_sms_send"]));
                }
                updateConfiguration("sms_operator", $sms_operator);
            }
            if ($range_type == 1) {
                if (empty($param["sms_operator_global"])) {
                    return jsonrule(["status" => 400, "msg" => "请选择国际短信供应商"]);
                }
                $sms_operator = strtolower(trim($param["sms_operator_global"]));
                if (isset($param["shd_allow_sms_send_global"])) {
                    updateConfiguration("shd_allow_sms_send_global", intval($param["shd_allow_sms_send_global"]));
                }
                updateConfiguration("sms_operator_global", $sms_operator);
            }
            $generated_invoice = isset($param["generated_invoice"]) ? $param["generated_invoice"] : 0;
            $invoice_pay = isset($param["invoice_pay"]) ? $param["invoice_pay"] : 0;
            $invoice_overdue_pay = isset($param["invoice_overdue_pay"]) ? $param["invoice_overdue_pay"] : 0;
            $submit_ticket = isset($param["submit_ticket"]) ? $param["submit_ticket"] : 0;
            $ticket_reply = isset($param["ticket_reply"]) ? $param["ticket_reply"] : 0;
            $host_suspend = isset($param["host_suspend"]) ? $param["host_suspend"] : 0;
            $unpay_invoice = isset($param["unpay_invoice"]) ? $param["unpay_invoice"] : 0;
            $send_code = isset($param["send_code"]) ? $param["send_code"] : 0;
            $uncertify_reminder = isset($param["uncertify_reminder"]) ? $param["uncertify_reminder"] : 0;
            $login_sms_remind = isset($param["login_sms_remind"]) ? $param["login_sms_remind"] : 0;
            $order_refund = isset($param["order_refund"]) ? $param["order_refund"] : 0;
            $admin_order_paid = isset($param["admin_order_paid"]) ? $param["admin_order_paid"] : 0;
            $admin_order = isset($param["admin_order"]) ? $param["admin_order"] : 0;
            $invoice_payment_confirmation = isset($param["invoice_payment_confirmation"]) ? $param["invoice_payment_confirmation"] : 0;
            $second_renew_product_reminder = isset($param["second_renew_product_reminder"]) ? $param["second_renew_product_reminder"] : 0;
            $renew_product_reminder = isset($param["renew_product_reminder"]) ? $param["renew_product_reminder"] : 0;
            $third_invoice_payment_reminder = isset($param["third_invoice_payment_reminder"]) ? $param["third_invoice_payment_reminder"] : 0;
            $second_invoice_payment_reminder = isset($param["second_invoice_payment_reminder"]) ? $param["second_invoice_payment_reminder"] : 0;
            $first_invoice_payment_reminder = isset($param["first_invoice_payment_reminder"]) ? $param["first_invoice_payment_reminder"] : 0;
            $new_order_notice = isset($param["new_order_notice"]) ? $param["new_order_notice"] : 0;
            $admin_product_suspension_faild = isset($param["admin_product_suspension_faild"]) ? $param["admin_product_suspension_faild"] : 0;
            $admin_login_success = isset($param["admin_login_success"]) ? $param["admin_login_success"] : 0;
            $admin_new_ticket_reply = isset($param["admin_new_ticket_reply"]) ? $param["admin_new_ticket_reply"] : 0;
            $admin_new_ticket = isset($param["admin_new_ticket"]) ? $param["admin_new_ticket"] : 0;
            $default_product_welcome = isset($param["default_product_welcome"]) ? $param["default_product_welcome"] : 0;
            $zjmf_dcim_rebuild_system_success = isset($param["zjmf_dcim_rebuild_system_success"]) ? $param["zjmf_dcim_rebuild_system_success"] : 0;
            $service_termination_notification = isset($param["service_termination_notification"]) ? $param["service_termination_notification"] : 0;
            $service_unsuspension_notification = isset($param["service_unsuspension_notification"]) ? $param["service_unsuspension_notification"] : 0;
            $service_suspension_notification = isset($param["service_suspension_notification"]) ? $param["service_suspension_notification"] : 0;
            $zjmf_dcim_product_welcome = isset($param["zjmf_dcim_product_welcome"]) ? $param["zjmf_dcim_product_welcome"] : 0;
            $support_ticket_auto_close_notification = isset($param["support_ticket_auto_close_notification"]) ? $param["support_ticket_auto_close_notification"] : 0;
            $support_ticket_opened = isset($param["support_ticket_opened"]) ? $param["support_ticket_opened"] : 0;
            $email_bond_notice = isset($param["email_bond_notice"]) ? $param["email_bond_notice"] : 0;
            $registration_success = isset($param["registration_success"]) ? $param["registration_success"] : 0;
            $credit_limit_invoice_notice = isset($param["credit_limit_invoice_notice"]) ? $param["credit_limit_invoice_notice"] : 0;
            $credit_limit_invoice_payment_reminder = isset($param["credit_limit_invoice_payment_reminder"]) ? $param["credit_limit_invoice_payment_reminder"] : 0;
            $credit_limit_invoice_payment_reminder_host_suspend = isset($param["credit_limit_invoice_payment_reminder_host_suspend"]) ? $param["credit_limit_invoice_payment_reminder_host_suspend"] : 0;
            $resume_use = isset($param["resume_use"]) ? $param["resume_use"] : 0;
            $realname_pass_remind = isset($param["realname_pass_remind"]) ? $param["realname_pass_remind"] : 0;
            $binding_remind = isset($param["binding_remind"]) ? $param["binding_remind"] : 0;
            \think\Db::startTrans();
            try {
                if (!empty($generated_invoice[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 1, $generated_invoice);
                }
                if (!empty($invoice_pay[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 2, $invoice_pay);
                }
                if (!empty($invoice_overdue_pay[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 3, $invoice_overdue_pay);
                }
                if (!empty($submit_ticket[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 4, $submit_ticket);
                }
                if (!empty($ticket_reply[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 5, $ticket_reply);
                }
                if (!empty($host_suspend[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 6, $host_suspend);
                }
                if (!empty($unpay_invoice[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 7, $unpay_invoice);
                }
                if (!empty($send_code[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 8, $send_code);
                }
                if (!empty($login_sms_remind[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 9, $login_sms_remind);
                }
                if (!empty($order_refund[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 10, $order_refund);
                }
                if (!empty($admin_order_paid[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 11, $admin_order_paid);
                }
                if (!empty($admin_order[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 12, $admin_order);
                }
                if (!empty($invoice_payment_confirmation[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 13, $invoice_payment_confirmation);
                }
                if (!empty($second_renew_product_reminder[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 14, $second_renew_product_reminder);
                }
                if (!empty($renew_product_reminder[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 15, $renew_product_reminder);
                }
                if (!empty($third_invoice_payment_reminder[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 16, $third_invoice_payment_reminder);
                }
                if (!empty($second_invoice_payment_reminder[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 17, $second_invoice_payment_reminder);
                }
                if (!empty($first_invoice_payment_reminder[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 18, $first_invoice_payment_reminder);
                }
                if (!empty($new_order_notice[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 19, $new_order_notice);
                }
                if (!empty($admin_product_suspension_faild[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 20, $admin_product_suspension_faild);
                }
                if (!empty($admin_login_success[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 21, $admin_login_success);
                }
                if (!empty($admin_new_ticket_reply[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 22, $admin_new_ticket_reply);
                }
                if (!empty($admin_new_ticket[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 23, $admin_new_ticket);
                }
                if (!empty($default_product_welcome[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 24, $default_product_welcome);
                }
                if (!empty($zjmf_dcim_rebuild_system_success[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 25, $zjmf_dcim_rebuild_system_success);
                }
                if (!empty($service_termination_notification[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 26, $service_termination_notification);
                }
                if (!empty($service_unsuspension_notification[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 27, $service_unsuspension_notification);
                }
                if (!empty($uncertify_reminder[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 28, $uncertify_reminder);
                }
                if (!empty($zjmf_dcim_product_welcome[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 29, $zjmf_dcim_product_welcome);
                }
                if (!empty($support_ticket_auto_close_notification[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 30, $support_ticket_auto_close_notification);
                }
                if (!empty($support_ticket_opened[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 31, $support_ticket_opened);
                }
                if (!empty($email_bond_notice[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 32, $email_bond_notice);
                }
                if (!empty($registration_success[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 33, $registration_success);
                }
                if (!empty($credit_limit_invoice_notice[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 34, $credit_limit_invoice_notice);
                }
                if (!empty($credit_limit_invoice_payment_reminder[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 35, $credit_limit_invoice_payment_reminder);
                }
                if (!empty($credit_limit_invoice_payment_reminder_host_suspend[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 36, $credit_limit_invoice_payment_reminder_host_suspend);
                }
                if (!empty($resume_use[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 37, $resume_use);
                }
                if (!empty($realname_pass_remind[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 38, $realname_pass_remind);
                }
                if (!empty($binding_remind[0])) {
                    $this->updateOrInsert($sms_operator, $range_type, 39, $binding_remind);
                }
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => lang("UPDATE FAIL")]);
            }
            return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    protected function updateOrInsert($sms_operator, $range_type, $type, $temp_id)
    {
        $exist = \think\Db::name("message_template_link")->where("sms_operator", $sms_operator)->where("range_type", $range_type)->where("type", $type)->find();
        if (!empty($exist)) {
            $exist1 = \think\Db::name("message_template")->field("title,id")->where("id", $exist["sms_temp_id"])->find();
            $res = \think\Db::name("message_template_link")->where("sms_operator", $sms_operator)->where("range_type", $range_type)->where("type", $type)->update(["sms_temp_id" => $temp_id[0], "is_use" => $temp_id[1]]);
            if ($temp_id != $exist1["id"]) {
                active_log(sprintf($this->lang["ConfigMessage_admin_SetSmsTemplatePost"], $exist["id"], $temp_id[0], $exist1["title"]));
            }
        } else {
            $res = \think\Db::name("message_template_link")->insertGetId(["type" => $type, "sms_temp_id" => $temp_id[0], "range_type" => $range_type, "sms_operator" => $sms_operator, "is_use" => $temp_id[1]]);
            active_log(sprintf($this->lang["ConfigMessage_admin_SetSmsTemplateadd"], $res));
        }
        return $res;
    }
    public function testMessageTemplatePage()
    {
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "sms" => getCountryCode()]);
    }
    public function testMessageTemplate()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $id = isset($param["id"]) ? intval($param["id"]) : "";
            $phone = $param["code"] . $param["phone"];
            if (!$id) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            if (empty($phone)) {
                return jsonrule(["status" => 400, "msg" => lang("MESSAGE_TEMPLATE_TEST_PHONE")]);
            }
            $Sms = new \app\common\logic\Sms();
            $rangeType = $Sms->rangeType($phone);
            if ($rangeType == 0) {
                if (configuration("shd_allow_sms_send") == 0) {
                    return jsonrule(["status" => 400, "msg" => lang("国内短信功能已关闭")]);
                }
                $sms_operator = configuration("sms_operator");
            } else {
                if ($rangeType == 1) {
                    if (configuration("shd_allow_sms_send_global") == 0) {
                        return jsonrule(["status" => 400, "msg" => lang("国际短信功能已关闭")]);
                    }
                    $sms_operator = configuration("sms_operator_global");
                }
            }
            $temp = \think\Db::name("message_template")->field("template_id,range_type,content,status,sms_operator")->where("id", $id)->find();
            if (empty($temp)) {
                return jsonrule(["status" => 400, "msg" => lang("MESSAGE_TEMPLATE_NO_EXIST")]);
            }
            if (isset($temp["status"]) && $temp["status"] != 2) {
                return jsonrule(["status" => 400, "msg" => lang("MESSAGE_TEMPLATE_NOPASS_CHECK")]);
            }
            $templateParam = $Sms->templateParam();
            foreach ($templateParam as $k => $v) {
                if ($v == NULL) {
                    $templateParam[$k] = "测试短信模板";
                } else {
                    if ($v == "1970-01-01 08:00:00") {
                        $templateParam[$k] = "0000-00-00 00:00:00";
                    }
                }
            }
            $params = ["content" => $temp["content"], "template_id" => $temp["template_id"], "mobile" => $Sms->mobile86($temp["range_type"], $phone)];
            $result = $Sms->send($params, $templateParam, $temp["range_type"]);
            if ($result["status"] == "success") {
                return jsonrule(["status" => 200, "msg" => lang("发送成功,请注意查收")]);
            }
            return jsonrule(["status" => 400, "msg" => lang("发送失败,失败原因:") . $result["msg"]]);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function sendSmsTest()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $phone = $param["code"] . $param["phone"];
            $Sms = new \app\common\logic\Sms();
            $rangeType = $Sms->rangeType($phone);
            if ($rangeType == 0) {
                if (configuration("shd_allow_sms_send") == 0) {
                    return jsonrule(["status" => 400, "msg" => lang("国内短信功能已关闭")]);
                }
                $sms_operator = configuration("sms_operator");
            } else {
                if ($rangeType == 1) {
                    if (configuration("shd_allow_sms_send_global") == 0) {
                        return jsonrule(["status" => 400, "msg" => lang("国际短信功能已关闭")]);
                    }
                    $sms_operator = configuration("sms_operator_global");
                }
            }
            if (empty($phone)) {
                return jsonrule(["status" => 400, "msg" => lang("MESSAGE_TEMPLATE_TEST_PHONE")]);
            }
            $temp = \think\Db::name("message_template")->field("template_id,range_type,content,status,sms_operator")->where("status", 2)->where("range_type", $rangeType)->where("sms_operator", $sms_operator)->find();
            if (empty($temp)) {
                return jsonrule(["status" => 400, "msg" => lang("没有已审核通过的短信模板，请检查")]);
            }
            $templateParam = $Sms->templateParam();
            foreach ($templateParam as $k => $v) {
                if ($v == NULL) {
                    $templateParam[$k] = "测试短信";
                } else {
                    if ($v == "1970-01-01 08:00:00") {
                        $templateParam[$k] = "0000-00-00 00:00:00";
                    }
                }
            }
            $params = ["content" => $temp["content"], "template_id" => $temp["template_id"], "mobile" => $Sms->mobile86($temp["range_type"], $phone)];
            $result = $Sms->send($params, $templateParam, $rangeType);
            if ($result["status"] == "success") {
                return jsonrule(["status" => 200, "msg" => lang("发送成功,请注意查收")]);
            }
            return jsonrule(["status" => 400, "msg" => lang("发送失败,失败原因:") . $result["msg"]]);
        } else {
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function mobiletemplateList()
    {
        $sms_operator = configuration("sms_operator") ?? "";
        $param = $this->request->param();
        $templates = \think\Db::name("message_template")->field("id,title,template_id,range_type,content,status,sms_operator")->where("sms_operator", $sms_operator)->where("status", 2)->select();
        $templatesfilter = [];
        foreach ($templates as $key => $template) {
            $templatesfilter[$key] = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $template);
        }
        foreach ($templatesfilter as $key => $val) {
            $message_template_link = \think\Db::name("message_template_link")->where("sms_temp_id", $val["id"])->where("is_use", 1)->find();
            $message_template_type = array_column(config("message_template_type"), "name", "id");
            $mty = $message_template_type[$message_template_link["type"]];
            if ($mty != "default_product_welcome" && $mty != "service_termination_notification" && $mty != "host_suspend" && $mty != "service_unsuspension_notification") {
                unset($templatesfilter[$key]);
            }
        }
        $templatesfilter = array_values($templatesfilter);
        $default = [];
        foreach ($templatesfilter as $key => $value) {
            if ($value["title"] == "产品开通") {
                $default = $value;
                return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "templates" => $templatesfilter, "default" => $default]);
            }
        }
    }
    public function emailtemplateList()
    {
        $param = $this->request->param();
        $host = \think\Db::name("host")->alias("h")->join("products p", "p.id=h.productid")->where("h.id", $param["hid"])->field("p.type")->find();
        $results = \think\Db::name("email_templates")->field("id,type,disabled,name,custom")->where(function (\think\db\Query $query) {
            $data = $this->request->param();
            if (!empty($data["keyword"])) {
                $keyword = $data["keyword"];
                $query->where("name", "like", "%" . $keyword . "%");
            }
        })->where("type", "product")->where("language", "")->select();
        foreach ($results as $key => $value) {
            $elist[$key] = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $value);
        }
        $default = [];
        foreach ($results as $key => $value) {
            if ($host["type"] == "dcim") {
                if ($value["name"] == "裸金属产品开通邮件") {
                    $default = $value;
                }
            } else {
                if ($host["type"] == "cloud") {
                    if ($value["name"] == "云服务器开通邮件") {
                        $default = $value;
                    }
                } else {
                    if ($value["name"] == "服务器开通邮件") {
                        $default = $value;
                    }
                }
            }
        }
        return jsonrule(["status" => 200, "msg" => "请求成功", "default" => $default]);
    }
    public function sendEmailTest()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            if (empty($param["email"])) {
                return jsonrule(["status" => 400, "msg" => lang("邮箱不能为空")]);
            }
            $email_logic = new \app\common\logic\Email();
            $result = $email_logic->sendEmailCode($param["email"], rand(10000, 99999));
            if ($result["status"] == "success") {
                return jsonrule(["status" => 200, "msg" => lang("发送成功,请注意查收")]);
            }
            return jsonrule(["status" => 400, "msg" => lang("发送失败,失败原因:") . $result["msg"]]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function sendMessagePost1()
    {
        if ($this->request->isPost()) {
            $host = [];
            $param = $this->request->param();
            $param = array_map("trim", $param);
            $user = \think\Db::name("clients")->where("id", $param["id"])->find();
            if (!empty($param["msgtype"]) && $param["msgtype"]) {
                if (empty($user["phonenumber"])) {
                    return jsonrule(["status" => 400, "msg" => lang("手机号码为空")]);
                }
                $temp = \think\Db::name("message_template")->where("id", $param["msgid"])->find();
                if (empty($temp)) {
                    return jsonrule(["status" => 400, "msg" => lang("短信模板为空")]);
                }
                $type = $temp["sms_operator"];
                $message_template_link = \think\Db::name("message_template_link")->where("sms_temp_id", $param["msgid"])->where("sms_operator", $type)->where("is_use", 1)->find();
                if (empty($message_template_link)) {
                    return jsonrule(["status" => 400, "msg" => "未开启此模板发送"]);
                }
                $message_template_type = array_column(config("message_template_type"), "name", "id");
                $mty = $message_template_type[$message_template_link["type"]];
                $host = \think\Db::name("host")->alias("a")->field("a.port")->field("a.id,a.uid,a.productid,a.domainstatus,a.regdate,b.welcome_email,b.type,c.email,a.billingcycle,b.pay_type,b.name,a.nextduedate,a.billingcycle,a.dedicatedip,a.username,a.password,a.os,a.assignedips,a.create_time,a.nextinvoicedate,a.domain,a.suspendreason")->leftJoin("products b", "a.productid=b.id")->leftJoin("clients c", "a.uid=c.id")->where("a.id", $param["hid"])->find();
                if ($mty != "default_product_welcome") {
                    if ($mty != "service_termination_notification") {
                        if ($mty != "host_suspend") {
                            if ($mty == "service_unsuspension_notification") {
                            }
                        }
                    }
                }
                $content = $temp["content"];
                $billing_cycle = config("billing_cycle");
                $newvar = ["product_name" => $host["name"], "product_mainip" => $host["dedicatedip"], "product_passwd" => cmf_decrypt($host["password"]), "product_dcimbms_os" => $host["os"], "product_addonip" => $host["assignedips"], "product_first_time" => date("Y-m-d H:i:s", $host["create_time"]), "product_end_time" => 0 < $host["nextduedate"] ? date("Y-m-d H:i:s", $host["nextduedate"]) : "", "product_binlly_cycle" => $billing_cycle[$host["billingcycle"]], "hostname" => $host["domain"], "description" => $host["suspendreason"], "product_terminate_time" => date("Y-m-d H:i:s", time())];
                $newvar["product_mainip"] .= $host["port"] ? ":" . $host["port"] : "";
                $sms = new \app\common\logic\Sms();
                $ret = sendmsglimit($user["phonenumber"]);
                if ($ret["status"] == 400) {
                    return json(["status" => 400, "msg" => lang("SEND FAIL") . ":" . $ret["msg"]]);
                }
                $result = $sms->sendSms($message_template_link["type"], $user["phone_code"] . $user["phonenumber"], $newvar, false, $user["id"]);
                if ($result["status"] == 200) {
                    $data = ["ip" => get_client_ip6(), "phone" => $user["phonenumber"], "time" => time()];
                    \think\Db::name("sendmsglimit")->insertGetId($data);
                    return jsonrule(["status" => 200, "msg" => lang("发送成功,请注意查收")]);
                }
                return jsonrule(["status" => 400, "msg" => lang("发送失败,失败原因:") . $result["msg"]]);
            }
            if (!empty($param["ematype"]) && $param["ematype"]) {
                if (empty($user["email"])) {
                    return jsonrule(["status" => 400, "msg" => lang("邮件为空")]);
                }
                $temp = \think\Db::name("email_templates")->where("id", $param["emaid"])->find();
                if (empty($temp)) {
                    return jsonrule(["status" => 400, "msg" => lang("邮件模板为空")]);
                }
                $email = new \app\common\logic\Email();
                $res = $email->sendEmailBase($param["hid"], $temp["name"], "product", false);
                if ($res) {
                    return jsonrule(["status" => 200, "msg" => lang("发送成功,请注意查收")]);
                }
                return jsonrule(["status" => 400, "msg" => "发送失败"]);
            }
            return jsonrule(["status" => 400, "msg" => "请勾选需要发送的模板"]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function sendMessagePost()
    {
        try {
            $param = array_map("trim", $this->request->param());
            $user = \think\Db::name("clients")->where("id", $param["id"])->find();
            $data = $this->beforeSendMessageCheck($param, $user);
            $this->sendMessageSms($param, $data, $user);
            $this->sendMessageEmail($param, $data);
            return jsonrule(["status" => 200, "msg" => lang("发送成功,请注意查收")]);
        } catch (\Throwable $e) {
            return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    private function sendMessageEmail($param, $data)
    {
        if (!$param["ematype"]) {
            return false;
        }
        $email = new \app\common\logic\Email();
        $res = $email->sendEmailBase($param["hid"], $data["email"]["temp"]["name"], "product", false);
        if (!$res) {
            throw new \Exception($param["msgtype"] ? "短信发送成功，但邮件发送失败!" : "邮件发送失败!");
        }
    }
    private function sendMessageSms($param, $data, $user)
    {
        if (!$param["msgtype"]) {
            return false;
        }
        $message_template_link = \think\Db::name("message_template_link")->where("sms_temp_id", $param["msgid"])->where("sms_operator", $data["sms"]["type"])->where("is_use", 1)->find();
        if (empty($message_template_link)) {
            throw new \Exception("未开启此模板发送");
        }
        $message_template_type = array_column(config("message_template_type"), "name", "id");
        $mty = $message_template_type[$message_template_link["type"]];
        $host = \think\Db::name("host")->alias("a")->field("a.port")->field("a.id,a.uid,a.productid,a.domainstatus,a.regdate,b.welcome_email,b.type,c.email,a.billingcycle,b.pay_type,b.name,a.nextduedate,a.billingcycle,a.dedicatedip,a.username,a.password,a.os,a.assignedips,a.create_time,a.nextinvoicedate,a.domain,a.suspendreason")->leftJoin("products b", "a.productid=b.id")->leftJoin("clients c", "a.uid=c.id")->where("a.id", $param["hid"])->find();
        $billing_cycle = config("billing_cycle");
        $newvar = ["product_name" => $host["name"], "product_mainip" => $host["dedicatedip"], "product_passwd" => cmf_decrypt($host["password"]), "product_dcimbms_os" => $host["os"], "product_addonip" => $host["assignedips"], "product_first_time" => date("Y-m-d H:i:s", $host["create_time"]), "product_end_time" => 0 < $host["nextduedate"] ? date("Y-m-d H:i:s", $host["nextduedate"]) : "", "product_binlly_cycle" => $billing_cycle[$host["billingcycle"]], "hostname" => $host["domain"], "description" => $host["suspendreason"], "product_terminate_time" => date("Y-m-d H:i:s", time())];
        $newvar["product_mainip"] .= $host["port"] ? ":" . $host["port"] : "";
        $sms = new \app\common\logic\Sms();
        $result = $sms->sendSms($message_template_link["type"], $user["phone_code"] . $user["phonenumber"], $newvar, false, $user["id"]);
        if ($result["status"] != 200) {
            throw new \Exception($param["ematype"] ? "邮件未发送，" . lang("短信发送失败,失败原因:") . $result["msg"] : lang("短信发送失败,失败原因:") . $result["msg"]);
        }
    }
    public function beforeSendMessageCheck($param, $user)
    {
        $data = [];
        if (isset($param["msgtype"]) && $param["msgtype"]) {
            if (empty($user["phonenumber"])) {
                throw new \Exception("手机号码为空");
            }
            $temp = \think\Db::name("message_template")->where("id", $param["msgid"])->find();
            if (empty($temp)) {
                throw new \Exception("短信模板为空");
            }
            $data["sms"]["type"] = $temp["sms_operator"];
            $ret = sendmsglimit($user["phonenumber"]);
            if ($ret["status"] == 400) {
                throw new \Exception(lang("SEND FAIL") . ":" . $ret["msg"]);
            }
        }
        if (isset($param["ematype"]) && $param["ematype"]) {
            if (empty($user["email"])) {
                throw new \Exception("邮件为空");
            }
            $temp = \think\Db::name("email_templates")->where("id", $param["emaid"])->find();
            if (empty($temp)) {
                throw new \Exception("邮件模板为空");
            }
            $data["email"]["temp"] = $temp;
        }
        if (!$param["ematype"] && !$param["msgtype"]) {
            throw new \Exception("请勾选需要发送的模板");
        }
        return $data;
    }
}

?>