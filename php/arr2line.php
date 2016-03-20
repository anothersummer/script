<?php
$arr = array(
    'arr0' => array(
            'crr0' => array(
                'c01' => 1,
                'c02' => 2,
               
            ),
            'crr1' => array(
                'c11' => 1,
                'c12' => 2,
               
            ),
    )
);

//输出:
// arr0 crr0 c01 1
// arr0 crr0 c02 2
// arr0 crr1 c11 1
// arr0 crr1 c12 2

// 怎么实现?

function aprint($arr,$e=null){
    $ret = null;
    $pre = $e ? "$e\t" : ""; 
    $arr = (array)$arr;
    foreach($arr as $k => $v){
        if(is_array($v)){
         $ret .= aprint($v,$k);
        }else{
          $ret .= "{$pre}$k\t$v\n";
        }   

    }   
    return $ret;
}
