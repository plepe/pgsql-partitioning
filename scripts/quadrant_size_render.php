<?
include "conf.php";
require "simple.php";

$x_size=$x_steps*$step_size;
$y_size=$y_steps*$step_size;

$image=imagecreatetruecolor($x_size, $y_size);
imagealphablending($image, false);
imagesavealpha($image, true);
$col=imagecolorallocatealpha($image, 0, 0, 0, 127);
imagefilledrectangle($image, 0, 0, $x_size-1, $y_size-1, $col);
imagecolordeallocate($image, $col);

$res=sql_query("select * from quadrant_size");
while($elem=pg_fetch_assoc($res)) {
  $x1=$elem['x']*$step_size;
  $x2=($elem['x']+1)*$step_size-1;
  $y1=$elem['y']*$step_size;
  $y2=($elem['y']+1)*$step_size-1;

  $v=log($elem['count'])*10;
  $col=imagecolorallocate($image, $v, $v, $v);
  imagefilledrectangle($image, $x1, $y1, $x2, $y2, $col);
  imagecolordeallocate($image, $col);
}

imagepng($image, "test.png");
imagedestroy($image);
