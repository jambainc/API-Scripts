<?php

mb_language('uni');
mb_internal_encoding('UTF-8');


// SET PARAMETERS

if(isset($_GET['lang'])) {
  $lang = $_GET['lang']; } else { $lang = 1; }
if(isset($_GET['bayid'])) {
  $bayid = $_GET['bayid']; } else { $bayid = 0; }
if($_GET['lat']) {
  $lat = $_GET['lat']; } else { $lat = 0; }
if($_GET['long']) {
  $long = $_GET['long']; } else { $long = 0; }

// GET CURRENT TIME AND DATE IN AEST
date_default_timezone_set('Australia/Melbourne');
$dayOfWeek = intval(date("N"));
$time = date("Gis");

$keyfile = parse_ini_file('../../../.keys.ini');
$key = ($keyfile['key']);


$sql = "select distinct
cast(bayid_sensor as signed) as bayid,lat, lon, 0 as traveltime,
case when typedesc is NULL then (select translate from db_gopark.tb_translate where sign = 'No Restrictions') else e.translate end as typeDesc,
description, duration, fromDayOfWeek as adjFromDay, toDayOfWeek as adjToDay, current_status as status,
case when adj_cost is null then 0 else cast((adj_cost/100) as decimal(4,2)) end as cost,
case when (d.present/d.total) >= 0.6 or current_status = 'Present' then 'red' 
     when (d.present/d.total) < 0.6 and (d.present/d.total) >= 0.3 then 'blue' 
     when (d.present/d.total)  < 0.3 or current_status = 'Unocccupied' then 'green' end as occRate,
case when current_status = 'Present' and time_next_available is not null then time_next_available 
     when current_status = 'Present' and time_next_available is  null then 'N.A'
     when current_status = 'Unoccupied' then 'Now' else 'N.A' end as nextAvailable,
case when e.desription is null then (select translate from db_gopark.tb_translate where sign = 'No Restrictions') else e.translate end as humanDesc
from
tb_park_sensor_track a
left join tb_restrict b on a.restrict_id = b.id
inner join tb_occ_rate d on a.rd_seg_id = d.rd_seg_id
left join (select * from master_pay_stay where adj_dow = $dayOfWeek ) c on c.street_segment_id = a.rd_seg_id and
left(c.restriction_and_payment_display,3) = left(typedesc, 3)
left join tb_translate e on typedesc = e.sign
where
bayid_sensor  = $bayid ";

$sqlReturn = returnSQL_time($sql,$lat,$long);

function returnSQL($sql)
{
    // Create connection
    $config = parse_ini_file('../../../.config.ini');
    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['db']);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    mysqli_set_charset($conn, "utf8");
    mysqli_query($conn, "SET character_set_results=utf8");

    $result = mysqli_query($conn, $sql);
    $num_rows = mysqli_num_rows($result);
    $i = 1;
    if ($num_rows > 0) {
        echo "[";
        while($row = mysqli_fetch_assoc($result)) {
            if($i<$num_rows) {
                echo json_encode($row, JSON_NUMERIC_CHECK ) . ",";
            }
            else {
                echo json_encode($row, JSON_NUMERIC_CHECK );
            }
            $i++;
        }
        echo "]";
    }
    else {
        echo "0 results";
    }

    $res->close();
    $conn->close();
}

function returnSQL_time($sql,$lat,$long)
{
    // Create connection
    $config = parse_ini_file('../../../.config.ini');
    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['db']);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    mysqli_set_charset($conn, "utf8");
    mysqli_query($conn, "SET character_set_results=utf8");

    $result = mysqli_query($conn, $sql);
    $num_rows = mysqli_num_rows($result);
    $i = 1;
    $j = 0;

    $p1Array = array();

    $distance = "";

    while($row = mysqli_fetch_assoc($result)) {
        $p2Array = array("bayid"=>$row['bayid'],"lat"=>$row['lat'],"lon"=>$row['lon'],"traveltime"=>"0","cost"=>$row['cost'],"duration"=>$row['duration'],"sign"=>$row['typeDesc'],"status"=>$row['status'],"occRate"=>$row['occRate'],"nextAvailable"=>$row['nextAvailable'],"humanDesc"=>$row['humanDesc']);
        $lat2 = $row['lat'];
        $long2 = $row['lon'];
        $distance = ($lat2 . "," . $long2);
        $origin = ($lat . "," . $long );

        $keyfile = parse_ini_file('../../../.keys.ini');
        $key = ($keyfile['key']);
        $call = "https://maps.googleapis.com/maps/api/distancematrix/json?&origins=".$origin."&destinations=".$distance."&key=".$key;


        $return = file_get_contents($call);
        $obj = json_decode($return);

        $p2Array['traveltime']=$obj->rows[0]->elements[0]->duration->value;

        print("[" . json_encode($p2Array, JSON_NUMERIC_CHECK) . "]");
    }

    $res->close();
    $conn->close();
}

?>