<?
require "conf.php";
require "simple.php";

$x_size=$x_steps*$render_size;
$y_size=$y_steps*$render_size;

$image=imagecreatetruecolor($x_size, $y_size);
imagealphablending($image, false);
imagesavealpha($image, true);
$col=imagecolorallocatealpha($image, 0, 0, 0, 127);
imagefilledrectangle($image, 0, 0, $x_size-1, $y_size-1, $col);
imagecolordeallocate($image, $col);

$res=sql_query("select * from quadrant_size");
while($elem=pg_fetch_assoc($res)) {
  $x1=$elem['x']*$render_size;
  $x2=($elem['x']+1)*$render_size-1;
  $y1=($y_steps-$elem['y']-1)*$render_size;
  $y2=($y_steps-$elem['y'])*$render_size-1;

  $v=log($elem['count'])*10;
  $col=imagecolorallocate($image, $v, $v, $v);
  imagefilledrectangle($image, $x1, $y1, $x2, $y2, $col);
  imagecolordeallocate($image, $col);

  $v=log($elem['count_left'])*20;
  $col=imagecolorallocate($image, $v, $v, $v);
  imageline($image, $x1, $y1, $x1, $y2, $col);
  imagecolordeallocate($image, $col);

  $v=log($elem['count_top'])*20;
  $col=imagecolorallocate($image, $v, $v, $v);
  imageline($image, $x1, $y1, $x2, $y1, $col);
  imagecolordeallocate($image, $col);
}

imagepng($image, $render_name);
imagedestroy($image);
