<?php


namespace app\admin\controller;

class LoginController extends \think\Controller
{
    public function adminLogin()
    {
        if ($this->request->isPost()) {
            $a = $this->request->module();
            $a .= $this->request->controller();
            $a .= $this->request->action();
            return $a;
        }
        return json(["status" => 400, "msg" => "请求错误"]);
    }
}

?>