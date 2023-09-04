<?php


namespace app\admin\controller;

/**
 * @title 后台自动任务
 * @description 接口说明
 */
class CronController extends AdminBaseController
{
    public function detail()
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
        $cron_config = config("cron_config");
        $data = getConfig(array_keys($cron_config));
        $data = array_merge($cron_config, $data);
        $data["cron_command"] = config("cron_command");
        $data["marking_cron_command"] = config("marking_cron_command");
        $data["cron_last_run_time_over"] = configuration("cron_last_run_time_over");
        if (1200 < $data["cron_last_run_time_over"] - $data["cron_last_run_time"]) {
            $data["diff_run_time"] = -1;
        } else {
            $data["diff_run_time"] = 1;
        }
        if (900 < time() - $data["cron_last_run_time_over"]) {
            $data["diff_run_time"] = -1;
        }
        $result["status"] = 200;
        $result["msg"] = lang("SUCCESS MESSAGE");
        $result["data"] = $data;
        return jsonrule($result);
    }
    public function saveCron()
    {
        $params = input("post.");
        $rule = ["cron_day_start_time" => "number|between:0,23", "cron_host_suspend_time" => "number", "cron_host_terminate_time" => "number", "cron_invoice_first_overdue_email" => "number", "cron_invoice_second_overdue_email" => "number", "cron_invoice_third_overdue_email" => "number", "cron_ticket_close_time" => "number", "cron_client_delete_time" => "number", "cron_other_client_update" => "in:0,1,2", "cron_host_terminate_high" => "in:0,1", "cron_host_terminate_time_hostingaccount" => "number", "cron_host_terminate_time_server" => "number", "cron_host_terminate_time_cloud" => "number", "cron_host_terminate_time_dcimcloud" => "number", "cron_host_terminate_time_dcim" => "number", "cron_host_terminate_time_cdn" => "number", "cron_host_terminate_time_other" => "number", "cron_credit_limit_suspend_time" => "number", "cron_credit_limit_invoice_unpaid_email" => "number", "cron_credit_limit_invoice_first_overdue_email" => "number", "cron_credit_limit_invoice_second_overdue_email" => "number", "cron_credit_limit_invoice_third_overdue_email" => "number", "cron_order_unpaid_time_high" => "in:0,1", "cron_invoice_recharge_delete" => "in:0,1", "cron_invoice_recharge_delete_time" => "number"];
        $msg = ["cron_day_start_time.number" => lang("FORMAT_ERROR"), "cron_day_start_time.between" => lang("FORMAT_ERROR"), "cron_host_suspend_time.number" => lang("FORMAT_ERROR"), "cron_host_terminate_time.number" => lang("FORMAT_ERROR"), "cron_invoice_first_overdue_email.number" => lang("FORMAT_ERROR"), "cron_invoice_second_overdue_email.number" => lang("FORMAT_ERROR"), "cron_invoice_third_overdue_email.number" => lang("FORMAT_ERROR"), "cron_ticket_close_time.number" => lang("FORMAT_ERROR"), "cron_client_delete_time.number" => lang("FORMAT_ERROR"), "cron_other_client_update.in" => lang("FORMAT_ERROR"), "cron_host_terminate_high.in" => lang("FORMAT_ERROR"), "cron_host_terminate_time_hostingaccount.number" => lang("FORMAT_ERROR"), "cron_host_terminate_time_server.number" => lang("FORMAT_ERROR"), "cron_host_terminate_time_cloud.number" => lang("FORMAT_ERROR"), "cron_host_terminate_time_dcimcloud.number" => lang("FORMAT_ERROR"), "cron_host_terminate_time_dcim.number" => lang("FORMAT_ERROR"), "cron_host_terminate_time_cdn.number" => lang("FORMAT_ERROR"), "cron_host_terminate_time_other.number" => lang("FORMAT_ERROR"), "cron_credit_limit_suspend_time.number" => lang("FORMAT_ERROR"), "cron_credit_limit_invoice_unpaid_email.number" => lang("FORMAT_ERROR"), "cron_credit_limit_invoice_first_overdue_email.number" => lang("FORMAT_ERROR"), "cron_credit_limit_invoice_second_overdue_email.number" => lang("FORMAT_ERROR"), "cron_credit_limit_invoice_third_overdue_email.number" => lang("FORMAT_ERROR"), "cron_order_unpaid_time_high.in" => lang("FORMAT_ERROR"), "cron_invoice_recharge_delete.in" => lang("FORMAT_ERROR"), "cron_invoice_recharge_delete_time.number" => lang("FORMAT_ERROR")];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $cron_config = config("cron_config");
        $data = getConfig(array_keys($cron_config));
        $data = array_merge($cron_config, $data);
        unset($data["cron_last_run_time"]);
        $dec = "";
        foreach ($data as $k => $v) {
            if (isset($params[$k]) && $v != $params[$k]) {
                $company_name = configuration($k);
                updateConfiguration($k, $params[$k]);
                if ($k == "cron_host_unsuspend") {
                    if ($params[$k] == 1) {
                        $dec .= " -  启用解除暂停功能";
                    } else {
                        $dec .= " -  禁用解除暂停功能";
                    }
                } else {
                    if ($k == "cron_host_unsuspend_send") {
                        if ($params[$k] == 1) {
                            $dec .= " -  发送解除暂停邮件";
                        } else {
                            $dec .= " -  发送解除不暂停邮件";
                        }
                    } else {
                        if ($k == " cron_host_suspend_send") {
                            if ($params[$k] == 1) {
                                $dec .= " -  发送暂停邮件";
                            } else {
                                $dec .= " -  发送不暂停邮件";
                            }
                        } else {
                            if ($k == "cron_host_suspend") {
                                if ($params[$k] == 1) {
                                    $dec .= " -  启用暂停功能";
                                } else {
                                    $dec .= " -  不启用暂停功能";
                                }
                            } else {
                                if ($k == "cron_host_suspend_time") {
                                    $dec .= " - 暂停时间:" . $company_name . "改为" . $params[$k];
                                } else {
                                    if ($k == "cron_host_terminate_time") {
                                        $dec .= " - 删除时间:" . $company_name . "改为" . $params[$k];
                                    } else {
                                        if ($k == "cron_day_start_time") {
                                            $dec .= " - 每天何时:" . $company_name . "改为" . $params[$k];
                                        } else {
                                            if ($k == "cron_invoice_create_default_days") {
                                                $dec .= " - 生成账单:" . $company_name . "改为" . $params[$k];
                                            } else {
                                                if ($k == "cron_invoice_pay_email") {
                                                    if ($params[$k] == 1) {
                                                        $dec .= " -  付款提醒邮件";
                                                    } else {
                                                        $dec .= " -  付款提醒邮件";
                                                    }
                                                } else {
                                                    if ($k == "cron_invoice_pay_email") {
                                                        if ($params[$k] == 1) {
                                                            $dec .= " -  付款提醒邮件";
                                                        } else {
                                                            $dec .= " -  关闭付款提醒邮件";
                                                        }
                                                    } else {
                                                        if ($k == "cron_invoice_create_hour") {
                                                            $dec .= " - 小时付:" . $company_name . "改为" . $params[$k];
                                                        } else {
                                                            if ($k == "cron_invoice_create_day") {
                                                                $dec .= " - 天付:" . $company_name . "改为" . $params[$k];
                                                            } else {
                                                                if ($k == "cron_invoice_create_monthly") {
                                                                    $dec .= " - 月付:" . $company_name . "改为" . $params[$k];
                                                                } else {
                                                                    if ($k == "cron_invoice_create_quarterly") {
                                                                        $dec .= " - 季付:" . $company_name . "改为" . $params[$k];
                                                                    } else {
                                                                        if ($k == "cron_invoice_create_semiannually") {
                                                                            $dec .= " - 半年付:" . $company_name . "改为" . $params[$k];
                                                                        } else {
                                                                            if ($k == "cron_invoice_create_annually") {
                                                                                $dec .= " - 年付:" . $company_name . "改为" . $params[$k];
                                                                            } else {
                                                                                if ($k == "cron_invoice_create_biennially") {
                                                                                    $dec .= " - 两年付:" . $company_name . "改为" . $params[$k];
                                                                                } else {
                                                                                    if ($k == "cron_invoice_create_triennially") {
                                                                                        $dec .= " - 三年付:" . $company_name . "改为" . $params[$k];
                                                                                    } else {
                                                                                        if ($k == "cron_invoice_create_fourly") {
                                                                                            $dec .= " - 四年付:" . $company_name . "改为" . $params[$k];
                                                                                        } else {
                                                                                            if ($k == "cron_invoice_create_fively") {
                                                                                                $dec .= " - 五年付:" . $company_name . "改为" . $params[$k];
                                                                                            } else {
                                                                                                if ($k == "cron_invoice_create_sixly") {
                                                                                                    $dec .= " - 六年付:" . $company_name . "改为" . $params[$k];
                                                                                                } else {
                                                                                                    if ($k == "cron_invoice_create_sevenly") {
                                                                                                        $dec .= " - 七年付:" . $company_name . "改为" . $params[$k];
                                                                                                    } else {
                                                                                                        if ($k == "cron_invoice_create_eightly") {
                                                                                                            $dec .= " - 八年付:" . $company_name . "改为" . $params[$k];
                                                                                                        } else {
                                                                                                            if ($k == "cron_invoice_create_ninely") {
                                                                                                                $dec .= " - 九年付:" . $company_name . "改为" . $params[$k];
                                                                                                            } else {
                                                                                                                if ($k == "cron_invoice_create_tenly") {
                                                                                                                    $dec .= " - 十年付:" . $company_name . "改为" . $params[$k];
                                                                                                                } else {
                                                                                                                    if ($k == "cron_invoice_unpaid_email") {
                                                                                                                        $dec .= " - 账单未付款提醒天数:" . $company_name . "改为" . $params[$k];
                                                                                                                    } else {
                                                                                                                        if ($k == "cron_invoice_first_overdue_email") {
                                                                                                                            $dec .= " - 第 1 次逾期提醒:" . $company_name . "改为" . $params[$k];
                                                                                                                        } else {
                                                                                                                            if ($k == "cron_invoice_second_overdue_email") {
                                                                                                                                $dec .= " - 第 2 次逾期提醒:" . $company_name . "改为" . $params[$k];
                                                                                                                            } else {
                                                                                                                                if ($k == "cron_invoice_third_overdue_email") {
                                                                                                                                    $dec .= " - 第 3 次逾期提醒:" . $company_name . "改为" . $params[$k];
                                                                                                                                } else {
                                                                                                                                    if ($k == "cron_ticket_close_time") {
                                                                                                                                        $dec .= " - 关闭工单:" . $company_name . "改为" . $params[$k];
                                                                                                                                    } else {
                                                                                                                                        if ($k == "cron_order_unpaid_time_high") {
                                                                                                                                            if ($params[$k] == 1) {
                                                                                                                                                $dec .= " -  删除取消功能启用";
                                                                                                                                            } else {
                                                                                                                                                $dec .= " -  删除取消功能关闭";
                                                                                                                                            }
                                                                                                                                            $dec .= " - 删除取消:" . $company_name . "改为" . $params[$k];
                                                                                                                                        } else {
                                                                                                                                            if ($k == "cron_order_unpaid_time") {
                                                                                                                                                $dec .= " - 删除取消:" . $company_name . "改为" . $params[$k];
                                                                                                                                            } else {
                                                                                                                                                if ($k == "cron_order_unpaid_action:Delete") {
                                                                                                                                                    $dec .= " - 操作类型:" . $company_name . "改为" . $params[$k];
                                                                                                                                                } else {
                                                                                                                                                    if ($k == "cron_credit_limit_suspend_time") {
                                                                                                                                                        $dec .= " - 信用额账单未付款到期暂停天数:" . $company_name . "改为" . $params[$k];
                                                                                                                                                    } else {
                                                                                                                                                        if ($k == "cron_credit_limit_invoice_unpaid_email") {
                                                                                                                                                            $dec .= " - 信用额账单未付款提醒天数:" . $company_name . "改为" . $params[$k];
                                                                                                                                                        } else {
                                                                                                                                                            if ($k == "cron_credit_limit_invoice_first_overdue_email") {
                                                                                                                                                                $dec .= " - 信用额账单第 1 次逾期提醒:" . $company_name . "改为" . $params[$k];
                                                                                                                                                            } else {
                                                                                                                                                                if ($k == "cron_credit_limit_invoice_second_overdue_email") {
                                                                                                                                                                    $dec .= " - 信用额账单第 2 次逾期提醒:" . $company_name . "改为" . $params[$k];
                                                                                                                                                                } else {
                                                                                                                                                                    if ($k == "cron_credit_limit_invoice_third_overdue_email") {
                                                                                                                                                                        $dec .= " - 信用额账单第 3 次逾期提醒:" . $company_name . "改为" . $params[$k];
                                                                                                                                                                    } else {
                                                                                                                                                                        if ($k == "cron_invoice_recharge_delete") {
                                                                                                                                                                            if ($params[$k] == 1) {
                                                                                                                                                                                $dec .= " -  自动删除未支付的充值账单功能启用";
                                                                                                                                                                            } else {
                                                                                                                                                                                $dec .= " -  自动删除未支付的充值账单功能关闭";
                                                                                                                                                                            }
                                                                                                                                                                            $dec .= " - 自动删除未支付的充值账单:" . $company_name . "改为" . $params[$k];
                                                                                                                                                                        } else {
                                                                                                                                                                            if ($k == "cron_invoice_recharge_delete_time") {
                                                                                                                                                                                $dec .= " - 自动删除未支付的充值账单天数:" . $company_name . "改为" . $params[$k];
                                                                                                                                                                            } else {
                                                                                                                                                                                $dec .= " - " . $k . ":" . $company_name . "改为" . $params[$k];
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
        hook("cron_config_save", ["adminid" => cmf_get_current_admin_id()]);
        active_log(sprintf($this->lang["Cron_home_editCron"], $dec));
        unset($dec);
        unset($company_name);
        $result["status"] = 200;
        $result["msg"] = lang("UPDATE SUCCESS");
        return jsonrule($result);
    }
}

?>