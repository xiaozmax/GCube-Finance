<?php


namespace app\api\controller;

class ProductController
{
    public function proInfo()
    {
        $param = request()->param();
        if (isset($param["pids"])) {
            if (!is_array($param["pids"])) {
                $pids = [$param["pids"]];
            } else {
                $pids = $param["pids"];
            }
        } else {
            $pids = [];
        }
        $logic = new \app\common\logic\Product();
        $infos = $logic->getInfoCache();
        if (empty($infos)) {
            $logic->updateInfoCache();
            $infos = $logic->getInfoCache();
        }
        if (!empty($pids[0])) {
            $infos = array_filter($infos, function ($value) use($pids) {
                if (!in_array($value["id"], $pids)) {
                    return false;
                }
                return true;
            });
            $infos = array_values($infos);
            if (empty($infos)) {
                $logic->updateInfoCache();
                $infos = $logic->getInfoCache();
                $infos = array_filter($infos, function ($value) use($pids) {
                    if (!in_array($value["id"], $pids)) {
                        return false;
                    }
                    return true;
                });
                $infos = array_values($infos);
            }
        }
        $currency = \think\Db::name("currencies")->where("default", 1)->value("code");
        $data = ["info" => $infos, "currency" => $currency];
        return json(["status" => 200, "msg" => "请求成功", "data" => $data]);
    }
    public function proDetail()
    {
        $param = request()->param();
        if (isset($param["pids"])) {
            if (!is_array($param["pids"])) {
                $pids = [$param["pids"]];
            } else {
                $pids = $param["pids"];
            }
        } else {
            $pids = \think\Db::name("products")->column("id") ?: [];
        }
        $logic = new \app\common\logic\Product();
        $concurrent = $logic->concurrent;
        if ($concurrent < count($pids)) {
            return json(["status" => 400, "msg" => "商品数量过多,请分批请求,最大请求数量为" . $concurrent . "个"]);
        }
        $detail = [];
        foreach ($pids as $pid) {
            $tmp = $logic->getDetailCache($pid);
            if (empty($tmp)) {
                $logic->updateDetailCache([$pid]);
            }
            $tmp = $logic->getDetailCache($pid);
            $detail[$pid] = $tmp[$pid];
        }
        $data = ["detail" => $detail];
        return json(["status" => 200, "msg" => "请求成功", "data" => $data]);
    }
}

?>