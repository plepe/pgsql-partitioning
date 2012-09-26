drop table if exists quadrant_size;
create table quadrant_size (
  x		int	not null,
  y		int	not null,
  count		int	not null,
  count_left	int	not null,
  count_top	int 	not null,
  primary key(x, y)
);
