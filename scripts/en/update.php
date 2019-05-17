<?php
// this will execute a bunch of update queries on the MySQL database

mb_language('uni');
mb_internal_encoding('UTF-8');


// GET CURRENT TIME AND DATE IN AEST
date_default_timezone_set('Australia/Melbourne');
$dayOfWeek = intval(date("N"));
$time = date("Gis");

// SQL queries
// don't run this one so often, it is an update of key information and doesn't need to be run frequently.
$sql_1 = "update tb_park_sensor_track a
inner join tb_park_sensor b on a.st_marker_id = b.st_marker_id
set current_status = status, bayid_sensor = bayid,
a.lat = b.lat, a.lon = b.lon,
last_updated = insertTime
where current_status = 'Unknown'";

echo("update query 1:  " . $sql_1 . "<BR><P>");

$sqlReturn = returnSQL($sql_1);

sleep(1);

// update the tb_park_sensor_track table with details of current restrictions in force 
$sql_2 = "update tb_park_sensor_track a
left join (select * from tb_restrict
where  adjFromDay <= $dayOfWeek and adjToDay >= $dayOfWeek
and adjStartTime <= cast($time as time) and adjEndTime >= cast($time as time)) b
on a.bayid_sensor = b.bay_id
set current_dow = $dayOfWeek,
disabled_flag = case when typeDesc like '%Dis%' then 1 else 0 end,
LZ_flag = case when typeDesc like '%Loading%' then 1 else 0 end,
paid_flag = case when typeDesc like '%Meter%' then 1  
		 when typeDesc like '%Ticket%' then 1  else 0 end,
no_parking_flag = case when typeDesc like '%P/%' then 1 
		when typeDesc like '%Clear%' then 1 else 0 end,
restrict_id = id,
restriction_in_force_flag = case when description is null then 0 else 1 end; ";

// if restriction_in_force_flag = 0 and bayid_sensor is NULL we will say that it is "n.a" and excluded
// other wise it will be "no restrictions"


echo("update query 2:  " . $sql_2 . "<BR>sql 2 complete<P>");


$sqlReturn = returnSQL($sql_2);

sleep(1);

// update where the status has changed from Unoccupied to Present - we want to estimate how long it will be before that car park becomes free
$sql_3 = "update tb_park_sensor_track a
inner join tb_park_sensor b on a.st_marker_id = b.st_marker_id
left join tb_restrict c on restrict_id = id
set current_status = status,
last_updated = insertTime,
time_occupied = insertTime,
time_next_available = date_add(insertTime, interval duration minute) 
where status = 'Present' and current_status in ('Unoccupied') ";

echo("update query 3:  " . $sql_3 . "<BR>sql 3 complete<P>");

$sqlReturn = returnSQL($sql_3);

sleep(1);

// update where the status has changed from Unoccupied to Present - we want to estimate how long it will be before that car park becomes free
$sql_4 = "update tb_park_sensor_track a
inner join tb_park_sensor b on a.st_marker_id = b.st_marker_id
set current_status = status,
last_updated = insertTime,
time_next_available = null
where status = 'Unoccupied' and current_status in ('Present') ";

echo("update query 3:  " . $sql_4 . "<BR>sql 4 complete<P>");

$sqlReturn = returnSQL($sql_4);

sleep(1);

// update the occupancy rate using live data
$sql_5 = "update tb_occ_rate a
inner join (
sum(case when current_status = 'Present' then 1 else 0 end) as present, 
sum(case when current_status != 'Unknown' then 1 else 0 end) as total,
rd_seg_id from tb_park_sensor_track b
where rd_seg_id != 9999 group by  rd_seg_id) b
on a.rd_seg_id = b.rd_seg_id
set a.present = b.present,
a.total = b.total" ;

echo("update query 5:  " . $sql_5 . "<BR>sql 5 complete<P>");

$sqlReturn = returnSQL($sql_5);

// update the historical occupancy rate using modelled data
$sql_6 = "update tb_park_sensor_track a
inner join tb_rdseg_area d
on a.rd_seg_id = d.rd_seg_id
inner join (select label from tb_time_bands where cast($time as time) between start_time
  and end_time) b 
inner join tb_roll_up_area c
on c.area = d.area and c.label = b.label and c.dowArr = $dayOfWeek
set  pred_occ_rate  = occRate ;" ;

echo("update query 6:  " . $sql_6 . "<BR>sql 6 complete<P>");

$sqlReturn = returnSQL($sql_6);

// updateas for public holidays
#$sql_7 = "select count(*) from tb_calendar where PHdate = '" . date('Ymd') . "'" ;
$sql_7 = "select * from 
tb_park_sensor_track a 
inner join tb_restrict b on a.restrict_id = b.id
where (
 select count(*) from tb_calendar where PHdate = '" . date('Ymd') . "' ) =  1
 and restrict_id is not null and b.effectiveOnPH = 0;";

echo("update query 7:  " . $sql_7 . "<BR>sql 7 complete<P>");

#$sqlReturn = countSQL($sql_7);

function returnSQL($sql)
{
    // Create connection
    $config = parse_ini_file('../../../.config.ini');
    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['db']);

    if ($conn->query($sql) === TRUE) {
        echo "Record updated successfully";
    }
    else {
        echo "Error updating record: " . $conn->error;
    }
    $conn->close();
}
?>