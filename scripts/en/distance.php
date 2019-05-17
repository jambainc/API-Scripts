<?php
#require('header.html');

if(isset($_GET['lat'])) {
  $lat = $_GET['lat']; } else { $lat = 0; }
if(isset($_GET['long'])) {
  $long = $_GET['long']; } else { $long = 0; }

  //filter flags
if(isset($_GET['disabled'])) {
  $disabled = $_GET['disabled']; } else { $disabled = 0; }
if(isset($_GET['lz'])) {
  $lz = $_GET['lz']; } else { $lz = 0; }
if(isset($_GET['paid'])) {
  $paid= $_GET['paid']; } else { $paid = 1; }
if(isset($_GET['duration'])) {
  $duration = $_GET['duration']; } else { $duration = 5; }

// GET CURRENT TIME AND DATE IN AEST
date_default_timezone_set('Australia/Melbourne');
$dayOfWeek = intval(date("N"));
$time = date("Gis");

$keyfile = parse_ini_file('../../../.keys.ini');
$key = ($keyfile['key']);

$sql = "select distinct cast(bayid_sensor as signed) as bayid, lat, lon,
case when adj_cost is null then 0 else cast((adj_cost/100) as decimal(4,2)) end as cost,
case when description is NULL then 'No Restrictions' else description end as sign,
cast(case when duration is NULL then 0 else duration end as signed) as duration,
case when typedesc is NULL then 'No Restrictions' else typedesc end as typeDesc ,
sqrt(power(($lat-lat),2)+power(($long-lon),2)) as euclid,current_status, 
case when typedesc is NULL then 'No Restrictions' else typedesc end as typedesc,
case when (d.present/d.total) >= 0.6 or current_status = 'Present' then 'red' 
  when (d.present/d.total) < 0.6 and (d.present/d.total) >= 0.3 then 'blue' 
  when (d.present/d.total)  < 0.3 or current_status = 'Unocccupied' then 'green' end as occRate,
case when current_status = 'Present' and time_next_available is not null then time_next_available
     when current_status = 'Present' and time_next_available is  null then 'N.A'
     when current_status = 'Unoccupied' then 'Now' else 'N.A' end as time_next_available
from
tb_park_sensor_track a
left join tb_restrict b on a.restrict_id = b.id
left join (select * from master_pay_stay where adj_dow = $dayOfWeek) c on c.street_segment_id = rd_seg_id and
left(c.restriction_and_payment_display,3) = left(typedesc, 3)
inner join tb_occ_rate d on a.rd_seg_id = d.rd_seg_id
where
bayid_sensor is not NULL and disabled_flag <= $disabled and LZ_flag <= $lz
and paid_flag <= $paid and no_parking_flag = 0 and (duration is NULL or duration >= $duration)
order by euclid limit 30; ";

$sqlReturn = returnSQL($sql,$lat,$long);


function returnSQL($sql,$lat,$long)
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
    if ($num_rows > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $p2Array = array("bayid"=>$row['bayid'],"lat"=>$row['lat'],"lon"=>$row['lon'],"address"=>"N.A","traveltime"=>0,"cost"=>$row['cost'],"duration"=>$row['duration'],"sign"=>$row['typeDesc'],"status"=>$row['current_status'],"occRate"=>$row['occRate'],"nextAvailable"=>$row['time_next_available']);
            $p1Array[] = $p2Array;
            $bay_id2 = $row['bayid'];
            $lat2 = $row['lat'];
            $long2 = $row['lon'];
            $d1 = ($lat2 . "," . $long2 . "|");
            $distance = ($distance . $d1);
            $i++;
        }
        $origin = ($lat . "," . $long );

        $keyfile = parse_ini_file('../../../.keys.ini');
        $key = ($keyfile['key']);
        $call = "https://maps.googleapis.com/maps/api/distancematrix/json?&origins=".$origin."&destinations=".$distance."&key=".$key;

        $return = file_get_contents($call);
        $obj = json_decode($return);

        while($j<30) {
            $p1Array[$j]['address']=$obj->destination_addresses[$j];
            $p1Array[$j]['traveltime']=$obj->rows[0]->elements[$j]->duration->value;
            $j++;
        }
        print(json_encode($p1Array, JSON_NUMERIC_CHECK));
    }
    else {
        echo "0 results";
    }

    $res->close();
    $conn->close();
}

?>