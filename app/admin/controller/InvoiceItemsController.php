<?php


namespace app\admin\controller;

/**
 * @title 账单项目管理
 * @group 后台账单管理
 */
class InvoiceItemsController extends AdminBaseController
{
    public function index()
    {
    }
    public function create()
    {
    }
    public function save(\think\Request $request)
    {
        $param = $request->only("id,uid,description,amount,taxed");
        $validate = new \app\admin\validate\InvoiceItemValidate();
        if (!$validate->check($param)) {
            return jsonrule($validate->getError(), 400);
        }
        try {
            db("invoice_items")->insert($param);
            return jsonrule(["status" => 200, "msg" => "ok"]);
        } catch (\Punic\Exception $e) {
            return jsonrule($e->getError(), 400);
        }
    }
    public function read($id)
    {
        $param = $this->request->param();
        $order = isset($param["order"][0]) ? trim($param["order"]) : "id";
        $sort = isset($param["sort"][0]) ? trim($param["sort"]) : "DESC";
        $where["invoice_id"] = $id;
        $where["delete_time"] = NULL;
        $rows = db("invoice_items")->field("id,invoice_id,description,amount,taxed")->order($order, $sort)->where($where)->select();
        return jsonrule(["data" => $rows, "status" => 200, "msg" => "ok"]);
    }
    public function edit($id)
    {
    }
    public function update(\think\Request $request, $id)
    {
        $data = request()->put("data");
        $items = [];
        foreach ($data as $k => $v) {
            $item = model("invoice_items")->where("id", $v["id"])->field("id,invoice_id,description,amount")->find();
            if ($id == $item["invoice_id"]) {
                $item->amount = $v["amount"];
                $item->description = $v["description"];
                $item->save();
                $items[] = $item;
            } else {
                return jsonrule(["status" => 400, "msg" => "项目与账单不匹配"]);
            }
        }
        return jsonrule(["data" => $items, "status" => 200, "msg" => "ok"]);
    }
    public function delete($id)
    {
        $rows = db("invoice_items")->delete($id);
        return jsonrule(["data" => $rows, "status" => 200, "msg" => "ok"]);
    }
}

?>