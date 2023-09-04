<?php


namespace app\admin\controller;

/**
 * @title 发票管理
 * @group 发票管理
 */
class VoucherController extends GetUserController
{
    private $type = ["person" => "个人", "company" => "公司"];
    private $voucher_type = ["common" => "增值税普通发票", "dedicated" => "增值税专用发票"];
    public function getRate()
    {
        $data = ["voucher_manager" => configuration("voucher_manager") ?: 0, "rate" => configuration("voucher_rate") ?? ""];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postRate()
    {
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            $app = [];
        } else {
            $_strcode = _strcode($zjmf_authorize, "DECODE", "zjmf_key_strcode");
            $_strcode = explode("|zjmf|", $_strcode);
            $authkey = "-----BEGIN PUBLIC KEY-----\r\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDg6DKmQVwkQCzKcFYb0BBW7N2f\r\nI7DqL4MaiT6vibgEzH3EUFuBCRg3cXqCplJlk13PPbKMWMYsrc5cz7+k08kgTpD4\r\ntevlKOMNhYeXNk5ftZ0b6MAR0u5tiyEiATAjRwTpVmhOHOOh32MMBkf+NNWrZA/n\r\nzcLRV8GU7+LcJ8AH/QIDAQAB\r\n-----END PUBLIC KEY-----";
            $pu_key = openssl_pkey_get_public($authkey);
            foreach ($_strcode as $v) {
                openssl_public_decrypt(base64_decode($v), $de, $pu_key);
                $de_str .= $de;
            }
            $auth = json_decode($de_str, true);
            if ($auth["last_license_time"] + 1296000 < time() || ltrim(str_replace("https://", "", str_replace("http://", "", $auth["domain"])), "www.") != ltrim(str_replace("https://", "", str_replace("http://", "", $_SERVER["HTTP_HOST"])), "www.") || $auth["installation_path"] != CMF_ROOT || $auth["license"] != configuration("system_license")) {
                $app = [];
            } else {
                $app = $auth["app"];
            }
        }
        if (!in_array("InvoiceContract", $app)) {
            return jsonrule(["status" => 400, "msg" => "免费版该功能不可用"]);
        }
        $param = $this->request->param();
        $voucher_manager = intval($param["voucher_manager"]);
        $rate = 0 < $param["rate"] ? floatval($param["rate"]) : 0;
        updateConfiguration("voucher_manager", $voucher_manager);
        updateConfiguration("voucher_rate", $rate);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function getExpressList()
    {
        $express = \think\Db::name("express")->field("id,name,price,create_time")->select()->toArray();
        $total = \think\Db::name("express")->count();
        $data = ["total" => $total, "express" => $express];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getExpress()
    {
        $param = $this->request->param();
        if (isset($param["id"])) {
            $id = intval($param["id"]);
            $tmp = \think\Db::name("express")->field("name,price")->where("id", $id)->find();
            if (empty($tmp)) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
        }
        $data = ["express" => $tmp ?: []];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postExpress()
    {
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            $app = [];
        } else {
            $_strcode = _strcode($zjmf_authorize, "DECODE", "zjmf_key_strcode");
            $_strcode = explode("|zjmf|", $_strcode);
            $authkey = "-----BEGIN PUBLIC KEY-----\r\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDg6DKmQVwkQCzKcFYb0BBW7N2f\r\nI7DqL4MaiT6vibgEzH3EUFuBCRg3cXqCplJlk13PPbKMWMYsrc5cz7+k08kgTpD4\r\ntevlKOMNhYeXNk5ftZ0b6MAR0u5tiyEiATAjRwTpVmhOHOOh32MMBkf+NNWrZA/n\r\nzcLRV8GU7+LcJ8AH/QIDAQAB\r\n-----END PUBLIC KEY-----";
            $pu_key = openssl_pkey_get_public($authkey);
            foreach ($_strcode as $v) {
                openssl_public_decrypt(base64_decode($v), $de, $pu_key);
                $de_str .= $de;
            }
            $auth = json_decode($de_str, true);
            if ($auth["last_license_time"] + 1296000 < time() || ltrim(str_replace("https://", "", str_replace("http://", "", $auth["domain"])), "www.") != ltrim(str_replace("https://", "", str_replace("http://", "", $_SERVER["HTTP_HOST"])), "www.") || $auth["installation_path"] != CMF_ROOT || $auth["license"] != configuration("system_license")) {
                $app = [];
            } else {
                $app = $auth["app"];
            }
        }
        if (!in_array("InvoiceContract", $app)) {
            return jsonrule(["status" => 400, "msg" => "免费版该功能不可用"]);
        }
        $param = $this->request->param();
        if (50 < strlen($param["name"])) {
            return jsonrule(["status" => 400, "msg" => "名称不超过50个字符"]);
        }
        if ($param["price"] < 0) {
            return jsonrule(["status" => 400, "msg" => "价格大于0"]);
        }
        $data = ["name" => trim($param["name"]), "price" => floatval($param["price"])];
        if (isset($param["id"])) {
            $id = intval($param["id"]);
            $express = \think\Db::name("express")->field("name,price")->where("id", $id)->find();
            if (empty($express)) {
                return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            $data["update_time"] = time();
            \think\Db::name("express")->where("id", $id)->update($data);
        } else {
            $data["create_time"] = time();
            \think\Db::name("express")->insertGetId($data);
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function deleteExpress()
    {
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            $app = [];
        } else {
            $_strcode = _strcode($zjmf_authorize, "DECODE", "zjmf_key_strcode");
            $_strcode = explode("|zjmf|", $_strcode);
            $authkey = "-----BEGIN PUBLIC KEY-----\r\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDg6DKmQVwkQCzKcFYb0BBW7N2f\r\nI7DqL4MaiT6vibgEzH3EUFuBCRg3cXqCplJlk13PPbKMWMYsrc5cz7+k08kgTpD4\r\ntevlKOMNhYeXNk5ftZ0b6MAR0u5tiyEiATAjRwTpVmhOHOOh32MMBkf+NNWrZA/n\r\nzcLRV8GU7+LcJ8AH/QIDAQAB\r\n-----END PUBLIC KEY-----";
            $pu_key = openssl_pkey_get_public($authkey);
            foreach ($_strcode as $v) {
                openssl_public_decrypt(base64_decode($v), $de, $pu_key);
                $de_str .= $de;
            }
            $auth = json_decode($de_str, true);
            if ($auth["last_license_time"] + 1296000 < time() || ltrim(str_replace("https://", "", str_replace("http://", "", $auth["domain"])), "www.") != ltrim(str_replace("https://", "", str_replace("http://", "", $_SERVER["HTTP_HOST"])), "www.") || $auth["installation_path"] != CMF_ROOT || $auth["license"] != configuration("system_license")) {
                $app = [];
            } else {
                $app = $auth["app"];
            }
        }
        if (!in_array("InvoiceContract", $app)) {
            return jsonrule(["status" => 400, "msg" => "免费版该功能不可用"]);
        }
        $param = $this->request->param();
        $id = intval($param["id"]);
        $tmp = \think\Db::name("express")->where("id", $id)->find();
        if (empty($tmp)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $count = \think\Db::name("voucher")->where("express_id", $id)->count();
        if (0 < $count) {
            return jsonrule(["status" => 400, "msg" => lang("DELETE FAIL")]);
        }
        \think\Db::name("express")->where("id", $id)->delete();
        return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function getVoucherList()
    {
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $status = isset($params["status"]) ? $params["status"] : "";
        $voucher_status = config("voucher_status");
        $where = function (\think\db\Query $query) {
            if (!empty($status)) {
                $query->where("a.status", $status);
            }
        };
        $voucher = \think\Db::name("voucher")->alias("a")->field("a.id,e.username,a.create_time,b.title,b.issue_type,b.issue_type as issue_type_zh,a.amount,a.status,a.status as status_zh,c.province,c.city,c.region,c.detail,d.name,a.notes,a.check_time,f.prefix,f.suffix")->leftJoin("voucher_type b", "a.type_id = b.id")->leftJoin("voucher_post c", "a.post_id = c.id")->leftJoin("express d", "a.express_id = d.id")->leftJoin("clients e", "a.uid = e.id")->leftJoin("currencies f", "f.id = e.currency")->where($where)->withAttr("issue_type_zh", function ($value, $data) {
            return $this->type[$value];
        })->withAttr("status_zh", function ($value, $data) {
            return $voucher_status[$value];
        });
        if ($this->user["id"] != 1 && $this->user["is_sale"]) {
            $voucher->whereIn("e.id", $this->str);
        }
        $voucher = $voucher->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        $total = \think\Db::name("voucher")->alias("a")->leftJoin("voucher_type b", "a.type_id = b.id")->leftJoin("voucher_post c", "a.post_id = c.id")->leftJoin("express d", "a.express_id = d.id")->leftJoin("clients e", "a.uid = e.id")->leftJoin("currencies f", "f.id = e.currency")->where($where);
        if ($this->user["id"] != 1 && $this->user["is_sale"]) {
            $total->whereIn("e.id", $this->str);
        }
        $total = $total->count();
        $taxed = 0 < configuration("voucher_rate") ? floatval(configuration("voucher_rate")) : 0;
        foreach ($voucher as $k => &$v) {
            $invoice_ids = \think\Db::name("voucher_invoices")->where("voucher_id", $v["id"])->column("invoice_id");
            $invoice_ids = array_unique($invoice_ids);
            $subtotal = \think\Db::name("invoices")->field("id,tax as taxed,subtotal,subtotal as taxed_amount")->whereIn("id", $invoice_ids)->where("delete_time", 0)->sum("subtotal");
            $v["voucher_amount"] = $v["amount"];
            $v["amount"] = bcadd($subtotal, 0, 2);
        }
        $data = ["voucher" => $voucher, "total" => $total];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getVoucherDetail()
    {
        $param = $this->request->param();
        $id = intval($param["id"]);
        $tmp = \think\Db::name("voucher")->where("id", $id)->find();
        if (empty($tmp)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $id = intval($param["id"]);
        $type = config("invoice_type");
        unset($type["recharge"]);
        unset($type["combine"]);
        unset($type["voucher"]);
        unset($type["express"]);
        $type = array_keys($type);
        $taxed = 0 < configuration("voucher_rate") ? floatval(configuration("voucher_rate")) : 0;
        $voucher_status = config("voucher_status");
        $voucher = \think\Db::name("voucher")->alias("a")->field("a.id,a.invoice_id,a.create_time,b.title,b.issue_type,b.issue_type as issue_type_zh,\r\n            e.subtotal as amount,a.status,a.status as status_zh,c.province,c.city,c.region,c.detail,d.name,\r\n            a.notes,d.price,g.prefix,g.suffix,b.bank,b.account,b.address,b.tax_id,b.phone,b.voucher_type,b.voucher_type as voucher_type_zh")->leftJoin("voucher_type b", "a.type_id = b.id")->leftJoin("voucher_post c", "a.post_id = c.id")->leftJoin("express d", "a.express_id = d.id")->leftJoin("invoices e", "a.invoice_id = e.id")->leftJoin("clients f", "a.uid = f.id")->leftJoin("currencies g", "g.id = f.currency")->withAttr("issue_type_zh", function ($value, $data) {
            return $this->type[$value];
        })->withAttr("status_zh", function ($value, $data) {
            return $voucher_status[$value];
        })->withAttr("voucher_type_zh", function ($value) {
            $voucher_type = ["common" => "增值税普通发票", "dedicated" => "增值税专用发票"];
            return $voucher_type[$value];
        })->where("a.id", $id)->find();
        $invoice_ids = \think\Db::name("voucher_invoices")->where("voucher_id", $id)->column("invoice_id");
        $invoice_ids = array_unique($invoice_ids);
        $invoices = \think\Db::name("invoices")->field("id,tax as taxed,subtotal,subtotal as taxed_amount")->withAttr("taxed", function ($value, $data) use($taxed) {
            return $taxed . "%";
        })->withAttr("taxed_amount", function ($value, $data) use($taxed) {
            return bcmul($data["subtotal"], $taxed / 100, 2);
        })->whereIn("id", $invoice_ids)->where("delete_time", 0)->select()->toArray();
        $voucher_amount = 0;
        foreach ($invoices as &$invoice) {
            $voucher_amount += $invoice["taxed_amount"];
            $items = \think\Db::name("invoice_items")->field("id,description")->where("invoice_id", $invoice["id"])->whereIn("type", $type)->withAttr("description", function ($value) {
                return str_replace("|", " ", $value);
            })->select()->toArray();
            $invoice["items"] = $items;
        }
        $invoice_id = $voucher["invoice_id"];
        $status = \think\Db::name("invoices")->where("id", $invoice_id)->value("status");
        $data = ["voucher" => $voucher, "invoices" => $invoices, "voucher_amount" => $voucher_amount, "invoice_id" => $invoice_id, "uid" => $tmp["uid"], "status" => $status, "status_zh" => config("invoice_payment_status")[$status]];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postVoucherStatus()
    {
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            $app = [];
        } else {
            $_strcode = _strcode($zjmf_authorize, "DECODE", "zjmf_key_strcode");
            $_strcode = explode("|zjmf|", $_strcode);
            $authkey = "-----BEGIN PUBLIC KEY-----\r\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDg6DKmQVwkQCzKcFYb0BBW7N2f\r\nI7DqL4MaiT6vibgEzH3EUFuBCRg3cXqCplJlk13PPbKMWMYsrc5cz7+k08kgTpD4\r\ntevlKOMNhYeXNk5ftZ0b6MAR0u5tiyEiATAjRwTpVmhOHOOh32MMBkf+NNWrZA/n\r\nzcLRV8GU7+LcJ8AH/QIDAQAB\r\n-----END PUBLIC KEY-----";
            $pu_key = openssl_pkey_get_public($authkey);
            foreach ($_strcode as $v) {
                openssl_public_decrypt(base64_decode($v), $de, $pu_key);
                $de_str .= $de;
            }
            $auth = json_decode($de_str, true);
            if ($auth["last_license_time"] + 1296000 < time() || ltrim(str_replace("https://", "", str_replace("http://", "", $auth["domain"])), "www.") != ltrim(str_replace("https://", "", str_replace("http://", "", $_SERVER["HTTP_HOST"])), "www.") || $auth["installation_path"] != CMF_ROOT || $auth["license"] != configuration("system_license")) {
                $app = [];
            } else {
                $app = $auth["app"];
            }
        }
        if (!in_array("InvoiceContract", $app)) {
            return jsonrule(["status" => 400, "msg" => "免费版该功能不可用"]);
        }
        $param = $this->request->param();
        $id = intval($param["id"]);
        $tmp = \think\Db::name("voucher")->where("id", $id)->find();
        if (empty($tmp)) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        if ($tmp["status"] != "Pending" && $tmp["status"] != "Unpaid") {
            return jsonrule(["status" => 400, "msg" => lang("仅待审核和待支付状态可进行修改")]);
        }
        $status = trim($param["status"]);
        if (!in_array($status, ["Reject", "Send"])) {
            return jsonrule(["status" => 400, "msg" => lang("参数错误")]);
        }
        if (500 < strlen($param["notes"])) {
            return jsonrule(["status" => 400, "msg" => lang("备注不超过500个字符")]);
        }
        \think\Db::name("voucher")->where("id", $id)->update(["status" => $status, "notes" => $param["notes"], "update_time" => time(), "check_time" => time()]);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
}

?>