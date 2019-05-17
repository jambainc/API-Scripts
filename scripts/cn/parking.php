<?php

mb_language('uni'); 
mb_internal_encoding('UTF-8');

// Default Query Strings

if(isset($_GET['lang'])) {
  $lang = $_GET['lang']; } else { $lang = 1; }
if(isset($_GET['bayid'])) {
  $bayid = $_GET['bayid']; } else { $bayid = 0; }

//filter flags
if(isset($_GET['disabled'])) {
  $disabled = $_GET['disabled']; } else { $disabled = 0; }
if(isset($_GET['lz'])) {
  $lz = $_GET['lz']; } else { $lz = 0; }
if(isset($_GET['paid'])) {
  $paid= $_GET['paid']; } else { $paid = 1; }
if(isset($_GET['duration'])) {
  $duration = $_GET['duration']; } else { $duration = 5; }

// Get Current Dat and Time in AEST
date_default_timezone_set('Australia/Melbourne');
$dayOfWeek = intval(date("N"));
$time = date("Gis");

$sql = "select 
cast(bayid_sensor as signed) as bayid,lat,lon, 
cast(case when duration is NULL then 0 else duration end as signed) as duration, 
case when typedesc is NULL then (select translate from db_gopark.tb_translate where sign = 'No Restrictions') else c.translate end as typedesc,
case when (d.present/d.total) >= 0.6 or current_status = 'Present' then 'red' 
     when (d.present/d.total)  < 0.6 and (d.present/d.total) >= 0.3 then 'blue' 
     when (d.present/d.total)  < 0.3 or current_status = 'Unocccupied' then 'green' end as occRate
from 
tb_park_sensor_track a left join tb_restrict b on a.restrict_id = b.id 
left join tb_translate c on b.TypeDesc = c.sign 
inner join tb_occ_rate d on a.rd_seg_id = d.rd_seg_id
where 
bayid_sensor is not NULL 
and disabled_flag <= $disabled and LZ_flag <= $lz and paid_flag <= $paid and no_parking_flag = 0 
and (duration is NULL or duration >= $duration) ;";

$sqlReturn = returnSQL($sql);

function returnSQL($sql)
{
    // Establish Connection to Database
    $config = parse_ini_file('../../../.config.ini');
    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['db']);

    // Check for connection errors
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

?>