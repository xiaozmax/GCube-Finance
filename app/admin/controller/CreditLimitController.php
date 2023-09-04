<?php


namespace app\admin\controller;

/**
 * @title 后台 用户信用额管理
 */
class CreditLimitController extends AdminBaseController
{
    public function index()
    {
        if (!getEdition()) {
            return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
        }
        $data = $this->request->param();
        $uid = input("uid");
        $user = \think\Db::name("clients")->alias("a")->field("username,phonenumber,email,is_open_credit_limit,credit_limit,repayment_date,bill_generation_date,bill_repayment_period,credit_limit_create_time,b.prefix,b.suffix")->leftJoin("currencies b", "a.currency = b.id")->where("a.id", $uid)->find();
        $user["certify"] = checkCertify($uid);
        $user["is_open_credit_limit"] = configuration("shd_credit_limit") == 1 ? $user["is_open_credit_limit"] : 0;
        $day = (int) date("d");
        $user["bill_generation_date"] = 0 < $user["bill_generation_date"] ? $user["bill_generation_date"] : 1;
        if ($day < $user["bill_generation_date"]) {
            $year = (int) date("Y");
            $month = (int) date("m");
            $days = date("t", strtotime($year . "-" . $month));
            if ($days < $user["bill_generation_date"]) {
                if ($day < $days) {
                    $user["next_bill_generation_date"] = $month . "-" . $days;
                } else {
                    if ($month == 12) {
                        $year++;
                        $month++;
                        $days = date("t", strtotime($year . "-" . $month));
                        if ($days < $user["bill_generation_date"]) {
                            $user["next_bill_generation_date"] = $month . "-" . $days;
                        } else {
                            $user["next_bill_generation_date"] = $month . "-" . $user["bill_generation_date"];
                        }
                    } else {
                        $month++;
                        $days = date("t", strtotime($year . "-" . $month));
                        if ($days < $user["bill_generation_date"]) {
                            $user["next_bill_generation_date"] = $month . "-" . $days;
                        } else {
                            $user["next_bill_generation_date"] = $month . "-" . $user["bill_generation_date"];
                        }
                    }
                }
            } else {
                $user["next_bill_generation_date"] = $month . "-" . $user["bill_generation_date"];
            }
        } else {
            $year = (int) date("Y");
            $month = (int) date("m");
            if ($month == 12) {
                $year++;
                $month++;
                $days = date("t", strtotime($year . "-" . $month));
                if ($days < $user["bill_generation_date"]) {
                    $user["next_bill_generation_date"] = $month . "-" . $days;
                } else {
                    $user["next_bill_generation_date"] = $month . "-" . $user["bill_generation_date"];
                }
            } else {
                $month++;
                $days = date("t", strtotime($year . "-" . $month));
                if ($days < $user["bill_generation_date"]) {
                    $user["next_bill_generation_date"] = $month . "-" . $days;
                } else {
                    $user["next_bill_generation_date"] = $month . "-" . $user["bill_generation_date"];
                }
            }
        }
        $user["amount_to_be_settled"] = \think\Db::name("invoices")->where("status", "Paid")->where("use_credit_limit", 1)->where("invoice_id", 0)->where("is_delete", 0)->where("uid", $uid)->sum("total");
        $unpaid = \think\Db::name("invoices")->where("type", "credit_limit")->where("status", "Unpaid")->where("is_delete", 0)->where("uid", $uid)->sum("total");
        $user["credit_limit_used"] = number_format($user["amount_to_be_settled"] + $unpaid, 2, ".", "");
        $user["credit_limit_balance"] = number_format(0 < $user["credit_limit"] - $user["credit_limit_used"] ? $user["credit_limit"] - $user["credit_limit_used"] : 0, 2, ".", "");
        if ($user["credit_limit"] <= $user["credit_limit_used"]) {
            $user["credit_limit_used_percent"] = 100;
        } else {
            $user["credit_limit_used_percent"] = round($user["credit_limit_used"] / $user["credit_limit"] * 100, 0);
        }
        $user["this_month_bill"] = \think\Db::name("invoices")->where("type", "credit_limit")->where("create_time", ">=", strtotime(date("Y-m")))->where("is_delete", 0)->where("credit_limit_prepayment", 0)->where("uid", $uid)->order("create_time", "desc")->find();
        $credit_limit_config["shd_credit_limit"] = configuration("shd_credit_limit");
        $credit_limit_config["shd_credit_limit_amount"] = configuration("shd_credit_limit_amount");
        $credit_limit_config["shd_credit_limit_bill_generation_date"] = configuration("shd_credit_limit_bill_generation_date");
        $credit_limit_config["shd_credit_limit_bill_repayment_period"] = configuration("shd_credit_limit_bill_repayment_period");
        $credit_limit_config["shd_credit_limit_liquidated_damages"] = configuration("shd_credit_limit_liquidated_damages");
        $credit_limit_config["shd_credit_limit_liquidated_damages_percent"] = configuration("shd_credit_limit_liquidated_damages_percent");
        return jsonrule(["user" => $user, "credit_limit_config" => $credit_limit_config, "status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function log()
    {
        if (!getEdition()) {
            return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
        }
        $data = $this->request->param();
        $order = isset($data["order"][0]) ? trim($data["order"]) : "id";
        $sort = isset($data["sort"][0]) ? trim($data["sort"]) : "DESC";
        $uid = input("uid");
        $page = input("page/d") ?? config("page");
        $page_size = input("size/d") ?? config("limit");
        if (isset($data["keywords"])) {
            $keywords = $data["keywords"];
        }
        if (isset($data["start_time"])) {
            $start_time = $data["start_time"];
        }
        if (isset($data["end_time"])) {
            $end_time = $data["end_time"];
        }
        $en = ["Change Credit Limit", "Change Repayment Date", "Change Bill Time"];
        $type = $data["type"] ?? [];
        if (!is_array($type) || empty($type)) {
            $type = [];
        } else {
            foreach ($type as $k => $v) {
                if (!in_array($v, $en)) {
                    unset($type[$k]);
                }
            }
        }
        $where = function (\think\db\Query $query) use($keywords, $type, $start_time) {
            if (!empty($type)) {
                $query->whereIn("type", $type);
            }
            if (isset($keywords[0])) {
                $query->where("a.description", "like", "%" . $keywords . "%");
            }
            if (!empty($start_time) && !empty($end_time)) {
                $query->where("a.create_time", "<=", $end_time);
                $query->where("a.create_time", ">=", $start_time);
            }
        };
        $cn = ["额度调整", "还款日调整", "账单时间调整"];
        $res = \think\Db::name("credit_limit")->alias("a")->field("a.id,a.uid,a.create_time,a.description,type,a.ip,b.user_login,b.user_nickname")->leftJoin("user b", "a.handle_id = b.id")->where("a.uid", $uid)->where($where)->withAttr("type", function ($value, $data) use($en, $cn) {
            $value = str_replace($en, $cn, $value);
            return $value;
        })->order($order, $sort)->limit($page_size)->page($page)->select()->toArray();
        $count = \think\Db::name("credit_limit")->alias("a")->where($where)->where("a.uid", $uid)->count();
        return jsonrule(["data" => $res, "count" => $count, "status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function save()
    {
        if (!getEdition()) {
            return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
        }
        $uid = input("uid/d");
        $credit_limit = input("credit_limit/f");
        $bill_generation_date = input("bill_generation_date/d");
        $bill_repayment_period = input("bill_repayment_period/d");
        $cli = \think\Db::name("clients")->where("id", $uid)->find();
        if (empty($cli)) {
            return jsonrule(["status" => 400, "msg" => "客户不存在"]);
        }
        $data = ["is_open_credit_limit" => 1, "credit_limit" => 0 < $credit_limit ? $credit_limit : 0, "bill_generation_date" => 0 < $bill_generation_date ? $bill_generation_date : 0, "bill_repayment_period" => 0 < $bill_repayment_period ? $bill_repayment_period : 0, "credit_limit_create_time" => time()];
        $flag = true;
        \think\Db::startTrans();
        try {
            Db("clients")->where("id", $uid)->update($data);
            \think\Db::commit();
        } catch (\Exception $e) {
            $flag = false;
            \think\Db::rollback();
        }
        if ($flag) {
            return jsonrule(["status" => 200, "msg" => "信用额启用成功"]);
        }
        return jsonrule(["status" => 400, "msg" => "信用额启用失败"]);
    }
    public function update()
    {
        if (!getEdition()) {
            return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
        }
        $uid = input("uid/d");
        $credit_limit = input("credit_limit/f");
        $bill_generation_date = input("bill_generation_date/d");
        $bill_repayment_period = input("bill_repayment_period/d");
        $repayment_date = input("repayment_date/d");
        $cli = \think\Db::name("clients")->where("id", $uid)->find();
        if (empty($cli)) {
            return jsonrule(["status" => 400, "msg" => "客户不存在"]);
        }
        $data = [];
        if (!is_null($credit_limit)) {
            $data["credit_limit"] = 0 < $credit_limit ? $credit_limit : 0;
        }
        if (!is_null($bill_generation_date)) {
            $data["bill_generation_date"] = 0 < $bill_generation_date ? $bill_generation_date : 0;
        }
        if (!is_null($bill_repayment_period)) {
            $data["bill_repayment_period"] = 0 < $bill_repayment_period ? $bill_repayment_period : 0;
        }
        if (!is_null($repayment_date)) {
            $data["repayment_date"] = 0 < $repayment_date ? $repayment_date : 0;
        }
        $flag = true;
        \think\Db::startTrans();
        try {
            if (!is_null($credit_limit)) {
                $description = sprintf("%s调整到%s", $cli["credit_limit"], $data["credit_limit"]);
                $type = "Change Credit Limit";
                $msg = "信用额修改成功";
            } else {
                if (!is_null($repayment_date)) {
                    $description = sprintf("%s调整到%s", $cli["repayment_date"], $data["repayment_date"]);
                    Db("invoices")->where("uid", $uid)->where("type", "credit_limit")->where("due_time", $cli["repayment_date"])->update(["due_time" => $data["repayment_date"]]);
                    $type = "Change Repayment Date";
                    $msg = "还款日修改成功";
                } else {
                    $description = sprintf("账单生成日%s调整到%s,还款期限%s调整到%s", $cli["bill_generation_date"], $data["bill_generation_date"], $cli["bill_repayment_period"], $data["bill_repayment_period"]);
                    $type = "Change Bill Time";
                    $msg = "账单时间修改成功";
                }
            }
            Db("clients")->where("id", $uid)->update($data);
            Db("credit_limit")->insertGetId(["description" => $description, "type" => $type, "uid" => $uid, "create_time" => time(), "handle_id" => cmf_get_current_admin_id(), "ip" => get_client_ip6()]);
            \think\Db::commit();
        } catch (\Exception $e) {
            if (!is_null($credit_limit)) {
                $msg = "信用额修改失败";
            } else {
                if (!is_null($repayment_date)) {
                    $msg = "还款日修改失败";
                } else {
                    $msg = "账单时间修改失败";
                }
            }
            $flag = false;
            \think\Db::rollback();
        }
        if ($flag) {
            return jsonrule(["status" => 200, "msg" => $msg]);
        }
        return jsonrule(["status" => 400, "msg" => $msg]);
    }
    public function delete()
    {
        if (!getEdition()) {
            return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
        }
        $uid = input("uid/d");
        $cli = \think\Db::name("clients")->where("id", $uid)->find();
        if (empty($cli)) {
            return jsonrule(["status" => 400, "msg" => "客户不存在"]);
        }
        $data = ["is_open_credit_limit" => 0];
        $flag = true;
        \think\Db::startTrans();
        try {
            Db("clients")->where("id", $uid)->update($data);
            \think\Db::commit();
        } catch (\Exception $e) {
            $flag = false;
            \think\Db::rollback();
        }
        if ($flag) {
            return jsonrule(["status" => 200, "msg" => "信用额关闭成功"]);
        }
        return jsonrule(["status" => 400, "msg" => "信用额关闭失败"]);
    }
    public function list()
    {
        if (!getEdition()) {
            return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
        }
        $params = $this->request->param();
        $page = input("get.page", 1, "intval");
        $limit = input("get.limit", 10, "intval");
        $order = input("get.order", "id");
        $sort = input("get.sort", "DESC");
        $page = 1 <= $page ? $page : config("page");
        $limit = 1 <= $limit ? $limit : config("limit");
        if (!in_array($order, ["id", "create_time", "due_time", "paid_time", "subtotal", "payment", "status"])) {
            $order = "id";
        }
        if (!in_array($sort, ["asc", "desc"])) {
            $sort = "asc";
        }
        $where = $this->get_search($params);
        $count = \think\Db::name("invoices")->alias("i")->leftjoin("clients c", "c.id=i.uid")->leftJoin("currencies cu", "cu.id = c.currency")->field("i.id,i.create_time,i.due_time,i.paid_time,i.subtotal,i.payment,i.status,cu.prefix,cu.suffix,i.type")->where($where)->where("i.use_credit_limit", 1)->count();
        $type_arr = ["renew" => "续费", "product" => "产品", "recharge" => "充值", "setup" => "初装费", "upgrade" => "产品升降级", "credit_limit" => "信用额"];
        $gateways = gateway_list();
        $invoices = \think\Db::name("invoices")->alias("i")->leftjoin("clients c", "c.id=i.uid")->leftJoin("currencies cu", "cu.id = c.currency")->field("i.id,i.create_time,i.due_time,i.paid_time,i.subtotal,i.subtotal as sub,i.total,i.credit,i.payment,i.status,cu.prefix,cu.suffix,i.type,i.use_credit_limit,c.username,i.uid")->where($where)->where("i.use_credit_limit", 1)->withAttr("payment", function ($value, $data) use($gateways) {
            if ($data["status"] == "Paid") {
                if ($data["use_credit_limit"] == 1) {
                    return "信用额";
                }
                if ($data["sub"] == $data["credit"]) {
                    return "余额支付";
                }
                if ($data["credit"] < $data["sub"] && 0 < $data["credit"]) {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $value) {
                            return "部分余额支付+" . $v["title"];
                        }
                    }
                } else {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $value) {
                            return $v["title"];
                        }
                    }
                }
            } else {
                foreach ($gateways as $v) {
                    if ($v["name"] == $value) {
                        return $v["title"];
                    }
                }
            }
        })->withAttr("paid_time", function ($value, $data) {
            if ($data["status"] == "Unpaid" || $data["status"] == "Cancelled") {
                return "";
            }
            return $value;
        })->withAttr("subtotal", function ($value, $data) {
            return $data["prefix"] . $value . $data["suffix"];
        })->withAttr("type", function ($value) {
            return $type_arr[$value];
        })->order($order, $sort)->page($page)->limit($limit)->group("i.id")->select()->toArray();
        $invoice_items = \think\Db::name("invoice_items")->alias("t")->field("p.name,t.type,t.invoice_id,t.id,t.description")->leftJoin("host h", "t.rel_id = h.id")->leftJoin("products p", "p.id = h.productid")->whereIn("invoice_id", array_column($invoices, "id"))->where("t.delete_time", 0)->select()->toArray();
        $items = [];
        $type_arr = config("invoice_type_all");
        $type_arr["host"] = "新购";
        $type_arr["product"] = "新购";
        foreach ($invoice_items as $key => $value) {
            $desc = isset($type_arr[$value["type"]]) && !is_null($value["name"]) ? "【" . $type_arr[$value["type"]] . "】" . $value["name"] : $value["description"];
            $items[$value["invoice_id"]][] = $desc;
        }
        foreach ($invoices as $key => &$value) {
            $value["name"] = implode(", ", $items[$value["id"]]);
        }
        $status = config("invoice_payment_status");
        $gateway = gateway_list();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "count" => $count, "invoices" => $invoices, "invoice_status" => $status]);
    }
    public function userInvoice()
    {
        if (!getEdition()) {
            return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
        }
        $params = $this->request->param();
        $page = input("get.page", 1, "intval");
        $limit = input("get.limit", 10, "intval");
        $order = input("get.order", "id");
        $sort = input("get.sort", "DESC");
        $page = 1 <= $page ? $page : config("page");
        $limit = 1 <= $limit ? $limit : config("limit");
        if (!in_array($order, ["id", "create_time", "due_time", "paid_time", "subtotal", "payment", "status"])) {
            $order = "id";
        }
        if (!in_array($sort, ["asc", "desc"])) {
            $sort = "asc";
        }
        $where = $this->get_search($params);
        $count = \think\Db::name("invoices")->alias("i")->leftjoin("clients c", "c.id=i.uid")->leftJoin("currencies cu", "cu.id = c.currency")->field("i.id,i.create_time,i.due_time,i.paid_time,i.subtotal,i.payment,i.status,cu.prefix,cu.suffix,i.type")->where($where)->where("i.type", "credit_limit")->count();
        $type_arr = ["renew" => "续费", "product" => "产品", "recharge" => "充值", "setup" => "初装费", "upgrade" => "产品升降级", "credit_limit" => "信用额"];
        $gateways = gateway_list();
        $invoices = \think\Db::name("invoices")->alias("i")->leftjoin("clients c", "c.id=i.uid")->leftJoin("currencies cu", "cu.id = c.currency")->field("i.id,i.create_time,i.due_time,i.paid_time,i.subtotal,i.subtotal as sub,i.total,i.credit,i.payment,i.status,cu.prefix,cu.suffix,i.type,i.use_credit_limit,c.username,i.uid,i.credit_limit_prepayment")->where($where)->where("i.type", "credit_limit")->withAttr("payment", function ($value, $data) use($gateways) {
            if ($data["status"] == "Paid") {
                if ($data["use_credit_limit"] == 1) {
                    return "信用额";
                }
                if ($data["sub"] == $data["credit"]) {
                    return "余额支付";
                }
                if ($data["credit"] < $data["sub"] && 0 < $data["credit"]) {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $value) {
                            return "部分余额支付+" . $v["title"];
                        }
                    }
                } else {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $value) {
                            return $v["title"];
                        }
                    }
                }
            } else {
                foreach ($gateways as $v) {
                    if ($v["name"] == $value) {
                        return $v["title"];
                    }
                }
            }
        })->withAttr("paid_time", function ($value, $data) {
            if ($data["status"] == "Unpaid" || $data["status"] == "Cancelled") {
                return "";
            }
            return $value;
        })->withAttr("subtotal", function ($value, $data) {
            return $data["prefix"] . $value . $data["suffix"];
        })->withAttr("type", function ($value) {
            return $type_arr[$value];
        })->order($order, $sort)->page($page)->limit($limit)->group("i.id")->select()->toArray();
        $time = time();
        foreach ($invoices as $key => &$value) {
            $value["due_days"] = 0;
            if ($value["status"] == "Paid" && $value["credit_limit_prepayment"] == 1) {
                $value["paid_amount"] = $value["sub"];
                $value["payment_status"] = "Prepayment";
            } else {
                if ($value["status"] == "Paid") {
                    $value["paid_amount"] = $value["sub"];
                    $value["payment_status"] = "Paid";
                } else {
                    if ($time < $value["due_time"]) {
                        $value["paid_amount"] = "0.00";
                        $value["payment_status"] = "Unpaid";
                    } else {
                        if ($value["due_time"] < $time) {
                            $value["paid_amount"] = "0.00";
                            $value["due_days"] = ceil(($time - $value["due_time"]) / 86400);
                            $value["payment_status"] = "Overdue";
                        }
                    }
                }
            }
        }
        $status = config("credit_limit_invoice_payment_status");
        $gateway = gateway_list();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "count" => $count, "invoices" => $invoices, "invoice_status" => config("invoice_payment_status"), "credit_limit_invoice_status" => $status]);
    }
    public function creditLimitInvoice()
    {
        if (!getEdition()) {
            return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
        }
        $params = $this->request->param();
        $invoice_id = isset($params["invoice_id"]) ? intval($params["invoice_id"]) : "";
        if (!$invoice_id) {
            return jsonrule(["status" => 400, "msg" => "ID_ERROR"]);
        }
        unset($params["invoice_id"]);
        $page = input("get.page", 1, "intval");
        $limit = input("get.limit", 10, "intval");
        $order = input("get.order", "id");
        $sort = input("get.sort", "DESC");
        $page = 1 <= $page ? $page : config("page");
        $limit = 1 <= $limit ? $limit : config("limit");
        if (!in_array($order, ["id", "create_time", "due_time", "paid_time", "subtotal", "payment", "status"])) {
            $order = "id";
        }
        if (!in_array($sort, ["asc", "desc"])) {
            $sort = "asc";
        }
        $where = $this->get_search($params);
        $count = \think\Db::name("invoices")->alias("i")->leftjoin("clients c", "c.id=i.uid")->leftJoin("currencies cu", "cu.id = c.currency")->field("i.id,i.create_time,i.due_time,i.paid_time,i.subtotal,i.payment,i.status,cu.prefix,cu.suffix,i.type")->where($where)->where("i.invoice_id", $invoice_id)->count();
        $type_arr = ["renew" => "续费", "product" => "产品", "recharge" => "充值", "setup" => "初装费", "upgrade" => "产品升降级", "credit_limit" => "信用额"];
        $gateways = gateway_list();
        $invoices = \think\Db::name("invoices")->alias("i")->leftjoin("clients c", "c.id=i.uid")->leftJoin("currencies cu", "cu.id = c.currency")->leftJoin("invoice_items t", "t.invoice_id = i.id")->leftJoin("host h", "t.rel_id = h.id")->leftJoin("products p", "p.id = h.productid")->field("i.id,i.create_time,i.due_time,i.paid_time,i.subtotal,i.subtotal as sub,i.total,i.credit,i.payment,i.status,cu.prefix,cu.suffix,i.type,i.use_credit_limit,p.name")->where($where)->where("i.invoice_id", $invoice_id)->withAttr("payment", function ($value, $data) use($gateways) {
            if ($data["status"] == "Paid") {
                if ($data["use_credit_limit"] == 1) {
                    return "信用额";
                }
                if ($data["sub"] == $data["credit"]) {
                    return "余额支付";
                }
                if ($data["credit"] < $data["sub"] && 0 < $data["credit"]) {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $value) {
                            return "部分余额支付+" . $v["title"];
                        }
                    }
                } else {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $value) {
                            return $v["title"];
                        }
                    }
                }
            } else {
                foreach ($gateways as $v) {
                    if ($v["name"] == $value) {
                        return $v["title"];
                    }
                }
            }
        })->withAttr("paid_time", function ($value, $data) {
            if ($data["status"] == "Unpaid" || $data["status"] == "Cancelled") {
                return "";
            }
            return $value;
        })->withAttr("subtotal", function ($value, $data) {
            return $data["prefix"] . $value . $data["suffix"];
        })->withAttr("type", function ($value) {
            return $type_arr[$value];
        })->order($order, $sort)->page($page)->limit($limit)->group("i.id")->select()->toArray();
        $status = config("invoice_payment_status");
        $gateway = gateway_list();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "count" => $count, "invoices" => $invoices, "invoice_status" => $status]);
    }
    public function get_search($param)
    {
        $where = [["i.is_delete", "=", 0], ["i.delete_time", "=", 0]];
        if (isset($param["uid"]) && 0 < $param["uid"]) {
            $where[] = ["i.uid", "=", $param["uid"]];
        }
        if (isset($param["hid"]) && 0 < $param["hid"]) {
            $where[] = ["h.id", "=", $param["hid"]];
        }
        if (!empty($param["payment"])) {
            $where[] = ["i.payment", "=", $param["payment"]];
        }
        if (!empty($param["payment_status"])) {
            if ($param["payment_status"] == "Prepayment") {
                $where[] = ["i.status", "=", "Paid"];
                $where[] = ["i.credit_limit_prepayment", "=", 1];
            } else {
                if ($param["payment_status"] == "Paid") {
                    $where[] = ["i.status", "=", "Paid"];
                    $where[] = ["i.credit_limit_prepayment", "=", 0];
                } else {
                    if ($param["payment_status"] == "Unpaid") {
                        $time = time();
                        $where[] = ["i.status", "=", "Unpaid"];
                        $where[] = ["i.due_time", ">", $time];
                    } else {
                        if ($param["payment_status"] == "Overdue") {
                            $time = time();
                            $where[] = ["i.status", "=", "Unpaid"];
                            $where[] = ["i.due_time", "<=", $time];
                        }
                    }
                }
            }
        }
        if (!empty($param["status"])) {
            $where[] = ["i.status", "=", $param["status"]];
        }
        if (!empty($param["hostid"])) {
            $where[] = ["t.rel_id", "=", $param["hostid"]];
        }
        if (isset($param["create_time"])) {
            $create_time = is_string($param["create_time"]) ? strtotime($param["create_time"]) : $param["create_time"];
            if (is_array($param["create_time"])) {
                $where[] = ["i.create_time", ">=", $create_time[0]];
                $where[] = ["i.create_time", "<=", $create_time[1]];
            }
        }
        if (isset($param["due_time"])) {
            $due_time = is_string($param["due_time"]) ? strtotime($param["due_time"]) : $param["due_time"];
            if (is_array($param["due_time"])) {
                $where[] = ["i.due_time", ">=", $due_time[0]];
                $where[] = ["i.due_time", "<=", $due_time[1]];
            }
        }
        if (isset($param["date"])) {
            $date = explode("-", $param["date"]);
            $date[0] = (int) $date[0];
            $date[1] = (int) $date[1];
            $start = mktime(0, 0, 0, $date[1], 1, $date[0]);
            $end = mktime(0, 0, 0, 12 < $date[1] + 1 ? 1 : $date[1] + 1, 1, 12 < $date[1] + 1 ? $data[0] + 1 : $date[0]) - 1;
            $where[] = ["i.due_time", ">=", $start];
            $where[] = ["i.due_time", "<=", $end];
        }
        if (isset($param["paid_time"])) {
            $paid_time = is_string($param["paid_time"]) ? strtotime($param["paid_time"]) : $param["paid_time"];
            if (is_array($param["paid_time"])) {
                $where[] = ["i.paid_time", ">=", $paid_time[0]];
                $where[] = ["i.paid_time", "<=", $paid_time[1]];
            }
        }
        if (isset($param["type"]) && $param["type"]) {
            $type = $param["type"];
            $where[] = ["i.type", "=", $type];
        }
        if (!empty($param["invoice_id"])) {
            $invoice_id = $param["invoice_id"];
            $where[] = ["i.id", "like", "%" . $invoice_id . "%"];
        }
        if (!empty($param["subtotal_small"]) && 0 < $param["subtotal_small"]) {
            $where[] = ["i.subtotal", ">=", $param["subtotal_small"]];
        }
        if (!empty($param["subtotal_big"]) && 0 < $param["subtotal_small"]) {
            $where[] = ["i.subtotal", "<=", $param["subtotal_big"]];
        }
        return $where;
    }
    public function clientList()
    {
        $params = $data = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        if ($order != "payment_status" && $order != "amount_to_be_settled" && $order != "credit_limit_unpaid" && $order != "credit_limit_balance") {
            $order = "a." . $order;
        }
        $keywords = !empty($params["keywords"]) ? trim($params["keywords"]) : "";
        $payment_status = !empty($params["payment_status"]) ? trim($params["payment_status"]) : "";
        if (!empty($payment_status)) {
            if ($payment_status == "Prepayment") {
                $uid = \think\Db::name("invoices")->where("type", "credit_limit")->where("create_time", ">=", strtotime(date("Y-m")))->where("is_delete", 0)->where("status", "Paid")->where("credit_limit_prepayment", 1)->order("create_time", "desc")->group("uid")->column("uid");
            } else {
                if ($payment_status == "Paid") {
                    $uid = \think\Db::name("invoices")->where("type", "credit_limit")->where("create_time", ">=", strtotime(date("Y-m")))->where("is_delete", 0)->where("status", "Paid")->where("credit_limit_prepayment", 0)->order("create_time", "desc")->group("uid")->column("uid");
                } else {
                    if ($payment_status == "Unpaid") {
                        $time = time();
                        $uid = \think\Db::name("invoices")->where("type", "credit_limit")->where("create_time", ">=", strtotime(date("Y-m")))->where("is_delete", 0)->where("status", "Unpaid")->where("due_time", ">", $time)->order("create_time", "desc")->group("uid")->column("uid");
                    } else {
                        if ($payment_status == "Overdue") {
                            $time = time();
                            $uid = \think\Db::name("invoices")->where("type", "credit_limit")->where("create_time", ">=", strtotime(date("Y-m")))->where("is_delete", 0)->where("status", "Unpaid")->where("due_time", "<=", $time)->order("create_time", "desc")->group("uid")->column("uid");
                        }
                    }
                }
            }
        }
        $fun = function (\think\db\Query $query) use($keywords, $uid, $payment_status) {
            $query->where("a.is_open_credit_limit", 1);
            if (!empty($keywords)) {
                $query->where("a.username|a.email|a.phonenumber", "like", "%" . $keywords . "%");
            }
            if (!empty($payment_status)) {
                $query->whereIn("a.id", $uid);
            }
        };
        $total = \think\Db::name("clients")->alias("a")->field("a.id")->leftJoin("currencies b", "a.currency = b.id")->where($fun)->count();
        if ($order != "payment_status" && $order != "amount_to_be_settled" && $order != "credit_limit_unpaid" && $order != "credit_limit_balance") {
            $list = \think\Db::name("clients")->alias("a")->field("a.id,a.username,a.phonenumber,a.email,a.is_open_credit_limit,a.credit_limit,b.prefix,b.suffix")->leftJoin("currencies b", "a.currency = b.id")->where($fun)->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        } else {
            $list = \think\Db::name("clients")->alias("a")->field("a.id,a.username,a.phonenumber,a.email,a.is_open_credit_limit,a.credit_limit,b.prefix,b.suffix")->leftJoin("currencies b", "a.currency = b.id")->where($fun)->select()->toArray();
        }
        foreach ($list as $key => &$value) {
            $value["amount_to_be_settled"] = \think\Db::name("invoices")->where("status", "Paid")->where("use_credit_limit", 1)->where("invoice_id", 0)->where("is_delete", 0)->where("uid", $value["id"])->sum("total");
            $value["amount_to_be_settled"] = number_format($value["amount_to_be_settled"], 2, ".", "");
            $unpaid = \think\Db::name("invoices")->where("type", "credit_limit")->where("status", "Unpaid")->where("is_delete", 0)->where("uid", $value["id"])->sum("total");
            $value["credit_limit_unpaid"] = number_format($unpaid, 2, ".", "");
            $value["credit_limit_used"] = number_format($value["amount_to_be_settled"] + $unpaid, 2, ".", "");
            $value["credit_limit_balance"] = number_format(0 < $value["credit_limit"] - $value["credit_limit_used"] ? $value["credit_limit"] - $value["credit_limit_used"] : 0, 2, ".", "");
            $this_month_bill = \think\Db::name("invoices")->where("type", "credit_limit")->where("create_time", ">=", strtotime(date("Y-m")))->where("is_delete", 0)->where("uid", $value["id"])->order("create_time", "desc")->find();
            if (empty($this_month_bill)) {
                $value["payment_status"] = "";
            } else {
                $time = time();
                if ($this_month_bill["status"] == "Paid" && $this_month_bill["credit_limit_prepayment"] == 1) {
                    $value["payment_status"] = "Prepayment";
                } else {
                    if ($this_month_bill["status"] == "Paid") {
                        $value["payment_status"] = "Paid";
                    } else {
                        if ($time < $this_month_bill["due_time"]) {
                            $value["payment_status"] = "Unpaid";
                        } else {
                            if ($this_month_bill["due_time"] < $time) {
                                $value["payment_status"] = "Overdue";
                            }
                        }
                    }
                }
            }
        }
        if (!($order != "payment_status" && $order != "amount_to_be_settled" && $order != "credit_limit_unpaid" && $order != "credit_limit_balance")) {
            array_multisort(array_column($list, $order), $sort == "desc" ? SORT_DESC : SORT_ASC, $list);
            $offset = ($page - 1) * $limit;
            $list = array_slice($list, $offset, $limit);
        }
        $status = config("credit_limit_invoice_payment_status");
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "total" => $total, "list" => $list, "credit_limit_invoice_status" => $status]);
    }
    public function getConfig()
    {
        $res = [];
        $res["status"] = 200;
        $res["msg"] = lang("SUCCESS MESSAGE");
        $res["shd_credit_limit"] = configuration("shd_credit_limit");
        $res["shd_credit_limit_amount"] = configuration("shd_credit_limit_amount");
        $res["shd_credit_limit_bill_generation_date"] = configuration("shd_credit_limit_bill_generation_date");
        $res["shd_credit_limit_bill_repayment_period"] = configuration("shd_credit_limit_bill_repayment_period");
        $res["shd_credit_limit_liquidated_damages"] = configuration("shd_credit_limit_liquidated_damages");
        $res["shd_credit_limit_liquidated_damages_percent"] = configuration("shd_credit_limit_liquidated_damages_percent");
        return jsonrule($res);
    }
    public function postConfig()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $dec = "";
            $company_name = configuration("shd_credit_limit");
            if ($params["shd_credit_limit"] != $company_name) {
                if ($params["shd_credit_limit"] == 1) {
                    $dec .= "开启信用额总开关";
                } else {
                    $dec .= "关闭信用额总开关";
                }
            }
            updateConfiguration("shd_credit_limit", $params["shd_credit_limit"] ?? 1);
            $company_name = configuration("shd_credit_limit_amount");
            if ($company_name != $params["shd_credit_limit_amount"]) {
                $dec .= "信用额额度设置" . $company_name . "改为" . $params["shd_credit_limit_amount"];
            }
            updateConfiguration("shd_credit_limit_amount", $params["shd_credit_limit_amount"]);
            $company_name = configuration("shd_credit_limit_bill_generation_date");
            if ($company_name != $params["shd_credit_limit_bill_generation_date"]) {
                $dec .= "出账日" . $company_name . "改为" . $params["shd_credit_limit_bill_generation_date"];
            }
            updateConfiguration("shd_credit_limit_bill_generation_date", $params["shd_credit_limit_bill_generation_date"]);
            $company_name = configuration("shd_credit_limit_bill_repayment_period");
            if ($company_name != $params["shd_credit_limit_bill_repayment_period"]) {
                $dec .= "最后还款日" . $company_name . "改为" . $params["shd_credit_limit_bill_repayment_period"];
            }
            updateConfiguration("shd_credit_limit_bill_repayment_period", $params["shd_credit_limit_bill_repayment_period"]);
            $company_name = configuration("shd_credit_limit_liquidated_damages");
            if ($params["shd_credit_limit_liquidated_damages"] != $company_name) {
                if ($params["shd_credit_limit_liquidated_damages"] == 1) {
                    $dec .= "开启违约金总开关";
                } else {
                    $dec .= "关闭违约金总开关";
                }
            }
            updateConfiguration("shd_credit_limit_liquidated_damages", $params["shd_credit_limit_liquidated_damages"] ?? 0);
            $company_name = configuration("shd_credit_limit_liquidated_damages_percent");
            if ($company_name != $params["shd_credit_limit_liquidated_damages_percent"]) {
                $dec .= "单日违约金百分比" . $company_name . "改为" . $params["shd_credit_limit_liquidated_damages_percent"];
            }
            updateConfiguration("shd_credit_limit_liquidated_damages_percent", $params["shd_credit_limit_liquidated_damages_percent"]);
            active_log_final(sprintf("信用额设置 %s", $dec));
            unset($dec);
            unset($company_name);
            return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
}

?>