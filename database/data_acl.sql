create table if not exists measurement_access_rules
(
    id serial not null
        constraint measurement_access_rules_pk
			primary key,
	operator_id integer,
	monitoringpoint_selector text,
	observed_property_selector text

);

create table group_measurement_access_rules
(
    id serial not null
		constraint group_measurement_access_rules_pk
			primary key,
	measurement_access_rule_id integer,
	group_id integer,
	interval interval
);
