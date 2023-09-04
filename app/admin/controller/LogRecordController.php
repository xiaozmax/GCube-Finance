<?php

namespace app\admin\controller;

/**
 * @title 日志记录
 * @description 接口描述
 */
class LogRecordController extends AdminBaseController
{
    public function getSystemLog(\think\Request $request)
    {
        $zjmf_authorize = configuration("zjmf_authorize");
        if (empty($zjmf_authorize)) {
            compareLicense();
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
            if ($auth["last_license_time"] + 86400 < time()) {
                compareLicense();
            }
        }
        $param = $request->param();
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "id";
        $sorting = $param["sorting"] ?? "DESC";
        $log_list = \think\Db::name("activity_log")->field("create_time,id,ipaddr,description as new_desc,uid,user,port")->where(function (\think\db\Query $query) use($param) {
            $query->where("user", "neq", "System");
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("create_time", "<=", $end_time);
                $query->where("create_time", ">=", $start_time);
            }
            if (!empty($param["search_name"])) {
                $search_name = $param["search_name"];
                $query->where("user", "like", "%" . $search_name . "%");
            }
            if (!empty($param["search_desc"])) {
                $search_desc = $param["search_desc"];
                $query->where("description", "like", "%" . $search_desc . "%");
            }
            if (!empty($param["search_ip"])) {
                $search_ip = $param["search_ip"];
                $query->where("ipaddr", "like", "%" . $search_ip . "%");
            }
        })->withAttr("new_desc", function ($value, $data) {
            $pattern = "/(?P<name>\\w+ ID):(?P<digit>\\d+)/";
            preg_match_all($pattern, $value, $matches);
            $name = $matches["name"];
            $digit = $matches["digit"];
            if (!empty($name)) {
                foreach ($name as $k => $v) {
                    $relid = $digit[$k];
                    $str = $v . ":" . $relid;
                    if ($v == "Invoice ID") {
                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/bill-detail?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                        $value = str_replace($str, $url, $value);
                    } else {
                        if ($v == "User ID") {
                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/customer-view/abstract?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: \">" . $str . "</span></a>";
                            $value = str_replace($str, $url, $value);
                        } else {
                            if ($v == "FlowPacket ID") {
                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/dcim-traffic?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                $value = str_replace($str, $url, $value);
                            } else {
                                if ($v == "Host ID") {
                                    $host = \think\Db::name("host")->alias("a")->field("a.uid")->where("a.id", $relid)->find();
                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/customer-view/product-innerpage?hid=" . $relid . "&id=" . $host["uid"] . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                    $value = str_replace($str, $url, $value);
                                } else {
                                    if ($v == "Promo_codeID") {
                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/promo-code-add?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                        $value = str_replace($str, $url, $value);
                                    } else {
                                        if ($v == "Order ID") {
                                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/order-detail?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                            $value = str_replace($str, $url, $value);
                                        } else {
                                            if ($v == "Admin ID") {
                                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/admin-edit?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                $value = str_replace($str, $url, $value);
                                            } else {
                                                if ($v != "Contacts ID") {
                                                    if ($v == "News ID") {
                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/add-news?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                        $value = str_replace($str, $url, $value);
                                                    } else {
                                                        if ($v == "TD ID") {
                                                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/new-work-order-dept?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                            $value = str_replace($str, $url, $value);
                                                        } else {
                                                            if ($v == "Ticket ID") {
                                                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/support-ticket?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                $value = str_replace($str, $url, $value);
                                                            } else {
                                                                if ($v == "Product ID") {
                                                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/edit-product?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                    $value = str_replace($str, $url, $value);
                                                                } else {
                                                                    if ($v != "IP") {
                                                                        if ($v == "PCG ID") {
                                                                            $pco = \think\Db::name("product_config_options")->where("id", $relid)->find();
                                                                            $url = "<a class=\"el-link el-link--primary is-underline\" target=\"blank\" href=\"#/edit-configurable-option-group?groupId=" . $pco["gid"] . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                            $value = str_replace($str, $url, $value);
                                                                        } else {
                                                                            if ($v == "Service ID") {
                                                                                $server = \think\Db::name("servers")->where("id", $relid)->find();
                                                                                if ($server["server_type"] == "dcimcloud") {
                                                                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/zjmfcloud\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                    $value = str_replace($str, $url, $value);
                                                                                } else {
                                                                                    if ($server["server_type"] == "dcim") {
                                                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/dcim\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                        $value = str_replace($str, $url, $value);
                                                                                    } else {
                                                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/add-server?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                        $value = str_replace($str, $url, $value);
                                                                                    }
                                                                                }
                                                                            } else {
                                                                                if ($v == "Create ID") {
                                                                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/balance-details?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                    $value = str_replace($str, $url, $value);
                                                                                } else {
                                                                                    if ($v == "Transaction ID") {
                                                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/business-statement?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                        $value = str_replace($str, $url, $value);
                                                                                    } else {
                                                                                        if ($v == "Role ID") {
                                                                                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/permissions-edit?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                            $value = str_replace($str, $url, $value);
                                                                                        } else {
                                                                                            if ($v == "Group ID") {
                                                                                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/customer-group?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                                $value = str_replace($str, $url, $value);
                                                                                            } else {
                                                                                                if ($v == "ProductGroup ID") {
                                                                                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/add-product-group?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                                    $value = str_replace($str, $url, $value);
                                                                                                } else {
                                                                                                    if ($v == "Currency ID") {
                                                                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/currency-settings?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                                        $value = str_replace($str, $url, $value);
                                                                                                    }
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                return $value;
            } else {
                return $value;
            }
        })->withAttr("ipaddr", function ($value, $data) {
            if (empty($data["port"])) {
                return $value;
            }
            return $value .= ":" . $data["port"];
        })->order($orderby . " " . $sorting)->order("id", "DESC")->page($page)->limit($limit)->select()->toArray();
        $count = \think\Db::name("activity_log")->where(function (\think\db\Query $query) use($param) {
            $query->where("user", "neq", "System");
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("create_time", "<=", $end_time);
                $query->where("create_time", ">=", $start_time);
            }
            if (!empty($param["search_name"])) {
                $search_name = $param["search_name"];
                $query->where("user", "like", "%" . $search_name . "%");
            }
            if (!empty($param["search_desc"])) {
                $search_desc = $param["search_desc"];
                $query->where("description", "like", "%" . $search_desc . "%");
            }
            if (!empty($param["search_ip"])) {
                $search_ip = $param["search_ip"];
                $query->where("ipaddr", "like", "%" . $search_ip . "%");
            }
        })->count();
        $user_list = \think\Db::name("activity_log")->field("user")->group("user")->select()->toArray();
        $returndata = [];
        $returndata["count"] = $count;
        $returndata["user_list"] = array_column($user_list, "user");
        $returndata["log_list"] = $log_list;
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $returndata]);
    }
    public function getCronSystemLog(\think\Request $request)
    {
        $param = $request->param();
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "id";
        $sorting = $param["sorting"] ?? "DESC";
        $log_list = \think\Db::name("activity_log")->field("create_time,id,ipaddr,description as new_desc,uid,user,port")->where(function (\think\db\Query $query) use($param) {
            $query->where("user", "System");
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("create_time", "<=", $end_time);
                $query->where("create_time", ">=", $start_time);
            }
            if (!empty($param["search_name"])) {
                $search_name = $param["search_name"];
                $query->where("user", "like", "%" . $search_name . "%");
            }
            if (!empty($param["search_desc"])) {
                $search_desc = $param["search_desc"];
                $query->where("description", "like", "%" . $search_desc . "%");
            }
            if (!empty($param["search_ip"])) {
                $search_ip = $param["search_ip"];
                $query->where("ipaddr", "like", "%" . $search_ip . "%");
            }
        })->withAttr("new_desc", function ($value, $data) {
            $pattern = "/(?P<name>\\w+ ID):(?P<digit>\\d+)/";
            preg_match_all($pattern, $value, $matches);
            $name = $matches["name"];
            $digit = $matches["digit"];
            if (!empty($name)) {
                foreach ($name as $k => $v) {
                    $relid = $digit[$k];
                    $str = $v . ":" . $relid;
                    if ($v == "Invoice ID") {
                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/bill-detail?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                        $value = str_replace($str, $url, $value);
                    } else {
                        if ($v == "User ID") {
                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/customer-view/abstract?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: \">" . $str . "</span></a>";
                            $value = str_replace($str, $url, $value);
                        } else {
                            if ($v == "FlowPacket ID") {
                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/dcim-traffic?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                $value = str_replace($str, $url, $value);
                            } else {
                                if ($v == "Host ID") {
                                    $host = \think\Db::name("host")->alias("a")->field("a.uid")->where("a.id", $relid)->find();
                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/customer-view/product-innerpage?hid=" . $relid . "&id=" . $host["uid"] . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                    $value = str_replace($str, $url, $value);
                                } else {
                                    if ($v == "Promo_codeID") {
                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/promo-code-add?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                        $value = str_replace($str, $url, $value);
                                    } else {
                                        if ($v == "Order ID") {
                                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/order-detail?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                            $value = str_replace($str, $url, $value);
                                        } else {
                                            if ($v == "Admin ID") {
                                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/admin-edit?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                $value = str_replace($str, $url, $value);
                                            } else {
                                                if ($v != "Contacts ID") {
                                                    if ($v == "News ID") {
                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/add-news?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                        $value = str_replace($str, $url, $value);
                                                    } else {
                                                        if ($v == "TD ID") {
                                                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/new-work-order-dept?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                            $value = str_replace($str, $url, $value);
                                                        } else {
                                                            if ($v == "Ticket ID") {
                                                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/support-ticket?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                $value = str_replace($str, $url, $value);
                                                            } else {
                                                                if ($v == "Product ID") {
                                                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/edit-product?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                    $value = str_replace($str, $url, $value);
                                                                } else {
                                                                    if ($v != "IP") {
                                                                        if ($v == "PCG ID") {
                                                                            $pco = \think\Db::name("product_config_options")->where("id", $relid)->find();
                                                                            $url = "<a class=\"el-link el-link--primary is-underline\" target=\"blank\" href=\"#/edit-configurable-option1?groupId=" . $pco["gid"] . "&optionId=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                            $value = str_replace($str, $url, $value);
                                                                        } else {
                                                                            if ($v == "Service ID") {
                                                                                $server = \think\Db::name("servers")->where("id", $relid)->find();
                                                                                if ($server["server_type"] == "dcimcloud") {
                                                                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/zjmfcloud\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                    $value = str_replace($str, $url, $value);
                                                                                } else {
                                                                                    if ($server["server_type"] == "dcim") {
                                                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/dcim\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                        $value = str_replace($str, $url, $value);
                                                                                    } else {
                                                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/add-server?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                        $value = str_replace($str, $url, $value);
                                                                                    }
                                                                                }
                                                                            } else {
                                                                                if ($v == "Create ID") {
                                                                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/balance-details?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                    $value = str_replace($str, $url, $value);
                                                                                } else {
                                                                                    if ($v == "Transaction ID") {
                                                                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/business-statement?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                        $value = str_replace($str, $url, $value);
                                                                                    } else {
                                                                                        if ($v == "Role ID") {
                                                                                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/permissions-edit?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                            $value = str_replace($str, $url, $value);
                                                                                        } else {
                                                                                            if ($v == "Group ID") {
                                                                                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/customer-group?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                                $value = str_replace($str, $url, $value);
                                                                                            } else {
                                                                                                if ($v == "Currency ID") {
                                                                                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/currency-settings?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                                                                                                    $value = str_replace($str, $url, $value);
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                return $value;
            } else {
                return $value;
            }
        })->withAttr("ipaddr", function ($value, $data) {
            if (empty($data["port"])) {
                return $value;
            }
            return $value .= ":" . $data["port"];
        })->order($orderby . " " . $sorting)->order("id", "DESC")->page($page)->limit($limit)->select()->toArray();
        $count = \think\Db::name("activity_log")->where(function (\think\db\Query $query) use($param) {
            $query->where("user", "System");
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("create_time", "<=", $end_time);
                $query->where("create_time", ">=", $start_time);
            }
            if (!empty($param["search_name"])) {
                $search_name = $param["search_name"];
                $query->where("user", "like", "%" . $search_name . "%");
            }
            if (!empty($param["search_desc"])) {
                $search_desc = $param["search_desc"];
                $query->where("description", "like", "%" . $search_desc . "%");
            }
            if (!empty($param["search_ip"])) {
                $search_ip = $param["search_ip"];
                $query->where("ipaddr", "like", "%" . $search_ip . "%");
            }
        })->count();
        $user_list = \think\Db::name("activity_log")->field("user")->group("user")->select()->toArray();
        $returndata = [];
        $returndata["count"] = $count;
        $returndata["user_list"] = array_column($user_list, "user");
        $returndata["log_list"] = $log_list;
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $returndata]);
    }
    public function getAdminLog(\think\Request $request)
    {
        $param = $request->param();
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "id";
        $sorting = $param["sorting"] ?? "DESC";
        $count = \think\Db::name("admin_log")->where(function (\think\db\Query $query) use($param) {
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("logintime", "<=", $end_time);
                $query->where("logintime", ">=", $start_time);
            }
            if (!empty($param["search_name"])) {
                $search_name = $param["search_name"];
                $query->where("admin_username", "like", "%" . $search_name . "%");
            }
            if (!empty($param["search_ip"])) {
                $search_ip = $param["search_ip"];
                $query->where("ipaddress", "like", "%" . $search_ip . "%");
            }
        })->count();
        $log_list = \think\Db::name("admin_log")->where(function (\think\db\Query $query) use($param) {
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("logintime", "<=", $end_time);
                $query->where("logintime", ">=", $start_time);
            }
            if (!empty($param["search_name"])) {
                $search_name = $param["search_name"];
                $query->where("admin_username", "like", "%" . $search_name . "%");
            }
            if (!empty($param["search_ip"])) {
                $search_ip = $param["search_ip"];
                $query->where("ipaddress", "like", "%" . $search_ip . "%");
            }
        })->withAttr("ipaddress", function ($value, $data) {
            if (empty($data["port"])) {
                return $value;
            }
            return $value .= ":" . $data["port"];
        })->order($orderby . " " . $sorting)->order("id", "DESC")->page($page)->limit($limit)->select()->toArray();
        $user_list = \think\Db::name("admin_log")->field("admin_username")->group("admin_username")->select()->toArray();
        $user_list = array_column($user_list, "admin_username");
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "count" => $count, "data" => $log_list, "user_list" => $user_list]);
    }
    public function getNotifyLog(\think\Request $request)
    {
        $param = $request->param();
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "id";
        $sorting = $param["sorting"] ?? "DESC";
        $count = \think\Db::name("notify_log")->where(function (\think\db\Query $query) use($param) {
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("create_time", "<=", $end_time);
                $query->where("create_time", ">=", $start_time);
            }
            if (!empty($param["message"])) {
                $message = $param["message"];
                $query->where("message", "like", "%" . $message . "%");
            }
            if (!empty($param["type"])) {
                $type = $param["type"];
                $query->where("type", "like", "%" . $type . "%");
            }
        })->count();
        $log_list = \think\Db::name("notify_log")->where(function (\think\db\Query $query) use($param) {
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("create_time", "<=", $end_time);
                $query->where("create_time", ">=", $start_time);
            }
            if (!empty($param["message"])) {
                $message = $param["message"];
                $query->where("message", "like", "%" . $message . "%");
            }
            if (!empty($param["type"])) {
                $type = $param["type"];
                $query->where("type", "like", "%" . $type . "%");
            }
        })->order($orderby . " " . $sorting)->order("id", "DESC")->page($page)->limit($limit)->select()->toArray();
        $type = ["email", "sms", "wechat"];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "count" => $count, "data" => $log_list, "type" => $type]);
    }
    public function getEmailLog()
    {
        $param = $this->request->param();
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "id";
        $sorting = $param["sorting"] ?? "DESC";
        $count = \think\Db::name("email_log")->alias("a")->field("a.id,a.to,a.create_time,a.subject,b.username,a.status,a.fail_reason,a.ip")->leftJoin("clients b", "b.id = a.uid")->where(function (\think\db\Query $query) use($param) {
            if (!empty($param["uid"])) {
                $query->where("uid", $param["uid"])->where("a.is_admin", 0);
            }
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("a.create_time", "<=", $end_time);
                $query->where("a.create_time", ">=", $start_time);
            }
            if (!empty($param["subject"])) {
                $subject = $param["subject"];
                $query->where("a.subject", "like", "%" . $subject . "%");
            }
            if (!empty($param["username"])) {
                $username = $param["username"];
                $query->where("a.to", "like", "%" . $username . "%");
            }
        })->count();
        $email_lists = \think\Db::name("email_log")->alias("a")->field("a.id,a.to,a.create_time,a.subject,b.username,a.status,a.fail_reason,a.ip,a.port")->leftJoin("clients b", "b.id = a.uid")->where(function (\think\db\Query $query) use($param) {
            if (!empty($param["uid"])) {
                $query->where("uid", $param["uid"])->where("a.is_admin", 0);
            }
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("a.create_time", "<=", $end_time);
                $query->where("a.create_time", ">=", $start_time);
            }
            if (!empty($param["subject"])) {
                $subject = $param["subject"];
                $query->where("a.subject", "like", "%" . $subject . "%");
            }
            if (!empty($param["username"])) {
                $username = $param["username"];
                $query->where("a.to", "like", "%" . $username . "%");
            }
        })->withAttr("ip", function ($value, $data) {
            if (empty($data["port"])) {
                return $value;
            }
            return $value .= ":" . $data["port"];
        })->order($orderby . " " . $sorting)->order("id", "DESC")->page($page)->limit($limit)->select()->toArray();
        $email_lists_filter = [];
        foreach ($email_lists as $key => $email_list) {
            $email_lists_filter[$key] = $email_list;
            $email_lists_filter[$key]["username"] = $email_list["to"];
        }
        $user_list = \think\Db::name("clients")->alias("b")->field("b.id,b.username")->select()->toArray();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "count" => $count, "data" => $email_lists_filter, "user_list" => $user_list]);
    }
    public function getEmailDetail()
    {
        $params = $this->request->param();
        $id = isset($params["id"]) ? intval($params["id"]) : "";
        if (!$id) {
            return jsonrule(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $detail = \think\Db::name("email_log")->field("to,subject,message,is_admin,uid")->where("id", $id)->find();
        if ($detail["is_admin"]) {
            $detail["username"] = \think\Db::name("user")->where("id", $detail["uid"])->value("user_nickname");
        } else {
            $detail["username"] = \think\Db::name("clients")->where("id", $detail["uid"])->value("username");
        }
        $detail["content"] = htmlspecialchars_decode($detail["content"]);
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "detail" => $detail]);
    }
    public function getWechatLog()
    {
    }
    public function getSmsLog()
    {
        $param = $this->request->param();
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "id";
        $sorting = $param["sorting"] ?? "DESC";
        $count = \think\Db::name("message_log")->alias("a")->field("a.id,a.uid,a.create_time,a.content,a.fail_reason,a.status,a.phone,a.phone_code,b.username,a.ip,a.port")->leftJoin("clients b", "b.id = a.uid")->where(function (\think\db\Query $query) use($param) {
            if (!empty($param["uid"])) {
                $query->where("uid", $param["uid"]);
            }
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("a.create_time", "<=", $end_time);
                $query->where("a.create_time", ">=", $start_time);
            }
            if (!empty($param["phone"])) {
                $phone = $param["phone"];
                $query->where("a.phone", "like", "%" . $phone . "%");
            }
            if (!empty($param["username"])) {
                $username = $param["username"];
                $query->where("b.username", "like", "%" . $username . "%");
            }
        })->count();
        $email_lists = \think\Db::name("message_log")->alias("a")->field("a.id,a.uid,a.create_time,a.content,a.fail_reason,a.status,a.phone,a.phone_code,b.username,a.ip,a.port")->leftJoin("clients b", "b.id = a.uid")->where(function (\think\db\Query $query) use($param) {
            if (!empty($param["uid"])) {
                $query->where("uid", $param["uid"]);
            }
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("a.create_time", "<=", $end_time);
                $query->where("a.create_time", ">=", $start_time);
            }
            if (!empty($param["phone"])) {
                $phone = $param["phone"];
                $query->where("a.phone", "like", "%" . $phone . "%");
            }
            if (!empty($param["username"])) {
                $username = $param["username"];
                $query->where("b.username", "like", "%" . $username . "%");
            }
        })->withAttr("ip", function ($value, $data) {
            if (empty($data["port"])) {
                return $value;
            }
            return $value .= ":" . $data["port"];
        })->order($orderby . " " . $sorting)->order("id", "DESC")->page($page)->limit($limit)->select()->toArray();
        $email_lists_filter = [];
        foreach ($email_lists as $key => $email_list) {
            $email_lists_filter[$key] = $email_list;
        }
        $user_list = \think\Db::name("clients")->alias("b")->field("b.id,b.username")->select()->toArray();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "count" => $count, "data" => $email_lists_filter, "user_list" => $user_list]);
    }
    public function getSmsLogM()
    {
        $param = $this->request->param();
        $uid = intval($param["uid"]);
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "id";
        $sorting = $param["sorting"] ?? "DESC";
        $count = \think\Db::name("message_log")->alias("a")->field("a.id,a.uid,a.create_time,a.content,a.fail_reason,a.status,a.phone,a.phone_code,b.username,a.ip")->leftJoin("clients b", "b.id = a.uid")->where(function (\think\db\Query $query) use($uid, $param) {
            $query->where("uid", $uid);
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("a.create_time", "<=", $end_time);
                $query->where("a.create_time", ">=", $start_time);
            }
            if (!empty($param["phone"])) {
                $phone = $param["phone"];
                $query->where("a.phone", "like", "%" . $phone . "%");
            }
            if (!empty($param["username"])) {
                $username = $param["username"];
                $query->where("b.username", "like", "%" . $username . "%");
            }
        })->count();
        $email_lists = \think\Db::name("message_log")->alias("a")->field("a.id,a.uid,a.create_time,a.content,a.fail_reason,a.status,a.phone,a.phone_code,b.username")->leftJoin("clients b", "b.id = a.uid")->where(function (\think\db\Query $query) use($uid, $param) {
            $query->where("uid", $uid);
            if (!empty($param["search_time"])) {
                $start_time = strtotime(date("Y-m-d", $param["search_time"]));
                $end_time = strtotime(date("Y-m-d", $param["search_time"])) + 86400;
                $query->where("a.create_time", "<=", $end_time);
                $query->where("a.create_time", ">=", $start_time);
            }
            if (!empty($param["phone"])) {
                $phone = $param["phone"];
                $query->where("a.phone", "like", "%" . $phone . "%");
            }
            if (!empty($param["username"])) {
                $username = $param["username"];
                $query->where("b.username", "like", "%" . $username . "%");
            }
        })->order($orderby . " " . $sorting)->order("id", "DESC")->page($page)->limit($limit)->select()->toArray();
        $email_lists_filter = [];
        foreach ($email_lists as $key => $email_list) {
            $email_lists_filter[$key] = $email_list;
        }
        $user_list = \think\Db::name("message_log")->alias("a")->leftJoin("clients b", "b.id = a.uid")->field("b.id,b.username")->group("b.username")->select()->toArray();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "count" => $count, "data" => $email_lists_filter, "user_list" => $user_list]);
    }
    public function getSystemMessageLog(\think\Request $request)
    {
        $param = $this->request->param();
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "id";
        $sorting = $param["sorting"] ?? "DESC";
        $count = \think\Db::name("system_message")->alias("sm")->join("clients c", "c.id = sm.uid")->where(function (\think\db\Query $query) use($param) {
            if (intval($param["uid"])) {
                $query->where("sm.uid", intval($param["uid"]));
            }
            if ($param["search_time"][0]) {
                $query->where("sm.create_time", ">=", $param["search_time"][0]);
            }
            if ($param["search_time"][1]) {
                $query->where("sm.create_time", "<=", $param["search_time"][1]);
            }
            if ($param["read_type"] == 0) {
                $query->where("sm.read_time", 0);
            }
            if ($param["read_type"] == 1) {
                $query->where("sm.read_time", ">", 0);
            }
            if (trim($param["keywords"])) {
                $query->where("sm.title", "like", "%" . trim($param["keywords"]) . "%");
            }
            if (trim($param["username"])) {
                $query->where("c.username", "like", "%" . trim($param["username"]) . "%");
            }
        })->count();
        $list = \think\Db::name("system_message")->alias("sm")->field("sm.*,c.username,c.phonenumber,c.email")->join("clients c", "c.id = sm.uid")->where(function (\think\db\Query $query) use($param) {
            if (intval($param["uid"])) {
                $query->where("sm.uid", intval($param["uid"]));
            }
            if ($param["search_time"][0]) {
                $query->where("sm.create_time", ">=", $param["search_time"][0]);
            }
            if ($param["search_time"][1]) {
                $query->where("sm.create_time", "<=", $param["search_time"][1]);
            }
            if ($param["read_type"] == 0) {
                $query->where("sm.read_time", 0);
            }
            if ($param["read_type"] == 1) {
                $query->where("sm.read_time", ">", 0);
            }
            if (trim($param["keywords"])) {
                $query->where("sm.title", "like", "%" . trim($param["keywords"]) . "%");
            }
            if (trim($param["username"])) {
                $query->where("c.username", "like", "%" . trim($param["username"]) . "%");
            }
        })->order($orderby, $sorting)->page($page)->limit($limit)->select()->toArray();
        if ($list) {
            foreach ($list as &$item) {
                $item["content"] = htmlspecialchars_decode($item["content"]);
                $item["create_time"] = date("Y-m-d H:i:s", $item["create_time"]);
                if ($item["attachment"]) {
                    $attachment = explode(",", $item["attachment"]);
                    $attachment_arr = [];
                    foreach ($attachment as $at_item) {
                        $at_info = explode("^", $at_item);
                        $temp = [];
                        $temp["path"] = $_SERVER["REQUEST_SCHEME"] . "://" . $request->host() . config("system_message_url") . $at_item;
                        $temp["name"] = $at_info[1];
                        $attachment_arr[] = $temp;
                    }
                    $item["attachment"] = $attachment_arr;
                }
            }
        }
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => ["count" => $count, "list" => $list]]);
    }
    public function getApiLog()
    {
        $param = $this->request->param();
        $keywords = isset($param["keywords"]) ? trim($param["keywords"]) : "";
        $time = isset($param["time"]) ? $param["time"] : 0;
        $uid = isset($param["uid"]) ? intval($param["uid"]) : 0;
        $page = isset($param["page"]) ? intval($param["page"]) : config("page");
        $limit = isset($param["limit"]) ? intval($param["limit"]) : (configuration("NumRecordstoDisplay") ?: config("limit"));
        $orderby = strval($param["orderby"]) ? strval($param["orderby"]) : "a.id";
        $sorting = $param["sorting"] ?? "DESC";
        $where = function (\think\db\Query $query) use($keywords, $time) {
            if (!empty($keywords)) {
                $query->where("a.description|a.ip", "like", "%" . $keywords . "%");
            }
            if (!empty($uid)) {
                $query->where("a.uid", $uid);
            }
            if (!empty($time)) {
                $start_time = strtotime(date("Y-m-d", $time));
                $end_time = strtotime(date("Y-m-d", $time)) + 86400;
                $query->where("a.create_time", "<=", $end_time);
                $query->where("a.create_time", ">=", $start_time);
            }
        };
        $count = \think\Db::name("api_resource_log")->alias("a")->field("a.id,a.create_time,a.description,a.ip,b.username")->leftJoin("clients b", "a.uid = b.id")->where($where)->count();
        $list = \think\Db::name("api_resource_log")->alias("a")->field("a.id,a.create_time,a.description,a.ip,b.username,a.port,a.uid")->leftJoin("clients b", "a.uid = b.id")->where($where)->withAttr("description", function ($value, $data) {
            $pattern = "/(?P<name>\\w+ ID):(?P<digit>\\d+)/";
            preg_match_all($pattern, $value, $matches);
            $name = $matches["name"];
            $digit = $matches["digit"];
            if (!empty($name)) {
                foreach ($name as $k => $v) {
                    $relid = $digit[$k];
                    $str = $v . ":" . $relid;
                    if ($v == "User ID") {
                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/customer-view/abstract?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: \">" . $str . "</span></a>";
                        $value = str_replace($str, $url, $value);
                    } else {
                        if ($v == "Product ID") {
                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/edit-product?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;\">" . $str . "</span></a>";
                            $value = str_replace($str, $url, $value);
                        }
                    }
                }
                return $value;
            } else {
                return $value;
            }
        })->withAttr("ip", function ($value, $data) {
            if (empty($data["port"])) {
                return $value;
            }
            return $value .= ":" . $data["port"];
        })->order($orderby, $sorting)->page($page)->limit($limit)->select()->toArray();
        $uids = \think\Db::name("api_resource_log")->field("uid")->distinct(true)->column("uid");
        $user = \think\Db::name("clients")->field("id,username")->whereIn("id", $uids)->select()->toArray();
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => ["count" => $count, "list" => $list, "user" => $user]]);
    }
    public function getDeleteLogPage()
    {
        $param = $this->request->param();
        $type = $param["type"];
        if (!in_array($type, array_keys(config("log_type")))) {
            $type = "system_log";
        }
        switch ($type) {
            case "system_log":
                $count = \think\Db::name("activity_log")->where("user", "neq", "System")->count();
                break;
            case "admin_log":
                $count = \think\Db::name("admin_log")->count();
                break;
            case "email_log":
                $count = \think\Db::name("email_log")->count();
                break;
            case "sms_log":
                $count = \think\Db::name("message_log")->count();
                break;
            case "system_message_log":
                $count = \think\Db::name("system_message")->count();
                break;
            case "cron_system_log":
                $count = \think\Db::name("activity_log")->where("user", "like", "System")->count();
                break;
            case "api_log":
                $count = \think\Db::name("api_resource_log")->count();
                break;
            default:
                $count = 0;
                $data = ["count" => $count, "type" => config("log_type")];
                return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
        }
    }
    public function getAffirmDeleteLogPage()
    {
        $param = $this->request->param();
        $type = $param["type"];
        if (!in_array($type, array_keys(config("log_type")))) {
            return jsonrule(["status" => 400, "msg" => "日志类型错误"]);
        }
        if (isset($param["time"])) {
            $time = $param["time"];
        }
        $where = function (\think\db\Query $query) use($time) {
            if ($time) {
                if ($type == "admin_log") {
                    $query->where("logintime", "<=", $time);
                } else {
                    $query->where("create_time", "<=", $time);
                }
            }
        };
        switch ($type) {
            case "system_log":
                $count = \think\Db::name("activity_log")->where($where)->where("user", "neq", "System")->count();
                break;
            case "admin_log":
                $count = \think\Db::name("admin_log")->where($where)->count();
                break;
            case "email_log":
                $count = \think\Db::name("email_log")->where($where)->count();
                break;
            case "sms_log":
                $count = \think\Db::name("message_log")->where($where)->count();
                break;
            case "system_message_log":
                $count = \think\Db::name("system_message")->where($where)->count();
                break;
            case "cron_system_log":
                $count = \think\Db::name("activity_log")->where($where)->where("user", "like", "System")->count();
                break;
            case "api_log":
                $count = \think\Db::name("api_resource_log")->where($where)->count();
                break;
            default:
                $count = 0;
                $data = ["count" => $count, "time" => time()];
                return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
        }
    }
    public function deleteLog()
    {
        $param = $this->request->param();
        $type = $param["type"];
        if (!in_array($type, array_keys(config("log_type")))) {
            return jsonrule(["status" => 400, "msg" => "日志类型错误"]);
        }
        if (isset($param["time"])) {
            $time = $param["time"];
        }
        $where = function (\think\db\Query $query) use($time) {
            if ($time) {
                if ($type == "admin_log") {
                    $query->where("logintime", "<=", $time);
                } else {
                    $query->where("create_time", "<=", $time);
                }
            }
        };
        $hook_data = ["adminid" => cmf_get_current_admin_id(), "type" => $type];
        hook("before_delete_log", $hook_data);
        switch ($type) {
            case "system_log":
                $count = \think\Db::name("activity_log")->where($where)->where("user", "neq", "System")->delete();
                break;
            case "admin_log":
                $count = \think\Db::name("admin_log")->where($where)->delete();
                break;
            case "email_log":
                $count = \think\Db::name("email_log")->where($where)->delete();
                break;
            case "sms_log":
                $count = \think\Db::name("message_log")->where($where)->delete();
                break;
            case "system_message_log":
                $count = \think\Db::name("system_message")->where($where)->delete();
                break;
            case "cron_system_log":
                $count = \think\Db::name("activity_log")->where($where)->where("user", "like", "System")->delete();
                break;
            case "api_log":
                $count = \think\Db::name("api_resource_log")->where($where)->delete();
                break;
            default:
                $count = 0;
                return jsonrule(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
        }
    }
}

?>