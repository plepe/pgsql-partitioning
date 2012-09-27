drop table if exists quadrant_size;
create table quadrant_size (
  x		int	not null,
  y		int	not null,
  count		int	not null,
  count_left	int	not null,
  count_top	int 	not null,
  primary key(x, y)
);

drop table if exists quadrant_part;
create table quadrant_part (
  id		serial	not null,
  x_min		int	not null,
  y_min		int	not null,
  x_max		int	not null,
  y_max		int	not null,
  count		int	not null,
  primary key(id)
);
