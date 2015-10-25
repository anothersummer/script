#!/bin/bash

# by hotsnow

# trap
trap_exit() { kill -15 0;}
trap 'trap_exit; exit 2' 1 2 3 15

# process number
process=9

# parent fifo, parent get idle from child
pf=/tmp/parent.fifo
#[ -p "$pf" ] || { mkfifo $pf; }
rm -f $pf
mkfifo $pf

# child fifo, send job to child
for i in `seq $process`; do
        eval c$i=/tmp/child${i}.fifo
done
for i in `seq $process`; do
        rm -f /tmp/child$i.fifo
        mkfifo /tmp/child$i.fifo
done

# sub child
sub_read() {
        local child_num=$1
        eval child_fifo=\$c$child_num
        # send first signal to parent, child start
        echo $child_num > $pf

        while true; do
                #while read line; do
                local job=`head -n1 $child_fifo`
                [ "$job" = "end" ] && {
                        # 没有这个sleep可能会导致72行: "echo end > $cf" 出错:
                        # ./multi_read_fifo.sh: line 75: /tmp/child15.fifo: Interrupted system call
                        # 可能原因看最后
                        sleep 0.1
                        rm -f $child_fifo
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
    ( sub_read $i; exit 0; ) &  # $i is the child flag
done
sleep 1
 
# jobs
jobs=50
# jobs assign
while true; do
        all_sig=(`cat $pf`)
        for sig in ${all_sig[@]}; do
                echo "get sig: $sig"
                eval cf=\$c$sig # child fifo
                if [ $jobs -gt 0 ]; then
                        echo "job: $jobs" > $cf
                        let jobs--
                else
                        echo "end" > $cf
                        let process--
                        echo "child decrease: $process"
                        #break  # parent end all jobs assigned
                fi
        done
 
        [ $process = 0 ] && {
                echo "parent end"
                break
        }
done

sleep 2
ps -ef |grep bash
rm -f $pf
 
echo "parent end"

exit 0

: <<!
Interrupted system call 出错可能的原因:
[url]http://www.linuxforums.org/forum/linux-programming-scripting/37618-tmp-sh-np-xxxxxx-interrupted-system-call.html[/url]
 
The only possible explanation that I can think of is that the shell first forks the innermost expression (the process substitution expression), which runs to its end before its timeslice ends, and that the middle expression (the command substitution), is at that time still blocking on opening the named FIFO. It then receives the ECHLD signal from the innermost expression that exited, which causes it to return EINTR before it the open can finish.
!
