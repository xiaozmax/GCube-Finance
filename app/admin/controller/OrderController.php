<?php


namespace app\admin\controller;

/**
 * @title 后台订单管理
 */
class OrderController extends GetUserController
{
    private $imageaddress = NULL;
    private $allowSystem = NULL;
    private $system = NULL;
    private $osIco = NULL;
    private $ext = "jpg";
    public function initialize()
    {
        parent::initialize();
        $this->allowSystem = config("allow_system");
        $this->system = config("system_list");
        $this->imageaddress = config("servers");
        $this->osIco = config("system");
    }
    public function searchPage()
    {
        session_write_close();
        $pay_status = config("invoice_payment_status");
        if ($this->user["id"] != 1) {
            $sessionAdminId = session("ADMIN_ID");
            $list = \think\Db::name("user")->field("id,user_nickname")->where("is_sale", 1)->where("id", $sessionAdminId)->select()->toArray();
            $users = [];
        } else {
            $list = [];
            $users = [];
            $list = \think\Db::name("user")->field("id,user_nickname")->where("is_sale", 1)->select()->toArray();
        }
        $order_status = [];
        foreach (config("order_status") as $k => $v) {
            $order_status[$k] = $v["name"];
        }
        $gateway = gateway_list1();
        $other_pay = [["id" => 0, "name" => "creditPay", "title" => "余额支付", "status" => 1], ["id" => -1, "name" => "creditLimitPay", "title" => "信用额支付", "status" => 1]];
        array_unshift($pay_status, lang("NO_INVOICE"));
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "gateway" => array_merge($gateway, $other_pay), "user" => $users, "type" => $order_status, "pay_status" => $pay_status, "list" => $list]);
    }
    public function index()
    {
        session_write_close();
        $page = input("page") ?? config("page");
        $limit = input("limit") ?? config("limit");
        $order = input("order");
        $sort = input("sort") ?? "desc";
        $params = $this->request->param();
        $result = $this->validate(["page" => $page, "limit" => $limit, "order" => $order, "sort" => $sort], "app\\admin\\validate\\OrderValidate");
        if ($result !== true) {
            return jsonrule($result, 400);
        }
        $start_time = $params["start_time"] ?? 0;
        $end_time = $params["end_time"] ?? time();
        $fun = function (\think\db\Query $query) use($params) {
            $str = $this->str;
            $query->where("o.delete_time", 0);
            if (isset($params["id"])) {
                $id = $params["id"];
                $query->where("o.id", "like", "%" . $id . "%");
            }
            if (isset($params["uid"])) {
                $username = $params["uid"];
                $query->where("c.id", $username);
            }
            if (isset($params["amount"])) {
                $amount = $params["amount"];
                $query->where("o.amount", "like", "%" . $amount . "%");
            }
            if (isset($params["username"])) {
                $user = $params["username"];
                $query->where("c.username", "like", "%" . $user . "%");
            }
            if (isset($params["ordernum"])) {
                $ordernum = $params["ordernum"];
                $query->where("o.ordernum", "like", "%" . $ordernum . "%");
            }
            if (isset($params["status"])) {
                $status = $params["status"];
                $query->where("o.status", "like", "%" . $status . "%");
            }
            if (isset($params["product_name"]) && !empty($params["product_name"])) {
                $pro_ids = \think\Db::name("products")->where("name", $params["product_name"])->column("id");
                $_host_id = \think\Db::name("host")->whereIn("productid", $pro_ids)->column("orderid");
                $query->whereIn("o.id", $_host_id);
            }
            if (isset($params["payment"]) && !empty($params["payment"])) {
                if ($params["payment"] == "creditLimitPay") {
                    $query->where("i.use_credit_limit", 1);
                } else {
                    if ($params["payment"] == "creditPay") {
                        $query->where("i.credit", ">", 0);
                    } else {
                        $query->where("o.payment", $params["payment"]);
                    }
                }
            }
            $sale_id = isset($params["sale_id"]) ? $params["sale_id"] : "";
            if (!empty($sale_id)) {
                $query->where("c.sale_id", "=", $sale_id);
            }
            if ($this->user["id"] != 1 && $this->user["is_sale"]) {
                $query->where("c.id", "in", $str);
            }
            if (1 <= strlen($params["pay_status"])) {
                if ($params["pay_status"] == 0 && strlen($params["pay_status"]) == 1) {
                    $query->where("o.invoiceid", "");
                } else {
                    $query->where("i.status", $params["pay_status"]);
                }
            }
        };
        $price_total = \think\Db::name("orders")->alias("o")->leftjoin("clients c", "o.uid=c.id")->leftjoin("currencies cu", "cu.id = c.currency")->leftJoin("user u", "u.id = c.sale_id")->leftJoin("invoices i", "o.invoiceid = i.id")->field("o.amount")->where($fun)->where("o.create_time", ">=", $start_time)->where("o.create_time", "<=", $end_time)->sum("o.amount");
        $gateways = gateway_list1("gateways", 0);
        $rows = \think\Db::name("orders")->alias("o")->leftjoin("clients c", "o.uid=c.id")->leftjoin("currencies cu", "cu.id = c.currency")->leftJoin("user u", "u.id = c.sale_id")->leftJoin("invoices i", "o.invoiceid = i.id")->leftJoin("upgrades up", "up.order_id = o.id")->field("o.notes as order_notes")->field("up.id as upid,i.type,i.subtotal,c.username,c.companyname,c.notes,o.id,o.uid,c.sale_id,o.status,o.ordernum,i.status as i_status,i.subtotal as sub,i.credit,i.use_credit_limit,o.create_time,o.invoiceid,o.amount,o.amount as am,o.payment,cu.prefix,cu.suffix,u.user_nickname,i.delete_time,i.payment as i_payment")->withAttr("payment", function ($value, $data) use($gateways) {
            $i_value = $data["i_payment"] ?: $value;
            if ($data["i_status"] == "Paid") {
                if ($data["use_credit_limit"] == 1) {
                    return "信用额支付";
                }
                if ($data["sub"] == $data["credit"]) {
                    return "余额支付";
                }
                if ($data["credit"] < $data["sub"] && 0 < $data["credit"]) {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $i_value) {
                            return "部分余额支付+" . $v["title"];
                        }
                    }
                } else {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $i_value) {
                            return $v["title"];
                        }
                    }
                }
            } else {
                foreach ($gateways as $v) {
                    if ($v["name"] == $i_value) {
                        return $v["title"];
                    }
                }
            }
        })->withAttr("amount", function ($value, $data) {
            return $data["prefix"] . $value . $data["suffix"];
        })->where($fun)->where("o.create_time", ">=", $start_time)->where("o.create_time", "<=", $end_time)->page($page)->limit($limit)->order($order, $sort)->group("o.id")->select()->toArray();
        $billingcycle = config("billing_cycle");
        $order_status = config("order_status");
        $invoice_payment_status = config("invoice_payment_status");
        $price_total_page = 0;
        $rows_filter = [];
        foreach ($rows as $k => $row) {
            $row["order_notes"] = $row["order_notes"] ?: "";
            if (!isset($rows_filter[$row["id"]])) {
                $price_total_page = bcadd($row["am"], $price_total_page, 2);
                $invoice_status = $row["i_status"];
                $invoice_type = $row["type"];
                if (!empty($row["upid"])) {
                    $invoice_type = "upgrade";
                }
                $hosts = [];
                if ($invoice_type == "zjmf_flow_packet") {
                    $hosts[] = ["hostid" => 0, "name" => "流量包", "firstpaymentamount" => $row["prefix"] . $row["subtotal"] . $row["suffix"], "billingcycle" => " -"];
                } else {
                    if ($invoice_type == "zjmf_reinstall_times") {
                        $hosts[] = ["hostid" => 0, "name" => "重装次数", "firstpaymentamount" => $row["prefix"] . $row["subtotal"] . $row["suffix"], "billingcycle" => " -"];
                    } else {
                        $hosts = \think\Db::name("host")->alias("a")->leftJoin("products b", "a.productid = b.id")->leftJoin("upgrades u", "u.relid = a.id")->leftJoin("cancel_requests cr", "cr.relid = a.id")->field("a.uid,a.initiative_renew,a.id as hostid,b.id as invoice_type,b.name,a.domain,a.dedicatedip,a.billingcycle,a.firstpaymentamount,a.productid")->distinct(true)->where(function (\think\db\Query $query) use($row) {
                            $query->where("a.orderid", $row["id"]);
                            $query->whereOr("u.order_id", $row["id"]);
                        })->withAttr("billingcycle", function ($value) {
                            return $billingcycle[$value];
                        })->withAttr("firstpaymentamount", function ($value) use($row) {
                            return $row["prefix"] . $value . $row["suffix"];
                        })->withAttr("invoice_type", function ($value, $data) use($invoice_type) {
                            if ($invoice_type == "upgrade") {
                                return "upgrade";
                            }
                            return "";
                        })->order("a.id", "desc")->select()->toArray();
                        foreach ($hosts as $k1 => $v1) {
                            if (!empty($v1["type"])) {
                                if ($v1["type"] == "Immediate") {
                                    $v1["type"] = "立即停用";
                                } else {
                                    $v1["type"] = "到期时停用";
                                }
                                $hosts[$k1]["cancel_list"] = ["type" => $v1["type"], "reason" => $v1["reason"]];
                            }
                        }
                    }
                }
                $row["hosts"] = $hosts;
                $row["order_status"] = $order_status[$row["status"]];
                if (empty($row["invoiceid"]) || empty($invoice_status)) {
                    $row["pay_status"] = ["name" => lang("NO_INVOICE"), "color" => "#808080"];
                    $row["pay_status_tmp"] = "0";
                } else {
                    if (0 < $row["delete_time"]) {
                        $row["pay_status"] = ["name" => lang("账单已删除"), "color" => "#808080"];
                        $row["pay_status_tmp"] = "0";
                    } else {
                        $row["pay_status"] = $invoice_payment_status[$invoice_status];
                        $row["pay_status_tmp"] = $invoice_status;
                    }
                }
                unset($rows[$k]["am"]);
                $rows_filter[$row["id"]] = $row;
            }
        }
        $rows = array_values($rows_filter);
        $count = \think\Db::name("orders")->alias("o")->leftjoin("clients c", "o.uid=c.id")->leftJoin("invoices i", "o.invoiceid = i.id")->field("c.username,o.id")->where($fun)->where("o.create_time", ">=", $start_time)->where("o.create_time", "<=", $end_time)->count();
        $arr = ["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "list" => $rows, "count" => $count, "price_total" => $price_total, "price_total_page" => $price_total_page];
        session_write_close();
        return jsonrule($arr);
    }
    public function index11()
    {
        session_write_close();
        $page = input("page") ?? config("page");
        $limit = input("limit") ?? config("limit");
        $order = input("order");
        $sort = input("sort") ?? "desc";
        $params = $this->request->param();
        $result = $this->validate(["page" => $page, "limit" => $limit, "order" => $order, "sort" => $sort], "app\\admin\\validate\\OrderValidate");
        if ($result !== true) {
            return jsonrule($result, 400);
        }
        $start_time = $params["start_time"] ?? 0;
        $end_time = $params["end_time"] ?? time();
        $fun = function (\think\db\Query $query) use($params) {
            $str = $this->str;
            $query->where("o.delete_time", 0);
            if (isset($params["id"])) {
                $id = $params["id"];
                $query->where("o.id", "like", "%" . $id . "%");
            }
            if (isset($params["uid"])) {
                $username = $params["uid"];
                $query->where("c.id", $username);
            }
            if (isset($params["amount"])) {
                $amount = $params["amount"];
                $query->where("o.amount", "like", "%" . $amount . "%");
            }
            if (isset($params["ordernum"])) {
                $ordernum = $params["ordernum"];
                $query->where("o.ordernum", "like", "%" . $ordernum . "%");
            }
            if (isset($params["status"])) {
                $status = $params["status"];
                $query->where("o.status", "like", "%" . $status . "%");
            }
            if (isset($params["payment"]) && !empty($params["payment"])) {
                if ($params["payment"] == "creditLimitPay") {
                    $query->where("i.use_credit_limit", 1);
                } else {
                    if ($params["payment"] == "creditPay") {
                        $query->where("i.credit", ">", 0);
                    } else {
                        $query->where("o.payment", $params["payment"]);
                    }
                }
            }
            $sale_id = isset($params["sale_id"]) ? $params["sale_id"] : "";
            if (!empty($sale_id)) {
                $query->where("c.sale_id", "=", $sale_id);
            }
            if ($this->user["id"] != 1 && $this->user["is_sale"]) {
                $query->where("c.id", "in", $str);
            }
        };
        $price_total = \think\Db::name("orders")->alias("o")->leftjoin("clients c", "o.uid=c.id")->leftjoin("currencies cu", "cu.id = c.currency")->leftJoin("user u", "u.id = c.sale_id")->leftJoin("invoices i", "o.invoiceid = i.id")->field("o.id,c.sale_id,o.invoiceid,cu.prefix,cu.suffix,o.amount")->where($fun)->whereBetweenTime("o.create_time", $start_time, $end_time)->sum("o.amount");
        $gateways = gateway_list("gateways", 0);
        $rows = \think\Db::name("orders")->alias("o")->leftjoin("clients c", "o.uid=c.id")->leftjoin("currencies cu", "cu.id = c.currency")->leftJoin("user u", "u.id = c.sale_id")->leftJoin("invoices i", "o.invoiceid = i.id")->field("c.username,c.companyname,o.id,o.uid,c.sale_id,o.status,o.ordernum,i.status as i_status,i.subtotal as sub,i.credit,i.use_credit_limit,o.create_time,o.invoiceid,o.amount,o.amount as am,o.payment,cu.prefix,cu.suffix,u.user_nickname")->withAttr("payment", function ($value, $data) use($gateways) {
            if ($data["i_status"] == "Paid") {
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
        })->withAttr("amount", function ($value, $data) {
            return $data["prefix"] . $value . $data["suffix"];
        })->where($fun)->whereBetweenTime("o.create_time", $start_time, $end_time)->page($page)->limit($limit)->order($order, $sort)->select()->toArray();
        $billingcycle = config("billing_cycle");
        $order_status = config("order_status");
        $invoice_payment_status = config("invoice_payment_status");
        $price_total_page = 0;
        foreach ($rows as $k => $row) {
            $price_total_page = bcadd($row["am"], $price_total_page, 2);
            $invoices = \think\Db::name("invoices")->field("status,type,subtotal")->where("id", $row["invoiceid"])->where("delete_time", 0)->find();
            $invoice_status = $invoices["status"];
            $invoice_type = $invoices["type"];
            $exist_upgrade_order = \think\Db::name("upgrades")->where("order_id", $row["id"])->find();
            if (!empty($exist_upgrade_order)) {
                $invoice_type = "upgrade";
            }
            $hosts = [];
            if ($invoice_type == "zjmf_flow_packet") {
                $hosts[] = ["hostid" => 0, "name" => "流量包", "firstpaymentamount" => $row["prefix"] . $invoices["subtotal"] . $row["suffix"], "billingcycle" => " -"];
            } else {
                if ($invoice_type == "zjmf_reinstall_times") {
                    $hosts[] = ["hostid" => 0, "name" => "重装次数", "firstpaymentamount" => $row["prefix"] . $invoices["subtotal"] . $row["suffix"], "billingcycle" => " -"];
                } else {
                    $hosts = \think\Db::name("host")->alias("a")->leftJoin("products b", "a.productid = b.id")->leftJoin("upgrades u", "u.relid = a.id")->leftJoin("cancel_requests cr", "cr.relid = a.id")->field("cr.type,cr.reason,a.initiative_renew,a.id as hostid,b.id as invoice_type,b.name,a.domain,a.dedicatedip,a.billingcycle,a.firstpaymentamount,a.productid")->distinct(true)->where(function (\think\db\Query $query) use($row) {
                        $query->where("a.orderid", $row["id"]);
                        $query->whereOr("u.order_id", $row["id"]);
                    })->withAttr("billingcycle", function ($value) {
                        return $billingcycle[$value];
                    })->withAttr("firstpaymentamount", function ($value) use($row) {
                        return $row["prefix"] . $value . $row["suffix"];
                    })->withAttr("invoice_type", function ($value, $data) use($invoice_type) {
                        if ($invoice_type == "upgrade") {
                            return "upgrade";
                        }
                        return "";
                    })->order("a.id", "desc")->select()->toArray();
                    foreach ($hosts as $k1 => $v1) {
                        if (!empty($v1["type"])) {
                            if ($v1["type"] == "Immediate") {
                                $v1["type"] = "立即停用";
                            } else {
                                $v1["type"] = "到期时停用";
                            }
                            $hosts[$k1]["cancel_list"] = ["type" => $v1["type"], "reason" => $v1["reason"]];
                        }
                    }
                }
            }
            $rows[$k]["hosts"] = $hosts;
            $rows[$k]["order_status"] = $order_status[$row["status"]];
            if (empty($row["invoiceid"]) || empty($invoice_status)) {
                $rows[$k]["pay_status"] = ["name" => lang("NO_INVOICE"), "color" => "#2F4F4F"];
                $rows[$k]["pay_status_tmp"] = "0";
            } else {
                $rows[$k]["pay_status"] = $invoice_payment_status[$invoice_status];
                $rows[$k]["pay_status_tmp"] = $invoice_status;
            }
        }
        $count = \think\Db::name("orders")->alias("o")->leftjoin("clients c", "o.uid=c.id")->field("c.username,o.id")->where($fun)->whereBetweenTime("o.create_time", $start_time, $end_time)->count();
        if (isset($params["pay_status"]) && $params["pay_status"]) {
            $i = 0;
            $row_filter = [];
            foreach ($rows as $row) {
                if ($params["pay_status"] === $row["pay_status_tmp"]) {
                    $row_filter[] = $row;
                    $i++;
                }
            }
            $count = $i;
            $rows = $row_filter;
        }
        session_write_close();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "list" => $rows, "count" => $count, "price_total" => $price_total, "price_total_page" => $price_total_page]);
    }
    public function indexPost()
    {
        session_write_close();
        $page = input("page") ?? config("page");
        $limit = input("limit") ?? config("limit");
        $order = input("order");
        $sort = input("sort") ?? "desc";
        $params = $this->request->param();
        $str = $this->str;
        $result = $this->validate(["page" => $page, "limit" => $limit, "order" => $order, "sort" => $sort], "app\\admin\\validate\\OrderValidate");
        if ($result !== true) {
            return jsonrule($result, 400);
        }
        $where = ["o.delete_time" => 0];
        $id = isset($params["id"]) ? $params["id"] : "";
        $username = isset($params["username"]) ? $params["username"] : "";
        $start_time = $params["start_time"] ?? 0;
        $end_time = $params["end_time"] ?? time();
        $amount = isset($params["amount"]) ? $params["amount"] : "";
        $status = isset($params["status"]) ? $params["status"] : "";
        $ordernum = isset($params["ordernum"]) ? $params["ordernum"] : "";
        $gateways = gateway_list("gateways", 0);
        $rows = \think\Db::name("orders")->alias("o")->leftjoin("clients c", "o.uid=c.id")->leftjoin("currencies cu", "cu.id = c.currency")->leftJoin("user u", "u.id = c.sale_id")->leftJoin("invoices i", "o.invoiceid = i.id")->field("i.status,i.type,o.id,c.sale_id,o.invoiceid,cu.prefix,cu.suffix,o.amount as am,i.use_credit_limit,i.invoice_id")->withAttr("payment", function ($value) {
            foreach ($gateways as $v) {
                if ($v["name"] == $value) {
                    return $v["title"];
                }
            }
        })->withAttr("amount", function ($value, $data) {
            return $data["prefix"] . $value . $data["suffix"];
        })->where($where)->where("o.id", "like", "%" . $id . "%")->where("c.username", "like", "%" . $username . "%")->where("o.create_time", ">=", $start_time)->where("o.create_time", "<=", $end_time)->where("o.ordernum", "like", "%" . $ordernum . "%")->where("o.amount", "like", "%" . $amount . "%")->where("o.status", "like", "%" . $status . "%")->where(function (\think\db\Query $query) use($params, $str) {
            if (isset($params["payment"]) && !empty($params["payment"])) {
                if ($params["payment"] == "creditLimitPay") {
                    $query->where("i.use_credit_limit", 1);
                } else {
                    if ($params["payment"] == "creditPay") {
                        $query->where("i.credit", ">", 0);
                    } else {
                        $query->where("o.payment", $params["payment"]);
                    }
                }
            }
            if (isset($params["sale_id"]) && !empty($params["sale_id"])) {
                $query->where("c.sale_id", $params["sale_id"]);
            }
            if ($this->user["id"] != 1 && $this->user["is_sale"]) {
                $query->where("c.id", "in", $str);
            }
            if (isset($params["product_name"]) && !empty($params["product_name"])) {
                $pro_ids = \think\Db::name("products")->whereLike("name", "%" . $params["product_name"] . "%")->column("id");
                $_host_id = \think\Db::name("host")->whereIn("productid", $pro_ids)->column("orderid");
                $query->whereIn("o.id", $_host_id);
            }
            if (1 <= strlen($params["pay_status"])) {
                if ($params["pay_status"] == 0 && strlen($params["pay_status"]) == 1) {
                    $query->where("o.invoiceid", "");
                } else {
                    $query->where("i.status", $params["pay_status"]);
                }
            }
        })->page($page)->limit($limit)->order($order, $sort)->select()->toArray();
        $row1 = \think\Db::name("orders")->alias("o")->leftjoin("clients c", "o.uid=c.id")->leftjoin("currencies cu", "cu.id = c.currency")->leftJoin("user u", "u.id = c.sale_id")->leftJoin("invoices i", "o.invoiceid = i.id")->field("o.id,c.sale_id,o.invoiceid,cu.prefix,cu.suffix,o.amount,i.use_credit_limit,i.invoice_id")->where($where)->where("o.id", "like", "%" . $id . "%")->where("c.username", "like", "%" . $username . "%")->where("o.create_time", ">=", $start_time)->where("o.create_time", "<=", $end_time)->where("o.ordernum", "like", "%" . $ordernum . "%")->where("o.amount", "like", "%" . $amount . "%")->where("o.status", "like", "%" . $status . "%")->where(function (\think\db\Query $query) use($params, $str) {
            if (isset($params["payment"]) && !empty($params["payment"])) {
                if ($params["payment"] == "creditLimitPay") {
                    $query->where("i.use_credit_limit", 1);
                } else {
                    if ($params["payment"] == "creditPay") {
                        $query->where("i.credit", ">", 0);
                    } else {
                        $query->where("o.payment", $params["payment"]);
                    }
                }
            }
            if (isset($params["sale_id"]) && !empty($params["sale_id"])) {
                $query->where("c.sale_id", $params["sale_id"]);
            }
            if (isset($params["product_name"]) && !empty($params["product_name"])) {
                $pro_ids = \think\Db::name("products")->whereLike("name", "%" . $params["product_name"] . "%")->column("id");
                $_host_id = \think\Db::name("host")->whereIn("productid", $pro_ids)->column("orderid");
                $query->whereIn("o.id", $_host_id);
            }
            if ($this->user["id"] != 1 && $this->user["is_sale"]) {
                $query->where("c.id", "in", $str);
            }
            if (1 <= strlen($params["pay_status"])) {
                if ($params["pay_status"] == 0 && strlen($params["pay_status"]) == 1) {
                    $query->where("o.invoiceid", "");
                } else {
                    $query->where("i.status", $params["pay_status"]);
                }
            }
        })->select()->toArray();
        $sums1 = 0;
        foreach ($row1 as $k => $row) {
            $sums1 = bcadd($row["amount"], $sums1);
        }
        $sums = 0;
        foreach ($rows as $k => &$row) {
            $sums = bcadd($row["am"], $sums);
            $invoice_status = $row["status"];
            $invoice_type = $row["type"];
            if ($invoice_type == "upgrade") {
                $hosts = \think\Db::name("host")->alias("a")->leftJoin("products b", "a.productid = b.id")->leftJoin("sale_products c", "c.pid = b.id")->leftJoin("sales_product_groups d", "d.id = c.gid")->leftJoin("upgrades u", "u.relid = a.id")->field("a.id as hostid,b.name,a.billingcycle,u.id as upid,a.firstpaymentamount,a.productid,d.bates,d.renew_bates,d.upgrade_bates,d.is_renew,d.updategrade")->distinct(true)->where(function (\think\db\Query $query) use($row) {
                    $query->where("a.orderid", $row["id"]);
                    $query->whereOr("u.order_id", $row["id"]);
                })->withAttr("billingcycle", function ($value) {
                    return config("billing_cycle")[$value];
                })->withAttr("firstpaymentamount", function ($value) use($row) {
                    return $row["prefix"] . $value . $row["suffix"];
                })->withAttr("name", function ($value, $data) use($invoice_type) {
                    if ($invoice_type == "upgrade") {
                        return "[升降级]" . $value;
                    }
                    return $value;
                })->order("a.id", "desc")->select()->toArray();
            } else {
                $hosts = \think\Db::name("invoices")->alias("i")->leftJoin("invoice_items im", "im.invoice_id = i.id")->leftJoin("host a", "a.id = im.rel_id")->leftJoin("products b", "a.productid = b.id")->leftJoin("sale_products c", "c.pid = b.id")->leftJoin("sales_product_groups d", "d.id = c.gid")->field("a.id as hostid,b.name,a.billingcycle,a.firstpaymentamount")->field("a.productid,d.bates,d.renew_bates,d.upgrade_bates,d.is_renew,d.updategrade")->distinct(true)->where("i.id", $row["invoiceid"])->withAttr("billingcycle", function ($value) {
                    return config("billing_cycle")[$value];
                })->withAttr("firstpaymentamount", function ($value) use($row) {
                    return $row["prefix"] . $value . $row["suffix"];
                })->withAttr("name", function ($value, $data) use($invoice_type) {
                    if ($invoice_type == "upgrade") {
                        return "[升降级]" . $value;
                    }
                    return $value;
                })->order("a.id", "desc")->select()->toArray();
            }
            $sum = 0;
            $sum1 = 0;
            $refund = 0;
            $refund1 = 0;
            $wheres = [];
            $ladder = $this->getLadder($row["sale_id"]);
            $rows[$k]["ladder"] = $ladder;
            if ($invoice_status == "Paid" && ($row["use_credit_limit"] == 0 || $row["use_credit_limit"] == 1 && 0 < $row["invoice_id"])) {
                foreach ($hosts as $val) {
                    $wheres[] = ["type", "neq", "renew"];
                    if ($val["updategrade"] == 1 && !empty($val["upid"])) {
                        $nums = \think\Db::name("invoice_items")->where("rel_id", $val["upid"])->where("type", "upgrade")->where($wheres)->sum("amount");
                        $sum = bcadd(bcmul($val["upgrade_bates"] / 100, $nums, 4), $sum, 4);
                    } else {
                        if (!empty($val["upid"])) {
                            $nums = 0;
                        } else {
                            $nums = \think\Db::name("invoice_items")->where("rel_id", $val["hostid"])->where("invoice_id", $row["invoiceid"])->where($wheres)->sum("amount");
                        }
                        $sum = bcadd(bcmul($val["bates"] / 100, $nums, 4), $sum, 4);
                    }
                    if (!empty($ladder["turnover"]["turnover"])) {
                        $sum1 = bcadd(bcmul($ladder["turnover"]["bates"] / 100, $nums, 4), $sum1, 4);
                    }
                }
                $amount_out = \think\Db::name("accounts")->field("id,amount_out")->where("invoice_id", $row["invoiceid"])->where("refund", ">", 0)->sum("amount_out");
                $bates = 0;
                foreach ($hosts as $vals) {
                    if ($val["updategrade"] == 1 && !empty($val["upid"])) {
                        if ($vals["upgrade_bates"] / 100 != 0) {
                            $bates = $vals["upgrade_bates"] / 100;
                        }
                    } else {
                        if ($vals["bates"] / 100 != 0) {
                            $bates = $vals["bates"] / 100;
                        }
                    }
                }
                $refund = bcmul($bates, $amount_out, 4);
                if (!empty($ladder["turnover"]["turnover"])) {
                    $refund1 = bcmul($ladder["turnover"]["bates"] / 100, $amount_out, 4);
                }
                $sum = round(bcsub($sum, $refund, 4), 2);
                if ($sum < 0) {
                    $sum = 0;
                }
                $sum1 = round(bcsub($sum1, $refund1, 4), 2);
                if ($sum1 < 0) {
                    $sum1 = 0;
                }
                if (0 < $sum1) {
                    $rows[$k]["sum2"] = $row["prefix"] . number_format($sum, 2, ".", "") . $row["suffix"];
                    $sum = bcadd($sum, $sum1);
                    $rows[$k]["flag"] = true;
                    $rows[$k]["sum"] = $row["prefix"] . number_format($sum, 2, ".", "") . $row["suffix"];
                    $rows[$k]["sum1"] = $row["prefix"] . number_format($sum1, 2, ".", "") . $row["suffix"];
                } else {
                    $rows[$k]["sum2"] = $row["prefix"] . number_format($sum, 2, ".", "") . $row["suffix"];
                    $rows[$k]["flag"] = true;
                    $rows[$k]["sum"] = $row["prefix"] . number_format($sum, 2, ".", "") . $row["suffix"];
                }
            } else {
                $rows[$k]["flag"] = false;
                $rows[$k]["sum"] = $row["prefix"] . "0.00" . $row["suffix"];
            }
        }
        session_write_close();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "list" => $rows, "price" => $sums, "totalprice" => $sums1]);
    }
    public function indexSale()
    {
        session_write_close();
        $page = input("page") ?? config("page");
        $limit = input("limit") ?? config("limit");
        $order = input("order");
        $sort = input("sort") ?? "desc";
        $params = $this->request->param();
        $str = $this->str;
        $result = $this->validate(["page" => $page, "limit" => $limit, "order" => $order, "sort" => $sort], "app\\admin\\validate\\OrderValidate");
        if ($result !== true) {
            return jsonrule($result, 400);
        }
        $where = ["o.delete_time" => 0];
        $id = isset($params["id"]) ? $params["id"] : "";
        $username = isset($params["username"]) ? $params["username"] : "";
        $start_time = $params["start_time"] ?? 0;
        $end_time = $params["end_time"] ?? time();
        $amount = isset($params["amount"]) ? $params["amount"] : "";
        $status = isset($params["status"]) ? $params["status"] : "";
        $ordernum = isset($params["ordernum"]) ? $params["ordernum"] : "";
        $rows = \think\Db::name("orders")->alias("o")->join("clients c", "o.uid=c.id")->join("currencies cu", "cu.id = c.currency")->join("user u", "u.id = c.sale_id")->field("o.id,c.sale_id,o.invoiceid,cu.prefix,cu.suffix,o.amount")->where($where)->where("o.id", "like", "%" . $id . "%")->where("c.username", "like", "%" . $username . "%")->whereBetweenTime("o.create_time", $start_time, $end_time)->where("o.ordernum", "like", "%" . $ordernum . "%")->where("o.amount", "like", "%" . $amount . "%")->where("o.status", "like", "%" . $status . "%")->where(function (\think\db\Query $query) use($params, $str) {
            if (isset($params["payment"]) && !empty($params["payment"])) {
                $query->where("o.payment", $params["payment"]);
            }
            if ($this->user["id"] != 1 && $this->user["is_sale"]) {
                $query->where("c.id", "in", $str);
            }
        })->select()->toArray();
        $sum = 0;
        foreach ($rows as $k => $row) {
            $sum = bcadd($row["amount"], $sum);
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "sum" => $sum]);
    }
    public function indexPostone()
    {
        session_write_close();
        $param = $this->request->param();
        $rows = [];
        $invoices = \think\Db::name("invoices")->field("status,type")->where("id", $param["invoiceid"])->where("delete_time", 0)->find();
        $invoice_status = $invoices["status"];
        $invoice_type = $invoices["type"];
        $hosts = \think\Db::name("host")->alias("a")->leftJoin("products b", "a.productid = b.id")->leftJoin("sale_products c", "c.pid = b.id")->leftJoin("sales_product_groups d", "d.id = c.gid")->leftJoin("upgrades u", "u.relid = a.id")->field("a.id as hostid,b.name,a.billingcycle,u.id as upid,a.firstpaymentamount,a.productid,d.bates,d.is_renew,d.updategrade")->distinct(true)->where(function (\think\db\Query $query) use($param) {
            $query->where("a.orderid", $param["id"]);
            $query->whereOr("u.order_id", $param["id"]);
        })->withAttr("billingcycle", function ($value) {
            return config("billing_cycle")[$value];
        })->withAttr("firstpaymentamount", function ($value) use($param) {
            return $param["prefix"] . $value . $param["suffix"];
        })->withAttr("name", function ($value, $data) use($invoice_type) {
            if ($invoice_type == "upgrade") {
                return "[升降级]" . $value;
            }
            return $value;
        })->order("a.id", "desc")->select()->toArray();
        $sum = 0;
        $sum1 = 0;
        $refund = 0;
        $refund1 = 0;
        $wheres = [];
        $ladder = $this->getLadder($param["sale_id"]);
        $rows["ladder"] = $ladder;
        if ($invoice_status == "Paid") {
            foreach ($hosts as $val) {
                $wheres[] = ["type", "neq", "renew"];
                if ($val["updategrade"] == 1 && !empty($val["upid"])) {
                    $nums = \think\Db::name("invoice_items")->where("rel_id", $val["upid"])->where("type", "upgrade")->where($wheres)->sum("amount");
                } else {
                    if (!empty($val["upid"])) {
                        $nums = 0;
                    } else {
                        $nums = \think\Db::name("invoice_items")->where("rel_id", $val["hostid"])->where($wheres)->sum("amount");
                    }
                }
                $sum = bcadd(bcmul($val["bates"] / 100, $nums, 4), $sum, 4);
                if (!empty($ladder["turnover"]["turnover"])) {
                    $sum1 = bcadd(bcmul($ladder["turnover"]["bates"] / 100, $nums, 4), $sum1, 4);
                }
            }
            $amount_out = \think\Db::name("accounts")->field("id,amount_out")->where("invoice_id", $param["invoiceid"])->where("refund", ">", 0)->sum("amount_out");
            $bates = 0;
            foreach ($hosts as $vals) {
                if ($vals["bates"] / 100 != 0) {
                    $bates = $vals["bates"] / 100;
                }
            }
            $refund = bcmul($bates, $amount_out, 4);
            if (!empty($ladder["turnover"]["turnover"])) {
                $refund1 = bcmul($ladder["turnover"]["bates"] / 100, $amount_out, 4);
            }
            $sum = round(bcsub($sum, $refund, 4), 2);
            if ($sum < 0) {
                $sum = 0;
            }
            $sum1 = round(bcsub($sum1, $refund1, 4), 2);
            if ($sum1 < 0) {
                $sum1 = 0;
            }
            if (0 < $sum1) {
                $rows["flag"] = true;
                $rows["sum"] = number_format($sum, 2, ".", "") . " + " . number_format($sum1, 2, ".", "");
            } else {
                $rows["flag"] = false;
                $rows["sum"] = number_format($sum, 2, ".", "");
            }
        } else {
            $rows["sum"] = "0.00";
        }
        session_write_close();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "list" => $rows]);
    }
    public function check()
    {
        $params = $this->request->param();
        $ids = $params["ids"];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        if (empty($ids[0])) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $invoiceids = \think\Db::name("orders")->field("invoiceid,id,uid")->where("delete_time", 0)->whereIn("id", $ids)->select()->toArray();
        $hosts = \think\Db::name("invoice_items")->alias("a")->field("b.id,c.auto_setup")->leftJoin("host b", "a.rel_id = b.id")->leftJoin("products c", "b.productid = c.id")->where("a.type", "host")->where("a.delete_time", 0)->whereIn("a.invoice_id", array_column($invoiceids, "invoiceid"))->select()->toArray();
        $update = \think\Db::name("orders")->where("delete_time", 0)->whereIn("id", $ids)->update(["status" => "Active"]);
        if ($update) {
            foreach ($hosts as $host) {
                if ($host["auto_setup"] == "on") {
                    $host_logic = new \app\common\logic\Host();
                    $host_logic->is_admin = true;
                    $result = $host_logic->create($host["id"]);
                    $logic_run_map = new \app\common\logic\RunMap();
                    $model_host = new \app\common\model\HostModel();
                    $data_i = [];
                    $data_i["host_id"] = $host["id"];
                    $data_i["active_type_param"] = [$host["id"], ""];
                    $is_zjmf = $model_host->isZjmfApi($data_i["host_id"]);
                    if ($result["status"] == 200) {
                        $data_i["description"] = " 手动审核后 - 开通 Host ID:" . $data_i["host_id"] . "的产品成功";
                        if ($is_zjmf) {
                            $logic_run_map->saveMap($data_i, 1, 400, 1, 1);
                        }
                        if (!$is_zjmf) {
                            $logic_run_map->saveMap($data_i, 1, 200, 1, 1);
                        }
                    } else {
                        $data_i["description"] = " 手动审核后 - 开通 Host ID:" . $data_i["host_id"] . "的产品失败：" . $result["msg"];
                        if ($is_zjmf) {
                            $logic_run_map->saveMap($data_i, 0, 400, 1, 1);
                        }
                        if (!$is_zjmf) {
                            $logic_run_map->saveMap($data_i, 0, 200, 1, 1);
                        }
                    }
                }
            }
        }
        foreach ($invoiceids as $ids1) {
            active_log(sprintf($this->lang["Order_admin_check_success"], $ids1["uid"], $ids1["id"]), $ids1["uid"]);
            hook("order_pass_check", ["orderid" => $ids1["id"]]);
        }
        return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
    }
    public function cancel()
    {
        $params = $this->request->param();
        $ids = $params["ids"];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        if (empty($ids[0])) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $invoiceids = \think\Db::name("orders")->whereIn("id", $ids)->column("invoiceid");
        $invoiceidss = \think\Db::name("orders")->field("id,uid")->whereIn("id", $ids)->select();
        $hostids = \think\Db::name("invoice_items")->field("rel_id,uid,id")->where("type", "host")->where("delete_time", 0)->whereIn("invoice_id", $invoiceids)->select()->toArray();
        $productids_qty = \think\Db::name("orders")->alias("o")->leftJoin("host h", "o.id = h.orderid")->whereIn("o.id", $ids)->whereIn("o.status", ["Pending", "Active", "Suspend"])->column("h.productid");
        \think\Db::startTrans();
        try {
            \think\Db::name("orders")->where("delete_time", 0)->whereIn("id", $ids)->update(["status" => "Cancelled"]);
            foreach ($invoiceids as $invoiceid) {
                $invoice_status = \think\Db::name("invoices")->where("delete_time", 0)->where("id", $invoiceid)->value("status");
                if ($invoice_status != "Paid") {
                    \think\Db::name("invoices")->where("delete_time", 0)->where("id", $invoiceid)->update(["status" => "Cancelled"]);
                }
            }
            \think\Db::name("host")->whereIn("id", array_column($hostids, "rel_id"))->update(["domainstatus" => "Cancelled"]);
            \think\Db::name("products")->whereIn("id", $productids_qty)->setInc("qty", 1);
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 400, "msg" => lang("取消失败")]);
        }
        foreach ($invoiceidss as $ids1) {
            active_log(sprintf($this->lang["Order_admin_cancel_success"], $ids1["uid"], $ids1["id"]), $ids1["uid"]);
            active_log(sprintf($this->lang["Order_admin_cancel_success"], $ids1["uid"], $ids1["id"]), $ids1["uid"], "", 2);
            hook("order_cancel", ["orderid" => $ids1["id"]]);
        }
        return jsonrule(["status" => 200, "msg" => lang("取消成功")]);
    }
    public function delete()
    {
        $params = $this->request->param();
        $ids = $params["ids"];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        if (empty($ids[0])) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $orders = \think\Db::name("orders")->field("invoiceid,uid,id")->whereIn("id", $ids)->where("delete_time", 0)->select()->toArray();
        $invoiceids = array_column($orders, "invoiceid");
        \think\Db::name("invoice_items")->whereIn("invoice_id", $invoiceids)->select()->toArray();
        $hosts = \think\Db::name("host")->field("id,productid")->whereIn("orderid", $ids)->select()->toArray();
        $hostids = array_column($hosts, "id");
        $productids = array_column($hosts, "productid");
        $customfields = \think\Db::name("customfields")->field("id")->where("type", "product")->whereIn("relid", $productids)->select()->toArray();
        $customfieldids = array_column($customfields, "id");
        $productids_qty = \think\Db::name("orders")->alias("o")->leftJoin("host h", "o.id = h.orderid")->whereIn("o.id", $ids)->whereIn("o.status", ["Pending", "Active", "Suspend"])->column("h.productid");
        \think\Db::startTrans();
        try {
            \think\Db::name("orders")->whereIn("id", $ids)->delete();
            \think\Db::name("host_config_options")->whereIn("relid", $hostids)->delete();
            \think\Db::name("host")->whereIn("orderid", $ids)->delete();
            \think\Db::name("invoice_items")->whereIn("invoice_id", $invoiceids)->delete();
            \think\Db::name("invoices")->whereIn("id", $invoiceids)->delete();
            foreach ($hostids as $hostid) {
                \think\Db::name("customfieldsvalues")->whereIn("fieldid", $customfieldids)->where("relid", $hostid)->delete();
            }
            \think\Db::name("products")->whereIn("id", $productids_qty)->setInc("qty", 1);
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
        }
        foreach ($orders as $ids) {
            active_log(sprintf($this->lang["Order_admin_delete_success"], $ids["uid"], $ids["id"]), $ids["uid"]);
            active_log(sprintf($this->lang["Order_admin_delete_success"], $ids["uid"], $ids["id"]), $ids["uid"], "", 2);
            hook("order_delete", ["orderid" => $ids1["id"]]);
        }
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function createPage()
    {
        session_write_close();
        $params = $this->request->param();
        if ($params["flag"] == 1) {
            $cycle = config("billing_cycle");
            $currency_id = \think\Db::name("currencies")->where("default", 1)->value("id");
            $pid = $params["pid"];
            $product_info = \think\Db::name("products")->field("pay_type")->where("id", $pid)->find();
            $pricing = \think\Db::name("pricing")->where("type", "product")->where("currency", $currency_id)->where("relid", $pid)->find();
            $pay_type = json_decode($product_info["pay_type"], true);
            $product["cycle"] = [];
            if ($pay_type["pay_type"] == "free") {
                $cycle_show = ["value" => "free", "label" => $cycle["free"]];
                $product["cycle"][] = $cycle_show;
            } else {
                if ($pay_type["pay_type"] == "onetime") {
                    if (0 <= $pricing["onetime"]) {
                        $cycle_show = ["value" => "onetime", "label" => $cycle["onetime"]];
                        $product["cycle"][] = $cycle_show;
                    }
                } else {
                    if ($pay_type["pay_type"] == "recurring") {
                        if (0 <= $pricing["hour"]) {
                            $cycle_show = ["value" => "hour", "label" => $cycle["hour"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["day"]) {
                            $cycle_show = ["value" => "day", "label" => $cycle["day"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["monthly"]) {
                            $cycle_show = ["value" => "monthly", "label" => $cycle["monthly"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["quarterly"]) {
                            $cycle_show = ["value" => "quarterly", "label" => $cycle["quarterly"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["semiannually"]) {
                            $cycle_show = ["value" => "semiannually", "label" => $cycle["semiannually"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["annually"]) {
                            $cycle_show = ["value" => "annually", "label" => $cycle["annually"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["biennially"]) {
                            $cycle_show = ["value" => "biennially", "label" => $cycle["biennially"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["triennially"]) {
                            $cycle_show = ["value" => "triennially", "label" => $cycle["triennially"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["fourly"]) {
                            $cycle_show = ["value" => "fourly", "label" => $cycle["fourly"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["fively"]) {
                            $cycle_show = ["value" => "fively", "label" => $cycle["fively"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["sixly"]) {
                            $cycle_show = ["value" => "sixly", "label" => $cycle["sixly"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["sevenly"]) {
                            $cycle_show = ["value" => "sevenly", "label" => $cycle["sevenly"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["eightly"]) {
                            $cycle_show = ["value" => "eightly", "label" => $cycle["eightly"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["ninely"]) {
                            $cycle_show = ["value" => "ninely", "label" => $cycle["ninely"]];
                            $product["cycle"][] = $cycle_show;
                        }
                        if (0 <= $pricing["tenly"]) {
                            $cycle_show = ["value" => "tenly", "label" => $cycle["tenly"]];
                            $product["cycle"][] = $cycle_show;
                        }
                    }
                }
            }
            if (!empty($pay_type["pay_ontrial_status"])) {
                $product["cycle"][] = ["value" => "ontrial", "label" => $cycle["ontrial"]];
            }
            $data = ["product" => $product];
            return jsonrule(["data" => $data, "msg" => lang("SUCCESS MESSAGE")], 200);
        }
        if (isset($params["uid"]) && !empty($params["uid"])) {
            $sessionAdminId = session("ADMIN_ID");
            if ($sessionAdminId != 1 && $this->user["is_sale"] == 1 && !in_array($params["uid"], $this->str)) {
                return jsonrule(["status" => 400, "msg" => "此销售员没有这个客户，无法添加"]);
            }
        }
        $users = [];
        $data = ["users" => $users, "default" => $default ?? []];
        return jsonrule(["data" => $data, "msg" => lang("SUCCESS MESSAGE")], 200);
    }
    public function setConfig()
    {
        $data = $this->request->param();
        $pid = intval($data["pid"]);
        $billingcycle = $data["billingcycle"] ?? "monthly";
        $defaultcurrency = db("currencies")->field("id,code,prefix,suffix")->where("default", 1)->find();
        $currencyid = $defaultcurrency["id"];
        $fields = (new \app\common\logic\Customfields())->getCartCustomField($pid, 1);
        $product = (new \app\common\logic\Cart())->getProductPricing($pid, $currencyid);
        $alloption = controller("app\\home\\controller\\CartController")->getOptions($pid, $currencyid, true);
        return jsonrule(["status" => 200, "msg" => "请求成功", "product" => $product, "option" => $alloption, "custom_fields" => $fields]);
    }
    public function getMultiTotal()
    {
        $params = $this->request->only(["pid", "billingcycle", "configoption", "price_override", "price_override_renew", "qty", "uid", "customfield", "code"]);
        $cart = new \app\common\logic\Cart();
        $res = [];
        $res["status"] = 200;
        $res["msg"] = lang("SUCCESS MESSAGE");
        $uid = intval($params["uid"]);
        $client = \think\Db::name("clients")->field("credit")->where("id", $uid)->find();
        if (!$client) {
            return jsonrule(["status" => 400, "msg" => lang("用户不存在")]);
        }
        $credit = $client["credit"];
        if (0 < $credit) {
            $res["credit"] = $credit;
        }
        $currencyid = priorityCurrency($uid);
        $currency_client = \think\Db::name("currencies")->field("id,code,prefix,suffix,rate")->where("id", $currencyid)->find();
        $res["currency"] = $currency_client;
        $rate = 1;
        $pids = $params["pid"];
        $billingcycles = $params["billingcycle"];
        $price_overrides = $params["price_override"];
        $price_override_renews = $params["price_override_renew"];
        $configoption = $params["configoption"];
        $customfield = $params["customfield"];
        $qtys = $params["qty"];
        $promo_code = trim($params["code"]);
        $billing_cycle = config("billing_cycle");
        $total = $discount = $saleproduct = $subtotal = 0;
        $promo = \think\Db::name("promo_code")->where("code", $promo_code)->find();
        bcscale(2);
        if (!is_array($pids) || empty($pids)) {
            return jsonrule(["status" => 400, "msg" => lang("参数错误")]);
        }
        foreach ($pids as $kp => $pid) {
            $price_override = $price_overrides[$kp];
            $price_override_renew = $price_override_renews[$kp];
            $products = \think\Db::name("products")->where("id", $pid)->select()->toArray();
            if (empty($products)) {
                return jsonrule(["status" => 400, "msg" => lang("产品不存在")]);
            }
            $current_product_price_total = 0;
            $billingcycle = $billingcycles[$kp];
            $qty = $qtys[$kp] ?: 1;
            $customfield = $customfield[$kp];
            $field_model = new \app\common\model\CustomfieldsModel();
            $fields = (new \app\common\logic\Customfields())->getCartCustomField($pid, 1);
            if ($field_model->check($fields, $customfield)["status"] == "success") {
                foreach ($customfield as $key => $value) {
                    $field_model->updateCustomValue($key, $pid, $customfield, "product");
                }
            }
            $all_option = $product_filter = [];
            $format_zero = number_format(0, 2);
            if (!in_array($billingcycle, array_keys($billing_cycle))) {
                return jsonrule(["status" => 400, "msg" => lang("CYCLE_ERROR")]);
            }
            $setupfeecycle = $cart->changeCycleToupfee($billingcycle);
            $product = \think\Db::name("products")->alias("a")->field("a.name,a.pay_type,b.*,a.api_type,a.upstream_version,a.upstream_price_type,a.upstream_price_value")->leftJoin("pricing b", "a.id = b.relid")->where("a.id", $pid)->where("b.type", "product")->where("b.currency", $currencyid)->find();
            if ($product[$billingcycle] < 0) {
                return jsonrule(["status" => 400, "msg" => "请配置产品'" . $product["name"] . "'在货币'" . $currency_client["code"] . "'下的价格"]);
            }
            $product_filter["name"] = $product["name"];
            $product_filter["billingcycle"] = $billingcycle;
            $product_filter["billingcycle_zh"] = $billing_cycle[$billingcycle];
            $product_filter["qty"] = $qty;
            $price_setup = 0 < $price_override ? $format_zero : bcsub($billingcycle == "free" ? number_format(0, 2) : (0 < $product[$setupfeecycle] ? $product[$setupfeecycle] * $rate : number_format(0, 2)), 0, 2);
            $price_cycle = 0 < $price_override ? bcsub($price_override, 0, 2) * $rate : bcsub($billingcycle == "free" ? number_format(0, 2) : (0 < $product[$billingcycle] ? $product[$billingcycle] * $rate : number_format(0, 2)), 0, 2);
            if ($product["api_type"] == "zjmf_api" && 0 < $product["upstream_version"] && $product["upstream_price_type"] == "percent") {
                $price_setup = bcmul($price_setup, $product["upstream_price_value"]) / 100;
                $price_cycle = bcmul($price_cycle, $product["upstream_price_value"]) / 100;
            }
            $product_filter["product_setup_fee"] = $price_setup;
            $product_filter["product_price"] = $price_cycle;
            $product_base_sale = bcadd($price_setup, $price_cycle);
            $product_base_sale_setupfee = $price_setup;
            $product_base_sale_price = $price_cycle;
            $product_rebate_price = $price_cycle;
            $product_rebate_setupfee = $price_setup;
            $edition = getEdition();
            $configoptions_base_sale = [];
            foreach ($configoption as $k => $v) {
                if ($k == $kp) {
                    foreach ($v as $kk => $vv) {
                        $option_types = \think\Db::name("product_config_options")->where("id", $kk)->field("option_type,is_discount")->find();
                        $option_type = $option_types["option_type"];
                        if ($option_type) {
                            $option_filter = [];
                            if (!judgeQuantity($option_type)) {
                                $config_base_sale = 0;
                                $config_base_sale_setupfee = 0;
                                $option = \think\Db::name("product_config_options_sub")->alias("pcos")->field("pcos.id,pcos.option_name as suboption_name,pco.option_type,pco.option_name as option_name,pco.is_discount,pco.id as oid,pco.is_rebate")->leftJoin("product_config_options pco", "pco.id = pcos.config_id")->where("pcos.id", $vv)->where("pcos.config_id", $kk)->find();
                                if (!$option) {
                                    active_log(sprintf($this->lang["Order_admin_getMultiTotal_fail"], $uid, lang("ERROR_OPERATE")), $uid);
                                    return jsonrule(["status" => 400, "msg" => lang("ERROR_OPERATE")]);
                                }
                                $pricing = \think\Db::name("pricing")->where("type", "configoptions")->where("currency", $currencyid)->where("relid", $option["id"])->find();
                                $optionname = $option["option_name"];
                                $optionprice = $pricing[$billingcycle] <= 0 ? 0 : $pricing[$billingcycle];
                                $optionupfee = $pricing[$setupfeecycle] <= 0 ? 0 : $pricing[$setupfeecycle];
                                if ($product["api_type"] == "zjmf_api" && 0 < $product["upstream_version"] && $product["upstream_price_type"] == "percent") {
                                    $optionprice = bcmul($optionprice, $product["upstream_price_value"]) / 100;
                                    $optionupfee = bcmul($optionupfee, $product["upstream_price_value"]) / 100;
                                }
                                $suboptionname = $option["suboption_name"];
                                $option_filter["option_name"] = explode("|", $optionname)[1] ? explode("|", $optionname)[1] : $optionname;
                                $option_filter["suboption_name"] = $suboptionname_deal = explode("|", $suboptionname)[1] ? explode("|", $suboptionname)[1] : $suboptionname;
                                if (in_array($suboptionname_deal, $this->allowSystem)) {
                                    $option_filter["suboption_name"] = implode(" ", explode("^", $suboptionname_deal));
                                }
                                $option_filter["suboption_setup_fee"] = $optionupfee = is_numeric($price_override) && 0 <= $price_override ? $format_zero : bcsub($optionupfee, 0, 2) * $rate;
                                $option_filter["suboption_price"] = $optionprice = is_numeric($price_override) && 0 <= $price_override ? $format_zero : bcsub($optionprice, 0, 2) * $rate;
                                $all_option[] = $option_filter;
                                $price_setup = bcadd($price_setup, $optionupfee);
                                $price_cycle = bcadd($price_cycle, $optionprice);
                                $config_base_sale += $optionupfee + $optionprice;
                                $config_base_sale_setupfee = 0 < $optionupfee ? $optionupfee : 0;
                                $configoptions_base_sale[] = ["config_base_sale" => $config_base_sale, "config_base_sale_setupfee" => $config_base_sale_setupfee, "is_discount" => $option["is_discount"], "id" => $option["oid"], "is_rebate" => $option["is_rebate"]];
                            } else {
                                $options = \think\Db::name("product_config_options_sub")->alias("pcos")->field("pco.id as oid,pcos.id,pcos.option_name as suboption_name,pcos.qty_minimum,pcos.qty_maximum,pco.option_type,pco.option_name as option_name,pco.qty_minimum as min,pco.qty_maximum as max,pco.is_discount,pco.is_rebate")->leftJoin("product_config_options pco", "pco.id = pcos.config_id")->where("pcos.config_id", $kk)->select()->toArray();
                                if (!empty($options[0])) {
                                    foreach ($options as $option) {
                                        $pricing = \think\Db::name("pricing")->where("type", "configoptions")->where("currency", $currencyid)->where("relid", $option["id"])->find();
                                        $min = $option["qty_minimum"];
                                        $max = $option["qty_maximum"];
                                        if (0 < $vv && $option["min"] <= $vv && $vv <= $option["max"] && $min <= $vv && $vv <= $max) {
                                            $optionprice = $pricing[$billingcycle] <= 0 ? 0 : $pricing[$billingcycle] * $vv;
                                            $optionupfee = $pricing[$setupfeecycle] <= 0 ? 0 : $pricing[$setupfeecycle];
                                            if ($product["api_type"] == "zjmf_api" && 0 < $product["upstream_version"] && $product["upstream_price_type"] == "percent") {
                                                $optionprice = bcmul($optionprice, $product["upstream_price_value"]) / 100;
                                                $optionupfee = bcmul($optionupfee, $product["upstream_price_value"]) / 100;
                                            }
                                            if (judgeQuantityStage($option_type)) {
                                                $sum = quantityStagePrice($kk, $currencyid, $vv, $billingcycle);
                                                list($optionprice, $optionupfee) = $sum;
                                            }
                                            $suboptionname = $option["suboption_name"];
                                            $optionname = $option["option_name"];
                                            $option_filter["option_name"] = explode("|", $optionname)[1] ? explode("|", $optionname)[1] : $optionname;
                                            $option_filter["suboption_name"] = explode("|", $suboptionname)[1] ? explode("|", $suboptionname)[1] : $suboptionname;
                                            $option_filter["suboption_setup_fee"] = $optionupfee = is_numeric($price_override) && 0 <= $price_override ? $format_zero : bcsub($optionupfee, 0, 2) * $rate;
                                            $option_filter["suboption_price"] = $optionprice = is_numeric($price_override) && 0 <= $price_override ? $format_zero : bcsub($optionprice, 0, 2) * $rate;
                                            $option_filter["qty"] = $vv;
                                            $all_option[] = $option_filter;
                                            if (judgeQuantityStage($option_type)) {
                                                $price_cycle = bcadd($price_cycle, $optionprice);
                                                $price_setup = bcadd($price_setup, $optionupfee);
                                                $config_base_sale = $optionprice + $optionupfee;
                                                $config_base_sale_setupfee = $optionupfee;
                                            } else {
                                                $price_setup = bcadd($price_setup, $optionupfee);
                                                $price_cycle = bcadd($price_cycle, bcmul($optionprice, 1));
                                                $config_base_sale = $optionupfee + bcmul($optionprice, 1);
                                                $config_base_sale_setupfee = $optionupfee;
                                            }
                                            $configoptions_base_sale[] = ["config_base_sale" => $config_base_sale, "config_base_sale_setupfee" => $config_base_sale_setupfee, "is_discount" => $option["is_discount"], "id" => $option["oid"], "is_rebate" => $option["is_rebate"]];
                                        }
                                    }
                                } else {
                                    active_log(sprintf($this->lang["Order_admin_getMultiTotal_fail"], $uid, lang("ERROR_OPERATE")), $uid);
                                    return jsonrule(["status" => 400, "msg" => lang("ERROR_OPERATE")]);
                                }
                            }
                        }
                    }
                }
            }
            if ($product["api_type"] == "zjmf_api" && 0 < $product["upstream_version"] && $product["upstream_price_type"] == "percent") {
                $product_base_sale = bcmul($product_base_sale, $product["upstream_price_value"]) / 100;
                $config_base_sale_setupfee = bcmul($config_base_sale_setupfee, $product["upstream_price_value"]) / 100;
                $product_base_sale_price = $product_base_sale - $config_base_sale_setupfee;
                foreach ($configoptions_base_sale as &$m) {
                    $m["config_base_sale"] = bcmul($m["config_base_sale"], $product["upstream_price_value"]) / 100;
                    $m["config_base_sale_setupfee"] = bcmul($m["config_base_sale_setupfee"], $product["upstream_price_value"]) / 100;
                }
                $product_rebate_price = bcmul($product_rebate_price, $product["upstream_price_value"]) / 100;
                $product_rebate_setupfee = bcmul($product_rebate_setupfee, $product["upstream_price_value"]) / 100;
            }
            $product_total_price = bcadd($price_setup, $price_cycle);
            if ($product_total_price < 0) {
                $product_total_price = 0;
            }
            $flag = getSaleProductUser($pid, $uid);
            if ($flag && $price_override <= 0) {
                $config_total = 0;
                $config_total_setupfee = 0;
                $config_total_price = 0;
                if ($flag["type"] == 1) {
                    $bates = $flag["bates"];
                    $product_base_sale = bcmul($bates / 100, $product_base_sale);
                    $product_base_sale_setupfee = bcmul($bates / 100, $product_base_sale_setupfee);
                    $product_base_sale_price = $product_base_sale - $product_base_sale_setupfee;
                    foreach ($configoptions_base_sale as &$mm) {
                        if ($mm["is_rebate"] || !$edition) {
                            $mm["config_base_sale"] = bcmul($bates / 100, $mm["config_base_sale"]);
                            $mm["config_base_sale_setupfee"] = bcmul($bates / 100, $mm["config_base_sale_setupfee"]);
                        }
                        $config_total += $mm["config_base_sale"];
                        $config_total_setupfee += $mm["config_base_sale_setupfee"];
                        $config_total_price += $mm["config_base_sale"] - $mm["config_base_sale_setupfee"];
                    }
                } else {
                    if ($flag["type"] == 2) {
                        $bates = $flag["bates"];
                        $product_base_sale = $product_base_sale / $product_total_price * ($product_total_price - $bates);
                        $product_base_sale_setupfee = $product_base_sale_setupfee / $product_total_price * ($product_total_price - $bates);
                        $product_base_sale_price = $product_base_sale - $product_base_sale_setupfee;
                        foreach ($configoptions_base_sale as &$mm) {
                            if ($mm["is_rebate"] || !$edition) {
                                $mm["config_base_sale"] = $mm["config_base_sale"] / $product_total_price * ($product_total_price - $bates);
                                $mm["config_base_sale_setupfee"] = $mm["config_base_sale_setupfee"] / $product_total_price * ($product_total_price - $bates);
                            }
                            $config_total += $mm["config_base_sale"];
                            $config_total_setupfee += $mm["config_base_sale_setupfee"];
                            $config_total_price += $mm["config_base_sale"] - $mm["config_base_sale_setupfee"];
                        }
                    }
                }
                $product_total_price_sale = bcadd($product_base_sale, $config_total);
                $product_total_price_sale_setupfee = bcadd($product_base_sale_setupfee, $config_total_setupfee);
                $product_total_price_sale_price = bcadd($product_base_sale_price, $config_total_price);
                $total = $total + $product_total_price_sale * $qty;
            } else {
                $product_total_price_sale = $product_total_price;
                $product_total_price_sale_setupfee = $price_setup;
                $product_total_price_sale_price = $price_cycle;
                $total = bcadd($total, $product_total_price_sale * $qty);
            }
            if (is_numeric($price_override_renew) && 0 <= $price_override_renew) {
                $current_product_price_total += $price_override_renew;
            } else {
                $current_product_price_total += $product_total_price_sale_price * $rate;
            }
            if (!empty($promo) && 0 < $product_total_price_sale && $price_override <= 0) {
                if ($promo["type"] == "percent") {
                    $promo_value = 100 < $promo["value"] ? 100 : (0 < $promo["value"] ? $promo["value"] : 0);
                    $discount_pricing = $discount_recurring = 0;
                    $discount_pricing += $product_base_sale * (1 - $promo_value / 100);
                    $discount_recurring += $product_base_sale_price * (1 - $promo_value / 100);
                    foreach ($configoptions_base_sale as $h) {
                        if ($h["is_discount"] == 1) {
                            $discount_pricing += $h["config_base_sale"] * (1 - $promo_value / 100);
                            $discount_recurring += ($h["config_base_sale"] - $h["config_base_sale_setupfee"]) * (1 - $promo_value / 100);
                        }
                    }
                    if (0 < $promo["recurring"]) {
                        $current_product_price_total = bcsub($current_product_price_total, $discount_recurring);
                    }
                } else {
                    if ($promo["type"] == "fixed") {
                        $discount_pricing = $product_total_price_sale < $promo["value"] ? $product_total_price_sale : $promo["value"];
                    } else {
                        if ($promo["type"] == "override") {
                            if ($product_total_price_sale < $promo["value"]) {
                                $discount_pricing = $product_total_price_sale;
                            } else {
                                $discount_pricing = $product_total_price_sale - $promo["value"];
                            }
                        } else {
                            if ($promo["type"] == "free") {
                                $discount_pricing = $product_total_price_sale_setupfee;
                            } else {
                                $discount_pricing = 0;
                            }
                        }
                    }
                }
                $discount_pricing = 0 < $discount_pricing ? $discount_pricing : 0;
                if ($promo["one_time"] == 1) {
                    if (empty($one_time)) {
                        $qty = 1;
                        $total = bcsub($total, $discount_pricing * $qty);
                        $discount += $discount_pricing * $qty;
                        $one_time = true;
                    }
                } else {
                    $discount += $discount_pricing * $qty;
                    $total = bcsub($total, $discount_pricing * $qty);
                }
            }
            $product_filter["product_price_total_recurring"] = bcsub($current_product_price_total * $rate, 0, 2);
            $product_filter["child"] = $all_option;
            $res["products"][] = $product_filter;
            $subtotal += $product_total_price * $qty * $rate;
            $saleproduct += ($product_total_price - $product_total_price_sale) * $qty;
        }
        $res["subtotal"] = 0 < $subtotal ? bcsub($subtotal, 0, 2) : bcsub(0, 0, 2);
        $res["discount"] = 0 < $discount ? bcsub($discount, 0, 2) : bcsub(0, 0, 2);
        $res["saleproducts"] = 0 < $saleproduct ? bcsub($saleproduct, 0, 2) : bcsub(0, 0, 2);
        $res["total"] = 0 < $total ? bcsub($total, 0, 2) : bcsub(0, 0, 2);
        return jsonrule($res);
    }
    public function getClients()
    {
        $params = $this->request->param();
        $username = $params["username"];
        $where = [];
        if ($this->user["id"] != 1 && $this->user["is_sale"]) {
            $where[] = ["id", "in", $this->str];
        }
        $clents = (new \app\common\model\ClientModel())->checkclient($username, $where);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $clents]);
    }
    public function save()
    {
        $param = request()->only(["ops", "uid", "promo_code", "payment", "use_credit", "status", "adminorderconf", "admingenerateinvoice", "adminsendinvoice"]);
        $uid = $param["uid"];
        $user_info = db("clients")->where("id", $uid)->find();
        if (!$user_info) {
            return jsonrule(["status" => 400, "msg" => lang("用户不存在")]);
        }
        $payment = $param["payment"];
        if (!in_array($payment, array_column(gateway_list(), "name"))) {
            return jsonrule(["status" => 400, "msg" => lang("支付方式无效")]);
        }
        if (!empty($param["promo_code"])) {
            $promo = \think\Db::name("promo_code")->where("code", $param["promo_code"])->find();
            if (empty($promo)) {
                return jsonrule(["status" => 400, "msg" => lang("优惠码不存在")]);
            }
        }
        $status = $param["status"];
        if (!in_array($status, array_keys(config("domainstatus")))) {
            return jsonrule(["status" => 400, "msg" => lang("订单状态错误")]);
        }
        if (empty($param["ops"][0])) {
            return jsonrule(["status" => 400, "msg" => lang("未选择产品")]);
        }
        $currency = priorityCurrency($uid);
        $total = 0;
        $price_type = config("price_type");
        bcscale(2);
        $all_product_pay_type = [];
        foreach ($param["ops"] as $k => $v) {
            $checkProcut = (new \app\common\model\ProductModel())->checkProductPrice($v["pid"], $v["billingcycle"], $currency);
            if (!$checkProcut) {
                return jsonrule(["status" => 400, "msg" => lang("错误的购买周期")]);
            }
            $qty = $v["qty"] ?: 1;
            if ($v["billingcycle"] == "free") {
                $product = db("products")->where("id", $v["pid"])->find();
            } else {
                $product_price_type = $price_type[$v["billingcycle"]];
                if (empty($product_price_type)) {
                    $result["status"] = 400;
                    $result["msg"] = "错误的购买周期";
                    return jsonrule($result);
                }
                $product_price_field = "b." . implode(",b.", $product_price_type);
                $product = \think\Db::name("products")->alias("a")->field("a.*," . $product_price_field)->leftJoin("pricing b", "b.type=\"product\" and a.id=b.relid and currency=" . $currency)->where("a.id", $v["pid"])->find();
            }
            if (empty($product)) {
                $result["status"] = 400;
                $result["msg"] = lang("产品不存在");
                return jsonrule($result);
            }
            if (!judgeOntrialNum($v["pid"], $uid, $qty, true) && $v["billingcycle"] == "ontrial") {
                return jsonrule(["status" => 400, "msg" => lang("CART_ONTRIAL_NUM", [$product["name"]])]);
            }
            $pay_type = json_decode($product["pay_type"], true);
            if (!empty($pay_type["pay_ontrial_condition"]) && $v["billingcycle"] == "ontrial") {
                $one_error = $this->checkProduct($pay_type["pay_ontrial_condition"], $uid) ?? [];
                if (!empty($one_error)) {
                    $product_error[] = "产品" . $product["name"] . ",试用需要" . implode(",", $one_error);
                }
            }
            if (empty($product_error)) {
                if (!empty($product["retired"])) {
                    $result["status"] = 400;
                    $result["msg"] = "产品" . $product["name"] . "已下架";
                    return jsonrule($result);
                }
                if (!empty($product["stock_control"]) && $product["qty"] <= 0) {
                    $result["status"] = 400;
                    $result["msg"] = "产品" . $product["name"] . "库存不足";
                    return jsonrule($result);
                }
                $nextduedate = time();
                $customfields = db("customfields")->where("relid", $v["pid"])->where("type", "product")->order("sortorder", "asc")->select()->toArray();
                $_products = \think\Db::name("products")->field("server_group as gid")->where("id", $v["pid"])->find();
                $server = [];
                if ($_products) {
                    $server = getServesId($_products["gid"]);
                }
                $serverid = $server["id"] ?: 0;
                $all_product_pay_type[] = $pay_type["pay_type"];
                $item_desc = [];
                if ($pay_type["pay_type"] == "free") {
                    $v["billingcycle"] = "free";
                    $product_item = ["uid" => $uid, "productid" => $v["pid"], "serverid" => $serverid ?? 0, "regdate" => time(), "payment" => $payment, "firstpaymentamount" => 0, "amount" => 0, "billingcycle" => $v["billingcycle"], "domainstatus" => $status, "create_time" => time(), "qty" => $qty, "auto_terminate_reason" => "", "product_config" => [], "customfields" => [], "dcim_os" => array_keys($v["os"])[0] ?? 0, "os" => array_values($v["os"])[0] ?? "", "host" => $v["host"] ?? "", "password" => $v["password"] ?? ""];
                    $item_desc[] = $product["name"] . " (" . date("Y-m-d H", time()) . " - ) ";
                    foreach ($customfields as $ck => $cv) {
                        if (isset($v["customfield"][$cv["id"]])) {
                            $product_item["customfields"][] = ["fieldid" => $cv["id"], "value" => $v["customfield"][$cv["id"]]];
                        }
                    }
                    $config_price = model("Product")->getConfigOptionsPrice($v["pid"], $currency, $product_price_type);
                    if (!empty($v["configoptions"])) {
                        foreach ($config_price as $kkk => $vvv) {
                            if (isset($v["configoptions"][$vvv["id"]])) {
                                if (judgeOs($vvv["option_type"])) {
                                    $configoptions_logic = new \app\common\logic\ConfigOptions();
                                    $os = $configoptions_logic->getOs($vvv["id"], $v["configoptions"][$vvv["id"]]);
                                    $product_item["os"] = $os["os"] ?? "";
                                    $product_item["os_url"] = $os["os_url"] ?? "";
                                }
                                if (judgeQuantity($vvv["option_type"])) {
                                    if ($v["configoptions"][$vvv["id"]] < $vvv["qty_minimum"]) {
                                        $v["configoptions"][$vvv["id"]] = $vvv["qty_minimum"];
                                    }
                                    if ($vvv["qty_maximum"] < $v["configoptions"][$vvv["id"]]) {
                                        $v["configoptions"][$vvv["id"]] = $vvv["qty_minimum"];
                                    }
                                    $sub_id = 0;
                                    foreach ($vvv["sub"] as $kkkk => $vvvv) {
                                        if ($sub_id == 0) {
                                            $sub_id = $kkkk;
                                        }
                                        if ($vvvv["qty_minimum"] <= $v["configoptions"][$vvv["id"]] && $v["configoptions"][$vvv["id"]] <= $vvvv["qty_maximum"]) {
                                            $sub_id = $kkkk;
                                            if (0 < $sub_id) {
                                                $product_item["product_config"][] = ["configid" => $vvv["id"], "optionid" => $sub_id, "qty" => $v["configoptions"][$vvv["id"]]];
                                            }
                                        }
                                    }
                                } else {
                                    if (isset($vvv["sub"][$v["configoptions"][$vvv["id"]]])) {
                                        $product_item["product_config"][] = ["configid" => $vvv["id"], "optionid" => $v["configoptions"][$vvv["id"]], "qty" => 0];
                                    }
                                }
                            }
                        }
                    }
                    $product_items[] = $product_item;
                } else {
                    if (is_numeric($product[$v["billingcycle"]]) && $product[$v["billingcycle"]] == -1) {
                        $result["status"] = 400;
                        $result["msg"] = "错误的购买周期";
                        return jsonrule($result);
                    }
                    $product_item = ["uid" => $uid, "productid" => $v["pid"], "serverid" => $serverid ?? 0, "regdate" => time(), "payment" => $payment, "billingcycle" => $v["billingcycle"], "nextduedate" => $nextduedate, "nextinvoicedate" => $nextduedate, "domainstatus" => $status, "create_time" => time(), "qty" => $qty, "auto_terminate_reason" => "", "invoices_items" => [], "product_config" => [], "customfields" => [], "dcim_os" => array_keys($v["os"])[0] ?? 0, "os" => array_values($v["os"])[0] ?? "", "host" => $v["host"] ?? "", "password" => $v["password"] ?? ""];
                    $item_desc = $item_desc_home = [];
                    if ($pay_type["pay_type"] == "onetime") {
                        $item_desc_home[] = $product["name"];
                        $item_desc[] = $item_desc_home;
                    } else {
                        $next_time = getNextTime($v["billingcycle"], $pay_type["pay_" . $v["billingcycle"] . "_cycle"], 0, $pay_type["pay_ontrial_cycle_type"] ?: "day");
                        $item_desc_home[] = $product["name"] . " (" . date("Y-m-d H", time()) . " - " . date("Y-m-d H", $next_time) . ") ";
                        $item_desc[] = $item_desc_home;
                    }
                    foreach ($customfields as $ck => $cv) {
                        if (isset($v["customfield"][$cv["id"]])) {
                            $product_item["customfields"][] = ["fieldid" => $cv["id"], "value" => $v["customfield"][$cv["id"]]];
                        }
                    }
                    $price_setup = $product[$product_price_type[1]];
                    $price_cycle = $product[$product_price_type[0]];
                    $product_base_sale = $product[$product_price_type[1]] + $product[$product_price_type[0]];
                    $product_base_sale_setupfee = $product[$product_price_type[1]];
                    $product_base_sale_price = $product[$product_price_type[0]];
                    $product_rebate_price = $product[$product_price_type[0]];
                    $product_rebate_setupfee = $product[$product_price_type[1]];
                    $edition = getEdition();
                    $config_price = model("Product")->getConfigOptionsPrice($v["pid"], $currency, $product_price_type);
                    $configoptions_base_sale = [];
                    if (!empty($v["configoptions"])) {
                        foreach ($config_price as $kkk => $vvv) {
                            if (isset($v["configoptions"][$vvv["id"]])) {
                                if (judgeOs($vvv["option_type"])) {
                                    $configoptions_logic = new \app\common\logic\ConfigOptions();
                                    $os = $configoptions_logic->getOs($vvv["id"], $v["configoptions"][$vvv["id"]]);
                                    $product_item["os"] = $os["os"] ?? "";
                                    $product_item["os_url"] = $os["os_url"] ?? "";
                                }
                                if (strpos($vvv["option_name"], "|") !== false) {
                                    $item_desc_name = substr($vvv["option_name"], strpos($vvv["option_name"], "|"));
                                } else {
                                    $item_desc_name = $vvv["option_name"];
                                }
                                if (judgeQuantity($vvv["option_type"])) {
                                    if ($v["configoptions"][$vvv["id"]] < $vvv["qty_minimum"]) {
                                        $v["configoptions"][$vvv["id"]] = $vvv["qty_minimum"];
                                    }
                                    if ($vvv["qty_maximum"] < $v["configoptions"][$vvv["id"]]) {
                                        $v["configoptions"][$vvv["id"]] = $vvv["qty_minimum"];
                                    }
                                    $sub_price_setup = 0;
                                    $sub_price_cycle = 0;
                                    $config_base_sale = 0;
                                    $config_base_sale_setupfee = 0;
                                    $sub_id = 0;
                                    foreach ($vvv["sub"] as $kkkk => $vvvv) {
                                        if ($sub_price_setup === "") {
                                            $sub_id = $kkkk;
                                            $sub_price_setup = $vvvv["price_setup"];
                                            $sub_price_cycle = $vvvv["price_cycle"];
                                        }
                                        if ($vvvv["qty_minimum"] <= $v["configoptions"][$vvv["id"]] && $v["configoptions"][$vvv["id"]] <= $vvvv["qty_maximum"]) {
                                            $sub_price_setup = $vvvv["price_setup"];
                                            $sub_price_cycle = $vvvv["price_cycle"];
                                            $sub_id = $kkkk;
                                            if (0 < $sub_id) {
                                                $item_desc_name .= ": " . $v["configoptions"][$vvv["id"]];
                                                $sub_price_setup = $sub_price_setup < 0 ? 0 : $sub_price_setup;
                                                $sub_price_cycle = $sub_price_cycle < 0 ? 0 : $sub_price_cycle;
                                                if ($vvv["hidden"] != 1) {
                                                    if (judgeQuantityStage($vvv["option_type"])) {
                                                        $sum = quantityStagePrice($vvv["id"], $currency, $v["configoptions"][$vvv["id"]], $v["billingcycle"]);
                                                        $price_cycle = bcadd($price_cycle, $sum[0]);
                                                        $price_setup = bcadd($price_setup, $sum[1]);
                                                        $config_base_sale = $sum[0] + $sum[1];
                                                        $config_base_sale_setupfee = $sum[1];
                                                    } else {
                                                        if (0 < intval($v["configoptions"][$vvv["id"]])) {
                                                            $price_setup = bcadd($price_setup, $sub_price_setup);
                                                        }
                                                        $price_cycle = bcadd($price_cycle, bcmul($sub_price_cycle, $v["configoptions"][$vvv["id"]]));
                                                        $config_base_sale = (0 < intval($v["configoptions"][$vvv["id"]]) ? $sub_price_setup : 0) + bcmul($sub_price_cycle, $v["configoptions"][$vvv["id"]]);
                                                        $config_base_sale_setupfee = 0 < intval($v["configoptions"][$vvv["id"]]) ? $sub_price_setup : 0;
                                                    }
                                                }
                                                $product_item["product_config"][] = ["configid" => $vvv["id"], "optionid" => $sub_id, "qty" => $v["configoptions"][$vvv["id"]]];
                                            }
                                            $configoptions_base_sale[] = ["config_base_sale" => $config_base_sale, "config_base_sale_setupfee" => $config_base_sale_setupfee, "is_discount" => $vvv["is_discount"], "id" => $vvv["id"], "is_rebate" => $vvv["is_rebate"]];
                                        }
                                    }
                                } else {
                                    $config_base_sale = 0;
                                    $config_base_sale_setupfee = 0;
                                    if (isset($vvv["sub"][$v["configoptions"][$vvv["id"]]])) {
                                        if (0 < $vvv["sub"][$v["configoptions"][$vvv["id"]]]["price_setup"] && $vvv["hidden"] != 1) {
                                            $price_setup = bcadd($price_setup, $vvv["sub"][$v["configoptions"][$vvv["id"]]]["price_setup"]);
                                            $config_base_sale += $vvv["sub"][$v["configoptions"][$vvv["id"]]]["price_setup"];
                                            $config_base_sale_setupfee = $vvv["sub"][$v["configoptions"][$vvv["id"]]]["price_setup"];
                                        }
                                        if (0 < $vvv["sub"][$v["configoptions"][$vvv["id"]]]["price_cycle"] && $vvv["hidden"] != 1) {
                                            $price_cycle = bcadd($price_cycle, $vvv["sub"][$v["configoptions"][$vvv["id"]]]["price_cycle"]);
                                            $config_base_sale += $vvv["sub"][$v["configoptions"][$vvv["id"]]]["price_cycle"];
                                        }
                                        if (strpos($vvv["sub"][$v["configoptions"][$vvv["id"]]]["option_name"], "|") !== false) {
                                            $item_desc_name .= ": " . substr($vvv["sub"][$v["configoptions"][$vvv["id"]]]["option_name"], strpos($vvv["sub"][$v["configoptions"][$vvv["id"]]]["option_name"], "|"));
                                        } else {
                                            $item_desc_name .= $vvv["sub"][$v["configoptions"][$vvv["id"]]]["option_name"];
                                        }
                                        $product_item["product_config"][] = ["configid" => $vvv["id"], "optionid" => $v["configoptions"][$vvv["id"]], "qty" => 0];
                                        $configoptions_base_sale[] = ["config_base_sale" => $config_base_sale, "config_base_sale_setupfee" => $config_base_sale_setupfee, "is_discount" => $vvv["is_discount"], "id" => $vvv["id"], "is_rebate" => $vvv["is_rebate"]];
                                    }
                                }
                                $item_desc_name = str_replace("|", " ", $item_desc_name);
                                if (empty($vvv["hidden"])) {
                                    $item_desc_home[] = $item_desc_name;
                                }
                                $item_desc[] = $item_desc_name;
                            }
                        }
                    }
                    if ($product["api_type"] == "zjmf_api" && 0 < $product["upstream_pid"] && $product["upstream_price_type"] == "percent") {
                        $price_setup = bcmul($price_setup, $product["upstream_price_value"]) / 100;
                        $price_cycle = bcmul($price_cycle, $product["upstream_price_value"]) / 100;
                        $product_base_sale = bcmul($product_base_sale, $product["upstream_price_value"]) / 100;
                        $config_base_sale_setupfee = bcmul($config_base_sale_setupfee, $product["upstream_price_value"]) / 100;
                        $product_base_sale_price = $product_base_sale - $config_base_sale_setupfee;
                        foreach ($configoptions_base_sale as &$m) {
                            $m["config_base_sale"] = bcmul($m["config_base_sale"], $product["upstream_price_value"]) / 100;
                            $m["config_base_sale_setupfee"] = bcmul($m["config_base_sale_setupfee"], $product["upstream_price_value"]) / 100;
                        }
                        $product_rebate_price = bcmul($product_rebate_price, $product["upstream_price_value"]) / 100;
                        $product_rebate_setupfee = bcmul($product_rebate_setupfee, $product["upstream_price_value"]) / 100;
                    }
                    $product_total_price = bcadd($price_setup, $price_cycle);
                    if ($product_total_price < 0) {
                        $product_total_price = 0;
                    }
                    $is_interior_price = isset($v["interior_price"]) && is_numeric($v["interior_price"]) && 0 <= $v["interior_price"] ? true : false;
                    if (0 < $price_setup && !$is_interior_price) {
                        $product_item["invoices_items"][] = ["uid" => $uid, "type" => "setup", "description" => "初装费", "description2" => "初装费", "amount" => $price_setup, "due_time" => $nextduedate, "payment" => $payment];
                    }
                    $product_item["invoices_items"][] = ["uid" => $uid, "type" => "host", "description" => implode("\n", $item_desc), "description2" => implode("\n", $item_desc_home) ?? "", "amount" => $is_interior_price ? $v["interior_price"] : $price_cycle, "due_time" => $nextduedate, "payment" => $payment];
                    $flag = getSaleProductUser($v["pid"], $uid);
                    if ($flag && !$is_interior_price) {
                        $config_total = 0;
                        $config_total_setupfee = 0;
                        $config_total_price = 0;
                        $userdiscount = 0;
                        if ($flag["type"] == 1) {
                            $bates = $flag["bates"];
                            $userdiscount += (1 - $bates / 100) * ($product_rebate_price + $product_rebate_setupfee);
                            foreach ($configoptions_base_sale as &$mm) {
                                if ($mm["is_rebate"] || !$edition) {
                                    $userdiscount += (1 - $bates / 100) * $mm["config_base_sale"];
                                    $mm["config_base_sale"] = bcmul($bates / 100, $mm["config_base_sale"]);
                                    $mm["config_base_sale_setupfee"] = bcmul($bates / 100, $mm["config_base_sale_setupfee"]);
                                }
                                $config_total += $mm["config_base_sale"];
                                $config_total_setupfee += $mm["config_base_sale_setupfee"];
                                $config_total_price += $mm["config_base_sale"] - $mm["config_base_sale_setupfee"];
                            }
                            $product_base_sale = bcmul($bates / 100, $product_base_sale);
                            $product_base_sale_setupfee = bcmul($bates / 100, $product_base_sale_setupfee);
                            $product_base_sale_price = $product_base_sale - $product_base_sale_setupfee;
                        } else {
                            if ($flag["type"] == 2) {
                                $bates = $flag["bates"];
                                $product_total_rebate_price = $product_total_price;
                                $product_base_sale = $product_base_sale / $product_total_price * ($product_total_price - $bates);
                                $product_base_sale_setupfee = $product_base_sale_setupfee / $product_total_price * ($product_total_price - $bates);
                                $product_base_sale_price = $product_base_sale - $product_base_sale_setupfee;
                                foreach ($configoptions_base_sale as &$mm) {
                                    if ($mm["is_rebate"] || !$edition) {
                                        $mm["config_base_sale"] = $mm["config_base_sale"] / $product_total_price * ($product_total_price - $bates);
                                        $mm["config_base_sale_setupfee"] = $mm["config_base_sale_setupfee"] / $product_total_price * ($product_total_price - $bates);
                                    } else {
                                        $product_total_rebate_price = $product_total_rebate_price - $mm["config_base_sale"];
                                    }
                                    $config_total += $mm["config_base_sale"];
                                    $config_total_setupfee += $mm["config_base_sale_setupfee"];
                                    $config_total_price += $mm["config_base_sale"] - $mm["config_base_sale_setupfee"];
                                }
                                $userdiscount = $bates < $product_total_rebate_price ? $bates : $product_total_rebate_price;
                            }
                        }
                        $userdiscount = 0 < $userdiscount ? $userdiscount : 0;
                        $product_item["invoices_items"][] = ["uid" => $uid, "type" => "discount", "description" => "客戶折扣", "description2" => "客戶折扣", "amount" => "-" . $userdiscount, "due_time" => $nextduedate, "payment" => $payment];
                        $product_item["flag"] = 1;
                        $product_item["flag_cycle"] = $v["billingcycle"];
                        $product_total_price_sale = bcadd($product_base_sale, $config_total);
                        $product_total_price_sale_setupfee = bcadd($product_base_sale_setupfee, $config_total_setupfee);
                        $product_total_price_sale_price = bcadd($product_base_sale_price, $config_total_price);
                        $total = $total + $product_total_price_sale * $qty;
                    } else {
                        if ($is_interior_price) {
                            $product_total_price = $v["interior_price"];
                        }
                        $product_item["flag"] = 0;
                        $product_item["flag_cycle"] = $v["billingcycle"];
                        $product_total_price_sale = $product_total_price;
                        $product_total_price_sale_setupfee = $price_setup;
                        $product_total_price_sale_price = $price_cycle;
                        $total = bcadd($total, $product_total_price * $qty);
                    }
                    if (!empty($promo) && 0 < $product_total_price_sale && !$is_interior_price) {
                        if ($promo["type"] == "percent") {
                            $promo_value = 100 < $promo["value"] ? 100 : (0 < $promo["value"] ? $promo["value"] : 0);
                            $discount_pricing = $discount_recurring = 0;
                            $discount_pricing += $product_base_sale * (1 - $promo_value / 100);
                            $discount_recurring += $product_base_sale_price * (1 - $promo_value / 100);
                            foreach ($configoptions_base_sale as $h) {
                                if ($h["is_discount"] == 1) {
                                    $discount_pricing += $h["config_base_sale"] * (1 - $promo_value / 100);
                                    $discount_recurring += ($h["config_base_sale"] - $h["config_base_sale_setupfee"]) * (1 - $promo_value / 100);
                                }
                            }
                            if (0 < $promo["recurring"]) {
                                $product_total_price_sale_price = bcsub($product_item["amount"], $discount_recurring);
                            }
                        } else {
                            if ($promo["type"] == "fixed") {
                                $discount_pricing = $product_total_price_sale < $promo["value"] ? $product_total_price_sale : $promo["value"];
                            } else {
                                if ($promo["type"] == "override") {
                                    if ($product_total_price_sale < $promo["value"]) {
                                        $discount_pricing = $product_total_price_sale;
                                    } else {
                                        $discount_pricing = $product_total_price_sale - $promo["value"];
                                    }
                                } else {
                                    if ($promo["type"] == "free") {
                                        $discount_pricing = $product_total_price_sale_setupfee;
                                    } else {
                                        $discount_pricing = 0;
                                    }
                                }
                            }
                        }
                        $discount_pricing = 0 < $discount_pricing ? $discount_pricing : 0;
                        if ($promo["one_time"] == 1) {
                            if (empty($one_time)) {
                                $qty = 1;
                                $total = bcsub($total, $discount_pricing * $qty);
                                $product_item["invoices_items"][] = ["uid" => $uid, "type" => "promo", "description" => promoCodeDesc($promo), "description2" => promoCodeDesc($promo) ?? "", "amount" => "-" . $discount_pricing, "due_time" => $nextduedate, "payment" => $payment, "one_time" => 1];
                                $one_time = true;
                            }
                        } else {
                            $total = bcsub($total, $discount_pricing * $qty);
                            $product_item["invoices_items"][] = ["uid" => $uid, "type" => "promo", "description" => promoCodeDesc($promo), "description2" => promoCodeDesc($promo) ?? "", "amount" => "-" . $discount_pricing, "due_time" => $nextduedate, "payment" => $payment];
                        }
                        $product_item["promoid"] = $promo["id"];
                    } else {
                        $discount_pricing = 0;
                        $product_item["promoid"] = 0;
                    }
                    if ($is_interior_price) {
                        $product_item["firstpaymentamount"] = floatval($v["interior_price"]);
                    } else {
                        $product_item["firstpaymentamount"] = 0 < bcsub($product_total_price_sale, $discount_pricing, 2) ? bcsub($product_total_price_sale, $discount_pricing, 2) : 0;
                    }
                    if (isset($v["interior_price_renew"]) && 0 <= floatval($v["interior_price_renew"])) {
                        $product_item["amount"] = $v["interior_price_renew"];
                    } else {
                        $product_item["amount"] = 0 < $product_total_price_sale_price ? $product_total_price_sale_price : 0;
                    }
                    $product_items[] = $product_item;
                }
            }
        }
        if (!empty($product_error)) {
            $result["status"] = 400;
            $result["msg"] = implode("\n", $product_error);
            return jsonrule($result);
        }
        $order_data = ["uid" => $uid, "ordernum" => cmf_get_order_sn(), "status" => $status, "create_time" => time(), "update_time" => 0, "amount" => $total, "payment" => $payment];
        $total = 0 < $total ? $total : 0;
        $subtotal = $total;
        $credit = 0;
        $use_credit = $param["use_credit"] ?? NULL;
        if (!empty($use_credit) && !empty($param["admingenerateinvoice"])) {
            if ($subtotal <= $user_info["credit"]) {
                $credit = $subtotal;
            } else {
                $credit = $user_info["credit"];
            }
            $total = bcsub($subtotal, $credit);
        }
        if ($promo && !$is_interior_price) {
            $order_data["promo_code"] = $promo["code"];
            $order_data["promo_type"] = $promo["type"];
            $order_data["promo_value"] = $promo["value"];
        }
        $create_after_order = [];
        $create_after_pay = [];
        \think\Db::startTrans();
        try {
            $pay_type_count = array_unique($all_product_pay_type);
            if (!empty($param["admingenerateinvoice"]) && (count($pay_type_count) != 1 || !in_array("free", $pay_type_count))) {
                $invoices_data = ["uid" => $uid, "create_time" => time(), "due_time" => time(), "paid_time" => 0, "last_capture_attempt" => 0, "subtotal" => $subtotal, "credit" => 0, "tax" => 0, "tax2" => 0, "total" => $total, "taxrate" => 0, "taxrate2" => 0, "status" => "Unpaid", "payment" => $payment, "notes" => "", "type" => "product"];
                $invoiceid = db("invoices")->insertGetId($invoices_data);
                if (empty($invoiceid)) {
                    throw new \Exception("账单生成失败");
                }
            } else {
                $invoiceid = 0;
            }
            if (($param["adminorderconf"] ?? 0) == 1) {
            }
            $order_data["invoiceid"] = $invoiceid;
            $orderid = \think\Db::name("orders")->insertGetId($order_data);
            $hosts = [];
            $all_host = [];
            foreach ($product_items as $k => $v) {
                if (($param["admingenerateinvoice"] ?? 0) == 1) {
                    $invoices_items = $v["invoices_items"];
                } else {
                    $invoices_items = NULL;
                }
                $product_config = $v["product_config"];
                $customfields_over = $v["customfields"];
                $qtys = $v["qty"];
                unset($v["invoices_items"]);
                unset($v["product_config"]);
                unset($v["customfields"]);
                unset($v["qty"]);
                $v["orderid"] = $orderid;
                $r = db("products")->field("name,stock_control,qty,auto_setup")->where("id", $v["productid"])->find();
                $pid = $v["productid"];
                $rule = \think\Db::name("products")->field("host,password,type")->where("id", $pid)->find();
                $current_rule = json_decode($rule["host"], true);
                $host_rule = $current_rule["rule"];
                $prefix = $current_rule["prefix"];
                $host_show = $current_rule["show"];
                $password_rule = json_decode($rule["password"], true);
                unset($v["host"]);
                unset($v["password"]);
                for ($i = 0; $i < $qtys; $i++) {
                    if ($v["billingcycle"] == "onetime") {
                        $v["amount"] = 0;
                        $v["nextduedate"] = 0;
                    }
                    $v["domain"] = generateHostName($prefix, $host_rule, $host_show);
                    $v["password"] = cmf_encrypt(generateHostPassword($password_rule, $rule["type"]));
                    $hostid = db("host")->insertGetId($v);
                    $h = [];
                    $h["hid"] = $hostid;
                    $h["billingcycle"] = $v["billingcycle"];
                    $hosts[] = $h;
                    $all_host[] = $hostid;
                    if ($r["auto_setup"] == "order") {
                        $create_after_order[] = $hostid;
                    } else {
                        if ($r["auto_setup"] == "payment") {
                            $create_after_pay[] = $hostid;
                        }
                    }
                    if (!empty($invoices_items)) {
                        foreach ($invoices_items as $kk => $vv) {
                            $invoices_items[$kk]["invoice_id"] = $invoiceid;
                            $invoices_items[$kk]["rel_id"] = $hostid;
                            if ($vv["one_time"] == 1) {
                                unset($vv["one_time"]);
                                $vv["invoice_id"] = $invoiceid;
                                $vv["rel_id"] = $hostid;
                                \think\Db::name("invoice_items")->insert($vv);
                                unset($invoices_items[$kk]);
                            }
                        }
                        db("invoice_items")->insertAll($invoices_items);
                    }
                    if (!empty($product_config)) {
                        foreach ($product_config as $kk => $vv) {
                            $product_config[$kk]["relid"] = $hostid;
                        }
                        \think\Db::name("host_config_options")->insertAll($product_config);
                    }
                    if (!empty($customfields_over)) {
                        foreach ($customfields_over as $kk => $vv) {
                            $customfields_over[$kk]["relid"] = $hostid;
                            $customfields_over[$kk]["create_time"] = time();
                        }
                        \think\Db::name("customfieldsvalues")->insertAll($customfields_over);
                    }
                }
                if (!empty($promo)) {
                    db("promo_code")->where("id", $promo["id"])->setInc("used", $qtys);
                }
            }
            if (count($all_host) == 1) {
                \think\Db::name("invoices")->where("id", $invoiceid)->update(["url" => "servicedetail?id=" . $all_host[0]]);
            } else {
                if (1 < count($all_host)) {
                    $menu = new \app\common\logic\Menu();
                    $fpid = \think\Db::name("host")->where("id", $all_host[0])->value("productid");
                    $url = $menu->proGetNavId(intval($fpid))["url"] ?: "";
                    \think\Db::name("invoices")->where("id", $invoiceid)->update(["url" => $url]);
                }
            }
            active_log(sprintf($this->lang["Order_admin_save_success"], $uid, $orderid), $uid);
            active_log(sprintf($this->lang["Order_admin_save_success"], $uid, $orderid), $uid, "", 2);
            \think\Db::commit();
            $result["data"]["orderid"] = $orderid;
            $result["status"] = 200;
            $result["msg"] = lang("创建订单成功");
        } catch (\Exception $e) {
            $result["status"] = 400;
            $result["msg"] = $e->getMessage();
            \think\Db::rollback();
        }
        if ($result["status"] != 200) {
            return jsonrule($result);
        }
        $curl_multi_data = [];
        if ($subtotal != 0) {
            foreach ($hosts as $h) {
                if ($h["billingcycle"] != "free") {
                    $arr_admin = ["relid" => $h["hid"], "name" => "【管理员】新订单通知", "type" => "invoice", "sync" => true, "admin" => true, "ip" => get_client_ip6()];
                    $curl_multi_data[count($curl_multi_data)] = ["url" => "async", "data" => $arr_admin];
                    $arr_client = ["relid" => $h["hid"], "name" => "新订单通知", "type" => "invoice", "sync" => true, "admin" => false, "ip" => get_client_ip6()];
                    $curl_multi_data[count($curl_multi_data)] = ["url" => "async", "data" => $arr_client];
                }
            }
            $message_template_type = array_column(config("message_template_type"), "id", "name");
            foreach ($product_items as $k => $v) {
                $hostre = \think\Db::name("products")->field("name")->where("id", $v["productid"])->find();
                $sms = new \app\common\logic\Sms();
                $client = check_type_is_use($message_template_type[strtolower("New_Order_Notice")], $v["uid"], $sms);
                if ($client && $v["billingcycle"] != "free") {
                    $b = config("billing_cycle");
                    $params = ["product_name" => $hostre["name"], "product_binlly_cycle" => $b[$v["billingcycle"]], "product_price" => $v["amount"], "order_create_time" => date("Y-m-d H:i:s", $v["create_time"])];
                    $arr = ["name" => $message_template_type[strtolower("New_Order_Notice")], "phone" => $client["phone_code"] . $client["phonenumber"], "params" => json_encode($params), "sync" => false, "uid" => $v["uid"], "delay_time" => 0, "is_market" => false];
                    $curl_multi_data[count($curl_multi_data)] = ["url" => "async_sms", "data" => $arr];
                }
            }
        }
        foreach ($create_after_order as $v) {
            $host_arr = ["hid" => $v, "is_admin" => true];
            $curl_multi_data[count($curl_multi_data)] = ["url" => "async_create", "data" => $host_arr];
        }
        if ($total == 0) {
            if (!empty($invoiceid)) {
                \think\Db::startTrans();
                try {
                    \think\Db::name("invoices")->where("id", $invoiceid)->update(["status" => "Paid", "paid_time" => time()]);
                    if (0 < $credit) {
                        \think\Db::name("invoices")->where("id", $invoiceid)->update(["credit" => $credit]);
                        $inc_credit = \think\Db::name("clients")->where("id", $uid)->where("credit", ">=", $credit)->setDec("credit", $credit);
                        if (empty($inc_credit)) {
                            active_log(sprintf($this->lang["Order_admin_clients_updatecredit_fail"], $uid), $uid);
                            throw new \Exception("余额不足");
                        }
                        credit_log(["uid" => $uid, "desc" => "Credit Applied to Invoice #" . $invoiceid, "amount" => $credit, "relid" => $invoiceid]);
                    }
                    \think\Db::commit();
                } catch (\Exception $e) {
                    \think\Db::rollback();
                    return jsonrule(["status" => 400, "msg" => "支付失败:" . $e->getMessage()]);
                }
                $invoice_logic = new \app\common\logic\Invoices();
                $invoice_logic->is_admin = true;
                $invoice_logic->processPaidInvoice($invoiceid);
            } else {
                foreach ($create_after_pay as $hh) {
                    $curl_multi_data[count($curl_multi_data)] = ["url" => "async_create", "data" => ["hid" => $hh]];
                }
            }
            $result["status"] = 200;
            $result["msg"] = lang("BUY_SUCCESS");
        } else {
            $result["status"] = 200;
            $result["data"]["invoiceid"] = $invoiceid;
        }
        asyncCurlMulti($curl_multi_data);
        return jsonrule($result);
    }
    public function read($id)
    {
        if (!intval($id)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $gateways = gateway_list("gateways", 0);
        $row = \think\Db::name("orders")->alias("o")->join("clients c", "c.id=o.uid")->leftJoin("currencies cu", "cu.id = c.currency")->leftJoin("invoices i", "o.invoiceid = i.id")->field("o.notes as order_notes")->field("i.subtotal,i.status as i_status,i.subtotal as sub,i.credit,i.use_credit_limit,i.payment as i_payment,c.username,c.ip,c.country,o.id,o.uid,o.status,o.create_time,o.amount,o.promo_code,o.payment,o.invoiceid,cu.prefix,cu.suffix,o.invoiceid")->withAttr("amount", function ($value, $data) {
            return $data["prefix"] . $value . $data["suffix"];
        })->withAttr("payment", function ($value, $data) use($gateways) {
            $i_value = $data["i_payment"] ?: $value;
            if ($data["i_status"] == "Paid") {
                if ($data["use_credit_limit"] == 1) {
                    return "信用额支付";
                }
                if ($data["sub"] == $data["credit"]) {
                    return "余额支付";
                }
                if ($data["credit"] < $data["sub"] && 0 < $data["credit"]) {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $i_value) {
                            return "部分余额支付+" . $v["title"];
                        }
                    }
                } else {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $i_value) {
                            return $v["title"];
                        }
                    }
                }
            } else {
                foreach ($gateways as $v) {
                    if ($v["name"] == $i_value) {
                        return $v["title"];
                    }
                }
            }
        })->where("o.delete_time", 0)->where("o.id", $id)->find();
        if ($row["invoiceid"] == 0) {
            $row["invoiceid_zh"] = lang("NO_INVOICE");
        } else {
            $row["invoiceid_zh"] = $row["invoiceid"];
        }
        $row["order_notes"] = $row["order_notes"] ?: "";
        $row["promo_code"] = promoShow($row["promo_code"]);
        $currency = \think\Db::name("clients")->alias("a")->join("currencies b", "a.currency = b.id")->field("b.prefix,b.suffix")->where("a.id", $row["uid"])->find();
        $servers = \think\Db::name("host")->alias("h")->join("products p", "p.id=h.productid")->join("orders o", "o.id = h.orderid")->distinct(true)->field("h.id,h.billingcycle,h.firstpaymentamount as amount,h.serverid,h.username,h.password,p.type,p.name,p.description,p.welcome_email,h.domainstatus,o.invoiceid as invoice_id")->withAttr("billingcycle", function ($value) {
            return config("billing_cycle")[$value];
        })->withAttr("description", function ($value) {
            return "";
        })->where("o.id", $id)->select()->toArray();
        $upgrades_product = \think\Db::name("upgrades")->alias("a")->leftJoin("orders b", "a.order_id = b.id")->leftJoin("host c", "a.relid = c.id")->leftJoin("products d", "a.new_value = d.id")->distinct(true)->field("d.type,d.welcome_email,a.type as up_type,c.id,b.invoiceid as invoice_id,a.amount,a.new_cycle as billingcycle,c.serverid,c.username,c.password,d.name,a.description,a.status as domainstatus")->where("b.id", $id)->where("a.type", "product")->withAttr("name", function ($value, $data) {
            if ($data["up_type"] == "product") {
                return "升降级产品";
            }
            if ($data["up_type"] == "configoptions") {
                return "升降级可配置项";
            }
            return "";
        })->withAttr("billingcycle", function ($value) {
            if ($value) {
                return config("billing_cycle")[$value];
            }
            return "";
        })->select()->toArray();
        $upgrades_configoptions = \think\Db::name("upgrades")->alias("a")->leftJoin("orders b", "a.order_id = b.id")->leftJoin("host c", "a.relid = c.id")->leftJoin("products d", "c.productid = d.id")->distinct(true)->field("d.type,d.welcome_email,a.type as up_type,c.id,b.invoiceid as invoice_id,a.amount,a.new_cycle as billingcycle,c.serverid,c.username,c.password,d.name,a.description,a.status as domainstatus")->where("b.id", $id)->where("a.type", "configoptions")->withAttr("name", function ($value, $data) {
            if ($data["up_type"] == "product") {
                return "升降级产品";
            }
            if ($data["up_type"] == "configoptions") {
                return "升降级可配置项";
            }
            return "";
        })->withAttr("billingcycle", function ($value) {
            if ($value) {
                return config("billing_cycle")[$value];
            }
            return "";
        })->select()->toArray();
        if (!empty($upgrades_product[0]) || !empty($upgrades_configoptions[0])) {
            $servers = !empty($upgrades_product[0]) ? $upgrades_product : $upgrades_configoptions;
        }
        $total = 0;
        foreach ($servers as $k => $v) {
            $total = bcadd($total, $v["amount"], 2);
            if ($v["invoice_id"] == 0) {
                $servers[$k]["invoice_id"] = ["name" => lang("NO_INVOICE"), "color" => "#000000"];
            } else {
                $status = \think\Db::name("invoices")->where("id", $v["invoice_id"])->value("status");
                $servers[$k]["invoice_id"] = config("invoice_payment_status")[$status];
            }
            $servers[$k]["amount"] = $currency["prefix"] . $v["amount"] . $currency["suffix"];
            $servers[$k]["password"] = cmf_decrypt($servers[$k]["password"]);
            $upgrad_status = ["Pending" => "待核验", "Completed" => "已完成"];
            $servers[$k]["domainstatus"] = config("domainstatus")[$servers[$k]["domainstatus"]] ?? $upgrad_status[$servers[$k]["domainstatus"]];
            $servers[$k]["runcreate"] = false;
            $servers[$k]["sendwolcome"] = false;
            if (empty($v["serverid"])) {
                $servers[$k]["server_group"] = "";
            } else {
                $gid = \think\Db::name("servers")->where("id", $v["serverid"])->value("gid");
                $servers[$k]["server_group"] = db("servers")->field("id,name")->where("gid", $gid)->select();
            }
        }
        $row["server"] = $servers;
        $row["total"] = $currency["prefix"] . $total . $currency["suffix"];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $row, "currency" => $currency, "order_status" => config("order_status")]);
    }
    public function notes()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $validate = new \app\admin\validate\OrderValidate();
        if (!$validate->check($params)) {
            return jsonrule($validate->getError(), 400);
        }
        $res = \think\Db::name("orders")->field("notes,uid")->where("id", $id)->find();
        \think\Db::name("orders")->where("id", $id)->update(["notes" => $params["notes"]]);
        active_log(sprintf($this->lang["Order_admin_notes"], $id, $res["notes"], $params["notes"]), $res["uid"]);
        active_log(sprintf($this->lang["Order_admin_notes"], $id, $res["notes"], $params["notes"]), $res["uid"], "", 2);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "notes" => $params["notes"]]);
    }
    public function active()
    {
        $params = $this->request->param();
        $order_status = $params["status"];
        if (!in_array($order_status, array_keys(config("domainstatus")))) {
            return jsonrule(["status" => 400, "msg" => lang("订单状态有误")]);
        }
        $id = intval($params["id"]);
        $order = \think\Db::name("orders")->where("id", $id)->find();
        if (empty($order)) {
            return jsonrule(["status" => 400, "msg" => lang("订单不存在")]);
        }
        \think\Db::name("orders")->field("status", "update_time")->where("id", $id)->find();
        \think\Db::name("orders")->where("id", $id)->update(["status" => "Active", "update_time" => time()]);
        $st = config("order_status");
        active_log(sprintf($this->lang["Order_admin_active"], $id, $order["uid"], $st["Active"]["name"]), $order["uid"]);
        active_log(sprintf($this->lang["Order_admin_active"], $id, $order["uid"], $st["Active"]["name"]), $order["uid"], "", 2);
        $hosts = $params["host"];
        foreach ($hosts as $host) {
            $host_data = \think\Db::name("host")->alias("a")->field("a.*,b.welcome_email,b.auto_setup")->join("products b", "a.productid = b.id")->where("a.id", $host["id"])->find();
            \think\Db::name("host")->where("id", $host["id"])->update(["username" => $host["username"], "password" => cmf_encrypt($host["password"]), "serverid" => $host["server"]]);
            if ($host_data["auto_setup"] == "on" && !empty($host["runcreate"])) {
                $host_model = new \app\common\logic\Host();
                $result = $host_model->create($host["id"]);
                $logic_run_map = new \app\common\logic\RunMap();
                $model_host = new \app\common\model\HostModel();
                $data_i = [];
                $data_i["host_id"] = $host["id"];
                $data_i["active_type_param"] = [$host["id"], ""];
                $is_zjmf = $model_host->isZjmfApi($data_i["host_id"]);
                if ($result["status"] == 200) {
                    $data_i["description"] = " 手动审核后 - 开通 Host ID:" . $data_i["host_id"] . "的产品成功";
                    if ($is_zjmf) {
                        $logic_run_map->saveMap($data_i, 1, 400, 1, 1);
                    }
                    if (!$is_zjmf) {
                        $logic_run_map->saveMap($data_i, 1, 200, 1, 1);
                    }
                } else {
                    $data_i["description"] = " 手动审核后 - 开通 Host ID:" . $data_i["host_id"] . "的产品失败：" . $result["msg"];
                    if ($is_zjmf) {
                        $logic_run_map->saveMap($data_i, 0, 400, 1, 1);
                    }
                    if (!$is_zjmf) {
                        $logic_run_map->saveMap($data_i, 0, 200, 1, 1);
                    }
                }
            }
        }
        return jsonrule(["status" => 200, "msg" => lang("审核通过")]);
    }
    public function changeStatus()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        $orderss = \think\Db::name("orders")->field("uid,status,update_time")->where("id", $id)->find();
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $status = $params["status"];
        $st = config("order_status");
        if (!isset(config("order_status")[$status])) {
            return jsonrule(["status" => 400, "msg" => lang("订单状态错误")]);
        }
        \think\Db::name("orders")->where("id", $id)->update(["status" => $status, "update_time" => time()]);
        active_log(sprintf($this->lang["Order_admin_ordersttaus"], $id, $orderss["uid"], $st[$status]["name"]), $orderss["uid"]);
        active_log(sprintf($this->lang["Order_admin_ordersttaus"], $id, $orderss["uid"], $st[$status]["name"]), $orderss["uid"], "", 2);
        return jsonrule(["status" => 200, "msg" => lang("UPDATE SUCCESS")]);
    }
    public function customPromoPage()
    {
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "type" => config("promo_code_type")]);
    }
    public function customPromo()
    {
        if ($this->request->isPost()) {
            $params = $this->request->only(["code", "type", "recurring", "recurfor", "value"]);
            $rule = ["code" => "require|max:10", "type" => "in:percent,fixed,override,free", "value" => "float", "recurring" => "in:0,1", "recurfor" => "number"];
            $msg = ["code.require" => lang("PROMO_CODE_REQUIRE"), "type.in" => lang("PROMO_CODE_TYPE_ERROR"), "value.float" => lang("PROMO_CODE_VALUE_ERROR"), "recurfor.number" => lang("PROMO_CODE_CYCLE_TIMES_ERROR")];
            $validate = new \think\Validate($rule, $msg);
            $validate_result = $validate->check($params);
            if (!$validate_result) {
                active_log(sprintf($this->lang["Order_admin_create_customPromo_success"], $validate->getError()));
                return jsonrule(["status" => 400, "msg" => $validate->getError()]);
            }
            $code_exist = \think\Db::name("promo_code")->where("code", $params["code"])->find();
            if (!empty($code_exist)) {
                active_log(sprintf($this->lang["Order_admin_create_customPromo_success"], lang("PROMO_CODE_ALLREADY_EXIST")));
                $result["status"] = 400;
                $result["msg"] = lang("PROMO_CODE_ALLREADY_EXIST");
                return jsonrule($result);
            }
            $r = \think\Db::name("promo_code")->insertGetId($params);
            if ($r) {
                active_log(lang("PROMO_CODE_ADD_SUCCESS", [$params["code"], $r]));
                active_log(sprintf($this->lang["Order_admin_create_customPromo_success"], $r));
                $result["status"] = 200;
                $result["msg"] = lang("ADD SUCCESS");
                return jsonrule($result);
            }
            active_log(sprintf($this->lang["Order_admin_create_customPromo_success"], lang("ADD FAIL")));
            return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function checkProduct($payontrial_condition, $uid)
    {
        $user_info = \think\Db::name("clients")->field("email,phonenumber,wechat_id")->where("id", $uid)->find();
        foreach ($payontrial_condition as $vv) {
            if ($vv == "realname" && !checkCertify($uid)) {
                $one_error[] = "实名认证";
            }
            if ($vv == "email" && empty($user_info["email"])) {
                $one_error[] = "邮箱验证";
            }
            if ($vv == "phone" && empty($user_info["phonenumber"])) {
                $one_error[] = "手机验证";
            }
            if ($vv == "wechat" && empty($user_info["wechat_id"])) {
                $one_error[] = "微信验证";
            }
        }
        return $one_error;
    }
}

?>