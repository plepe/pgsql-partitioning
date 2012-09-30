#!/usr/bin/php
<?
require "conf.php";
require "simple.php";

$x_step_size=($x_axis[1]-$x_axis[0])/$x_steps;
$y_step_size=($y_axis[1]-$y_axis[0])/$y_steps;


$res=sql_query("select * from quadrant_part order by y_min, x_min");

$f_csv=fopen(sprintf($save_name, pg_num_rows($res)).".csv", "w");
$f_srid=fopen(sprintf($save_name, pg_num_rows($res)).".".$SRID, "w");
$f_4326=fopen(sprintf($save_name, pg_num_rows($res)).".4326", "w");
fwrite($f_csv, "x_min\ty_min\tx_max\ty_max\tobject_count\tassess\n");

while($elem=pg_fetch_assoc($res)) {
  $x_min=$x_axis[0]+$elem['x_min']*$x_step_size;
  $x_max=$x_axis[0]+($elem['x_max']+1)*$x_step_size;
  $y_min=$y_axis[0]+$elem['y_min']*$y_step_size;
  $y_max=$y_axis[0]+($elem['y_max']+1)*$y_step_size;

  fwrite($f_csv, "{$elem['x_min']}\t{$elem['y_min']}\t{$elem['x_max']}\t{$elem['y_max']}\t{$elem['count']}\t{$elem['assess']}\n");

  $res_bbox=sql_query("select ST_MakeEnvelope($x_min, $y_min, $x_max, $y_max, $SRID) as geom_srid, ST_Transform(ST_MakeEnvelope($x_min, $y_min, $x_max, $y_max, $SRID), 4326) as geom_4326");
  $elem_bbox=pg_fetch_assoc($res_bbox);

  fwrite($f_srid, "{$elem_bbox['geom_srid']}\n");
  fwrite($f_4326, "{$elem_bbox['geom_4326']}\n");
}

fclose($f_csv);
fclose($f_srid);
fclose($f_4326);
