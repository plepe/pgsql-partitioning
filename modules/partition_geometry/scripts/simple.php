<?
if(!function_exists("debug")) {
  define('D_ERROR', 3);
  define('D_WARNING', 2);
  define('D_NOTICE', 1);
  define('D_DEBUG', 0);

  function debug($msg, $module, $level=D_NOTICE) {
    print "DEBUG ($module, $level): $msg\n";
  }
}

if(!function_exists("sql_file")) {
  global $PG_PARAMS;
  $PG_PARAMS="-d {$db_central['name']}";
  $PG_PARAMS.=" --set ON_ERROR_STOP=1";

  function sql_file($file) {
    global $PG_PARAMS;

    debug("Executing $file", "openstreetbrowser-database", D_NOTICE);
    $ret=null;
    system("psql {$PG_PARAMS} -f '$file'", &$ret);

    if($ret!=0) {
      debug("An error occured executing $file", "openstreetbrowser-database", D_ERROR);
      exit;
    }
  }

  function sql_query($query) {
    $res=pg_query($query);
    if($res===false) {
      debug("An error occured: ".pg_last_error(), "openstreetbrowser-database", D_ERROR);
      exit;
    }

    return $res;
  }
}

// Connect to database
pg_connect("dbname={$db_central['name']} host={$db_central['host']} user={$db_central['name']} password={$db_central['passwd']}");


