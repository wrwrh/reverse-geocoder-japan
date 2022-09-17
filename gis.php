<?php
//国土地理院位置参照情報（街区レベル）対応逆ジオロケーション
//ini_set("display_errors", 'On');
//error_reporting(E_ALL);

$lat = (float)filter_input(INPUT_GET,'lat',FILTER_SANITIZE_SPECIAL_CHARS);
$lng = (float)filter_input(INPUT_GET,'lng',FILTER_SANITIZE_SPECIAL_CHARS);

//日本以外除外
if($lat < 20 || $lat > 46 ||$lng < 122 || $lng > 154) return;

//$time_start = microtime(true);
list($result,$max) = mainLoop($lat,$lng);
//var_dump(microtime(true) - $time_start);

if($result){
    //出力の構築
    $max = ceil($max);
    $arr = array("accuracy"=>$max,"geo"=>$result);
    $json = json_encode($arr);
    echo($json . "\n");
}

function mainLoop($lat,$lng){
    $db = new SQLite3('gis.db',SQLITE3_OPEN_READONLY);
    $stmt = $db->prepare('SELECT Latitude,Longitude,Prefecture,City,District,Street,Numbers FROM detail WHERE (Latitude BETWEEN ? AND ?) AND (Longitude BETWEEN ? AND ?)');
    $stmt2 = $db->prepare('SELECT Latitude,Longitude,Prefecture,City,District FROM simple WHERE (Latitude BETWEEN ? AND ?) AND (Longitude BETWEEN ? AND ?)');

    //10km四方で日本国内はだいたいOK
    //対象の緯度経度を中心として、東西と南北の四角形内に該当するデータを抽出する
    $arr = array(500, 3000, 10000);
    foreach($arr as $MaxLength){
        $sqlResult = FetchAddr($lat, $lng, $stmt, $MaxLength);
        $max = INF;
        $minIndex = INF;
        //見つからなかった場合、市区町村レベルも
        if(count($sqlResult) == 0 && $MaxLength >= 3000){
            $sqlResult = FetchAddr($lat, $lng, $stmt2, $MaxLength);
            //var_dump($sqlResult);
        }
        for($i = 0; $i < count($sqlResult); $i++)
        {
            $tmpLength = calcLength($lat, $lng, $sqlResult[$i]->lat, $sqlResult[$i]->lng);
            if ($max >= $tmpLength)
            {
                $max = $tmpLength;
                $minIndex = $i;
            }
        }
        //1ループで見つからなかった場合次へ
        if(is_infinite($minIndex)) {
            continue;
        }
        else{
            $db->close();
            return [$sqlResult[$minIndex],$max];
        } 
    }
    $db->close();
    return null;
}

//SQLの回答
class SQLResult
{
    public $lat;
    public $lng;
    public $pref;
    public $city;
    public $district;
    public $street;
    public $numbers;
}

//２点間の距離を求める
function calcLength($startLat, $startLng, $endLat, $endLng)
{
    //近似式
    $pi2 = 0.018;// Pi÷180
    $Rx = 6371000.0;    //地球平均半径
    $startlatPi = $startLat*$pi2;
    $endlatPi = $endLat*$pi2;
    $d = $Rx*acos(cos($startlatPi)*cos($endlatPi)*cos($endLng*$pi2 - $startLng*$pi2) + sin($startlatPi)*sin($endlatPi));
    return $d;


    //地球の丸みを考慮しない(距離比較ならこれで十分)
    //return sqrt(pow($endLat - $startLat,2) + pow($endLng - $startLng,2));
/*    
    //ヒュベニの式、距離はメートルで算出される
    $Rx = 6378137;    //赤道半径
    $pi2 = 0.017453;// Pi÷180
    $ecc2 = 0.006694;  //第２離心率
    $di = ($endLat - $startLat) * $pi2;
    $dk = ($endLng - $startLng) * $pi2;
    $i = ($startLng + $endLng) / 2 * $pi2;
    $w = sqrt(1 - $ecc2 * pow(sin($i), 2));
    $m = $Rx * (1 - $ecc2) / pow($w, 3);
    $n = $Rx / $w;
    $district = sqrt(pow($di * $m, 2) + pow($dk * $n * cos($i), 2));
    return $district;
  */  
}

//開始点の緯度経度と角度・距離から到達点の緯度経度を求める関数
function calcLatLng($startLat, $startLng, $length, $angle)
{
    //ヒュベニの式を使用し、開始点の緯度経度に対し、距離と角度をもとに終点の緯度経度を取得する。
    //角度は北を０度とし、東は９０度となる。
    $Rx = 6378137;                //赤道半径
    $ecc2 = 0.006694;  //第２離心率
    $pi2 = 0.0174532;  // Pi÷180
    $pi3 = 57.29578;     //180÷Pi

    $wt = sqrt(1 - $ecc2 * pow(sin(($startLat * $pi2)), 2));
    $mt = $Rx * (1 - $ecc2) / pow($wt, 3);
    $dit = $length * cos($angle * $pi2) / $mt;
    $tmpLat = $startLat * $pi2 + $dit / 2;
    $w = sqrt(1 - $ecc2 * pow(sin($tmpLat), 2));
    $m = $Rx * (1 - $ecc2) / pow($w, 3);
    $di = $length * cos($angle * $pi2) / $m;
    $endLat = $startLat + $di * $pi3; //計算した緯度
    $n = $Rx / $w;
    $dk = $length * sin(($angle * $pi2)) / ($n * cos($tmpLat));
    $endLng = $startLng + $dk * $pi3; //計算した経度

    $latlng = array($endLat, $endLng);
    return $latlng;
}
/*
//DBでは緯度8桁、経度9桁の整数で保持している場合(位置参照情報では少数点以下6桁で記録)
function toInt($s,$count){
    return $s;
    //return (int)str_pad(str_replace('.','',(string)round($s,6)), $count, '0', STR_PAD_RIGHT);
}
*/
//最大と最小の緯度経度を算出、その範囲内のデータをSQLiteファイルから抽出
function FetchAddr($lat,$lng,$stmt,$length)
{
    //$stmt->reset()//PHP7.2以前用
    $maxLat = calcLatLng($lat, $lng, $length, 0)[0];
    $maxLng = calcLatLng($lat, $lng, $length, 90)[1];
    $minLat = calcLatLng($lat, $lng, $length, 180)[0];
    $minLng = calcLatLng($lat, $lng, $length, 270)[1];

    $stmt->bindValue(1, $minLat ,SQLITE3_FLOAT);
    $stmt->bindValue(2, $maxLat ,SQLITE3_FLOAT);
    $stmt->bindValue(3, $minLng ,SQLITE3_FLOAT);
    $stmt->bindValue(4, $maxLng ,SQLITE3_FLOAT);
    $result = $stmt->execute();
    $sqlResult = array();
    $cnt = 0;
    while ($sdr = $result->fetchArray(SQLITE3_NUM)) {
        $sqlResult[$cnt] = new SQLResult();
        $sqlResult[$cnt]->lat = $sdr[0];
        $sqlResult[$cnt]->lng = $sdr[1];
        $sqlResult[$cnt]->pref = $sdr[2];
        $sqlResult[$cnt]->city = $sdr[3];
        $sqlResult[$cnt]->district = $sdr[4];
        if(count($sdr)==7){
            $sqlResult[$cnt]->street = $sdr[5];
            $sqlResult[$cnt]->numbers = $sdr[6];
        }
        $cnt++;
    }
    return $sqlResult;
}
?>
