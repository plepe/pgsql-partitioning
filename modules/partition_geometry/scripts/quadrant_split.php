#!/usr/bin/php
<?
require "conf.php";
require "simple.php";

sql_query("delete from quadrant_part");
sql_query("insert into quadrant_part (select 0, 0, 0, ".($x_steps-1).", ".($y_steps-1).", sum(count) from quadrant_size)");

if(!isset($optimal_count)) {
  $res=sql_query("select * from quadrant_part");
  $elem=pg_fetch_assoc($res);
  $optimal_count=$elem['count']/$part_count;
}

print "Optimal count per quadrant: {$optimal_count}\n";

function quadrant_split($elem, $axis, $split_pos) {
  if($split_pos[0]<$elem["{$axis}_min"])
    $split_pos[0]=$elem["{$axis}_min"];
  if($split_pos[2]>$elem["{$axis}_max"])
    $split_pos[2]=$elem["{$axis}_max"];

  $not_axis=($axis=="x"?"y":"x");
  $left_top=($axis=="x"?"left":"top");

  $min_sum=null;
  $ret=array();

  $res=sql_query("select {$axis}, sum(count_{$left_top}) as sum, abs($split_pos[1]-{$axis}) as abst from quadrant_size where {$axis}>={$split_pos[0]} and {$axis}<={$split_pos[2]} and {$not_axis}>={$elem["{$not_axis}_min"]} and {$not_axis}<={$elem["{$not_axis}_max"]} group by {$axis} order by sum asc, abs({$split_pos[1]}-{$axis}) asc limit 1");
  if(pg_num_rows($res)==0)
    return array();

  while($e=pg_fetch_assoc($res)) {
    $s=$e[$axis];

    if($min_sum===null)
      $min_sum=$e['sum'];
    if($e['sum']>$min_sum*1.25)
      return $ret;

    $ret[]=array(
      array(
	'id'=>$elem['id'],
	'x_min'=>$elem['x_min'],
	'y_min'=>$elem['y_min'],
	"{$axis}_max"=>$s,
	"{$not_axis}_max"=>$elem["{$not_axis}_max"],
      ),
      array(
	'id'=>$elem['id'],
	"{$axis}_min"=>$s+1,
	"{$not_axis}_min"=>$elem["{$not_axis}_min"],
	'x_max'=>$elem['x_max'],
	'y_max'=>$elem['y_max'],
      ),
    );
  }

  return $ret;
}

function quadrant_assess($part) {
  global $optimal_count;
  $ret=array();

  // assess count - best if it ~ equals optimal count (0.8 .. 1.1)
  // if it's more, it's okay, we can split later
  // if it's less, give a bad grade
  $c=(float)$part['count']/(float)$optimal_count;
  if($c>1.1)		$ret[]=1;
  elseif($c>0.8)	$ret[]=0;
  elseif($c>0.6)	$ret[]=2;
  elseif($c>0.4)	$ret[]=3;
  elseif($c>0.3)	$ret[]=4;
  elseif($c>0.05)	$ret[]=5;
  else			$ret[]=9;

  // assess ratio of geometry
  $c=
    (float)($part['x_max']-$part['x_min']+1)/
    (float)(real_y($part['y_max']+1)-real_y($part['y_min']));
  if($c>1.0) $c=1.0/$c;
  if($c>0.9)		$ret[]=0; // nearly square? best grade!
  elseif($c>0.7)	$ret[]=1;
  elseif($c>0.5)	$ret[]=2;
  elseif($c>0.3)	$ret[]=3;
  elseif($c>0.15)	$ret[]=4;
  else			$ret[]=5;

  return $ret;
}

function real_y($y) {
  $y=floor(128-cos($y/256*M_PI)*128);

  return $y;
}

for($p=1; $p<$part_count; $p++) {
  $res=sql_query("select * from quadrant_part where (x_max != x_min or y_max != y_min) and no_split=false and count>(select max(count) from quadrant_part)*0.9 order by count desc");
  if(($c=pg_num_rows($res))==0) {
    print "No parts left to split!\n";
    break;
  }
  print "Taking $c quadrants into account:\n";

  $poss=array();

  while($elem=pg_fetch_assoc($res)) {
    // Try vertical
    print "  split returns";

    if($elem['x_min']!=$elem['x_max']) {
      for($i=2; $i<7; $i++) {
	$s=floor($elem['x_min']+($elem['x_max']-$elem['x_min'])/8*$i);
	$s_tol=($elem['x_max']-$elem['x_min'])*$part_tolerance;
	$s_min=floor($s-$s_tol);
	$s_max=floor($s+$s_tol);

	$parts=quadrant_split($elem, 'x', array($s_min, $s, $s_max));
	$poss=array_merge($poss, $parts);
	print " x: ".sizeof($parts);
      }
    }

    if($elem['y_min']!=$elem['y_max']) {
      for($i=2; $i<7; $i++) {
	$s=floor($elem['y_min']+($elem['y_max']-$elem['y_min'])/8*$i);
	$s_tol=($elem['y_max']-$elem['y_min'])*$part_tolerance;
	$s_min=floor($s-$s_tol);
	$s_max=floor($s+$s_tol);

	$parts=quadrant_split($elem, 'y', array($s_min, $s, $s_max));
	$poss=array_merge($poss, $parts);
	print " y: ".sizeof($parts);
      }
    }

    print " parts\n";
  }

  $poss_assess=array();
  foreach($poss as $i=>$poss_part) {
    foreach($poss_part as $j=>$part) {
      $res=sql_query("select sum(count) as sum from quadrant_size where x>={$part['x_min']} and x<={$part['x_max']} and y>={$part['y_min']} and y<={$part['y_max']}");
      $e=pg_fetch_assoc($res);
      $poss[$i][$j]['count']=$e['sum'];

      $poss[$i][$j]['assess']=quadrant_assess($poss[$i][$j]);
      $poss[$i][$j]['assess_avg']=
	array_sum($poss[$i][$j]['assess'])/
	sizeof($poss[$i][$j]['assess']);
    }

    $poss[$i][0]['assess'][]=round($poss[$i][1]['assess_avg']);
    $poss[$i][1]['assess'][]=round($poss[$i][0]['assess_avg']);

    foreach($poss_part as $j=>$part) {
      $poss[$i][$j]['assess_avg']=
	(float)array_sum($poss[$i][$j]['assess'])/
	sizeof($poss[$i][$j]['assess']);
    }

//    if(($poss[$i][0]['assess_avg']>4)||($poss[$i][1]['assess_avg']>4))
//      ;
    if($poss[$i][0]['count']<$poss[$i][1]['count'])
      $poss_assess[$i]=$poss[$i][0]['assess_avg'];
    else
      $poss_assess[$i]=$poss[$i][1]['assess_avg'];
  }

  if(sizeof($poss)==0) {
//    print "Can't split part {$elem['id']}: {$elem['x_min']}x{$elem['y_min']}-{$elem['x_max']}x{$elem['y_max']}\n";
//    sql_query("update quadrant_part set no_split=true where id='{$elem['id']}'");
    //$p--;
    print "Did not find anything!\n";
    break;
  }

  asort($poss_assess);
  $poss_key=array_keys($poss_assess);

  $poss_count=array();
  $poss_assess_min=$poss_assess[$poss_key[0]];
  foreach($poss_assess as $i=>$assess) {
    if($assess==$poss_assess_min)
      $poss_count[$i]=$poss[$i][0]['count']+$poss[$i][1]['count'];
  }

  arsort($poss_count);
  $poss_key=array_keys($poss_count);
  $win=$poss[$poss_key[0]];

  print "Splitting part {$win[0]['id']}: "; //{$elem['x_min']}x{$elem['y_min']}-{$elem['x_max']}x{$elem['y_max']} to ";

  sql_query("delete from quadrant_part where id='{$win[0]['id']}'");
  foreach($win as $part) {
    sql_query("insert into quadrant_part (x_min, y_min, x_max, y_max, count, assess) values ({$part['x_min']}, {$part['y_min']}, {$part['x_max']}, {$part['y_max']}, {$part['count']}, {$part['assess_avg']})");
    print "{$part['x_min']}x{$part['y_min']}-{$part['x_max']}x{$part['y_max']} (".sprintf("%dk %.2f", $part['count']/1000, $part['assess_avg']).") ";
  }
  print "\n";
}
