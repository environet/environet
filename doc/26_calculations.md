# Calculations

## Summary

With calculations the distribution node administrators can create configuration, with which the system can calculate new data from the uploaded raw data.
The calculations are based on the uploaded raw data, and the results are stored in the database.

* **Method**: `GET`
* **URL**: `https://domain.com/api/monitoring-points`

---

## Configuration

Configuration is stored in database, under the `calculation_configs` table. The configuration contains the following columns:

* `id`: The unique identifier of the configuration, auto generated.
* `name` (string, required): The name of the configuration.
* `is_active` (boolean, required): The status of the configuration, if it is active or not.
* `mpoint_type` (string, required): The type of the monitoring point, can be `hydro` or `meteo`.
* `operatorid` (integer, required): The identifier of the operator. If `mpointid` is not set, the configuration will be applied to all monitoring points of the operator.
* `mpointid` (integer, optional): The identifier of the monitoring point. If it is set, the configuration will be applied only to this monitoring point.
* `method` (string, required): The method of the calculation. The available methods are:
    * `sum`: The sum of the values in the interval.
    * `difference`: The difference between the first and the last value in the interval.
* `source_interval` (string, required): The interval of the source data. The available intervals are:
    * `hourly`: The calculation will find one value for each hour inside the target interval. It will use full hours (0 minutes and 0 seconds).
    * `daily`: The calculation will find one value for each day inside the target interval. It will use the start of the day (0 hours, 0 minutes and 0 seconds).
    * `weekly`: The calculation will find one value for each week inside the target interval. It will use the start of the week (Monday, 0 hours, 0 minutes and 0 seconds).
    * `monthly`: The calculation will find one value for each month inside the target interval. It will use the start of the month (1st day of month, 0 hours, 0 minutes and 0 seconds).
    * `yearly`: The calculation will find one value for each year inside the target interval. It will use the start of the year (1st day of year, 0 hours, 0 minutes and 0 seconds).
* `target_interval` (string, required): The interval of the target data. The calculation will create one value for each target interval. The available intervals:
    * `hourly`: The calculation will create one value for each hour from the source interval.
    * `daily`: The calculation will create one value for each day from the source interval.
    * `weekly`: The calculation will create one value for each week from the source interval.
    * `monthly`: The calculation will create one value for each month from the source interval.
    * `yearly`: The calculation will create one value for each year from the source interval.
* `start_time` (string, required, '00:00'): The start time is in connection with the `target_interval`. The calculation will run if the 'current time (time of running)' is a whole interval after the `start_time`.
* `source_propertyid` (integer, required): The identifier of the source observed property.
* `target_propertyid` (integer, required): The identifier of the target observed property.
* `last_run` (datetime, optional): The last time when the calculation was run. This is updated by the system.

## Run calculation

You can run a calculation configuration with the following command:

```bash
./environet dist calc run`
```

Available options:

* `--date=YYYY-MM-DD HH:MM:SS`: The date and time when the calculation should run. If it is not set, the current time will be used.
* `--config-id=ID`: The identifier of the configuration. If it is not set, all active configurations will be run. If the id is set, it will run even if the configuration is not active.
* `--dry-run`: If this option is set, the calculation will not be saved to the database, but everything else will be logged and calculated

Example with dry run, a specific config id and date:

```bash
./environet dist calc run --date=2021-01-01 00:00:00 --config-id=1 --dry-run
```

In every time only some calculations can be run. If at the date of run a calculation is not in the interval, it will not be run. So it is possible to run cron jobs for the calculations periodically.

