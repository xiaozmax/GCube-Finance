<?php


namespace app\admin\controller;

/**
 * @title 销售管理
 * @description 接口说明
 */
class SaleController extends GetUserController
{
    private $validate = NULL;
    public function initialize()
    {
        parent::initialize();
        $this->validate = new \app\admin\validate\SaleValidate();
    }
    public function groupList()
    {
        $params = $data = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "ASC";
        $data["group_name"] = !empty($params["group_name"]) ? trim($params["group_name"]) : "";
        $data["bates"] = !empty($params["bates"]) ? trim($params["bates"]) : "";
        $total = \think\Db::name("sales_product_groups")->where(function (\think\db\Query $query) use($data) {
            if (!empty($data["group_name"])) {
                $query->where("group_name", "like", "%" . trim($data["group_name"]) . "%");
            }
            if (!empty($data["bates"])) {
                $query->where("bates", "like", "%" . trim($data["bates"]) . "%");
            }
        })->count();
        $list = \think\Db::name("sales_product_groups")->where(function (\think\db\Query $query) use($data) {
            if (!empty($data["group_name"])) {
                $query->where("group_name", "like", "%" . trim($data["group_name"]) . "%");
            }
            if (!empty($data["bates"])) {
                $query->where("bates", "like", "%" . trim($data["bates"]) . "%");
            }
        })->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "total" => $total, "list" => $list]);
    }
    public function getTimetype(\think\Request $request)
    {
        $returndata["time_type"] = config("time_type2");
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function addSalegroupPage()
    {
        $groups = getProductLists();
        $data = ["group" => $groups];
        return jsonrule(["status" => 200, "data" => $data, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function addSalegroup()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            unset($param["request_time"]);
            unset($param["languagesys"]);
            $sale = array_map("trim", $param);
            if (!$this->validate->scene("add_salegroup")->check($sale)) {
                return json(["status" => 400, "msg" => $this->validate->getError()]);
            }
            $pids = $param["pids"];
            $param["pids"] = implode(",", $param["pids"]);
            $cid = \think\Db::name("sales_product_groups")->insertGetId($param);
            foreach ($pids as $item) {
                $data = ["pid" => $item, "gid" => $cid];
                \think\Db::name("sale_products")->insertGetId($data);
            }
            if (!$cid) {
                return json(["status" => 400, "msg" => lang("ADD FAIL")]);
            }
            active_log(sprintf($this->lang["Sale_admin_addSalegroup"], $cid));
            return json(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        } else {
            return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function editSalegroupPage()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        $groups = getProductListss($id);
        $spg = \think\Db::name("sales_product_groups")->where("id", $id)->find();
        $spg["pids"] = explode(",", $spg["pids"]);
        foreach ($spg["pids"] as $key => $val) {
            $spg["pids"][$key] = (int) $val;
        }
        $data = ["group" => $groups, "spg" => $spg];
        return jsonrule(["status" => 200, "data" => $data, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function editSalegroup()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $sale = array_map("trim", $param);
            if (empty($param["id"])) {
                return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
            }
            if (!$this->validate->scene("add_salegroup")->check($sale)) {
                return jsonrule(["status" => 400, "msg" => $this->validate->getError()]);
            }
            $desc = "";
            $spg = \think\Db::name("sales_product_groups")->where("id", $param["id"])->find();
            $data["group_name"] = $param["group_name"];
            if ($spg["group_name"] != $param["group_name"]) {
                $desc .= "分组名由“" . $spg["group_name"] . "”改为“" . $param["group_name"] . "”，";
            }
            $data["bates"] = $param["bates"];
            if ($spg["bates"] != $param["bates"]) {
                $desc .= "提成比例由“" . $spg["bates"] . "”改为“" . $param["bates"] . "”，";
            }
            $data["renew_bates"] = $param["renew_bates"];
            if ($spg["renew_bates"] != $param["renew_bates"]) {
                $desc .= "续费提成比例由“" . $spg["bates"] . "”改为“" . $param["bates"] . "”，";
            }
            $data["upgrade_bates"] = $param["upgrade_bates"];
            if ($spg["upgrade_bates"] != $param["upgrade_bates"]) {
                $desc .= "升降级提成比例由“" . $spg["bates"] . "”改为“" . $param["bates"] . "”，";
            }
            $data["is_renew"] = $param["is_renew"];
            if ($spg["is_renew"] != $param["is_renew"]) {
                if ($spg["is_renew"] == 1) {
                    $desc .= "续费计算由“关闭”改为“开启”，";
                } else {
                    $desc .= "续费计算由“开启”改为“关闭”，";
                }
            }
            $data["updategrade"] = $param["updategrade"];
            if ($spg["updategrade"] != $param["updategrade"]) {
                if ($spg["updategrade"] == 1) {
                    $desc .= "计算升降级由“关闭”改为“开启”，";
                } else {
                    $desc .= "计算升降级由“开启”改为“关闭”，";
                }
            }
            $data["pids"] = implode(",", $param["pids"]);
            \think\Db::startTrans();
            try {
                \think\Db::name("sale_products")->where("gid", $param["id"])->delete();
                \think\Db::name("sales_product_groups")->where("id", $param["id"])->update($data);
                foreach ($param["pids"] as $item) {
                    $datas = ["pid" => $item, "gid" => $param["id"]];
                    \think\Db::name("sale_products")->insertGetId($datas);
                }
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 400, "msg" => $e->getMessage()]);
            }
            if (empty($desc)) {
                $desc .= "没有任何修改";
            }
            active_log(sprintf($this->lang["Sale_admin_editSalegroup"], $param["id"], $desc));
            return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function delSalegroup()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        $res = \think\Db::name("sales_product_groups")->where("id", $id)->find();
        if (!empty($res)) {
            \think\Db::name("sale_products")->where("gid", $id)->delete();
            \think\Db::name("sales_product_groups")->where("id", $id)->delete();
            active_log(sprintf($this->lang["Sale_admin_delSalegroup"], $id));
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
    }
    public function ladderList()
    {
        $params = $data = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "turnover";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "ASC";
        $total = \think\Db::name("sale_ladder")->count();
        $list = \think\Db::name("sale_ladder")->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "total" => $total, "list" => $list]);
    }
    public function addSaleLadder()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $data["turnover"] = isset($param["turnover"]) ? intval($param["turnover"]) : 0;
            $data["bates"] = isset($param["bates"]) ? intval($param["bates"]) : 0;
            $data["is_flag"] = isset($param["is_flag"]) ? intval($param["is_flag"]) : 0;
            $cid = \think\Db::name("sale_ladder")->insertGetId($data);
            if (!$cid) {
                return jsonrule(["status" => 400, "msg" => lang("ADD FAIL")]);
            }
            active_log(sprintf($this->lang["Sale_admin_addSaleladder"], $cid));
            return jsonrule(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function editSaleLadderPage()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        if (empty($id)) {
            return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
        $spg = \think\Db::name("sale_ladder")->where("id", $id)->find();
        $data = ["ladder" => $spg];
        return jsonrule(["status" => 200, "data" => $data, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function editSaleLadder()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $data["turnover"] = isset($param["turnover"]) ? floatval($param["turnover"]) : 0;
            $data["bates"] = isset($param["bates"]) ? floatval($param["bates"]) : 0;
            $data["is_flag"] = isset($param["is_flag"]) ? intval($param["is_flag"]) : 0;
            $desc = "";
            $spg = \think\Db::name("sale_ladder")->where("id", $param["id"])->find();
            if ($spg["turnover"] != $param["turnover"]) {
                $desc .= "营业额由“" . $spg["turnover"] . "”改为“" . $param["turnover"] . "”，";
            }
            if ($spg["bates"] != $param["bates"]) {
                $desc .= " 提成比例" . $spg["bates"] . "”改为“" . $param["bates"] . "”，";
            }
            if ($spg["is_flag"] != $param["is_flag"]) {
                if ($spg["is_flag"] == 1) {
                    $desc .= "阶梯提成由“关闭”改为“开启”，";
                } else {
                    $desc .= "阶梯提成由“开启”改为“关闭”，";
                }
            }
            \think\Db::name("sale_ladder")->where("id", $param["id"])->update($data);
            if (empty($desc)) {
                $desc .= "没有任何修改";
            }
            active_log(sprintf($this->lang["Sale_admin_editSaleladder"], $param["id"], $desc));
            return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function delSaleLadder()
    {
        $params = $this->request->param();
        $id = intval($params["id"]);
        $res = \think\Db::name("sale_ladder")->where("id", $id)->find();
        if (!empty($res)) {
            \think\Db::name("sale_ladder")->where("id", $id)->delete();
            active_log(sprintf($this->lang["Sale_admin_delSaleladder"], $id));
            return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
    }
    public function saleStatistics()
    {
        $params = $data = $this->request->param();
        $id = !empty($params["id"]) ? intval($params["id"]) : session("ADMIN_ID");
        $start_time = $params["start_time"];
        $type = $params["type"];
        if ($type == 2) {
            $array = [];
            if ($params["time"] == 1) {
                $month = getMonths();
                foreach ($month as $key => $value) {
                    $array[$value] = $this->getLaddersaleStatistics($value, $id);
                }
            } else {
                if ($params["time"] == 2) {
                    $month = getLastMonths();
                    foreach ($month as $key => $value) {
                        $array[$value] = $this->getLaddersaleStatistics($value, $id);
                    }
                } else {
                    if ($params["time"] == 3) {
                        $month = getAllStartMonths($start_time);
                        foreach ($month as $key => $value) {
                            $array[$value] = $this->getLaddersaleStatistics("allmonth", $id, $value);
                        }
                    } else {
                        if ($params["time"] == 4) {
                            $month = getStartMonths($start_time);
                            foreach ($month as $key => $value) {
                                $array[$value] = $this->getLaddersaleStatistics($value, $id);
                            }
                        } else {
                            $month = getMonths();
                            foreach ($month as $key => $value) {
                                $array[$value] = $this->getLaddersaleStatistics($value, $id);
                            }
                        }
                    }
                }
            }
            return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "array" => $array]);
        } else {
            if (!empty($params["time"])) {
                $month = $last_month = [];
                if ($params["time"] == 1) {
                    $month = $this->getLaddersaleStatistics("this_month", $id);
                    $last_month = $this->getLaddersaleStatistics("last_month", $id);
                } else {
                    if ($params["time"] == 2) {
                        $month = $this->getLaddersaleStatistics("last_three_month", $id);
                        $last_month = $this->getLaddersaleStatistics("last_six_month", $id);
                    } else {
                        if ($params["time"] == 4) {
                            $month = $this->getLaddersaleStatistics("diy_time", $id, $start_time);
                            $last_month = $this->getLaddersaleStatistics("diy_time", $id, $start_time);
                        } else {
                            if ($params["time"] == 3) {
                                $month = $this->getLaddersaleStatistics("alltime", $id);
                                $last_month = [];
                            }
                        }
                    }
                }
                return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "today" => $this->getLaddersaleStatistics("today", $id), "week" => $this->getLaddersaleStatistics("week", $id), "month" => $month, "last_month" => $last_month]);
            }
            return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "today" => $this->getLaddersaleStatistics("today", $id), "week" => $this->getLaddersaleStatistics("week", $id), "month" => $this->getLaddersaleStatistics("month", $id), "last_month" => $this->getLaddersaleStatistics("last_month", $id)]);
        }
    }
    public function saleRecords()
    {
        $params = $data = $this->request->param();
        $name = !empty($params["name"]) ? trim($params["name"]) : "";
        $pname = !empty($params["pname"]) ? trim($params["pname"]) : "";
        $type = !empty($params["type"]) ? trim($params["type"]) : "";
        $id = !empty($params["id"]) ? intval($params["id"]) : session("ADMIN_ID");
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "h.id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "ASC";
        $where = [];
        $ladder = $this->getLadderforall($id);
        if (!empty($name)) {
            $where[] = ["c.username", "like", "%" . $name . "%"];
        }
        if (!empty($pname)) {
            $where[] = ["p.name", "like", "%" . $pname . "%"];
        }
        if (!empty($type)) {
            $where[] = ["in.type", "=", $type];
        }
        if (!empty($param["search_time"])) {
            $where[] = ["i.paid_time", ">=", strtotime(date("Y-m", $param["search_time"]))];
            $where[] = ["i.paid_time", "<", strtotime(date("Y-m", $param["search_time"]) . "+1 month")];
        } else {
            $where[] = ["i.paid_time", ">=", strtotime(date("Y-m", time()))];
            $where[] = ["i.paid_time", "<", strtotime(date("Y-m", time()) . "+1 month")];
        }
        try {
            $count = \think\Db::name("invoice_items")->alias("in")->join("host h", "h.id=in.rel_id")->join("products p", "p.id=h.productid")->leftJoin("sale_products sp", "p.id=sp.pid")->leftJoin("sales_product_groups spg", "spg.id=sp.gid")->join("invoices i", "i.id=in.invoice_id")->join("clients c", "i.uid=c.id")->join("currencies cu", "cu.id = c.currency")->field("i.uid,in.invoice_id")->where("i.status", "=", "Paid")->where("c.sale_id", $id)->where("in.type", "neq", "upgrade")->where($where)->select()->toArray();
            foreach ($count as $k => $vs) {
                $fl = false;
                $ii = \think\Db::name("invoice_items")->where("invoice_id", $vs["invoice_id"])->select();
                foreach ($ii as $vs1) {
                    if ($vs1["type"] == "upgrade") {
                        $fl = true;
                    }
                }
                if ($fl) {
                    unset($count[$k]);
                }
            }
            $count1 = \think\Db::name("invoice_items")->alias("in")->join("upgrades u", "u.id=in.rel_id")->join("host h", "h.id=u.relid")->join("products p", "p.id=h.productid")->leftJoin("sale_products sp", "p.id=sp.pid")->leftJoin("sales_product_groups spg", "spg.id=sp.gid")->join("invoices i", "i.id=in.invoice_id")->join("clients c", "i.uid=c.id")->join("currencies cu", "cu.id = c.currency")->field("i.uid,in.invoice_id")->where("i.status", "=", "Paid")->where("c.sale_id", $id)->where("in.type='upgrade' OR in.type='discount'")->where($where)->select()->toArray();
            $counts = array_merge($count, $count1);
            $arrs = [];
            foreach ($counts as $key => $val) {
                if (!empty($arrs[$val["invoice_id"]])) {
                    $arrs[$val["invoice_id"]]["child"][] = $val;
                } else {
                    $arrs[$val["invoice_id"]]["child"][] = $val;
                }
            }
            $total = count($arrs);
            unset($arrs);
            unset($counts);
            unset($count1);
            unset($count);
        } catch (\think\Exception $e) {
            var_dump($e->getMessage());
        }
        $list = \think\Db::name("invoice_items")->alias("in")->join("host h", "h.id=in.rel_id")->join("products p", "p.id=h.productid")->leftJoin("sale_products sp", "p.id=sp.pid")->leftJoin("sales_product_groups spg", "spg.id=sp.gid")->join("invoices i", "i.id=in.invoice_id")->join("clients c", "i.uid=c.id")->join("currencies cu", "cu.id = c.currency")->field("i.uid,h.id as hostid,in.id,in.invoice_id,in.amount,spg.bates,p.name,c.username,c.companyname,in.type,i.type as typess,spg.is_renew,spg.updategrade,spg.renew_bates,spg.upgrade_bates,cu.suffix,h.domain,h.dedicatedip")->where("i.status", "=", "Paid")->where("c.sale_id", $id)->where("in.type", "neq", "upgrade")->where($where)->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        foreach ($list as $k => $vs) {
            $fl = false;
            $ii = \think\Db::name("invoice_items")->where("invoice_id", $vs["invoice_id"])->select();
            foreach ($ii as $vs1) {
                if ($vs1["type"] == "upgrade") {
                    $fl = true;
                }
            }
            if ($fl) {
                unset($list[$k]);
            }
        }
        $list1 = \think\Db::name("invoice_items")->alias("in")->join("upgrades u", "u.id=in.rel_id")->join("host h", "h.id=u.relid")->join("products p", "p.id=h.productid")->leftJoin("sale_products sp", "p.id=sp.pid")->leftJoin("sales_product_groups spg", "spg.id=sp.gid")->join("invoices i", "i.id=in.invoice_id")->join("clients c", "i.uid=c.id")->join("currencies cu", "cu.id = c.currency")->field("i.uid,h.id as hostid,in.id,in.invoice_id,in.amount,spg.bates,p.name,c.username,c.companyname,in.type,i.type as typess,spg.is_renew,spg.updategrade,spg.renew_bates,spg.upgrade_bates,cu.suffix,h.domain,h.dedicatedip")->where("i.status", "=", "Paid")->where("c.sale_id", $id)->where("in.type='upgrade' OR in.type='discount'")->where($where)->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        $list = array_merge($list, $list1);
        $arr = [];
        foreach ($list as $key => $val) {
            $str = $list[$key]["username"] . "(" . $list[$key]["companyname"] . ")";
            $list[$key]["username"] = "<a class=\"el-link el-link--primary is-underline\" \n            href=\"#/customer-view/abstract?id=" . $val["uid"] . "\">\n            <span class=\"el-link--inner\" style=\"display: block;height: 24px;line-height: 24px;\">" . $str . "</span></a>";
            $str = $list[$key]["name"] . "(" . $list[$key]["domain"] . ")";
            $list[$key]["name"] = "<a class=\"el-link el-link--primary is-underline\" \n                href=\"#/customer-view/product-innerpage?hid=" . $val["hostid"] . "&id=" . $val["uid"] . "\">\n                <span class=\"el-link--inner\" style=\"display: block;height: 24px;line-height: 24px;\">" . $str . "</span></a>";
            if (!empty($ladder["turnover"]["turnover"])) {
                if ($val["is_renew"] == 0 && $val["type"] == "renew" || $val["is_renew"] == 0 && $val["typess"] == "renew" && $val["type"] == "discount") {
                    $list[$key]["bates"] = 0;
                    $list[$key]["batesamount"] = "0.00+" . bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2);
                    if (bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2) == 0) {
                        $list[$key]["batesamount"] = "0.00";
                    }
                } else {
                    if ($val["updategrade"] == 0 && $val["type"] == "upgrade" || $val["updategrade"] == 0 && $val["typess"] == "upgrade" && $val["type"] == "discount") {
                        $list[$key]["bates"] = 0;
                        $list[$key]["batesamount"] = "0.00+" . bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2);
                        if (bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2) == 0) {
                            $list[$key]["batesamount"] = "0.00";
                        }
                    } else {
                        if ($val["is_renew"] == 1 && $val["type"] == "renew" || $val["is_renew"] == 1 && $val["typess"] == "renew" && $val["type"] == "discount") {
                            $list[$key]["batesamount"] = round(bcmul($val["amount"], $val["renew_bates"] / 100, 2), 2) . "+" . round(bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2), 2);
                            if (round(bcmul($val["amount"], $val["renew_bates"] / 100, 2), 2) == 0 && round(bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2), 2) == 0) {
                                $list[$key]["batesamount"] = "0.00";
                            }
                        } else {
                            if ($val["updategrade"] == 1 && $val["type"] == "upgrade" || $val["updategrade"] == 1 && $val["typess"] == "upgrade" && $val["type"] == "discount") {
                                $list[$key]["batesamount"] = round(bcmul($val["amount"], $val["upgrade_bates"] / 100, 2), 2) . "+" . round(bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2), 2);
                                if (round(bcmul($val["amount"], $val["upgrade_bates"] / 100, 2), 2) == 0 && round(bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2), 2) == 0) {
                                    $list[$key]["batesamount"] = "0.00";
                                }
                            } else {
                                $list[$key]["batesamount"] = round(bcmul($val["amount"], $val["bates"] / 100, 2), 2) . "+" . round(bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2), 2);
                                if (round(bcmul($val["amount"], $val["bates"] / 100, 2), 2) == 0 && round(bcmul($ladder["turnover"]["bates"] / 100, $val["amount"], 2), 2) == 0) {
                                    $list[$key]["batesamount"] = "0.00";
                                }
                            }
                        }
                    }
                }
            } else {
                if ($val["is_renew"] == 0 && $val["type"] == "renew" || $val["is_renew"] == 0 && $val["typess"] == "renew" && $val["type"] == "discount") {
                    $list[$key]["batesamount"] = "0.00";
                    $list[$key]["bates"] = 0;
                } else {
                    if ($val["updategrade"] == 0 && $val["type"] == "upgrade" || $val["updategrade"] == 0 && $val["typess"] == "upgrade" && $val["type"] == "discount") {
                        $list[$key]["batesamount"] = "0.00";
                        $list[$key]["bates"] = 0;
                    } else {
                        if ($val["is_renew"] == 1 && $val["type"] == "renew" || $val["is_renew"] == 1 && $val["typess"] == "renew" && $val["type"] == "discount") {
                            $list[$key]["batesamount"] = round(bcmul($val["amount"], $val["renew_bates"] / 100, 2), 2);
                        } else {
                            if ($val["updategrade"] == 1 && $val["type"] == "upgrade" || $val["updategrade"] == 1 && $val["typess"] == "upgrade" && $val["type"] == "discount") {
                                $list[$key]["batesamount"] = round(bcmul($val["amount"], $val["upgrade_bates"] / 100, 2), 2);
                            } else {
                                $list[$key]["batesamount"] = round(bcmul($val["amount"], $val["bates"] / 100, 2), 2);
                            }
                        }
                    }
                }
            }
            switch ($val["type"]) {
                case "renew":
                    $list[$key]["type"] = "续费";
                    break;
                case "discount":
                    $list[$key]["type"] = "客户折扣";
                    break;
                case "promo":
                    $list[$key]["type"] = "优惠码";
                    break;
                case "setup":
                    $list[$key]["type"] = "初装";
                    break;
                case "host":
                    $list[$key]["type"] = "新订购";
                    break;
                case "custom":
                    $list[$key]["type"] = "新流量包订购";
                    break;
                case "upgrade":
                    $list[$key]["type"] = "升级";
                    break;
                case "zjmf_flow_packet":
                    $list[$key]["type"] = "流量包订购";
                    break;
                case "zjmf_reinstall_times":
                    $list[$key]["type"] = "重装次数";
                    break;
            }
        }
        $list = array_values($list);
        foreach ($list as $key => $val) {
            if (!empty($arr[$val["invoice_id"]])) {
                if ($val["type"] == "新订购") {
                    $val["type"] = $val["name"];
                }
                $val["item_id"] = $val["invoice_id"] . "_" . rand(10000, 99999);
                $val["name"] = $val["type"];
                $c = explode("+", $val["batesamount"]);
                list($val["batesamount"], $val["batesamount1"]) = $c;
                $arr[$val["invoice_id"]]["child"][] = $val;
            } else {
                $val["item_id"] = $val["invoice_id"];
                if ($val["type"] == "初装") {
                    $val["type"] = "新订购 ";
                }
                $arr[$val["invoice_id"]] = $val;
                if ($val["type"] == "新订购 ") {
                    $val["type"] = "初装";
                }
                $val["item_id"] = $val["invoice_id"] . "_" . rand(10000, 99999);
                $c = explode("+", $val["batesamount"]);
                list($val["batesamount"], $val["batesamount1"]) = $c;
                $arr[$val["invoice_id"]]["child"][] = $val;
            }
        }
        $arr1 = [];
        foreach ($arr as $key => $val) {
            $arr1[] = $val;
        }
        foreach ($arr1 as $key => $val) {
            if ($val["type"] == "客户折扣") {
                foreach ($val["child"] as $k => $v) {
                    if ($v["type"] != "客户折扣") {
                        $arr1[$key]["type"] = $v["type"];
                    }
                }
            }
            $c3 = 0;
            $c1 = 0;
            $c2 = 0;
            foreach ($val["child"] as $key1 => $val1) {
                $c3 = bcadd($val1["amount"], $c3, 2);
                $c1 = bcadd($c1, $val1["batesamount"], 2);
                $c2 = bcadd($c2, $val1["batesamount1"], 2);
            }
            $arr1[$key]["amount"] = $c3;
            if (!empty($ladder["turnover"]["turnover"])) {
                if ($val["type"] != "续费" && $val["type"] != "升级") {
                    $arr1[$key]["batesamount"] = $c1 . "+" . $c2;
                }
            } else {
                if ($val["type"] != "续费" && $val["type"] != "升级") {
                    $arr1[$key]["batesamount"] = $c1;
                }
            }
        }
        foreach ($arr1 as $key => $val) {
            $refund = 0;
            $refund1 = 0;
            $refunds = 0;
            $bates = 0;
            foreach ($val["child"] as $keys => $vals) {
                if ($vals["type"] === "discount") {
                    $arr1[$key]["child"][$keys]["type"] = "客户折扣";
                }
                if ($vals["is_renew"] == 1 && $vals["type"] == "续费") {
                    if ($vals["renew_bates"] / 100 != 0) {
                        $bates = $vals["renew_bates"] / 100;
                    }
                } else {
                    if ($vals["updategrade"] == 1 && $vals["type"] == "升级") {
                        if ($vals["upgrade_bates"] / 100 != 0) {
                            $bates = $vals["upgrade_bates"] / 100;
                        }
                    } else {
                        if ($vals["bates"] / 100 != 0) {
                            $bates = $vals["bates"] / 100;
                        }
                    }
                }
            }
            $accounts = \think\Db::name("accounts")->field("id,amount_out")->where("invoice_id", $val["invoice_id"])->where("refund", ">", 0)->select()->toArray();
            if (!empty($accounts)) {
                foreach ($accounts as $val2) {
                    $refund = bcadd($refund, bcmul($bates, $val2["amount_out"], 4), 4);
                    $refunds = bcadd($refunds, $val2["amount_out"], 4);
                    if (!empty($ladder["turnover"]["turnover"])) {
                        $refund1 = bcadd($refund1, bcmul($ladder["turnover"]["bates"] / 100, $val2["amount_out"], 4), 4);
                    }
                }
                if (0 < bcadd($refund, $refund1, 4)) {
                    $count = explode("+", $val["batesamount"]);
                    if (0 < bcsub(bcadd($refund, $refund1, 4), bcadd($count[1], $count[0], 2), 2)) {
                        $arr1[$key]["refound"] = "-" . round($refunds, 2) . $val["suffix"] . ",提成-" . round($count[0], 2);
                        $arr1[$key]["batesamount"] = "0.00+0.00";
                    } else {
                        $arr1[$key]["refound"] = "-" . round($refunds, 2) . $val["suffix"] . ",提成-" . round($refund, 2);
                        if (empty($count[1])) {
                            $arr1[$key]["batesamount"] = round(bcsub($count[0], $refund, 2), 2);
                        } else {
                            $arr1[$key]["batesamount"] = round(bcsub($count[0], $refund, 2), 2) . "+" . round(bcsub($count[1], $refund1, 2), 2);
                        }
                    }
                }
            }
        }
        foreach ($arr1 as $kk => $vv) {
            $base = 0;
            foreach ($vv["child"] as $vvv) {
                $base = bcadd($base, $vvv["batesamount"], 2);
                $arr1[$kk]["batesamount"] = $base;
            }
        }
        $arrss = [["label" => "续费", "name" => "renew"], ["label" => "新订购", "name" => "host"], ["label" => "升级", "name" => "upgrade"], ["label" => "流量包订购", "name" => "zjmf_flow_packet"], ["label" => "重装次数", "name" => "zjmf_reinstall_times"]];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "total" => $total, "record" => $arr1, "type" => $arrss]);
    }
    public function saleRecordsNew()
    {
        error_reporting(0);
        $params = $data = $this->request->param();
        if (!isset($params["time"])) {
            $params["time"] = 1;
        }
        $record_list = $this->searchSaleRecordInfo("get_list", "", "", $params, false);
        $record_count = count($record_list);
        $record_list_stat = $this->searchSaleRecordInfo("get_list", "", "", $params, true);
        $this_month_sale = $this->get_this_month_sale($record_list_stat);
        $this_month_commission_total = $this_month_sale["this_month_commission_total"];
        $this_month_sale_total = $this_month_sale["this_month_sale_total"];
        $data = [];
        if ($record_list) {
            foreach ($record_list as $key => $item) {
                $currency_info = $this->getInvoicesCurrencyInfo($item);
                $item["suffix"] = $currency_info["suffix"];
                $item["prefix"] = $currency_info["prefix"];
                $temp["pay_time"] = empty($item["paid_time"]) ? "N/A" : date("Y-m-d H:i", $item["paid_time"]);
                $item = $this->getProductInoviceDetail($item, $this_month_sale_total);
                $temp["invoice_id"] = $item["invoice_id"];
                $temp["username"] = $item["username"];
                $temp["name"] = $item["name"];
                $temp["amount"] = $item["total"] ?? 0;
                $temp["refound"] = $item["refund"] ?? 0;
                $temp["batesamount"] = $item["commission_sum"] ?? 0;
                $temp["type"] = $item["type_string"] ?? "";
                $temp["child"] = [];
                if ($item["child_invoice"]) {
                    foreach ($item["child_invoice"] as &$child_item) {
                        $child_temp["type"] = $child_item["label"];
                        $child_temp["amount"] = $child_item["amount"] ?? 0;
                        $child_temp["batesamount"] = round($child_item["commision_amount"], 2) ?? 0;
                        $child_temp["suffix"] = $child_item["suffix"] ?? "元";
                        $temp["child"][] = $child_temp;
                    }
                }
                $data[] = $temp;
            }
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "record" => $data, "total" => $record_count, "this_month_commission_total" => $this_month_commission_total, "this_month_sale_total" => $this_month_sale_total, "type" => $this->getCommisionInvoice(), "last" => $this->getLastLadder($this_month_sale_total), "now_ladder" => $this->getLastLadder($this_month_sale_total, "now")]);
    }
    private function getLastLadder($this_month_sale_total = 0, $now_or_last = "last")
    {
        $ladder["turnover"] = 0;
        $ladder["bates"] = 0;
        $sale_ladder = \think\Db::name("sale_ladder")->order("turnover", $now_or_last == "last" ? "asc" : "desc")->select()->toArray();
        if ($sale_ladder) {
            foreach ($sale_ladder as $key => $item) {
                if ($now_or_last == "last") {
                    if ($this_month_sale_total < $item["turnover"]) {
                        $ladder["turnover"] = $item["turnover"];
                        $ladder["bates"] = $item["bates"];
                    }
                } else {
                    if ($now_or_last == "now" && $item["turnover"] <= $this_month_sale_total) {
                        $ladder["turnover"] = $item["turnover"];
                        $ladder["bates"] = $item["bates"];
                    }
                }
            }
        }
        $ladder["suffix"] = \think\Db::name("currencies")->where("default", 1)->value("suffix");
        return $ladder;
    }
    private function get_this_month_sale($record_list)
    {
        $this_month_commission_total = 0;
        $this_month_sale_total = 0;
        if ($record_list) {
            foreach ($record_list as $key => &$item) {
                $item = $this->getProductInoviceDetail($item);
                $this_month_sale_total = bcadd($this_month_sale_total, $item["sale_total"], 2);
            }
            foreach ($record_list as $key => &$item) {
                $item = $this->getProductInoviceDetail($item, $this_month_sale_total);
                $this_month_commission_total = bcadd($this_month_commission_total, $item["commission_sum_true_num"], 2);
            }
        }
        return ["this_month_commission_total" => $this_month_commission_total, "this_month_sale_total" => $this_month_sale_total];
    }
    private function getInvoicesCurrencyInfo($item)
    {
        $info = \think\Db::name("accounts")->alias("a")->leftJoin("currencies cu", "cu.code = a.currency")->where("a.invoice_id", $item["invoice_id"])->field("suffix,prefix")->find();
        return $info;
    }
    private function getProductInoviceDetail($item, $this_month_sale_total = 0)
    {
        if (empty($item["invoice_id"]) || empty($item["productid"])) {
            return [];
        }
        $item["child_invoice"] = [];
        $item["refund"] = "";
        $item["refund_sum"] = 0;
        $item["commission_sum"] = 0;
        $allow_invoice = $this->getCommisionInvoice();
        $allow_invoice_types = array_column($allow_invoice, "name");
        $allow_invoice_types_label = array_column($allow_invoice, "label", "name");
        $item["type_string"] = $allow_invoice_types_label[$item["type"]];
        if ($item["type"] === "product") {
            $item["type_string"] = "新订购";
        }
        $item["name"] = $this->packageNewHostLabel($item, $item);
        $item["username"] = $this->packageClientLabel($item);
        if ($item["type"] != "upgrade") {
            $invoice_item_list = \think\Db::name("invoice_items")->alias("im")->join("host h", "h.id = im.rel_id")->join("products p", "p.id = h.productid")->leftJoin("accounts ac", "ac.invoice_id = im.invoice_id")->leftJoin("currencies cu", "ac.currency = cu.code")->field("h.id as host_id,h.domain")->field("p.id as productid,p.name as product_name")->field("im.invoice_id,im.type,im.amount")->field("cu.prefix,cu.suffix")->group("im.id")->where("im.invoice_id", $item["invoice_id"])->where("im.delete_time", 0)->select()->toArray();
        } else {
            if ($item["type"] == "upgrade") {
                $invoice_item_list = \think\Db::name("invoice_items")->alias("im")->join("upgrades ug", "im.rel_id = ug.id")->join("host h", "h.id = ug.relid")->join("products p", "p.id = h.productid")->leftJoin("accounts ac", "ac.invoice_id = im.invoice_id")->leftJoin("currencies cu", "ac.currency = cu.code")->field("h.id as host_id,h.domain")->field("p.id as productid,p.name as product_name")->field("im.invoice_id,im.type,im.amount")->field("cu.prefix,cu.suffix")->group("im.id")->where("im.invoice_id", $item["invoice_id"])->where("im.delete_time", 0)->select()->toArray();
            }
        }
        $last_suffix = "";
        if ($invoice_item_list) {
            foreach ($invoice_item_list as $key => $child_item) {
                $sales_product_groups = $this->getCommissionSet($item, $child_item["productid"]);
                $child_invoice["label"] = $this->packageCommissionLabel($item, $child_item);
                $child_invoice["amount"] = $child_item["amount"];
                $child_invoice["commision_amount"] = $this->calculateCommisionAmount($child_item, $sales_product_groups);
                $child_invoice["commision_amount_str"] = round($child_invoice["commision_amount"], 2) . $item["suffix"];
                $item["commission_sum"] = bcadd($item["commission_sum"], $child_invoice["commision_amount"], 4);
                $child_invoice["suffix"] = $child_item["suffix"];
                $child_invoice["prefix"] = $child_item["prefix"];
                $last_suffix = $child_item["suffix"];
                $item["child_invoice"][] = $child_invoice;
            }
        }
        $item["commission_sum"] = round($item["commission_sum"], 2);
        $product_refund_bates = $this->getProductRefundBates($item["productid"], $item["type"], $item);
        $refund_info = \think\Db::name("accounts")->where("invoice_id", $item["invoice_id"])->where("refund", ">", 0)->find();
        $item["refund_sum"] = 0;
        if ($refund_info) {
            $item["refund_sum"] = bcadd($refund_info["amount_out"], $item["refund_sum"], 2);
            $refund_commission = bcmul($item["refund_sum"] / 100, $product_refund_bates, 2);
            $item["refund"] = "-" . $item["refund_sum"] . $last_suffix . "，" . "提成-" . $refund_commission . $last_suffix;
            $item["commission_sum"] = bcsub($item["commission_sum"], $refund_commission, 2);
            if ($item["commission_sum"] < 0) {
                $item["commission_sum"] = 0;
            }
        }
        $item["sale_total"] = round($item["total"] - $item["refund_sum"], 2);
        $item["commission_sum_true_num"] = $item["commission_sum"];
        $ladder_commission = $this->getLadderCommission($item["total"], $item["refund_sum"], $this_month_sale_total);
        if (0 < $ladder_commission) {
            $item["commission_sum"] .= "+" . $ladder_commission;
            $item["commission_sum_true_num"] = bcadd($ladder_commission, $item["commission_sum_true_num"], 2);
        }
        return $item;
    }
    private function getLadderCommission($item_amount = 0, $refund_sum = 0, $this_month_sale_total = 0)
    {
        $ladder_commission = 0;
        $true_sale_amount = $item_amount - $refund_sum;
        $sale_ladder = \think\Db::name("sale_ladder")->order("turnover", "desc")->select()->toArray();
        if ($sale_ladder) {
            foreach ($sale_ladder as $item) {
                if ($item["turnover"] <= $this_month_sale_total) {
                    $ladder_commission = bcmul($true_sale_amount, $item["bates"] / 100, 2);
                }
            }
        }
        return $ladder_commission;
    }
    private function getProductRefundBates($productid = "", $product_type = "", $item)
    {
        if (empty($productid) || empty($product_type)) {
            return 0;
        }
        $refund_bates = 0;
        $bates_info = $this->getCommissionSet($item, $productid);
        switch ($product_type) {
            case "product":
                $refund_bates = $bates_info["bates"];
                break;
            case "renew":
                $refund_bates = $bates_info["is_renew"] ? $bates_info["renew_bates"] : 0;
                break;
            case "upgrade":
                $refund_bates = $bates_info["updategrade"] ? $bates_info["upgrade_bates"] : 0;
                break;
            case "zjmf_flow_packet":
                $refund_bates = $bates_info["zjmf_flow_packet_bates"];
                break;
            case "zjmf_reinstall_times":
                $refund_bates = $bates_info["zjmf_reinstall_times_bates"];
                break;
            case "setup":
                $refund_bates = $bates_info["setup_bates"];
                break;
            case "discount":
                $refund_bates = $bates_info["discount_bates"];
                break;
            case "promo":
                $refund_bates = $bates_info["promo_bates"];
                break;
            default:
                return $refund_bates;
        }
    }
    private function packageCommissionLabel($item, $child_item)
    {
        $label = "";
        switch ($child_item["type"]) {
            case "renew":
                $label = "续费";
                break;
            case "discount":
                $label = "客户折扣";
                break;
            case "host":
                $label = $this->packageNewHostLabel($item, $child_item);
                break;
            case "promo":
                $label = "优惠码";
                break;
            case "recharge":
                $label = "充值";
                break;
            case "setup":
                $label = "初装";
                break;
            case "upgrade":
                $label = "升级";
                break;
            case "zjmf_flow_packet":
                $label = "流量包";
                break;
            case "zjmf_reinstall_times":
                $label = "重装次数";
                break;
            default:
                return $label;
        }
    }
    private function packageClientLabel($item)
    {
        $client_username_str = $item["client_username"] . "(" . $item["companyname"] . ")";
        $label = "<a class=\"el-link el-link--primary is-underline\" \n            href=\"#/customer-view/abstract?id=" . $item["client_id"] . "\">\n            <span class=\"el-link--inner\" style=\"display: block;height: 24px;line-height: 24px;\">" . $client_username_str . "</span></a>";
        return $label;
    }
    private function packageNewHostLabel($item, $child_item)
    {
        $host_name_str = $child_item["product_name"] . "(" . $child_item["domain"] . ")";
        $label = "<a class=\"el-link el-link--primary is-underline\" \n                href=\"#/customer-view/product-innerpage?hid=" . $child_item["host_id"] . "&id=" . $item["client_id"] . "\">\n                <span class=\"el-link--inner\" style=\"display: block;height: 24px;line-height: 24px;\">" . $host_name_str . "</span></a>";
        return $label;
    }
    private function calculateCommisionAmount($child_item, $sales_product_groups)
    {
        if (empty($child_item) || empty($sales_product_groups)) {
            return 0;
        }
        if ($child_item["type"] === "host" || $child_item["type"] === "product") {
            $bates_key = "bates";
        } else {
            $bates_key = $child_item["type"] . "_bates";
        }
        $sales_product_groups_bates = $sales_product_groups[$bates_key] ?? 0;
        $commision_amount = bcmul($child_item["amount"], $sales_product_groups_bates / 100, 4);
        return $commision_amount;
    }
    private function getCommissionSet($item, $child_invoice_productid = 0)
    {
        $where = "find_in_set('" . $child_invoice_productid . "',pids)";
        $sales_product_groups = \think\Db::name("sales_product_groups")->where($where)->find();
        if ($sales_product_groups) {
            if ($sales_product_groups["is_renew"] == 0) {
                $sales_product_groups["renew_bates"] = 0;
            }
            if ($sales_product_groups["updategrade"] == 0) {
                $sales_product_groups["upgrade_bates"] = 0;
            }
            $sales_product_groups["zjmf_flow_packet_bates"] = $sales_product_groups["bates"];
            $sales_product_groups["zjmf_reinstall_times_bates"] = $sales_product_groups["bates"];
            $sales_product_groups["setup_bates"] = $sales_product_groups["bates"];
            $sales_product_groups["discount_bates"] = $this->getDiscountProductBates($item, $sales_product_groups);
            $sales_product_groups["promo_bates"] = $this->getPromoProductBates($item, $sales_product_groups);
        }
        return $sales_product_groups;
    }
    private function getDiscountProductBates($item, $sales_product_groups)
    {
        if ($item["type"] === "host" || $item["type"] === "product") {
            $bates_key = "bates";
        } else {
            $bates_key = $item["type"] . "_bates";
        }
        return $sales_product_groups[$bates_key];
    }
    private function getPromoProductBates($item, $sales_product_groups)
    {
        if ($item["type"] === "host" || $item["type"] === "product") {
            $bates_key = "bates";
        } else {
            $bates_key = $item["type"] . "_bates";
        }
        return $sales_product_groups[$bates_key];
    }
    private function searchSaleRecordInfo($search_type = "get_list", $start_time = "", $end_time = "", $params = [], $statistics = false)
    {
        $name = !empty($params["name"]) ? trim($params["name"]) : "";
        $pname = !empty($params["pname"]) ? trim($params["pname"]) : "";
        $type = !empty($params["type"]) ? trim($params["type"]) : "";
        $id = !empty($params["id"]) ? intval($params["id"]) : session("ADMIN_ID");
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "i.id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "desc";
        $start_time = $params["start_time"];
        $where = [];
        if (!empty($params["time"])) {
            if ($params["time"] == 1) {
                $where[] = ["i.paid_time", ">=", strtotime(date("Y-m-01", time()))];
                $where[] = ["i.paid_time", "<", strtotime(date("Y-m", time()) . "+1 month")];
            } else {
                if ($params["time"] == 2) {
                    $where[] = ["i.paid_time", ">=", strtotime(date("Y-m-d", strtotime("-0 year -3 month -0 day")))];
                    $where[] = ["i.paid_time", "<", time()];
                } else {
                    if ($params["time"] == 4) {
                        if ($start_time[0]) {
                            $where[] = ["i.paid_time", "egt", $start_time[0]];
                        }
                        if ($start_time[1]) {
                            $where[] = ["i.paid_time", "elt", $start_time[1]];
                        }
                    }
                }
            }
        }
        $allow_invoice = $this->getCommisionInvoice();
        unset($allow_invoice[2]);
        $allow_invoice_types = array_column($allow_invoice, "name");
        $search_obj = \think\Db::name("invoices")->alias("i")->join("invoice_items im", "im.invoice_id = i.id")->join("host h", "h.id = im.rel_id")->join("products p", "p.id = h.productid")->join("clients c", "c.id = i.uid")->field("i.id as invoice_id,i.subtotal as total,i.type,i.paid_time")->field("p.id as productid,p.name as product_name")->field("h.id as host_id,h.domain")->field("c.id as client_id,c.username  as  client_username,c.companyname")->group("invoice_id")->where($where)->where("i.status", "Paid")->where("i.delete_time", 0)->where("c.sale_id", $id)->where("im.type", "in", $allow_invoice_types);
        if ($name) {
            $info = $search_obj->where("c.username", "like", "%" . $name . "%");
        }
        if ($type) {
            $info = $search_obj->where("im.type", $type);
        }
        if ($pname) {
            $info = $search_obj->where("p.name", "like", "%" . $pname . "%");
        }
        if ($search_type === "get_list") {
            if ($statistics) {
                $info = $search_obj->order($order, $sort)->order("im.id", "asc")->select()->toArray();
            } else {
                $info = $search_obj->order($order, $sort)->order("im.id", "asc")->page($page)->limit($limit)->select()->toArray();
            }
            if ($page == 1) {
                $invoice_upgrade_lists = \think\Db::name("invoices")->alias("i")->join("invoice_items im", "im.invoice_id = i.id")->join("upgrades ug", "ug.id = im.rel_id")->join("host h", "ug.relid = h.id")->join("products p", "h.productid = p.id")->join("clients c", "c.id = i.uid")->join("user u", "u.id = c.sale_id")->where("i.status", "Paid")->where("i.delete_time", 0)->where("c.sale_id", $id)->where("i.type", "upgrade")->where($where)->field("i.id as invoice_id,i.subtotal as total,i.type,i.paid_time")->field("p.id as productid,p.name as product_name")->field("h.id as host_id,h.domain")->field("c.id as client_id,c.username  as  client_username,c.companyname")->group("invoice_id")->order("invoice_id", "desc")->select()->toArray();
                $info = array_merge_recursive($info, $invoice_upgrade_lists);
            }
        } else {
            if ($search_type === "get_count") {
                $info = $search_obj->count();
            }
        }
        return $info;
    }
    private function getCommisionInvoice()
    {
        return [["label" => "续费", "name" => "renew"], ["label" => "新订购", "name" => "host"], ["label" => "升级", "name" => "upgrade"], ["label" => "流量包订购", "name" => "zjmf_flow_packet"], ["label" => "重装次数", "name" => "zjmf_reinstall_times"]];
    }
    public function saleUsers()
    {
        $params = $data = $this->request->param();
        $list = \think\Db::name("user")->field("id,user_nickname")->where("is_sale", 1)->select()->toArray();
        $sessionAdminId = session("ADMIN_ID");
        $user = \think\Db::name("user")->field("is_sale,sale_is_use,only_mine,all_sale")->where("id", $sessionAdminId)->find();
        if ($user["is_sale"] == 1) {
            if ($user["all_sale"] == 1) {
                $list = $list;
            } else {
                $list = [];
            }
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "all_sale" => $user["all_sale"], "list" => $list]);
    }
    public function adminList()
    {
        $page = input("get.page", 1, "intval");
        $search = input("get.search", "");
        $limit = input("get.limit", 10, "intval");
        $page = 1 <= $page ? $page : config("page");
        $limit = 1 <= $limit ? $limit : config("limit");
        $params = $this->request->param();
        $order = isset($params["order"][0]) ? trim($params["order"]) : "a.id";
        $sort = isset($params["sort"][0]) ? trim($params["sort"]) : "DESC";
        $count = \think\Db::name("user")->where("user_nickname LIKE '%" . $search . "%' OR user_login LIKE '%" . $search . "%'")->count();
        $data = \think\Db::name("user")->alias("a")->leftJoin("role_user b", "b.user_id = a.id")->leftJoin("role c", "c.id = b.role_id")->field("a.cat_ownerless")->field("a.id,a.user_login,a.user_nickname,a.user_email,a.create_time,a.user_status,a.last_login_time,a.last_login_ip,c.name as role,a.is_sale,a.sale_is_use,a.only_mine,a.all_sale,a.only_oneself_notice")->where("user_nickname LIKE '%" . $search . "%' OR user_login LIKE '%" . $search . "%'")->page($page)->limit($limit)->order($order, $sort)->select()->toArray();
        foreach ($data as &$val) {
            $val["cat_ownerless"] = $val["is_sale"] ? $val["cat_ownerless"] : 0;
        }
        $res["status"] = 200;
        $res["msg"] = lang("SUCCESS MESSAGE");
        $res["count"] = $count;
        $res["list"] = $data;
        return jsonrule($res);
    }
    public function editAdminList()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $data["is_sale"] = isset($param["is_sale"]) ? floatval($param["is_sale"]) : 0;
            $data["sale_is_use"] = isset($param["sale_is_use"]) ? floatval($param["sale_is_use"]) : 0;
            $data["only_mine"] = isset($param["only_mine"]) ? intval($param["only_mine"]) : 0;
            $data["all_sale"] = isset($param["all_sale"]) ? intval($param["all_sale"]) : 0;
            $data["only_oneself_notice"] = isset($param["only_oneself_notice"]) ? intval($param["only_oneself_notice"]) : 0;
            $data["cat_ownerless"] = isset($param["cat_ownerless"]) ? intval($param["cat_ownerless"]) : 0;
            $desc = "";
            $spg = \think\Db::name("user")->where("id", $param["id"])->find();
            if ($spg["is_sale"] != $param["is_sale"]) {
                if ($spg["is_sale"] == 1) {
                    $desc .= " 是销售";
                } else {
                    $param["cat_ownerless"] = 1;
                    $data["cat_ownerless"] = $param["cat_ownerless"];
                    $desc .= " 不是销售";
                }
            }
            if ($spg["sale_is_use"] != $param["sale_is_use"]) {
                if ($spg["sale_is_use"] == 1) {
                    $desc .= "销售启用";
                } else {
                    $desc .= " 销售不启用";
                }
            }
            if ($spg["only_mine"] != $param["only_mine"]) {
                if ($spg["only_mine"] == 1) {
                    $desc .= " 只能查看自己的客户";
                } else {
                    $desc .= " 可以不查看自己的客户";
                }
            }
            if ($spg["all_sale"] != $param["all_sale"]) {
                if ($spg["all_sale"] == 1) {
                    $desc .= " 只能查看自己的客户";
                } else {
                    $desc .= " 可以不查看自己的客户";
                }
            }
            if ($spg["cat_ownerless"] != $param["cat_ownerless"]) {
                if (!getEdition() && $param["cat_ownerless"] == 0) {
                    return jsonrule(["status" => 400, "msg" => "请购买专业版本"]);
                }
                if ($spg["cat_ownerless"] == 1) {
                    $desc .= " 可以查看未分配的客户";
                } else {
                    $desc .= " 不可以查看未分配的客户";
                }
            }
            if ($spg["only_oneself_notice"] != $param["only_oneself_notice"]) {
                if ($spg["only_oneself_notice"] == 1) {
                    $desc .= " 仅自己客户的工单提醒邮件";
                } else {
                    $desc .= " 仅自己客户的工单提醒邮件";
                }
            }
            \think\Db::name("user")->where("id", $param["id"])->update($data);
            active_log(sprintf($this->lang["Aff_admin_editAdminList"], $param["id"], $desc));
            return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function getSaleEnble()
    {
        $returndata = [];
        $config_files = ["sale_setting"];
        $config_files1 = ["sale_reg_setting"];
        $config_files2 = ["sale_auto_setting"];
        $config_files3 = ["only_oneself_notice"];
        $config_data = \think\Db::name("configuration")->whereIn("setting", $config_files)->select()->toArray();
        $config_data1 = \think\Db::name("configuration")->whereIn("setting", $config_files1)->select()->toArray();
        $config_data2 = \think\Db::name("configuration")->whereIn("setting", $config_files2)->select()->toArray();
        $config_data3 = \think\Db::name("configuration")->whereIn("setting", $config_files3)->select()->toArray();
        if (empty($config_data) && empty($config_data1)) {
            $config_value["sale_setting"] = 0;
            $config_value["sale_reg_setting"] = 0;
            $config_value["sale_auto_setting"] = 0;
            $config_value["only_oneself_notice"] = 0;
        } else {
            $config_value = [];
            foreach ($config_data as $key => $val) {
                $config_value[$val["setting"]] = $val["value"];
            }
            foreach ($config_data1 as $key => $val) {
                $config_value[$val["setting"]] = $val["value"];
            }
            foreach ($config_data2 as $key => $val) {
                $config_value[$val["setting"]] = $val["value"];
            }
            foreach ($config_data3 as $key => $val) {
                $config_value[$val["setting"]] = $val["value"];
            }
        }
        $returndata["config_value"] = $config_value;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function saleEnblePost()
    {
        if ($this->request->isPost()) {
            $param = $this->request->param();
            $trim = array_map("trim", $param);
            $param = array_map("htmlspecialchars", $trim);
            if ($param["sale_setting"] != NULL) {
                updateConfiguration("sale_setting", $param["sale_setting"]);
            }
            if ($param["sale_reg_setting"] != NULL) {
                updateConfiguration("sale_reg_setting", $param["sale_reg_setting"]);
            }
            if ($param["sale_auto_setting"] != NULL) {
                updateConfiguration("sale_auto_setting", $param["sale_auto_setting"]);
            }
            if ($param["only_oneself_notice"] != NULL) {
                updateConfiguration("only_oneself_notice", $param["only_oneself_notice"]);
            }
            return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return jsonrule(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
}

?>