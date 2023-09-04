<?php


namespace app\admin\controller;

class ViewStatisticsController extends ViewAdminBaseController
{
    public function display($data)
    {
        $arr = preg_split("/\\//", $_SERVER["REDIRECT_URL"]);
        return $this->view($arr[2], $data);
    }
    public function annualstatistics(\think\Request $request)
    {
        $result["data"] = "test";
        return $this->display($result);
    }
    public function newcustomer(\think\Request $request)
    {
        $result["data"] = "test";
        return $this->display($result);
    }
    public function productrevenue(\think\Request $request)
    {
        $result["data"] = "test";
        return $this->display($result);
    }
    public function revenueranking(\think\Request $request)
    {
        $result["data"] = "test";
        return $this->display($result);
    }
}

?>