<?
require "conf.php";
require "simple.php";

function get_color($image, $id, $v) {
  $num_list=array(64, 128, 192, 255);
  $rgb=array(255, 255, 255);

  if($id) {
    $rgb[0]=$num_list[$id%4];
    $rgb[1]=$num_list[($id>>2)%4];
    $rgb[2]=$num_list[($id>>4)%4];
  }

  return imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
}

$x_size=$x_steps*$render_size;
$y_size=$y_steps*$render_size;

$image=imagecreatetruecolor($x_size, $y_size);
imagealphablending($image, false);
imagesavealpha($image, true);
$col=imagecolorallocatealpha($image, 0, 0, 0, 127);
imagefilledrectangle($image, 0, 0, $x_size-1, $y_size-1, $col);
imagecolordeallocate($image, $col);
imagealphablending($image, true);

//$res=sql_query("select * from quadrant_size");
$res=sql_query("select s.*, p.id from quadrant_size s left join quadrant_part p on s.x>=p.x_min and s.x<=p.x_max and s.y>=p.y_min and s.y<=p.y_max");
while($elem=pg_fetch_assoc($res)) {
  $x1=$elem['x']*$render_size;
  $x2=($elem['x']+1)*$render_size-1;
  $y1=($y_steps-$elem['y']-1)*$render_size;
  $y2=($y_steps-$elem['y'])*$render_size-1;

  $v=log($elem['count'])*10;
  $col=get_color($image, $elem['id'], $v);
  imagefilledrectangle($image, $x1, $y1, $x2, $y2, $col);
  imagecolordeallocate($image, $col);

  if($v>127)
    $v=127;
  $col=imagecolorallocatealpha($image, 0, 0, 0, $v);
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
