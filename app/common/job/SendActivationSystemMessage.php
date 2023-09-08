<?php


namespace app\common\job;

/**
 * 发送站内信
 * Class SendActivationSystemMessage
 * @package app\common\job
 */
class SendActivationSystemMessage
{
    public function fire(\think\queue\Job $job, $data)
    {
        $systemMessageObject = new \app\common\logic\SystemMessage();
        $data = json_decode($data, true);
        $client_ids = $data["client_ids"];
        $info = $data["info"];
        $isJobDone = $systemMessageObject->sendAction($client_ids, $info, false);
        \think\facade\Log::log(2, "SendActivationSystemMessage执行结果：" . $isJobDone);
        if ($isJobDone) {
            $job->delete();
            \think\facade\Log::log(2, date("Y-m-d H:i:s") . "任务执行成功,,已经删除!结果：" . $isJobDone);
        } else {
            \think\facade\Log::log(2, date("Y-m-d H:i:s") . "任务执行失败!");
            if (3 < $job->attempts()) {
                \think\facade\Log::log(2, date("Y-m-d H:i:s") . "删除任务!");
                $job->delete();
            } else {
                $job->release();
                \think\facade\Log::log(2, date("Y-m-d H:i:s") . "<info>重新执行!第" . $job->attempts() . "次重新执行!</info>\n");
            }
        }
    }
}

?>