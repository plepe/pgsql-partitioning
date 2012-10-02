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

  $res_bbox=sql_query(<<<EOT
select geom as geom_srid,
  XMin(ST_Transform(geom, 4326)) as x_min,
  YMin(ST_Transform(geom, 4326)) as y_min,
  XMax(ST_Transform(geom, 4326)) as x_max,
  YMax(ST_Transform(geom, 4326)) as y_max
from (select ST_MakeEnvelope($x_min, $y_min, $x_max, $y_max, $SRID) as geom) orig
EOT
);
  $elem_bbox=pg_fetch_assoc($res_bbox);

  fwrite($f_srid, "{$elem_bbox['geom_srid']}\n");

  // hack,  snap outer boundaries to longitude +/- 180 resp. latitude +/- 90.
  if($elem_bbox['x_min']==-179.999999974944)
    $elem_bbox['x_min']=-180;
  if($elem_bbox['x_max']==179.999999974944)
    $elem_bbox['x_max']=180;
  if($elem_bbox['y_min']==-85.0511287776451)
    $elem_bbox['y_min']=-90;
  if($elem_bbox['y_max']==85.0511287776451)
    $elem_bbox['y_max']=90;

  $res_bbox1=sql_query("select ST_MakeEnvelope({$elem_bbox['x_min']}, {$elem_bbox['y_min']}, {$elem_bbox['x_max']}, {$elem_bbox['y_max']}, 4326) as geom_4326");
  $elem_bbox1=pg_fetch_assoc($res_bbox1);

  fwrite($f_4326, "{$elem_bbox1['geom_4326']}\n");
}

fclose($f_csv);
fclose($f_srid);
fclose($f_4326);
