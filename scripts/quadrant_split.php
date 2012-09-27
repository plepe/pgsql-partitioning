<?
require "conf.php";
require "simple.php";

sql_query("delete from quadrant_part");
sql_query("insert into quadrant_part (select 0, 0, 0, ".($x_steps-1).", ".($y_steps-1).", sum(count) from quadrant_size)");

for($p=1; $p<$part_count; $p++) {
  $res=sql_query("select * from quadrant_part where (x_max != x_min or y_max != y_min) order by count desc limit 1");
  $elem=pg_fetch_assoc($res);

  print "Splitting part {$elem['id']}: {$elem['x_min']}x{$elem['y_min']}-{$elem['x_max']}x{$elem['y_max']}\n";

  // Try vertical
  $poss=array();

  if($elem['x_min']!=$elem['x_max']) {
    $s=floor($elem['x_min']+($elem['x_max']-$elem['x_min'])/2);
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

  foreach($poss as $i=>$poss_part) {
    foreach($poss_part as $j=>$part) {
      $res=sql_query("select sum(count) as sum from quadrant_size where x>={$part['x_min']} and x<={$part['x_max']} and y>={$part['y_min']} and y<={$part['y_max']}");
      $e=pg_fetch_assoc($res);
      $poss[$i][$j]['count']=$e['sum'];
    }

    $poss_diff[$i]=abs($poss[$i][0]['count']-$poss[$i][1]['count']);
  }

  asort($poss_diff);
  $poss_key=array_keys($poss_diff);
  $win=$poss[$poss_key[0]];

  sql_query("delete from quadrant_part where id='{$elem['id']}'");
  foreach($win as $part) {
    sql_query("insert into quadrant_part (x_min, y_min, x_max, y_max, count) values ({$part['x_min']}, {$part['y_min']}, {$part['x_max']}, {$part['y_max']}, {$part['count']})");
  }
}
