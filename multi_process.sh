#!/bin/bash -x

# by hotsnow

# trap
trap_exit() { kill -15 0;}
trap 'trap_exit; exit 2' 1 2 3 15

# process number
process=8

# parent fifo, parent get idle from child
pf=/tmp/parent.fifo
#[ -p "$pf" ] || { mkfifo $pf; }
rm -f $pf
mkfifo $pf

# child fifo, send job to child
cf=/tmp/child.fifo
rm -f $cf
mkfifo $cf

# sub child
sub_read() {
        local child_num=$1
        # send first signal to parent, child start
        echo $child_num > $pf

        while true; do
                local job=`head -n1 $cf`        # get job from parent
                # 对于几个同时在等待读取fifo的子进程来说,某个子进程读取到这个fifo,那么其他子进程都读取不到,返回一个空.必须重新读一次
                # 子进程间竞争工作任务失败,子进程数量越多,失败几率越大
                # 但是子进程一般不会同时在等待任务,所以一般不会存在大量子进程在竞争任务.
                # 同时每个任务时间越长,子进程之间的竞争就越会少
                [ -z "$job" ] && { continue; }
 
                [ "$job" = "end" ] && {
                        # 没有这个sleep可能会导致72行: "echo end > $cf" 出错:
                        # ./multi_read_fifo.sh: line 75: /tmp/child15.fifo: Interrupted system call
                        # 可能原因看最后
                        sleep 0.1
                        break
                }       # child exit
 
                # do my job here
                {
                        echo $job $child_num
                        sleep 1
                }

                echo $child_num > $pf   # send signal to parent, child $i idle
        done
 
        echo "child end"
}
 
# child start
for i in `seq $process`; do
    ( sub_read $i; exit 0; ) &  # $i only a child flag
done
sleep 1
 
# jobs
jobs=50
# jobs assign
while true; do
        all_sig=(`cat $pf`)
        for sig in ${all_sig[@]}; do
                echo "get sig: $sig"
                if [ $jobs -gt 0 ]; then
                        echo "job: $jobs" > $cf
                        let jobs--
                else
                        echo "end" > $cf
                        let process--
                        echo "child decrease: $process"
                fi
                # 任务太快可能会出现任务丢失的情况,必须sleep
                # 也就是循环太快,在一个很短的时间片里,两个任务都发出去了.某个一个子进程接收这个任务,但只head出第一个执行,剩下的那个就丢失了.
                # 更具体的来说就是:
                # 1. 开始时某个子进程等待读fifo
                # 2. 父进程分发一个任务(写这个fifo)
                # 3. 子进程开始读取这个fifo
                # 4. 父进程就已经进入下一个循环继续向这个管道发送任务,这时子进程可能还没读取完,或者说读管道还没来得及关闭.也就是相当于父进程向一个子进程同时发送了两个任务
                # 5. 子进程的读fifo里有两个任务,但程序限制子进程只head -n1 接受一个任务,那么另一个就丢失了
                # 完全个人理解,不知理解得是否有问题?
                sleep 0.1
        done
 
        [ $process -eq 0 ] && {
                echo "parent end"
                break
        }
done

sleep 1
ps -ef |grep multi
rm -f $pf
 
echo "parent end"

exit 0
