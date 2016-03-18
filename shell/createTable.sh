#!/bin/sh
fields=`cat <<EOF
id  团单id
user_id 用户id
time    时间，与支付时间一致
deal_id 团单id
status  支付状态，该表均为2，表示支付成功
title   团单名称
address_id  地址id
price   单价
money   用户实际支付金额
count   购买份数
pay_type    支付渠道类型
serial_id   已废弃
purchase_vouchers_ids   已废弃
delivery_type   已废弃
order_serial    已废弃
bank_type   已废弃
deal_image  已废弃
expire_time 已废弃
deal_tinyurl    已废弃
total_money 订单总流水
pay_time    支付时间
deal_option 已废弃
award   已废弃
meta    已废弃
mobile  手机号
pay_record  已废弃
area_id 下单城市id
adjust_money    已废弃
delivery_cost   已废弃
gift_card_money 抵用券金额
gift_card_money_cnt 抵用券使用人数
gift_card_money_cnt_24  已废弃
update_time 更新时间，取系统时间
region_id   大区id
deal_area_id    团单所属城市id
cid_first   一级品类
cid_second  二级品类
channel 渠道
deal_cnt    团单售卖人数
deal_cnt_24 已废弃
discount    优惠金额
discount_cnt    当天优惠累计
discount_cnt_24 已废弃
deal_city_cnt   团单的售卖城市数
contract_price  合同价
gross_profit    毛利率
gross_profit_24 已废弃
cpm 毛利率
cpm_24  已废弃
sale_type   已废弃
new_user    是否新客，1为新客，0为老客
new_user_cnt    新客购买
new_user_cnt_24 已废弃
os_id   终端类型
user_cnt    已废弃
user_cnt_24 已废弃
user_mobile_cnt 购买用户数
user_mobile_cnt_24  已废弃
old_user_mobile_cnt 老客购买用户数
old_user_mobile_cnt_24  已废弃
discount_detail 优惠详情，json字串
EOF
`
prifix="CREATE TABLE \`nm_realtime_order\`";
surfix=");"
echo $prifix
echo  "$fields" |
while read line 
do
    #echo $line
    en=`echo $line | awk  '{print $1}'`
    cn=`echo $line | awk  '{print $2}'`
    if [[ $en =~ 'id' ]];then
        str="\`$en\` bigint(20) NOT NULL DEFAULT '0' COMMENT '$cn'"
    else
        str="\`$en\` varchar(255) NOT NULL DEFAULT '' COMMENT '$cn'"
    fi
    echo $str
done 
echo $surfix

