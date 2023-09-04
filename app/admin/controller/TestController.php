<?php


namespace app\admin\controller;

class TestController extends \think\Controller
{
    public function marketPingLun()
    {
    }
    public function index()
    {
        $data = [];
        $data["html"] = "<button class=\"custom-button\">转移</button>";
        $data["js"] = "<script>\$(\".custom-button\").on(\"click\", function(){alert(\"转移\")})</script>";
        echo json_encode($data);
        exit;
    }
    public function index1()
    {
        createMenus();
        productMenu();
        exit;
    }
    public function creditLimitInvoice($config)
    {
        if (0 < $config["cron_credit_limit_invoice_unpaid_email"]) {
            $day = $config["cron_credit_limit_invoice_unpaid_email"];
            $host = \think\Db::name("invoices")->alias("b")->field("b.id,c.email,c.phone_code,c.phonenumber,b.total,d.suffix,b.uid")->leftJoin("clients c", "b.uid=c.id")->leftJoin("currencies d", "d.id = c.currency")->withAttr("total", function ($value, $data) {
                return $value . $data["suffix"];
            })->where("b.status", "Unpaid")->where("b.due_time", ">=", strtotime(date("Y-m-d")) + $day * 24 * 3600)->where("b.due_time", "<=", strtotime(date("Y-m-d")) + $day * 24 * 3600 + 86400 - 1)->where("b.delete_time", 0)->where("b.type", "credit_limit")->select()->toArray();
            if (!empty($host[0])) {
                foreach ($host as $vv) {
                    if (!cancelRequest($vv["id"])) {
                        $email = new \app\common\logic\Email();
                        $result = $email->sendEmailBase($vv["id"], "信用额账单已生成", "invoice", true);
                        $message_template_type = array_column(config("message_template_type"), "id", "name");
                        $tmp = \think\Db::name("invoices")->field("id,total")->where("id", $vv["id"])->find();
                        $sms = new \app\common\logic\Sms();
                        $client = check_type_is_use($message_template_type[strtolower("credit_limit_invoice_notice")], $vv["uid"], $sms);
                        if ($client) {
                            $params = ["invoiceid" => $vv["id"], "total" => $vv["total"]];
                            $sms->sendSms($message_template_type[strtolower("credit_limit_invoice_notice")], $client["phone_code"] . $client["phonenumber"], $params, false, $vv["uid"]);
                        }
                        if ($result) {
                            $this->ad_log("信用额账单未付款提醒", "invoice", "发送邮件成功");
                            active_log("信用额账单未付款提醒 -  User ID:" . $vv["uid"] . "发送邮件成功", $vv["uid"]);
                        } else {
                            $this->ad_log("信用额账单未付款提醒", "invoice", "发送邮件失败");
                            active_log("信用额账单未付款提醒 -  User ID:" . $vv["uid"] . "发送邮件失败", $vv["uid"]);
                        }
                    }
                }
            }
        }
        if (0 < $config["cron_credit_limit_invoice_third_overdue_email"]) {
            $this->creditLimitInvoiceDueSend($config, 2);
        }
        if (0 < $config["cron_credit_limit_invoice_second_overdue_email"]) {
            $this->creditLimitInvoiceDueSend($config, 1);
        }
        if (0 < $config["cron_credit_limit_invoice_first_overdue_email"]) {
            $this->creditLimitInvoiceDueSend($config, 0);
        }
    }
    private function creditLimitInvoiceDueSend($config, $times = 0)
    {
        if ($times == 0) {
            $str = "first";
        } else {
            if ($times == 1) {
                $str = "second";
            } else {
                $str = "third";
            }
        }
        $day = $config["cron_credit_limit_invoice_" . $str . "_overdue_email"];
        $before_day_start_time = strtotime("-" . $day . " days", strtotime(date("Y-m-d")));
        $before_day_end_time = strtotime("+1 days -1 seconds", $before_day_start_time);
        $host = \think\Db::name("invoices")->alias("b")->field("b.id,c.email,c.phone_code,c.phonenumber,b.total,d.suffix,b.uid")->leftJoin("clients c", "b.uid=c.id")->leftJoin("currencies d", "d.id = c.currency")->withAttr("total", function ($value, $data) {
            return $value . $data["suffix"];
        })->where("b.status", "Unpaid")->where("b.due_time", "<=", $before_day_end_time)->where("b.due_email_times", $times)->where("b.delete_time", 0)->where("b.type", "credit_limit")->select()->toArray();
        if (!empty($host[0])) {
            $message_template_type = array_column(config("message_template_type"), "id", "name");
            $hostids = [];
            foreach ($host as $v) {
                $email = new \app\common\logic\Email();
                $result = $email->sendEmailBase($v["id"], "信用额账单逾期提醒", "credit_limit", true);
                $sms = new \app\common\logic\Sms();
                $client = check_type_is_use($message_template_type[strtolower("credit_limit_invoice_payment_reminder")], $v["uid"], $sms);
                if ($client) {
                    $params = ["invoiceid" => $v["id"], "total" => $v["total"]];
                    $sms->sendSms($message_template_type[strtolower("credit_limit_invoice_payment_reminder")], $client["phone_code"] . $client["phonenumber"], $params, false, $v["uid"]);
                }
                if ($result) {
                    $this->ad_log("信用额账单逾期提醒", "invoice", "逾期账单" . $v["id"] . "第" . ($times + 1) . "次邮件提醒成功");
                    active_log("信用额账单逾期提醒 - User ID:" . $v["uid"] . "逾期账单#Invoice ID:" . $v["id"] . "第" . ($times + 1) . "次邮件提醒成功", $v["uid"]);
                    \think\Db::name("invoices")->where("id", $v["id"])->where("delete_time", 0)->where("due_email_times", $times)->setInc("due_email_times");
                } else {
                    $this->ad_log("信用额账单逾期提醒", "invoice", "逾期账单" . $v["id"] . "第" . ($times + 1) . "次邮件提醒失败");
                    active_log("信用额账单逾期提醒 - User ID:" . $v["uid"] . "逾期账单#Invoice ID:" . $v["id"] . "第" . ($times + 1) . "次邮件提醒失败", $v["uid"]);
                }
                var_dump($result);
                exit;
            }
        }
    }
    private function generateRepaymentBill()
    {
        if (cache("?generate_repayment_bill")) {
            return false;
        }
        $year = (int) date("Y");
        $month = (int) date("m");
        $day = (int) date("d");
        $days = date("t", strtotime($year . "-" . $month));
        if ($day == $days) {
            if ($day == 28) {
                $whereIn = [28, 29, 30, 31];
            } else {
                if ($day == 29) {
                    $whereIn = [29, 30, 31];
                } else {
                    if ($day == 30) {
                        $whereIn = [30, 31];
                    } else {
                        $whereIn = [31];
                    }
                }
            }
        } else {
            $whereIn = [$day];
        }
        $clients = \think\Db::name("clients")->field("id")->whereIn("bill_generation_date", $whereIn)->select()->toArray();
        if (count($clients) == 0) {
            return false;
        }
        $invoice = new \app\common\logic\Invoices();
        foreach ($clients as $key => $value) {
            $invoice->createCreditLimit($value["id"]);
        }
    }
    private function getCronConfig()
    {
        $cron_config = config("cron_config");
        $keys = array_keys($cron_config);
        $keys[] = "auto_pay_renew";
        $config = getConfig($keys);
        $config = array_merge($cron_config, $config);
        return $config;
    }
    private function ad_log($name = "", $method = "", $value = "")
    {
        $idata = ["name" => $name, "method" => $method, "value" => $value, "create_time" => time()];
        $id = \think\Db::name("cron_log")->insertGetId($idata);
        return $id;
    }
    private function _xmlToJson($xml = "")
    {
        $arr = json_decode(json_encode(simplexml_load_string($xml)), true);
        return $this->_emptyArray($arr);
    }
    private function _emptyArray($array = [])
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                if (is_array($v) && count($v) == 0) {
                    $array[$k] = "";
                } else {
                    if (is_array($v) && 0 < count($v)) {
                        $array[$k] = $this->_emptyArray($v);
                    }
                }
            }
        }
        return $array;
    }
    public function localPost($url, $data, $proxy = NULL, $timeout = 10, $cookiename = "", $header = [])
    {
        if (!$url) {
            return false;
        }
        if ($data) {
            $data = http_build_query($data);
        }
        $ssl = substr($url, 0, 8) == "https://" ? true : false;
        $curl = curl_init();
        if (!is_null($proxy)) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy);
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        if ($ssl) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        }
        if (!empty($header)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        $cookie_file = dirname(__FILE__) . "/" . $cookiename;
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        $content = curl_exec($curl);
        $curl_errno = curl_errno($curl);
        curl_close($curl);
        if (0 < $curl_errno) {
            return false;
        }
        return $content;
    }
    public function localJson($url, $data = NULL, $json = false, $timeout = 30, $cookiename = "", $header = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $ssl = substr($url, 0, 8) == "https://" ? true : false;
        if ($ssl) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        }
        if (!empty($header)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        $cookie_file = dirname(__FILE__) . "/" . $cookiename;
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file);
        if (!empty($data)) {
            if ($json && is_array($data)) {
                $data = json_encode($data);
            }
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            if ($json) {
                curl_setopt($curl, CURLOPT_HEADER, 0);
                curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type:application/json;charset=utf-8", "Content-Length:" . strlen($data)]);
            }
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        $res = curl_exec($curl);
        $errorno = curl_errno($curl);
        curl_close($curl);
        if ($errorno) {
            return ["errorno" => false, "errmsg" => $errorno];
        }
        return json_decode($res, true);
    }
    public function localGet($url, $proxy = NULL, $timeout = 10, $cookiename = "", $header = [])
    {
        if (!$url) {
            return false;
        }
        $ssl = substr($url, 0, 8) == "https://" ? true : false;
        $curl = curl_init();
        if (!is_null($proxy)) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy);
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        if ($ssl) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1);
        }
        if (!empty($header)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        $cookie_file = dirname(__FILE__) . "/" . $cookiename;
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        $content = curl_exec($curl);
        $curl_errno = curl_errno($curl);
        curl_close($curl);
        if (0 < $curl_errno) {
            return false;
        }
        return $content;
    }
}
class _obfuscated_5C636C61737340616E6F6E796D6F7573002F686F6D652F7777772F6A656E6B696E732F776F726B73706163652F6D665F63772F6170702F61646D696E2F636F6E74726F6C6C65722F54657374436F6E74726F6C6C65722E7068703078376631323134613463383133_
{
    private $x = 14234;
    public function log($msg, $var)
    {
        echo $msg . $var;
    }
}

?>