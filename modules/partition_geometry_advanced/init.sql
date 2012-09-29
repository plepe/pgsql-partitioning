create table partition_geometry_tables (
  table_name	text	not null,
  options	hstore	default ''::hstore,
  indexes	text[]	default Array[]::text[],
  primary key(table_name)
);
