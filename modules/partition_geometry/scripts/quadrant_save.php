<?
require "conf.php";
require "simple.php";

$x_step_size=($x_axis[1]-$x_axis[0])/$x_steps;
$y_step_size=($y_axis[1]-$y_axis[0])/$y_steps;


$res=sql_query("select * from quadrant_part order by y_min, x_min");

$f=fopen(sprintf($save_name, pg_num_rows($res)), "w");
fwrite($f, "boundary\tobject_count\n");

while($elem=pg_fetch_assoc($res)) {
  $x_min=$x_axis[0]+$elem['x_min']*$x_step_size;
  $x_max=$x_axis[0]+($elem['x_max']+1)*$x_step_size;
  $y_min=$y_axis[0]+$elem['y_min']*$y_step_size;
  $y_max=$y_axis[0]+($elem['y_max']+1)*$y_step_size;

  fwrite($f, "ST_MakeEnvelope($x_min, $y_min, $x_max, $y_max, $SRID)\t{$elem['count']}\n");
}

fclose($f);
