create table measurement_access_rules
(
    operator_id integer,
    monitoringpoint_selector text,
    observed_property_selector text
);

create table group_measurement_access_rules
(
    measurement_access_rule_id integer,
    group_id integer,
    interval date
);

