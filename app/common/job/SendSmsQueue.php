<?php


namespace app\common\job;

class SendSmsQueue
{
    public function fire($job, $data)
    {
        $isJobStillNeedToBeDone = $this->checkDatabaseToSeeIfJobNeedToBeDone($data);
        if (!$isJobStillNeedToBeDone) {
            $job->delete();
        } else {
            $isJobDone = $this->doHelloJob($data);
            if ($isJobDone) {
                $job->delete();
                \think\facade\Log::record("sms_queue_info:" . $data, "info");
            } else {
                if (3 < $job->attempts()) {
                    \think\facade\Log::record("sms_queue_error:" . json_encode($data), "error");
                    $job->delete();
                }
            }
        }
    }
    private function checkDatabaseToSeeIfJobNeedToBeDone($data)
    {
        return true;
    }
    private function doHelloJob($data)
    {
        $data = json_decode($data, true);
        $phone = $data["phone"];
        $msgid = $data["msgid"];
        $uid = $data["uid"];
        unset($data["phone"]);
        $class = new \app\common\logic\Sms();
        $result = $class->sendSmsForMarerking($phone, $msgid, $data, false, $uid);
        if ($result["status"] == 200) {
            return true;
        }
        \think\facade\Log::log(2, $result["msg"]);
        return false;
    }
}

?>