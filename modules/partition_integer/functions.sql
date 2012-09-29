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

  -- save list of current indexes
  for r in execute 'select * from pg_indexes where tablename='''||table_name||'''' loop
    index_def=array_append(index_def, r.indexdef);
  end loop;
  update partition_tables
    set indexes=index_def
    where partition_tables.table_name=table_name;

  -- create all sub-tables, create indexes
  for i in 0..options->'id_mask' loop
    execute 'create table '||table_name||'_'||i||' () '||
      ' inherits ('||table_name||');';

    perform partition_table_indexes(table_name, cast(i as text));
  end loop;

  -- create functions and triggers
  perform partition_integer_update_functions(table_name);

  -- move current data from table to other-subtable
  execute 'insert into '||table_name||' (select * from only '||table_name||');';
  execute 'delete from only '||table_name||';';

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

CREATE OR REPLACE FUNCTION partition_integer_update_functions(in table_name text) returns boolean as $$
#variable_conflict use_variable
DECLARE
  fun text;
  options hstore;
BEGIN
  select partition_tables.options into options from partition_tables where partition_tables.table_name=table_name;

  -- create insert function which will be called by the trigger
  fun='create or replace function partition_integer_insert_trigger_'||table_name||'() returns trigger as $f$ DECLARE part_id int8; BEGIN part_id=(NEW.'||(options->'id_column')||'/'||(options->'id_div')||')&'||(options->'id_mask')||';';
  fun=fun||build_if_tree(0, cast(options->'id_mask' as int), 'part_id', 'insert into '||table_name||'_% values (NEW.*)');
  fun=fun||' return null; END; $f$ language plpgsql;';
  execute fun;

  -- create trigger on insert statement
  execute 'drop trigger if exists partition_integer_insert_trigger_'||table_name||' on '||table_name||';';
  execute 'create trigger partition_integer_insert_trigger_'||table_name||' before insert on '||table_name||' for each row execute procedure partition_integer_insert_trigger_'||table_name||'();';

  -- create query function
  fun='create or replace function '||table_name||'(in id int8, in _where text default null, in options hstore default ''''::hstore) returns setof '||table_name||' as $f$ ';
  fun=fun||'declare r '||table_name||'%rowtype; sql text; part_id int; begin ';
  fun=fun||'part_id=(id/'||(options->'id_div')||')&'||(options->'id_mask')||';';
  fun=fun||'sql:=''select * from '||table_name||'_''||part_id||'' where "'||(options->'id_column')||'"=''||id; ';
  fun=fun||'if _where is not null then sql=sql||'' and (''||_where||'')''; end if;';
  -- fun=fun||'raise notice ''%'', sql; ';
  fun=fun||'return query execute sql;';
  fun=fun||'return; end; $f$ language plpgsql;';
  execute fun;

  return true;
END;
$$ LANGUAGE plpgsql;
