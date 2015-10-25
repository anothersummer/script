<?php
declare(ticks = 1);
abstract class Lib_Multijob_Abstract
{
    public $maxProcesses = 25;
    /**
     * 子进程id列表
     * @var array
     */
    protected $currentJobs = array();
    /**
     * 信号队列
     * @var array
     */
    protected $signalQueue = array();
    /**
     * 父进程id
     * @var number
     */
    protected $parentPID;
    /**
     * 子进程的超时时间，秒
     * @var number
     */
    protected $childTimeout = 30;
    /**
     * 父进程等待子进程的时间间隔
     * @var number
     */
    protected $usleepTime = 10000;
    
    protected $cache_key_prefix = "";
    protected $file_lock_handle = "";
    /**
     * 构造函数
     * @param number $maxProcesses 最大进程数
     * @param number $childTimeout 子进程超时时间
     * @param number $usleepTime 父进程检查子进程的时间间隔
     * @return Lib_Multijob
     */
    public function __construct($maxProcesses = 25, $childTimeout = 30, $usleepTime = 10000)
    {
        //echo "constructed \n";
        Lib_Log::info(get_class($this)."_constructed");
        //cache key prefix
        $this->cache_key_prefix = strtolower("multijob_".get_class($this))."_".date("YmdHis");
        
        //init val
        $this->maxProcesses = (int)$maxProcesses;
        $this->childTimeout = (int)$childTimeout;
        $this->usleepTime = (int)$usleepTime;
        
        //获取父进程id
        $this->parentPID = getmypid();
        
        //子进程结束时, 父进程会收到这个信号
        pcntl_signal(SIGCHLD, array($this, "childSignalHandler"));
        //用来立即结束程序的运行. 本信号不能被阻塞、处理和忽略
        //pcntl_signal(SIGKILL, array($this, "childSignalHandler"));
        //程序结束(terminate)信号, 与SIGKILL不同的是该信号可以被阻塞和处理
        pcntl_signal(SIGTERM, array($this, "childSignalHandler"));
        
        //闹钟信号，利用闹钟信实现子进程超时退出
        pcntl_signal(SIGALRM, array($this, "childAlarmHandler"));
        //设置闹钟时间间e
        pcntl_alarm($this->childTimeout);
    }
    
    public function __destruct() {
        if (!empty($this->file_lock_handle))
            fclose($this->file_lock_handle);
    }
    
    /**
     * Run the Daemon
     */
    public function run()
    {
        try{
            //echo "Running \n";
            Lib_Log::info(get_class($this)."_Running...");
            $childDataArr = (array)$this->parentJob();
            foreach ( $childDataArr as $childData ) {
                while ( count($this->currentJobs) >= $this->maxProcesses ) {
                    //Lib_Log::debug("Maximum children allowed, waiting...");
                    usleep($this->usleepTime);
                }
                //Lib_Log::debug("currentJobs count is ".count($this->currentJobs));
                $launched = $this->launchJob($childData);
            }
            
            //Wait for child processes to finish before exiting here 
            while ( count($this->currentJobs) ) {
                //Lib_Log::debug("Waiting for current jobs to finish...");
                usleep($this->usleepTime);
            }
        }catch (Exception $e){
            Lib_Log::error($e->getMessage());
        }
    }
    
    /**
     * Launch a job from the job queue
     */
    protected function launchJob($childData)
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            //Problem launching the job 
            Lib_Log::error('Could not launch new job, exiting');
            return false;
        } else if ($pid) {
            $t = time();
            // Parent process 
            // Sometimes you can receive a signal to the childSignalHandler function before this code executes if
            // the child script executes quickly enough! 
            // 
            $this->currentJobs[$pid] = time();
            
            // In the event that a signal for this pid was caught before we get here, it will be in our signalQueue array
            // So let's go ahead and process it now as if we'd just received the signal
            if (isset($this->signalQueue[$pid])) {
                Lib_Log::info("found $pid in the signal queue, processing it now"); 
                $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]);
                unset($this->signalQueue[$pid]);
            }
        } else {
            try
            {
                //Forked child, do your deeds.... 
                $exitStatus = 0; //Error code if you need to or whatever
                $this->childJob($childData);
                exit($exitStatus);
            }catch (Exception $e){
                exit($e->getCode());
                Lib_Log::error($e->getMessage());
            }
        }
        return $pid;
    }
    
    /**
     * 闹钟程序
     * @param nmber $signo
     * @return boolean
     */
    public function childAlarmHandler($signo)
    {
        foreach ($this->currentJobs as $pid=>$startTime){
            if (time() - $startTime >= $this->childTimeout) {
                Lib_Log::info("kill -9 {$pid}");
                posix_kill($pid, SIGKILL);
            }
        }
        pcntl_alarm($this->childTimeout);//重新设置闹钟
        return true;
    }
    
    /**
     * 信号处理函数
     * @param number $signo
     * @param number $pid
     * @param number $status
     * @return boolean
     */
    public function childSignalHandler($signo, $pid = null, $status = null)
    {
        //If no pid is provided, that means we're getting the signal from the system.  Let's figure out
        //which child process ended 
        if (!$pid) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        
        //Make sure we get all of the exited children 
        while ( $pid > 0 ) {
            if ($pid && isset($this->currentJobs[$pid])) {
                $exitCode = pcntl_wexitstatus($status);
                if ($exitCode != 0) {
                    Lib_Log::info("{$pid} exited with status " . $exitCode);
                }
                unset($this->currentJobs[$pid]);
            } else if ($pid) {
                //Oh no, our job has finished before this parent process could even note that it had been launched!
                //Let's make note of it and handle it when the parent process is ready for it
                Lib_Log::info("..... Adding {$pid} to the signal queue .....");
                $this->signalQueue[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        return true;
    }
    
    /**
     * 获取缓存key
     * @param string $key
     * @return string
     */
    public function getSharedKey($key){
        return $this->cache_key_prefix."_".$key;
    }
    
    /**
     * 获取缓存变量
     * @param string $key
     * @param boolean|resource $fp 布尔值决定是否加锁 返回的因为为文件句柄
     * @return arr|string
     */
    public function getSharedVal($key, &$fp = false)
    {
        if($fp){
            do{
                $fp = fopen("/tmp/".$this->cache_key_prefix, "w+");
            }while (!$fp);
            if($fp){
                flock($fp, LOCK_EX);
            }
        }

        $key = $this->getSharedKey($key);
        return Lib_Fcache::get($key);
    }
    
    /**
     * 设置缓存变量
     * @param string|arr $key
     * @param string $val
     * @param string $lock 加锁
     */
    public function setSharedVal($key, $val = "", &$fp = false)
    {
        $key = $this->getSharedKey($key);
        Lib_Fcache::set($key, $val, 0, false);
        if($fp){
            flock($fp, LOCK_UN);
            fclose($fp);
            $fp = null;
        }
    }
    
    /**
     * 返回一个数组，以供子进程去轮询
     * @return arr
     */
    abstract public function parentJob();
    
    /**
     * 对父进程提供的信息做一些事情
     */
    abstract public function childJob($childData);
} 
