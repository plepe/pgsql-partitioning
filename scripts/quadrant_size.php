<?
include "conf.php";
require "simple.php";

$x_step_size=($x_axis[1]-$x_axis[0])/$x_steps;
$y_step_size=($y_axis[1]-$y_axis[0])/$y_steps;

for($x=0; $x<$x_steps; $x++) {
  for($y=0; $y<$y_steps; $y++) {
    $x1=$x_axis[0]+$x*$x_step_size;
    $x2=$x_axis[0]+($x+1)*$x_step_size;
    $y1=$y_axis[0]+$y*$y_step_size;
    $y2=$y_axis[0]+($y+1)*$y_step_size;

    $res=sql_query("select count(*) as c from osm_line_extract where osm_way && ST_MakeEnvelope($x1, $y1, $x2, $y2, 900913)");
    $elem=pg_fetch_assoc($res);
    $count_inside=$elem['c'];

    $res=sql_query("select count(*) as c from osm_line_extract where osm_way && ST_SetSRID(ST_MakeLine(ST_MakePoint($x1, $y1), ST_MakePoint($x1, $y2)), 900913)");
    $elem=pg_fetch_assoc($res);
    $count_left=$elem['c'];

    $res=sql_query("select count(*) as c from osm_line_extract where osm_way && ST_SetSRID(ST_MakeLine(ST_MakePoint($x1, $y1), ST_MakePoint($x2, $y1)), 900913)");
    $elem=pg_fetch_assoc($res);
    $count_top=$elem['c'];

    print "$x $y => {$count_inside}, {$count_left}, {$count_top}\n";
    sql_query("insert into quadrant_size values ($x, $y, {$count_inside}, {$count_left}, {$count_top});");
  }
}
