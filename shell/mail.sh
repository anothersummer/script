$mailbin -s "$(echo -e "$subject \nContent-Type: text/html;charset=gbk2312")" $address < $dest -- -f$from
