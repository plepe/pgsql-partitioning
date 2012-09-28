<?
require "conf.php";
require "simple.php";

sql_query("delete from quadrant_part");
sql_query("insert into quadrant_part (select 0, 0, 0, ".($x_steps-1).", ".($y_steps-1).", sum(count) from quadrant_size)");
$res=sql_query("select * from quadrant_part");
$elem=pg_fetch_assoc($res);
$min_count=$elem['count']/$part_count/4;

function quadrant_split($elem, $axis, $split_pos) {
  if($split_pos[0]<$elem["{$axis}_min"])
    $split_pos[0]=$elem["{$axis}_min"];
  if($split_pos[2]>$elem["{$axis}_max"])
    $split_pos[2]=$elem["{$axis}_max"];

  $not_axis=($axis=="x"?"y":"x");
  $left_top=($axis=="x"?"left":"top");

  $res=sql_query("select {$axis}, sum(count_{$left_top}) as sum, abs($split_pos[1]-{$axis}) as abst from quadrant_size where {$axis}>={$split_pos[0]} and {$axis}<={$split_pos[2]} and {$not_axis}>={$elem["{$not_axis}_min"]} and {$not_axis}<={$elem["{$not_axis}_max"]} group by {$axis} order by sum asc, abs({$split_pos[1]}-{$axis}) asc");
  if(!($e=pg_fetch_assoc($res)))
    return null;
  $s=$e[$axis];

  $ret=array(
    array(
      'x_min'=>$elem['x_min'],
      'y_min'=>$elem['y_min'],
      "{$axis}_max"=>$s,
      "{$not_axis}_max"=>$elem["{$not_axis}_max"],
    ),
    array(
      "{$axis}_min"=>$s+1,
      "{$not_axis}_min"=>$elem["{$not_axis}_min"],
      'x_max'=>$elem['x_max'],
      'y_max'=>$elem['y_max'],
    ),
  );

  return $ret;
}

for($p=1; $p<$part_count; $p++) {
  $res=sql_query("select * from quadrant_part where (x_max != x_min or y_max != y_min) and no_split=false order by count desc limit 1");
  if(!($elem=pg_fetch_assoc($res))) {
    print "No parts left to split!\n";
    break;
  }

  // Try vertical
  $poss=array();

  if($elem['x_min']!=$elem['x_max']) {
    for($i=2; $i<7; $i++) {
      $s=floor($elem['x_min']+($elem['x_max']-$elem['x_min'])/8*$i);
      $s_tol=($elem['x_max']-$elem['x_min'])*$part_tolerance;
      $s_min=floor($s-$s_tol);
      $s_max=floor($s+$s_tol);

      $parts=quadrant_split($elem, 'x', array($s_min, $s, $s_max));
      if($parts)
	$poss[]=$parts;
    }
  }

  if($elem['y_min']!=$elem['y_max']) {
    for($i=2; $i<7; $i++) {
      $s=floor($elem['y_min']+($elem['y_max']-$elem['y_min'])/8*$i);
      $s_tol=($elem['y_max']-$elem['y_min'])*$part_tolerance;
      $s_min=floor($s-$s_tol);
      $s_max=floor($s+$s_tol);

      $parts=quadrant_split($elem, 'y', array($s_min, $s, $s_max));
      if($parts)
	$poss[]=$parts;
    }
  }

  $poss_diff=array();
  foreach($poss as $i=>$poss_part) {
    foreach($poss_part as $j=>$part) {
      $res=sql_query("select sum(count) as sum from quadrant_size where x>={$part['x_min']} and x<={$part['x_max']} and y>={$part['y_min']} and y<={$part['y_max']}");
      $e=pg_fetch_assoc($res);
      $poss[$i][$j]['count']=$e['sum'];
    }

    if(($poss[$i][0]['count']<$min_count)||
       ($poss[$i][1]['count']<$min_count)) {
      // too few elements in one of the parts
      unset($poss[$i]);
    }
    else {
      $rel=$poss[$i][0]['count']/$poss[$i][1]['count'];
      $poss_diff[$i]=abs(1-($rel<1.0?$rel:1/$rel));
    }
  }

  if(sizeof($poss)==0) {
    print "Can't split part {$elem['id']}: {$elem['x_min']}x{$elem['y_min']}-{$elem['x_max']}x{$elem['y_max']}\n";
    sql_query("update quadrant_part set no_split=true where id='{$elem['id']}'");
    $p--;
    continue;
  }

  asort($poss_diff);
  $poss_key=array_keys($poss_diff);
  $win=$poss[$poss_key[0]];

  print "Splitting part {$elem['id']}: {$elem['x_min']}x{$elem['y_min']}-{$elem['x_max']}x{$elem['y_max']} to ";

  sql_query("delete from quadrant_part where id='{$elem['id']}'");
  foreach($win as $part) {
    sql_query("insert into quadrant_part (x_min, y_min, x_max, y_max, count) values ({$part['x_min']}, {$part['y_min']}, {$part['x_max']}, {$part['y_max']}, {$part['count']})");
    print "{$part['x_min']}x{$part['y_min']}-{$part['x_max']}x{$part['y_max']} ";
  }
  print "\n";
}