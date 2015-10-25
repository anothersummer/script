<?php


declare(ticks = 1); 
 class Lib_Multijob_Callbacknew extends Lib_Multijob_Abstract {

    protected $_retryNum;                            //pc or wise
    protected $_failedFile;
    protected $_failedInfoList      = array();
    protected $_parnetCb;    
    protected $_childCb;    

    const SIGFAILED = SIGUSR1;

    public function __construct($failedFile, $parentCallback, $childCallback, $parentArgs, 
        $maxProcesses = 25, $childTimeout = 30, $usleepTime = 10000, $retryNum = 3) {
        $this->_retryNum        = $retryNum; 
        $this->_failedFile       = $failedFile;
        $this->_parentArgs      = $parentArgs;
        parent::__construct($maxProcesses, $childTimeout, $usleepTime);
        pcntl_signal(SIGALRM, array($this, "childAlarmHandler"));
        $this->_parnetCb = $parentCallback; 
        $this->_childCb = $childCallback;
        pcntl_alarm($this->childTimeout);
    }

    public function run() {
        if (!file_exists(DATA_PATH)) {
            mkdir(DATA_PATH);
        }
        $failedFile = self::getFailedFile();
        if (is_file($failedFile)) {
            $bakupFailedListCmd = sprintf("mv %s %s.%s\n", $failedFile, $failedFile, date("Y-m-d-H:i"));
            Lib_Shell::cmd_exec($bakupFailedListCmd); 
        }
        $this->mainProcess();
    }

    protected function getFailedInfo() {
        $failedFile = self::getFailedFile();
        if (!empty($this->_failedInfoList)) {
            while($hotelInfo = array_pop($this->_failedInfoList)) {
                $logInfo = json_encode($hotelInfo['data']);
                if ($hotelInfo['retry_num'] >= $this->_retryNum) {
                    file_put_contents($failedFile, "$logInfo\n", FILE_APPEND);
                    continue;
                }
                Lib_Log::info("pid $pid ${hotelInfo['retry_num']}'s time retry, data:$logInfo");
                return $hotelInfo;
            }
        }
        return null;
    }


    function generateInfo() {
        $ret = $this->getFailedInfo();
        if (empty($ret)) {
            $data = call_user_func($this->_parnetCb, $this->_parentArgs);
            if (empty($data)) {
                return null;
            }
            $ret = array('data' => $data, 'retry_num' => 0);
        }
        return $ret;
    }

    /**
     * @brief 
     * 主进程，用来读取酒店id 同时将id分配给子进程处理
     * @return  public function 
     * @author duxin01
     * @date 2014/04/16 20:20:47
    **/
    public function mainProcess() {

        while (true) {
            $hotelInfo = $this->generateInfo();
            if (empty($hotelInfo)) {
                if (count($this->currentJobs) > 0) {
                    usleep($this->usleepTime);
                    continue;
                }
                else {
                    break;
                }
            }
            while (count($this->currentJobs) >= $this->maxProcesses) {
                //Lib_Log::debug("Maximum children allowed, waiting...");
                usleep($this->usleepTime);
            }   
            $this->createChildJob($hotelInfo);
        }
    }

    protected function createChildJob($hotelInfo) {
        $pid = $this->launchJob($hotelInfo);
        $this->_pid2ChildData[$pid] = $hotelInfo;
    }

    /**
     * @brief 
     * 子进程处理函数
     * 子进程返回非0值，则会重试 
     * @see 
     * @note 
     * @author duxin01
     * @date 2014/04/17 16:19:59
    **/
    public function childJob($hotelInfo) {
        $pid = posix_getpid();
        $loginfo = json_encode($hotelInfo);
        Lib_Log::debug("child job begin, pid:$pid, input data: $logInfo");
        $ret = call_user_func($this->_childCb, $hotelInfo['data']);
        Lib_Log::debug("child job end, pid:$pid, ");
        if ($ret != 0) {
            Lib_Log::debug("child job failed!pid:$pid return $ret");
            exit ($ret);
        }
    }

    public function childSignalHandler($signo, $pid = null, $status = null)
    {
        $pid = pcntl_waitpid(-1, $status, WNOHANG);
        while ( $pid > 0 ) {
            if ($pid && isset($this->currentJobs[$pid])) {
                $exitCode = pcntl_wexitstatus($status);
                //子进程失败 
                if ($exitCode != 0) {
                    $this->pushFaildData($pid);
                }
                unset($this->currentJobs[$pid]);
            } else if ($pid) {
                Lib_Log::info("..... Adding {$pid} to the signal queue .....");
                $this->signalQueue[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        return true;
    }

    public function parentJob() {
            
    }

    /**
     * @brief 
     * 定时器，用来检测是否有子进程超时
     * 同时增加超时控制
     * @see 
     * @note 
     * @author duxin01
     * @date 2014/04/17 13:32:38
    **/
    public function childAlarmHandler($signo)
    {
        foreach ($this->currentJobs as $pid => $startTime){
            if (time() - $startTime < $this->childTimeout) {
                continue;
            }
            Lib_Log::warn("child timeout, kill -9 {$pid}");
            $this->pushFaildData($pid); 
            posix_kill($pid, SIGKILL);
            // clear pid of current jobs
            unset ($this->currentJobs[$pid]);   
        }
        pcntl_alarm($this->childTimeout);//重新设置闹钟
        return true;
    }   

    protected function pushFaildData($childPid) {
        if (!isset($this->_pid2ChildData[$childPid])) {
            return;
        }
        $faildData = $this->_pid2ChildData[$childPid];
        $faildData['retry_num'] ++;
        $this->_failedInfoList[] = $faildData;
        unset ($this->_pid2ChildData[$childPid]);
    }

    protected function getFailedFile() {
        $failedFile = DATA_PATH . $this->_failedFile;
        return $failedFile;
    }

}

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
?>
