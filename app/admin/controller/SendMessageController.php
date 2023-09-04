<?php


namespace app\admin\controller;

/**
 * @title 批量发送邮件页面
 * @description: 自定义邮件内容直接向用户发送邮件
 */
class SendMessageController extends AdminBaseController
{
    protected $attachments_path = NULL;
    public function postEmailPage(\think\Request $request)
    {
        $param = $request->param();
        $type = $param["type"];
        $selected = $param["selected"];
        $load_message_id = $param["load_message_id"];
        if (empty($type)) {
            return jsonrule(["status" => 406, "msg" => "发送类型错误"]);
        }
        if (!is_array($selected) || empty($selected)) {
            return jsonrule(["status" => 406, "msg" => "发送人错误"]);
        }
        $returndata = [];
        $config = getConfig(["company_name", "company_email"]);
        $returndata["fromname"] = $config["company_name"] ?: "";
        $returndata["fromemail"] = $config["company_email"] ?: "";
        $returndata["type"] = $type;
        if (!empty($load_message_id)) {
            $template_data = \think\Db::name("email_templates")->field("id,name,subject,message,fromname,fromemail")->where("type", $type)->where("id", $load_message_id)->find();
            if (!empty($template_data)) {
                $returndata["subject"] = $template_data["subject"];
                $returndata["message"] = $template_data["message"];
                $returndata["fromname"] = $template_data["fromname"];
                $returndata["fromemail"] = $template_data["fromemail"];
            }
        }
        if ($type == "general") {
            $client_data = \think\Db::name("clients")->field("id,username,email")->whereIn("id", $selected)->select()->toArray();
            $returndata["clients"] = $client_data;
        } else {
            if ($type == "product") {
                $client_data = \think\Db::name("host")->field("c.id,c.username,c.email")->alias("h")->leftJoin("clients c", "c.id=h.uid")->whereIn("h.id", $selected)->select()->toArray();
                $returndata["clients"] = $client_data;
            }
        }
        $email_temp_list = \think\Db::name("email_templates")->field("id,name")->where("type", $type)->select()->toArray();
        $returndata["email_temp_list"] = $email_temp_list;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function postSendEmail(\think\Request $request)
    {
        $param = $request->param();
        $rule = ["type" => "require|in:product,general", "selected" => "require|array", "fromname" => "require", "fromemail" => "require|email", "subject" => "require", "message" => "require"];
        $msg = ["type.require" => "发送类型不能为空", "type.in" => "发送类型错误", "selected.require" => "接收人不能为空", "fromname.require" => "发送人信息必填", "fromemail.require" => "发送人信息必填", "fromemail.email" => "发送人邮箱格式错误", "subject.require" => "邮件主题不能为空", "message.require" => "邮件内容不能为空"];
        $param = $request->param();
        $validate = new \think\Validate($rule, $msg);
        $result = $validate->check($param);
        if (!$result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $type = $param["type"];
        $selected = $param["selected"];
        $fromname = $param["fromname"];
        $fromemail = $param["fromemail"];
        $cc = $param["cc"] ?: "";
        $bcc = $param["bcc"] ?: "";
        $subject = $param["subject"];
        $message = $param["message"];
        $savename = $param["savename"] ?: "";
        $files = request()->file("attachments");
        if (!empty($files)) {
            foreach ($files as $file) {
                $info = $file->validate(["size" => 5242880])->move($this->attachments_path);
                if ($info) {
                    $filename[] = $info->getFilename();
                } else {
                    foreach ($filename as $val) {
                        @unlink($this->attachments_path . $val);
                    }
                    $result["status"] = 406;
                    $result["msg"] = $file->getError();
                    return $result;
                }
            }
        }
        if (!empty($filename)) {
            $attachments = implode(",", $filename);
        }
        if (!empty($savename)) {
            $isname = \think\Db::name("email_templates")->where("name", $savename)->find();
            if (!empty($isname)) {
                return jsonrule(["status" => 400, "msg" => "邮件名称不唯一！"]);
            }
            \think\Db::startTrans();
            try {
                $newtemplate = [];
                $newtemplate = ["type" => $type, "name" => $savename, "subject" => $subject, "message" => $message, "attachments" => $attachments, "fromname" => $fromname, "fromemail" => $fromemail, "copyto" => $cc, "blind_copy_to" => $bcc, "create_time" => time()];
                $templateid = \think\Db::name("email_templates")->insertGetId($newtemplate);
                $langs = \think\Db::name("email_languages")->where("disabled", 1)->field("language")->group("language")->select();
                if (!empty($langs)) {
                    foreach ($langs as $lang) {
                        $newtemplate["language"] = $lang["language"];
                        \think\Db::name("email_templates")->insertGetId($newtemplate);
                    }
                }
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
            }
        }
        $email_logic = new \app\common\logic\Email();
        $email_logic->is_admin = true;
        foreach ($selected as $key => $relid) {
            $result = $email_logic->sendEmailBase($relid, $subject, $type, $cc, $bcc, $message, $attachments);
        }
        return jsonrule(["status" => 200, "msg" => "邮件已发送"]);
    }
}

?>