-- changes supplied table into a partitioned table (e.g. xxx)
-- there'll be a table xxx_other containing all objects which do not fit into
-- any of the subtables
-- use partition_add_part(table_name, 'great', 'value>10') to add partitions
--
-- parameters:
-- 1. name of the table (text)
-- 2. options (hstore)
--      none yet
CREATE OR REPLACE FUNCTION partition_integer_init_table(in table_name text, in options hstore default ''::hstore) returns boolean as $$
#variable_conflict use_variable
DECLARE
  r record;
  index_def text[]=Array[]::text[];
  i int;
  fun text;
BEGIN
  -- set default values
  options='id_div=>256, id_mask=>255, id_column=>id'||options;

  -- add table to the list of partition_tables
  insert into partition_tables values (table_name, Array[]::text[], Array[]::text[], options);

  -- create trigger for insert statement
  fun='create or replace function partition_integer_insert_trigger_'||table_name||'() returns trigger as $f$ DECLARE part_id int8; BEGIN part_id=(NEW.'||(options->'id_column')||'/'||(options->'id_div')||')&'||(options->'id_mask')||';';
  fun=fun||build_if_tree(0, cast(options->'id_mask' as int), 'part_id', 'insert into '||table_name||'_% values (NEW.*)');
  fun=fun||' return null; END; $f$ language plpgsql;';
  execute fun;

  -- set trigger on insert statement
  execute 'create trigger partition_integer_insert_trigger_'||table_name||' before insert on '||table_name||' for each row execute procedure partition_integer_insert_trigger_'||table_name||'();';

  for i in 0..options->'id_mask' loop
    execute 'create table '||table_name||'_'||i||' () '||
      ' inherits ('||table_name||');';

    perform partition_table_indexes(table_name, cast(i as text));
  end loop;

  -- save list of current indexes
  for r in execute 'select * from pg_indexes where tablename='''||table_name||'''' loop
    index_def=array_append(index_def, r.indexdef);
  end loop;
  update partition_tables
    set indexes=index_def
    where partition_tables.table_name=table_name;

  -- move current data from table to other-subtable
  execute 'insert into '||table_name||' (select * from only '||table_name||');';
  execute 'delete from only '||table_name||';';
  
  return true;
END;
$$ LANGUAGE plpgsql;

-- add part
CREATE OR REPLACE FUNCTION partition_add_part(in table_name text, in part_id text, in part_where text) returns boolean as $$
#variable_conflict use_variable
DECLARE
BEGIN
  update partition_tables set
    parts_id=array_append(parts_id, part_id),
    parts_where=array_append(parts_where, part_where)
  where partition_tables.table_name=table_name;

  execute 'create table '||table_name||'_'||part_id||' () inherits ('||table_name||');';

  -- fill subtable with fitting data
  execute 'insert into '||table_name||'_'||part_id||' (select * from '||table_name||'_query(null::text[], $f$'||part_where||'$f$));';
  -- delete fitting data from other-subtable
  execute 'delete from '||table_name||'_other where '||part_where||';';

  -- create indexes on table
  perform partition_table_indexes(table_name, part_id);

  return true;
END;
$$ LANGUAGE plpgsql;

-- remove all traces of a table
create or replace function partition_integer_drop_table(in table_name text) returns boolean as $$
#variable_conflict use_variable
DECLARE
BEGIN
  execute 'drop table '||table_name||' cascade;';
  execute 'delete from partition_tables where table_name='||quote_nullable(table_name)||';';

  return true;
END;
$$ language plpgsql;
