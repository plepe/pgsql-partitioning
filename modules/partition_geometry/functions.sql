-- changes to supplied table into a geometry partitioned table
-- parameters:
-- 1. name of the table (text)
-- 2. options (hstore)
--      'size'=>size when to split a quadrant (bytes, default: 256 MB)
--      'type'=>
--         'only_leaf' ... separate table only in leaf-tables, overlapping
--                         objects will be duplicated to each part
--         'full_quad' ... keep a full partition_geometry, with overlapping objects
--                         in the parent parts -> more tables need querying
--
-- the table needs to have a column 'way', a geometry column on which to 
-- decide which subtable(s) to insert.
-- TODO: configure column via options
CREATE OR REPLACE FUNCTION partition_geometry_init_table(in table_name text, in boundary_file text, in options hstore default ''::hstore) returns boolean as $$
#variable_conflict use_variable
DECLARE
  r record;
  index_def text[]=Array[]::text[];
  i int;
  geom geometry;
BEGIN
  -- set default values
  options='size=>268435456, type=>only_leaf'||options;

  -- add table to the list of partition_tables
  insert into partition_tables values (table_name, null, null, options);

  -- save list of current indexes
  for r in execute 'select * from pg_indexes where tablename='''||table_name||'''' loop
    index_def=array_append(index_def, r.indexdef);
  end loop;
  update partition_tables
    set indexes=index_def
    where partition_tables.table_name=table_name;

  -- create partition_geometry table
  execute 'create table '||table_name||'_partition_geometry ( );';
  perform AddGeometryColumn(table_name||'_partition_geometry', 'boundary', 900913, 'POLYGON', 2);
  execute 'copy '||table_name||'_partition_geometry FROM '''||boundary_file||E'''';
  execute 'alter table '||table_name||'_partition_geometry add column table_id serial';
  execute 'alter table '||table_name||'_partition_geometry add primary key(table_id)';
  execute 'create index '||table_name||'_partition_geometry_boundary on '||table_name||'_partition_geometry using gist(boundary);';

  -- iterate through all quadrants
  for r in execute 'select * from '||table_name||'_partition_geometry' loop
    i=r.table_id;

    -- create sub-table
    execute 'create table '||table_name||'_'||i||' () inherits ('||table_name||');';

    -- create indexes
    perform partition_table_indexes(table_name, cast(i as text));
  end loop;

  -- update parts_id column of partition_tables
  execute 'update partition_tables set parts_id=(select array_agg(cast(table_id as text)) from '||table_name||'_partition_geometry) where table_name='''||table_name||''';';

  -- create functions and triggers
  perform partition_geometry_update_functions(table_name);

  -- move current data from table to first-subtable
  execute 'insert into '||table_name||' (select * from only '||table_name||');';
  execute 'delete from only '||table_name||';';

  return true;
END;
$$ LANGUAGE plpgsql;

-- get list of subtables where the given geometry is part of
create or replace function partition_geometry_get_table_list(in table_name text, in way geometry) returns int2[] as $$
#variable_conflict use_variable
declare
  ret record;
  table_def record;
begin
  select * into table_def from partition_tables where partition_tables.table_name=table_name;

  if way is null then
    return null;
  end if;

  if table_def.options->'type'='only_leaf' then
    for ret in execute 'select array_agg(table_id) as c from '||
      table_name||'_partition_geometry where boundary && '''||cast(way as text)||''' and ST_Distance(boundary, '''||cast(way as text)||''')=0;' loop
    end loop;
  elsif table_def.options->'type'='full_quad' then
    for ret in execute 'select Array[table_id] as c from '||
      table_name||'_partition_geometry where ST_Within('''||cast(way as text)||''', boundary) order by length(path) desc limit 1;' loop
    end loop;
  end if;

  return ret.c;
end;
$$ language plpgsql;

-- called from the insert-trigger ... does the actual insert
CREATE OR REPLACE FUNCTION partition_geometry_on_insert(in table_name text, in NEW anyelement) returns boolean as $$
DECLARE
  way geometry;
  table_list int2[];
  i int2;
BEGIN
  way:=partition_geometry_get_way(NEW);
  table_list:=partition_geometry_get_table_list(table_name, way);

  if table_list is null then
    return false;
  end if;

  for i in array_lower(table_list, 1)..array_upper(table_list, 1) loop
    execute 'insert into '||table_name||'_'||table_list[i]||' select $1.*' using NEW;
  end loop;

  perform partition_geometry_check_split(table_name, table_list);

  return true;
END;
$$ LANGUAGE plpgsql;

-- compiles a query on a table as used by the XXX() function
create or replace function partition_geometry_compile_query(in table_name text, in boundary geometry, in _where text default '', in options hstore default ''::hstore) returns text as $$
#variable_conflict use_variable
DECLARE
  r record;
  sql text;
  tables text[]=Array[]::text[];
BEGIN
  -- get list of tables matching the boundary of the query
  for r in execute 'select * from '||table_name||'_partition_geometry where boundary && '||quote_nullable(cast(boundary as text))||';' loop
    tables=array_append(tables, 'select * from '||table_name||'_'||r.table_id);
  end loop;

  -- join tables with union
  sql='select * from ('||array_to_string(tables, ' union ')||') a';

  sql=sql||' where way && '||quote_nullable(cast(boundary as text));

  -- if there's a where specified concatenate to query
  if _where!='' then
    sql=sql||' and '||_where;
  end if;

  -- raise notice 'sql: %', sql;
  return sql;
END;
$$ language plpgsql;

-- remove all traces of a table
create or replace function partition_geometry_drop_table(in table_name text) returns boolean as $$
#variable_conflict use_variable
DECLARE
BEGIN
  execute 'drop table '||table_name||' cascade;';
  execute 'drop table '||table_name||'_partition_geometry';
  execute 'delete from partition_tables where table_name='||quote_nullable(table_name)||';';

  return true;
END;
$$ language plpgsql;

-- (re-)create all functions for table_name
CREATE OR REPLACE FUNCTION partition_geometry_update_functions(in table_name text) returns boolean as $$
#variable_conflict use_variable
DECLARE
  fun text;
  options hstore;
BEGIN
  select partition_tables.options into options from partition_tables where partition_tables.table_name=table_name;

  -- create insert trigger function
  fun='create or replace function partition_geometry_insert_trigger_'||table_name||'() returns trigger as $f$ DECLARE ';
  fun=fun||' BEGIN ';
  fun=fun||'perform partition_geometry_on_insert('''||table_name||''', NEW); return null; END;';
  fun=fun||' $f$ language plpgsql;';
  execute fun;

  -- set insert trigger
  execute 'drop trigger if exists partition_geometry_insert_trigger_'||table_name||' on '||table_name||';';
  execute 'create trigger partition_geometry_insert_trigger_'||table_name||' before insert on '||table_name||' for each row execute procedure partition_geometry_insert_trigger_'||table_name||'();';

  -- function to extract way from a row
  execute 'create or replace function partition_geometry_get_way('||table_name||') returns geometry as $f$ select $1.way $f$ language sql;';

  -- create query function
  execute 'create or replace function '||table_name||'(in boundary geometry, in _where text default '''', in options hstore default ''''::hstore) returns setof '||table_name||' as $f$ declare r '||table_name||'%rowtype; sql text; begin sql:=partition_geometry_compile('''||table_name||''', boundary, _where, options); return query execute sql; return; end; $f$ language plpgsql;';

  return true;
END;
$$ LANGUAGE plpgsql;
