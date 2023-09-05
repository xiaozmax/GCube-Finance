<?php

namespace app\admin\model;

class TicketDepartmentAdminModel
{
    public function getAllow()
    {
        $admin = cmf_get_current_admin_id();
        if (empty($admin)) {
            return [];
        }
        $result = \think\Db::name("ticket_department_admin")->field("dptid")->where("admin_id", $admin)->select()->toArray();
        return array_column($result, "dptid");
    }
    public function check($dptid = 0)
    {
        $admin = cmf_get_current_admin_id();
        if (empty($admin)) {
            return false;
        }
        if ($admin == 1) {
            return true;
        }
        $result = \think\Db::name("ticket_department_admin")->where("admin_id", $admin)->where("dptid", $dptid)->find();
        return !empty($result);
    }
}

?>
