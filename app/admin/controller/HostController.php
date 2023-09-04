<?php


namespace app\admin\controller;

/**
 * @title 产品/服务列表页
 * @description 接口说明
 */
class HostController extends GetUserController
{
    public function getList(\think\Request $request)
    {
        $param = $request->param();
        $pagecount = intval($param["pagecount"]) ?: configuration("NumRecordstoDisplay");
        $pagecount = $pagecount ?: 0;
        $order = isset($param["order"][0]) ? trim($param["order"]) : "h.id";
        $sort = isset($param["sort"][0]) ? trim($param["sort"]) : "DESC";
        $page = intval($param["page"]) ?: 1;
        $limit_start = ($page - 1) * $pagecount;
        $uid = $param["uid"];
        $name = $param["name"];
        $product_type = $param["product_type"];
        $server = $param["server"];
        $product = $param["product"];
        $payment = $param["payment"];
        $billingcycle = $param["billingcycle"];
        $domainstatus = $param["domainstatus"];
        $domain = $param["domain"];
        $dedicatedip = $param["ip"];
        $start_time = $param["start_time"];
        $where = [];
        if (isset($param["username"])) {
            $username = $param["username"];
            $where[] = ["c.username", "like", "%" . $username . "%"];
        }
        if (!empty($uid)) {
            $where[] = ["h.uid", "=", $uid];
        }
        if (!empty($name)) {
            $where[] = ["p.name", "=", $name];
        }
        if (!empty($product_type)) {
            $where[] = ["p.type", "=", $product_type];
        }
        if (!empty($server)) {
            $where[] = ["h.serverid", "=", $server];
        }
        if (!empty($product)) {
            $where[] = ["h.productid", "=", $product];
        }
        if (!empty($payment)) {
            $where[] = ["h.payment", "=", $payment];
        }
        if (!empty($billingcycle)) {
            $where[] = ["h.billingcycle", "=", $billingcycle];
        }
        if (!empty($domainstatus) && $domainstatus !== "All") {
            $where[] = ["h.domainstatus", "=", $domainstatus];
        }
        if (!empty($domain)) {
            $where[] = ["h.domain", "like", "%" . $domain . "%"];
        }
        if (!empty($dedicatedip)) {
            $where[] = ["h.dedicatedip|h.assignedips", "like", "%" . $dedicatedip . "%"];
        }
        if ($this->user["id"] != 1 && $this->user["is_sale"]) {
            $where[] = ["h.uid", "in", $this->str];
        }
        if (!empty($param["nextduedate"])) {
            if ($param["nextduedate"] == 1) {
                $where[] = ["h.nextduedate", "egt", strtotime(date("Y-m-d", time()))];
                $where[] = ["h.nextduedate", "elt", strtotime(date("Y-m-d 23:59:59", time()))];
            } else {
                if ($param["nextduedate"] == 2) {
                    $where[] = ["h.nextduedate", "egt", strtotime(date("Y-m-d", time()))];
                    $where[] = ["h.nextduedate", "elt", strtotime(date("Y-m-d 23:59:59", time() + 259200))];
                } else {
                    if ($param["nextduedate"] == 3) {
                        $where[] = ["h.nextduedate", "egt", strtotime(date("Y-m-d", time()))];
                        $where[] = ["h.nextduedate", "elt", strtotime(date("Y-m-d 23:59:59", time() + 604800))];
                    } else {
                        if ($param["nextduedate"] == 4) {
                            $where[] = ["h.nextduedate", "egt", strtotime(date("Y-m-d", time()))];
                            $where[] = ["h.nextduedate", "elt", strtotime(date("Y-m-d 23:59:59", time() + 2592000))];
                        } else {
                            if ($param["nextduedate"] == 6) {
                                $where[] = ["h.nextduedate", ">", 0];
                                $where[] = ["h.nextduedate", "elt", time()];
                            } else {
                                if ($start_time[0]) {
                                    $where[] = ["h.nextduedate", "egt", $start_time[0]];
                                }
                                if ($start_time[1]) {
                                    $where[] = ["h.nextduedate", "elt", $start_time[1]];
                                }
                            }
                        }
                    }
                }
            }
        }
        $total_arr = \think\Db::name("host")->alias("h")->field("h.firstpaymentamount,h.amount,h.billingcycle")->leftJoin("products p", "p.id=h.productid")->leftJoin("clients c", "c.id=h.uid")->where($where)->cursor();
        $total = 0;
        foreach ($total_arr as $val) {
            if ($val["billingcycle"] == "onetime") {
                $val["amount"] = $val["firstpaymentamount"];
            }
            $total = bcadd($total, $val["amount"], 2);
        }
        $host_list = \think\Db::name("host")->field("h.firstpaymentamount")->field("cr.type as crtype,cr.reason,h.id,h.initiative_renew,h.domain,h.uid,h.dedicatedip,h.productid,h.billingcycle,h.domain,h.payment,h.nextduedate,h.assignedips,h.regdate,h.dedicatedip,h.amount,h.domainstatus,c.username,p.name as productname,c.currency,p.type,h.serverid,u.user_nickname")->alias("h")->leftJoin("products p", "p.id=h.productid")->leftJoin("clients c", "c.id=h.uid")->leftJoin("user u", "c.sale_id=u.id")->leftJoin("cancel_requests cr", "cr.relid = h.id")->where($where)->order($order, $sort)->order("h.id", "desc")->limit($limit_start, $pagecount)->select()->toArray();
        $tmp = \think\Db::name("currencies")->select()->toArray();
        $currency = array_column($tmp, NULL, "id");
        $page_total = 0;
        foreach ($host_list as &$v) {
            if ($v["billingcycle"] == "onetime") {
                $v["amount"] = $v["firstpaymentamount"];
            }
            $page_total = bcadd($page_total, $v["amount"], 2);
            $v["status_color"] = config("public.domainstatus")[$v["domainstatus"]]["color"];
            $v["assignedips"] = !empty(explode(",", $v["assignedips"])[0]) ? explode(",", $v["assignedips"]) : [];
            $v["domainstatus"] = $v["domainstatus"] ? config("public.domainstatus")[$v["domainstatus"]] : ["name" => "未知状态", "color" => "#FF5722"];
            $v["amount"] = $currency[$v["currency"]]["prefix"] . $v["amount"] . $currency[$v["currency"]]["suffix"];
            $v["assignedips"] = array_filter($v["assignedips"]);
            if (!empty($v["crtype"])) {
                if ($v["crtype"] == "Immediate") {
                    $v["crtype"] = "立即停用";
                } else {
                    $v["crtype"] = "到期时停用";
                }
                $v["cancel_list"] = ["crtype" => $v["crtype"], "reason" => $v["reason"]];
            }
        }
        $count = \think\Db::name("host")->alias("h")->leftJoin("products p", "p.id=h.productid")->leftJoin("clients c", "c.id=h.uid")->leftJoin("cancel_requests cr", "cr.relid = h.id")->where($where)->count();
        $returndata = [];
        $returndata["list"] = $host_list;
        $returndata["pagination"]["pagecount"] = $pagecount;
        $returndata["pagination"]["page"] = $page;
        $returndata["pagination"]["orderby"] = $order;
        $returndata["pagination"]["sorting"] = $sort;
        $returndata["pagination"]["total_page"] = ceil($count / $pagecount);
        $returndata["pagination"]["count"] = $count;
        $returndata["search"]["product_type"] = $product_type ?: "";
        $returndata["search"]["server"] = $server ?: "";
        $returndata["search"]["product"] = $product ?: "";
        $returndata["search"]["payment"] = $payment ?: "";
        $returndata["search"]["billingcycle"] = $billingcycle ?: "";
        $returndata["search"]["domainstatus"] = $domainstatus ?: "";
        $prefix = $currency[$returndata["list"][0]["currency"]]["prefix"];
        $suffix = $currency[$returndata["list"][0]["currency"]]["suffix"];
        $returndata["total"] = $prefix . $total . $suffix;
        $returndata["page_total"] = $prefix . $page_total . $suffix;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function getTimetype(\think\Request $request)
    {
        $returndata["time_type"] = config("time_type");
        $returndata["product_type"] = config("product_type");
        $returndata["billingcycle"] = config("billing_cycle");
        $returndata["server_list"] = \think\Db::name("servers")->field("id,name")->select()->toArray();
        $product_groups = \think\Db::name("product_groups")->field("id,name as groupname")->select();
        $product_list = [];
        $i = 0;
        foreach ($product_groups as $key => $val) {
            $groupid = $val["id"];
            $product_list[$i] = $val;
            $product_list[$i]["clild"] = \think\Db::name("products")->field("id,name as productname")->where("gid", $groupid)->select()->toArray();
            $i++;
        }
        $returndata["product_list"] = $product_list;
        $returndata["gateway_list"] = gateway_list1("gateways");
        $returndata["domainstatus"] = config("domainstatus");
        $users = [];
        $returndata["clientlist"] = $users;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function userInfo(\think\Request $req)
    {
        $info = \think\Db::name("clients")->field("usertype,username,companyname,email,qq,lastloginip as ip,address1,phone_code,phonenumber,notes")->where("id", $req->uid)->find();
        if (empty($info)) {
            return jsonrule(["status" => 400, "msg" => "用户信息不存在"]);
        }
        if ($info["usertype"] == 1) {
            $info["usertype"] = "普通用户";
        }
        if ($info["usertype"] == 2) {
            $info["usertype"] = "会员";
        }
        return jsonrule(["status" => 200, "data" => $info]);
    }
}

?>