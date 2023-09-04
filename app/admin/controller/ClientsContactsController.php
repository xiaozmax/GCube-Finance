<?php


namespace app\admin\controller;

/**
 * @title 客户子账户管理
 * @description 接口说明
 */
class ClientsContactsController extends AdminBaseController
{
    public function getPage(\think\Request $request)
    {
        $param = $request->param();
        $uid = $param["uid"];
        $contactid = $param["contactid"];
        if (empty($uid)) {
            return jsonrule(["status" => 406, "msg" => "客户编号未找到"]);
        }
        $order = isset($param["order"]) ? trim($param["order"]) : "id";
        $sort = isset($param["sort"]) ? trim($param["sort"]) : "DESC";
        $contact_list = \think\Db::name("contacts")->field("id,username,email")->where("uid", $uid)->order($order, $sort)->select()->toArray();
        if (empty($contactid) && !empty($contact_list[0])) {
            $contactid = $contact_list[0]["id"];
        }
        $returndata = [];
        $returndata["uid"] = $uid;
        $returndata["contact_list"] = $contact_list;
        if (!empty($contactid)) {
            $contact_data = \think\Db::name("contacts")->where("id", $contactid)->where("uid", $uid)->find();
            $permissions = $contact_data["permissions"];
            if (!empty($permissions)) {
                $contact_data["permissions_arr"] = explode(",", $permissions);
            }
            $returndata["contact_data"] = $contact_data;
            $returndata["contactid"] = $contactid;
        }
        $returndata["permissions"] = config("contact_permissions");
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function postSave(\think\Request $request)
    {
        if ($request->isPost()) {
            $param = $request->param();
            $uid = $param["uid"];
            $contactid = $param["contactid"];
            $rule = ["uid" => "require|number", "contactid" => "number", "username" => "chsDash", "sex" => "in:0,1,2", "email" => "require|email", "postcode" => "number", "phonenumber" => "mobile", "generalemails" => "in:0,1", "invoiceemails" => "in:0,1", "productemails" => "in:0,1", "supportemails" => "in:0,1", "status" => "in:1,0,2", "permissions" => "array"];
            $msg = ["uid.require" => "用户id不能为空", "uid.number" => "用户id必须为数字", "contactid.number" => "子账户id必须为数字", "username.chsDash" => "用户名只能是汉字、字母、数字和下划线_及破折号-", "sex.in" => "性别错误", "email.require" => "邮箱不能为空", "email.email" => "邮箱格式错误", "postcode.number" => "邮编必须为数字", "phonenumber.mobile" => "手机号格式错误"];
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $user_data = \think\Db::name("clients")->field("id,username")->find($uid);
            if (empty($user_data)) {
                return jsonrule(["status" => 406, "msg" => "用户id错误"]);
            }
            $udata = [];
            $udata = ["uid" => $uid, "username" => $param["username"] ?: "", "sex" => $param["sex"] ?: 0, "avatar" => $param["avatar"] ?: "", "companyname" => $param["companyname"] ?: "", "email" => $param["email"], "wechat_id" => $param["wechat_id"], "country" => $param["country"] ?: "", "province" => $param["province"] ?: "", "city" => $param["city"] ?: "", "region" => $param["region"] ?: "", "address1" => $param["address1"] ?: "", "address2" => $param["address2"] ?: "", "postcode" => $param["postcode"] ?: 0, "phonenumber" => $param["phonenumber"] ?: "", "generalemails" => $param["generalemails"] ?: 0, "invoiceemails" => $param["invoiceemails"] ?: 0, "productemails" => $param["productemails"] ?: 0, "supportemails" => $param["supportemails"] ?: 0, "status" => $param["status"] ?: 0];
            $permissions = $param["permissions"];
            if (is_array($permissions) && !empty($permissions)) {
                $udata["permissions"] = implode(",", $permissions);
            }
            if (!empty($param["password"])) {
                $udata["password"] = cmf_password($param["password"]);
            }
            if (!empty($contactid)) {
                $contact_exists = \think\Db::name("contacts")->where("email", $param["email"])->find();
                $client_exists = \think\Db::name("clients")->where("email", $param["email"])->find();
                if (!empty($contact_exists) && $contact_exists["id"] != $contactid) {
                    return jsonrule(["status" => 406, "msg" => "该邮箱已存在"]);
                }
                if (!empty($client_exists)) {
                    return jsonrule(["status" => 406, "msg" => "该邮箱已存在"]);
                }
                $udata["update_time"] = time();
                \think\Db::name("contacts")->where("id", $contactid)->update($udata);
            } else {
                $contact_exists = \think\Db::name("contacts")->where("email", $param["email"])->find();
                $client_exists = \think\Db::name("clients")->where("email", $param["email"])->find();
                if (!empty($contact_exists) || !empty($client_exists)) {
                    return jsonrule(["status" => 406, "msg" => "该邮箱已存在"]);
                }
                $udata["create_time"] = time();
                $iid = \think\Db::name("contacts")->insertGetId($udata);
                active_log("添加联系人 - Contacts ID:" . $iid, $uid);
            }
            return jsonrule(["status" => 200, "msg" => "保存成功"]);
        }
    }
    public function deleteContact(\think\Request $request)
    {
        $param = $request->param();
        $uid = $param["uid"];
        $contactid = $param["contactid"];
        if (empty($uid)) {
            return jsonrule(["status" => 406, "msg" => "用户未找到"]);
        }
        if (empty($contactid)) {
            return jsonrule(["status" => 406, "msg" => "子账户未找到"]);
        }
        $contact_data = \think\Db::name("contacts")->where("id", $contactid)->where("uid", $uid)->find();
        if (empty($contact_data)) {
            return jsonrule(["status" => 406, "msg" => "子账户未找到"]);
        }
        \think\Db::name("contacts")->where("id", $contactid)->where("uid", $uid)->delete();
        return jsonrule(["status" => 200, "msg" => "删除成功"]);
    }
}

?>