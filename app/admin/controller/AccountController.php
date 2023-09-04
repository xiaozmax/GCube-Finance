<?php

namespace app\admin\controller;

/**
 * @title 后台交易流水
 */
class AccountController extends GetUserController
{
    public function searchPage()
    {
        $list = \think\Db::name("user")->field("id as value,user_nickname as label")->where("is_sale", 1)->select()->toArray();
        $salelist = $list;
        $other_pay = [["id" => 0, "name" => "creditPay", "title" => "余额支付", "status" => 1], ["id" => -1, "name" => "creditLimitPay", "title" => "信用额支付", "status" => 1]];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "gateway" => array_merge(gateway_list(), $other_pay), "salelist" => $list]);
    }
    public function index()
    {
        $data = $this->request->param();
        $order = isset($data["order"]) ? trim($data["order"]) : "id";
        $sort = isset($data["sort"]) ? trim($data["sort"]) : "DESC";
        if (!in_array($order, ["id", "username", "create_time", "gateway", "description", "amount_in", "fees", "amount_out"])) {
            return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
        $limit = isset($data["limit"]) ? intval($data["limit"]) : config("limit");
        $page = isset($data["page"]) ? intval($data["page"]) : config("page");
        $start_time = isset($data["start_time"]) && !empty($data["start_time"]) ? $data["start_time"] : 0;
        $end_time = isset($data["end_time"]) && !empty($data["end_time"]) ? $data["end_time"] : "2147483647";
        $where = [];
        if ($this->user["id"] != 1 && $this->user["is_sale"]) {
            $where[] = ["a.uid", "in", $this->str];
        }
        $fun = function (\think\db\Query $query) use($data) {
            $query->where("a.delete_time", 0);
            if (isset($data["uid"]) && !empty($data["uid"])) {
                $query->where("a.uid", $data["uid"]);
            }
            if (isset($data["show"]) && !empty($data["show"])) {
                $type = $data["show"];
                if (isset($data["amount"]) && !empty($data["amount"])) {
                    $query->where("a." . $type, $data["amount"]);
                }
                $query->where("a." . $type, ">", 0);
            }
            if (isset($data["amount"]) && !empty($data["amount"])) {
                $query->where("a.amount_in", $data["amount"])->whereOr("a.amount_out", $data["amount"]);
            }
            if (isset($data["trans_id"]) && !empty($data["trans_id"])) {
                $query->where("a.trans_id", "like", "%" . $data["trans_id"] . "%");
            }
            if (isset($data["description"]) && !empty($data["description"])) {
                $query->where("a.description", "like", "%" . $data["description"] . "%");
            }
            if (isset($data["gateway"]) && !empty($data["gateway"])) {
                if ($data["payment"] == "creditLimitPay") {
                    $query->where("ii.use_credit_limit", 1);
                } else {
                    if ($data["payment"] == "creditPay") {
                        $query->where("ii.credit", ">", 0);
                    } else {
                        $query->where("a.gateway", $data["gateway"]);
                    }
                }
            }
            if (isset($data["sale_id"]) && $data["sale_id"]) {
                $type = $data["sale_id"];
                $query->where("c.sale_id", $type);
            }
            if (isset($data["type"]) && !empty($data["type"]) && $data["type"] != "all") {
                if ($data["type"] == "host") {
                    $data["type"] = "product";
                }
                $query->where("ii.type", $data["type"]);
            }
        };
        $sale = array_column(get_sale(), "user_nickname", "id");
        $gateways = gateway_list1("gateway", 0);
        $rows = \think\Db::name("accounts")->alias("a")->leftjoin("clients c", "c.id=a.uid")->leftjoin("currencies cu", "cu.code = a.currency")->leftjoin("invoices ii", "a.invoice_id = ii.id")->where($fun)->whereBetweenTime("a.pay_time", $start_time, $end_time)->field("ii.type,c.companyname,a.id,a.invoice_id,c.id as uid,c.sale_id,c.username,a.currency,cu.prefix,cu.suffix,a.pay_time,a.update_time,a.gateway,a.description,a.amount_in,a.fees,a.amount_out,a.trans_id")->withAttr("fees", function ($value, $data) {
            return $data["prefix"] . $value . $data["suffix"];
        })->withAttr("gateway", function ($value) {
            foreach ($gateways as $v) {
                if ($v["name"] == $value) {
                    return $v["title"];
                }
            }
            return $value;
        })->withAttr("sale_id", function ($value) {
            return $sale[$value];
        })->withAttr("type", function ($value) {
            if ($value == "product") {
                return "host";
            }
            return $value;
        })->where($where)->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        $amount_in_totals = 0;
        $amount_out_totals = 0;
        foreach ($rows as &$value) {
            $amount_in_totals = bcadd($value["amount_in"], $amount_in_totals, 2);
            $amount_out_totals = bcadd($value["amount_out"], $amount_out_totals, 2);
            $value["type_zh"] = config("invoice_type")[$value["type"]];
        }
        $amount_in_totals = $amount_in_totals - $amount_out_totals;
        $count = db("accounts")->alias("a")->leftjoin("clients c", "c.id=a.uid")->leftjoin("currencies cu", "cu.code = a.currency")->leftJoin("invoices ii", "a.invoice_id = ii.id")->where($where)->where($fun)->whereBetweenTime("a.create_time", $start_time, $end_time)->count("a.id");
        $currencys = db("currencies")->distinct(true)->field("id,code,prefix,suffix")->select()->toArray();
        $currency_code = \think\Db::name("currencies")->where("default", 1)->value("code");
        $total = [];
        foreach ($currencys as $item) {
            $fun1 = function (\think\db\Query $query) use($data, $item) {
                $query->where("a.delete_time", 0);
                $query->where("a.currency", $item["code"]);
                if (isset($data["uid"]) && !empty($data["uid"])) {
                    $query->where("a.uid", $data["uid"]);
                }
                if (isset($data["sale_id"]) && $data["sale_id"]) {
                    $type = $data["sale_id"];
                    $query->where("c.sale_id", $type);
                }
            };
            $amount_in = db("accounts")->alias("a")->leftjoin("clients c", "c.id=a.uid")->where($fun1)->where($where)->sum("a.amount_in");
            $amount_out = db("accounts")->alias("a")->leftjoin("clients c", "c.id=a.uid")->where($fun1)->where($where)->sum("a.amount_out");
            $fees = db("accounts")->alias("a")->leftjoin("clients c", "c.id=a.uid")->where($fun1)->where($where)->sum("a.fees");
            $surplus = bcsub(bcsub($amount_in, $amount_out, 2), $fees, 2);
            $total[$item["code"]] = ["amount_in" => $item["prefix"] . bcsub($amount_in, 0, 2) . $item["suffix"], "amount_out" => $item["prefix"] . bcsub($amount_out, 0, 2) . $item["suffix"], "fees" => $item["prefix"] . bcsub($fees, 0, 2) . $item["suffix"], "surplus" => $item["prefix"] . bcsub($surplus, 0, 2) . $item["suffix"]];
            $amount_in_totals = $item["prefix"] . $amount_in_totals . $item["suffix"];
        }
        $pages = ["total" => $count];
        return jsonrule(["data" => $rows, "page" => $pages, "count" => $total, "amount_in_totals" => $amount_in_totals, "currency_id" => $currency_code, "status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function create()
    {
        $params = $this->request->param();
        $currency = (new \app\common\logic\Currencies())->getCurrencies("id,code");
        $gateways = gateway_list();
        if (isset($params["uid"]) && intval($params["uid"])) {
            $users = [];
            foreach ($users as $key => $user) {
                $users[$key]["invoices"] = \think\Db::name("invoices")->where("uid", $user["id"])->column("id");
                array_unshift($users[$key]["invoices"], lang("NULL"));
            }
        } else {
            $users = [];
            foreach ($users as $key => $user) {
                $users[$key]["invoices"] = \think\Db::name("invoices")->where("uid", $user["id"])->column("id");
                array_unshift($users[$key]["invoices"], lang("NULL"));
            }
        }
        $data = ["users" => $users, "currency" => $currency, "gateways" => $gateways];
        return jsonrule(["data" => $data, "status" => 200]);
    }
    public function createInvoice()
    {
        $params = $this->request->param();
        $invoices = \think\Db::name("invoices")->where("uid", $params["uid"])->column("id");
        $invoices = array_merge(["无"], $invoices);
        return jsonrule(["data" => $invoices, "status" => 200]);
    }
    public function save()
    {
        $param = request()->only(["uid", "currency", "pay_time", "description", "trans_id", "invoice_id", "gateway", "amount_in", "fees", "amount_out", "refund"]);
        $validate = new \app\admin\validate\AccountValidate();
        if (!$validate->scene("save")->check($param)) {
            return jsonrule(["status" => 400, "msg" => $validate->getError()]);
        }
        if (empty($param["description"]) && empty($param["trans_id"])) {
            return jsonrule(["status" => 400, "msg" => "描述和付款流水号必填其一"]);
        }
        $param["pay_time"] = intval($param["pay_time"]);
        if ($param["amount_in"] || $param["amount_out"] || $param["fees"]) {
            if (isset($param["invoice_id"]) && intval($param["invoice_id"])) {
                $invoice_id = intval($param["invoice_id"]);
                $res = db("invoices")->where("id", $invoice_id)->find();
                if (!$res) {
                    return jsonrule(["status" => 400, "msg" => "不存在的账单"]);
                }
            }
            if (!empty($param["refund"]) && $res) {
                return jsonrule(["status" => 400, "msg" => "退款至余额不能选择账单"]);
            }
            if (!empty($param["refund"])) {
                if (0 < $param["amount_out"] || 0 < $param["fees"]) {
                    return jsonrule(["status" => 400, "msg" => "选择退款至余额时，收入或者手续费金额不能大于0"]);
                }
                if ($param["amount_in"] <= 0) {
                    return jsonrule(["status" => 400, "msg" => "选择退款至余额时，支出必须大于0"]);
                }
            }
            $currency_exist = \think\Db::name("currencies")->where("code", $param["currency"])->count();
            if ($currency_exist <= 0) {
                return jsonrule(["status" => 400, "msg" => "错误的货币"]);
            }
            $param["create_time"] = time();
            $trans_id = $param["trans_id"];
            $credit = $param["amount_in"];
            \think\Db::startTrans();
            try {
                $accont = \think\Db::name("accounts")->insertGetId($param);
                active_log_final(sprintf($this->lang["Account_admin_add"], $accont, $param["uid"], $param["invoice_id"], $param["trans_id"]), $param["uid"]);
                if (!empty($param["refund"])) {
                    \think\Db::name("clients")->where("id", $param["uid"])->setInc("credit", $credit);
                    credit_log(["uid" => $param["uid"], "desc" => $param["description"] . "{交易流水编号： " . $trans_id . "}", "amount" => $credit]);
                }
                \think\Db::commit();
                $hook_data = ["account_id" => $accont, "amount_in" => $param["amount_in"], "amount_out" => $param["amount_out"], "currency" => $param["currency"], "description" => $param["description"], "trans_id" => $param["trans_id"], "invoice_id" => $param["invoice_id"], "gateway" => $param["gateway"], "refund" => $param["refund"], "uid" => $param["uid"]];
                hook("after_admin_add_account", $hook_data);
                return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
            }
        }
        return jsonrule(["status" => 400, "msg" => lang("ACOUNT_REQUIRE")]);
    }
    public function read($id)
    {
        $res = db("accounts")->where("id", $id)->find();
        $gateways = gateway_list();
        $invoices = \think\Db::name("invoices")->where("uid", $res["uid"])->column("id");
        array_unshift($invoices, lang("NULL"));
        return jsonrule(["stauts" => 200, "msg" => lang("SUCCESS MESSAGE"), "list" => $res, "gateway" => $gateways, "invoices" => $invoices]);
    }
    public function update($id)
    {
        $data = request()->only(["uid", "gateway", "pay_time", "description", "amount_in", "fees", "amount_out", "invoice_id", "trans_id"]);
        $validate = new \app\admin\validate\AccountValidate();
        if (!$validate->check($data)) {
            return jsonrule($validate->getError(), 400);
        }
        $data["update_time"] = time();
        $resaccounts = db("accounts")->where("id", $id)->find();
        $res = db("accounts")->where("id", $id)->update($data);
        if ($res) {
            $dec = "";
            if ($data["invoice_id"] != $resaccounts["invoice_id"]) {
                $dec .= "账单编号由“" . $resaccounts["invoice_id"] . "”修改为“" . $data["invoice_id"] . "”，";
            }
            if ($data["update_time"] != $resaccounts["update_time"]) {
                if (empty($resaccounts["update_time"]) || $resaccounts["update_time"] == 0) {
                    $time = "";
                } else {
                    $time = date("Y-m-d H:i:s", $resaccounts["update_time"]);
                }
                $dec .= "支付时间由“" . $time . "”修改为“" . date("Y-m-d H:i:s", $data["update_time"]) . "”，";
            }
            if ($data["trans_id"] != $resaccounts["trans_id"]) {
                $dec .= "付款流水号由“" . $resaccounts["trans_id"] . "”修改为“" . $data["trans_id"] . "”，";
            }
            if ($data["gateway"] != $resaccounts["gateway"]) {
                $arr = ["AliPay" => "支付宝支付", "WxPay" => "微信支付", "GlobalAliPay" => "支付宝国际支付"];
                $dec .= "支付方式由“" . $arr[$resaccounts["gateway"]] . "”修改为“" . $arr[$data["gateway"]] . "”，";
            }
            if ($data["amount_in"] != $resaccounts["amount_in"]) {
                $dec .= "收入由“" . $resaccounts["amount_in"] . "”修改为“" . $data["amount_in"] . "”，";
            }
            if ($data["amount_out"] != $resaccounts["amount_out"]) {
                $dec .= "支出由“" . $resaccounts["amount_out"] . "”修改为“" . $data["amount_out"] . "”，";
            }
            if ($data["fees"] != $resaccounts["fees"]) {
                $dec .= "手续费由“" . $resaccounts["fees"] . "”修改为“" . $data["fees"] . "”，";
            }
            if (empty($dec)) {
                $dec .= "什么都没修改";
            }
            $hook_data = $data;
            $hook_data["account_id"] = $id;
            unset($hook_data["update_time"]);
            hook("after_admin_edit_account", $hook_data);
            active_log_final(sprintf($this->lang["Account_admin_update"], $id, $data["uid"], $data["invoice_id"], $dec), $data["uid"]);
            unset($dec);
            return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")], 200);
        }
        return jsonrule(["status" => 400, "msg" => lang("UPDATE FAIL")], 400);
    }
    public function delete($id)
    {
        if (input("?ids")) {
            $id = input("ids/a");
        }
        $accont = db("accounts")->find($id);
        $res = db("accounts")->delete($id);
        if ($res) {
            active_log_final(sprintf($this->lang["Account_admin_delete"], $id, $accont["uid"], $accont["invoice_id"], $accont["trans_id"]), $accont["uid"]);
            hook("after_admin_delete_account", ["account_id" => $id]);
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
    }
}

?>