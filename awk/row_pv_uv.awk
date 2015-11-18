#

BEGIN{
  FS="\t"
}{
    cuid=$8
    oclientos=tolower($3)
    ofrom=tolower($4)
    oua=tolower($9)
    ourl=tolower($10)
    oresid=$2
    osrcfrom=tolower($7)

   type="nulltype"
   app="nullapp"
   os="nullos"

    if($1=="sak_detail" || $1 == "sak_bookable"){
       type=$1
        gsub("sak_","",type);
        if(oresid == "03"){
            app="shoubai"
        }else if(oresid == "98"){
            app = "bainuo"
        }else if(oresid == "99"){
            app = "lvyou"
        }else if(oresid == "31"){
            os="webapp"
            if(osrcfrom == "bainuo_hotel"){
                app="bainuo"
            }else if($6 == "wallet_hotel"){
                app="wallet"
            }else if($6 == "lvyou"){
                app="lvyou"
            }else if(osrcfrom ~ /^kuang/){
                app="shoubai"
            }else if(osrcfrom ~ /^wise/){
                app="webapp"
            }
            
        }else{
            app="map"
        }
        
        if(tolower(oclientos) ~ /android/){
            os="android"
        }else if(tolower(oclientos) ~ /iphone|ipad|ipod/) {
            os="iphone"
        }
        
    }else if($1=="ota_order_info" ){
        type="booking"
        if(ofrom=="android"){
            app="map"
            os="android"
        }else if(ofrom=="iphone"){
            app="map"
            os="iphone"
        }else if(ofrom=="maponline"){
            app="map"
            os="maponline"
        }else if(ofrom=="kuang_android"){
            app="shoubai"
            os="android"
        }else if(ofrom=="kuang_iphone"){
            app="shoubai"
            os="iphone"
        }else if(ofrom=="kuang_webapp" || ofrom=="kuang_maponline"){
            app="shoubai"
            os="webapp"
        }else if(ofrom=="lvyou_maponline"){
            app="lvyou"
            os="webapp"
        }else if(ofrom=="wallet_maponline"){
            app="map"
            os="iphone"
        }else if(ofrom=="bainuo_maponline"){
            app="bainuo"
            os="webapp"
        }
    }else if ($1 == "ur"){
        app="map"
        type="booking"
        if(ourl ~ /device_from=pc/){
            os="pc"
        }else if(ourl ~ /device_from=android/ || oua ~ /android/){
            os="android"
        }else if(ourl ~ /device_from=iphone/ || oua ~ /iphone|ipad|ipod/ ){
            os="iphone"
        }else if(ourl ~ /device_from=webapp|device_from=maponline/){
            os="webapp"
        }
    }
    key=type"\t"app"\t"os
    cuidkey=key"\t"cuid
    a[key]
    pv[key]++
    if(cuidkey  in cuidarr){

    }else{
        uv[key]++
        cuidarr[cuidkey]=1
    }
}END{
  for(k in a){
    print k"\t"pv[k]"\t"uv[k]
  }

}
