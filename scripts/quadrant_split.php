<?
require "conf.php";
require "simple.php";

sql_query("delete from quadrant_part");
sql_query("insert into quadrant_part (select 0, 0, 0, ".($x_steps-1).", ".($y_steps-1).", sum(count) from quadrant_size)");
$res=sql_query("select * from quadrant_part");
$elem=pg_fetch_assoc($res);
$min_count=$elem['count']/$part_count/4;

for($p=1; $p<$part_count; $p++) {
  $res=sql_query("select * from quadrant_part where (x_max != x_min or y_max != y_min) and no_split=false order by count desc limit 1");
  if(!($elem=pg_fetch_assoc($res))) {
    print "No parts left to split!\n";
    break;
  }

  // Try vertical
  $poss=array();

  if($elem['x_min']!=$elem['x_max']) {
    $s=floor($elem['x_min']+($elem['x_max']-$elem['x_min'])/2);
    $s_tol=($elem['x_max']-$elem['x_min'])*$part_tolerance;
    $s_min=floor($s-$s_tol);
    $s_max=floor($s+$s_tol);

    if($s_min<$elem['x_min'])
      $s_min=$elem['x_min'];
    if($s_max>$elem['x_max'])
      $s_max=$elem['x_max'];

    $res=sql_query("select x, sum(count_left) as sum, abs($s-x) as abst from quadrant_size where x>={$s_min} and x<={$s_max} and y>={$elem['y_min']} and y<={$elem['y_max']} group by x order by sum asc, abs($s-x) asc");
    $e=pg_fetch_assoc($res);
    $s=$e['x'];

    $poss[]=array(
      array(
        'x_min'=>$elem['x_min'],
	'y_min'=>$elem['y_min'], 
        'x_max'=>$s,
	'y_max'=>$elem['y_max'], 
      ),
      array(
        'x_min'=>$s+1,
	'y_min'=>$elem['y_min'], 
        'x_max'=>$elem['x_max'],
	'y_max'=>$elem['y_max'], 
      ),
    );
  }

  if($elem['y_min']!=$elem['y_max']) {
    $s=floor($elem['y_min']+($elem['y_max']-$elem['y_min'])/2);
    $s_tol=($elem['y_max']-$elem['y_min'])*$part_tolerance;
    $s_min=floor($s-$s_tol);
    $s_max=floor($s+$s_tol);

    if($s_min<$elem['y_min'])
      $s_min=$elem['y_min'];
    if($s_max>$elem['y_max'])
      $s_max=$elem['y_max'];

    $res=sql_query("select y, sum(count_top) as sum, abs($s-y) as abst from quadrant_size where y>={$s_min} and y<={$s_max} and x>={$elem['x_min']} and x<={$elem['x_max']} group by y order by sum asc, abs($s-y) asc");
    $e=pg_fetch_assoc($res);
    $s=$e['y'];

    $s=floor($elem['y_min']+($elem['y_max']-$elem['y_min'])/2);
    $poss[]=array(
      array(
        'x_min'=>$elem['x_min'],
	'y_min'=>$elem['y_min'], 
	'x_max'=>$elem['x_max'], 
        'y_max'=>$s,
      ),
      array(
	'x_min'=>$elem['x_min'], 
        'y_min'=>$s+1,
        'x_max'=>$elem['x_max'],
	'y_max'=>$elem['y_max'], 
      )
    );
  }

  $poss_diff=array();
  foreach($poss as $i=>$poss_part) {
    foreach($poss_part as $j=>$part) {
      $res=sql_query("select sum(count) as sum from quadrant_size where x>={$part['x_min']} and x<={$part['x_max']} and y>={$part['y_min']} and y<={$part['y_max']}");
      $e=pg_fetch_assoc($res);
      $poss[$i][$j]['count']=$e['sum'];
    }

    $rel=$poss[$i][0]['count']/$poss[$i][1]['count'];
    if(($poss[$i][0]['count']<$min_count)||
       ($poss[$i][1]['count']<$min_count)) {
      // too few elements in one of the parts
      unset($poss[$i]);
    }
    else {
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
