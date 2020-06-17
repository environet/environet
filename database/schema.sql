--
-- PostgreSQL database dump
--

-- Dumped from database version 12.1
-- Dumped by pg_dump version 12.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: dareffort; Type: DATABASE; Schema: -; Owner: -
--


SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA IF NOT EXISTS public;


--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA public IS 'standard public schema';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: discharge_measurement; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discharge_measurement (
    id integer NOT NULL,
    operatorid integer NOT NULL,
    discharge_measurement_equipmentid integer NOT NULL,
    mpointid integer NOT NULL,
    date timestamp(6) without time zone NOT NULL,
    q numeric(20,10) NOT NULL,
    h numeric(20,10) NOT NULL,
    width numeric(20,10) NOT NULL,
    area numeric(20,10) NOT NULL,
    wetted_perimeter numeric(20,10) NOT NULL,
    depth_max numeric(20,10) NOT NULL,
    velocity_max numeric(20,10) NOT NULL,
    velocity_average numeric(20,10) NOT NULL,
    temperature numeric(20,10) NOT NULL
);


--
-- Name: COLUMN discharge_measurement.date; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.date IS 'Date of discharge calibration measurement (UTC)';


--
-- Name: COLUMN discharge_measurement.q; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.q IS 'Discharge at time of measurement [m³/s]';


--
-- Name: COLUMN discharge_measurement.h; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.h IS 'Water level at time of measurement [cm]';


--
-- Name: COLUMN discharge_measurement.width; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.width IS 'Width of water surface [m]';


--
-- Name: COLUMN discharge_measurement.area; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.area IS 'Area of cross-section [m²]';


--
-- Name: COLUMN discharge_measurement.wetted_perimeter; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.wetted_perimeter IS 'Wetted perimeter [m]';


--
-- Name: COLUMN discharge_measurement.depth_max; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.depth_max IS 'Maximum depth [m]';


--
-- Name: COLUMN discharge_measurement.velocity_max; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.velocity_max IS 'Maximum velocity [m/s]';


--
-- Name: COLUMN discharge_measurement.velocity_average; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.velocity_average IS 'velocity_ average [m/s]';


--
-- Name: COLUMN discharge_measurement.temperature; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement.temperature IS 'Water temperature at time of measurement [°C]';


--
-- Name: discharge_measurement_equipment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discharge_measurement_equipment (
    id integer NOT NULL,
    description character varying(255) NOT NULL
);


--
-- Name: COLUMN discharge_measurement_equipment.description; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.discharge_measurement_equipment.description IS 'Description of equipment of discharge calibration measurement';


--
-- Name: discharge_measurement_equipment_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discharge_measurement_equipment_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discharge_measurement_equipment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discharge_measurement_equipment_id_seq OWNED BY public.discharge_measurement_equipment.id;


--
-- Name: discharge_measurement_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discharge_measurement_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discharge_measurement_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discharge_measurement_id_seq OWNED BY public.discharge_measurement.id;


--
-- Name: event_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_logs (
    id integer NOT NULL,
    event_type character varying(50) NOT NULL,
    data text,
    created_at timestamp without time zone,
    user_id integer,
    operator_id integer
);


--
-- Name: COLUMN event_logs.event_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.event_logs.event_type IS 'Identifier of event type';


--
-- Name: event_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_logs_id_seq OWNED BY public.event_logs.id;


--
-- Name: group_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.group_permissions (
    permissionsid integer NOT NULL,
    groupsid integer NOT NULL
);


--
-- Name: TABLE group_permissions; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.group_permissions IS 'Enabled permissions for groups';


--
-- Name: groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.groups (
    id integer NOT NULL,
    name character varying(255) NOT NULL
);


--
-- Name: COLUMN groups.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.groups.id IS 'Id of group';


--
-- Name: COLUMN groups.name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.groups.name IS 'Unique name of group';


--
-- Name: groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.groups_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.groups_id_seq OWNED BY public.groups.id;


--
-- Name: hydro_observed_property; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hydro_observed_property (
    id integer NOT NULL,
    symbol character varying(32) NOT NULL,
    type smallint NOT NULL,
    description character varying(64) NOT NULL,
    unit character varying(12) NOT NULL
);


--
-- Name: COLUMN hydro_observed_property.symbol; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_observed_property.symbol IS 'Abbreviation of observed property (e.g. “h_max_daily” for daily maximum of water level)';


--
-- Name: COLUMN hydro_observed_property.type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_observed_property.type IS 'Real time: 0, processed: 1';


--
-- Name: COLUMN hydro_observed_property.description; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_observed_property.description IS 'Human readable description of observed property (e.g. “Daily maximum of water level”)';


--
-- Name: COLUMN hydro_observed_property.unit; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_observed_property.unit IS 'Unit of parameter, e.g. “cm”';


--
-- Name: hydro_observed_property_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hydro_observed_property_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hydro_observed_property_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hydro_observed_property_id_seq OWNED BY public.hydro_observed_property.id;


--
-- Name: hydro_result; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hydro_result (
    id bigint NOT NULL,
    time_seriesid bigint NOT NULL,
    "time" timestamp(6) without time zone NOT NULL,
    value numeric(20,10) NOT NULL,
    created_at timestamp(6) without time zone NOT NULL,
    is_forecast boolean DEFAULT false NOT NULL
);


--
-- Name: COLUMN hydro_result."time"; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_result."time" IS 'Phenomenon timestamp of measured property (UTC)';


--
-- Name: COLUMN hydro_result.value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_result.value IS 'Value of the measured property, NULL for not available';


--
-- Name: hydro_result_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hydro_result_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hydro_result_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hydro_result_id_seq OWNED BY public.hydro_result.id;


--
-- Name: hydro_time_series; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hydro_time_series (
    id bigint NOT NULL,
    observed_propertyid integer NOT NULL,
    mpointid integer NOT NULL,
    phenomenon_time_begin timestamp(6) without time zone,
    phenomenon_time_end timestamp(6) without time zone,
    result_time timestamp(6) without time zone NOT NULL
);


--
-- Name: COLUMN hydro_time_series.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_time_series.id IS 'Identifier';


--
-- Name: COLUMN hydro_time_series.phenomenon_time_begin; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_time_series.phenomenon_time_begin IS 'Starting phenomenon time of time series (UTC)';


--
-- Name: COLUMN hydro_time_series.phenomenon_time_end; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_time_series.phenomenon_time_end IS 'End phenomenon time of time series (UTC)';


--
-- Name: COLUMN hydro_time_series.result_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydro_time_series.result_time IS 'Result time, when time series was processed (UTC)';


--
-- Name: hydro_time_series_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hydro_time_series_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hydro_time_series_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hydro_time_series_id_seq OWNED BY public.hydro_time_series.id;


--
-- Name: hydropoint; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hydropoint (
    id integer NOT NULL,
    station_classificationid integer,
    operatorid integer,
    bankid integer,
    waterbodyeuropean_river_code character varying(64),
    eucd_wgst character varying(64) NOT NULL,
    ncd_wgst character varying(64) NOT NULL,
    vertical_reference character varying(32),
    long numeric(20,10),
    lat numeric(20,10),
    z numeric(20,10),
    maplong numeric(20,10),
    maplat numeric(20,10),
    country character varying(2) NOT NULL,
    name character varying(128) NOT NULL,
    location character varying(255),
    river_kilometer numeric(20,10),
    catchment_area numeric(20,10),
    gauge_zero numeric(20,10),
    start_time timestamp(6) without time zone,
    end_time timestamp(6) without time zone,
    utc_offset integer,
    river_basin character varying(64)
);


--
-- Name: COLUMN hydropoint.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.id IS 'Identifier of the water gauge station';


--
-- Name: COLUMN hydropoint.eucd_wgst; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.eucd_wgst IS 'International code of water gauge station (Link to DanubeGIS database). [country] & [NCD_WGST] & “_HYDRO”';


--
-- Name: COLUMN hydropoint.ncd_wgst; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.ncd_wgst IS 'National code of water gauge station';


--
-- Name: COLUMN hydropoint.vertical_reference; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.vertical_reference IS 'Reference Vertical Datum identifier, e.g. European Vertical Reference Frame 2007 (EVRF2007)';


--
-- Name: COLUMN hydropoint.long; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.long IS 'Coordinates of water gauge station: EPSG 4326 (WGS 84) Longitude [°]';


--
-- Name: COLUMN hydropoint.lat; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.lat IS 'Coordinates of water gauge station: EPSG 4326 (WGS 84) Latitude [°]';


--
-- Name: COLUMN hydropoint.z; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.z IS 'Coordinates of water gauge station: Height [m]';


--
-- Name: COLUMN hydropoint.maplong; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.maplong IS 'Longitude Coordinates of water gauge station for display on map';


--
-- Name: COLUMN hydropoint.maplat; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.maplat IS 'Lattude Coordinates of water gauge station for display on map';


--
-- Name: COLUMN hydropoint.country; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.country IS 'Country code of water gauge station ISO3166-1 ALPHA-2 (e.g. “DE”)';


--
-- Name: COLUMN hydropoint.name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.name IS 'Locally used name of water gauge station';


--
-- Name: COLUMN hydropoint.location; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.location IS 'Closest commune or landmark';


--
-- Name: COLUMN hydropoint.river_kilometer; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.river_kilometer IS 'Location at river the water gauge station is located, distance from mouth';


--
-- Name: COLUMN hydropoint.catchment_area; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.catchment_area IS 'Drainage basin area of water gauge station [km²]';


--
-- Name: COLUMN hydropoint.gauge_zero; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.gauge_zero IS 'Gravity-related altitude of the zero level of the gauge above the sea level [m]';


--
-- Name: COLUMN hydropoint.start_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.start_time IS 'Starting time of time series measurements on this water gauge station (UTC)';


--
-- Name: COLUMN hydropoint.end_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.end_time IS 'Ending time of time series measurements on this water gauge station (UTC)';


--
-- Name: COLUMN hydropoint.utc_offset; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.utc_offset IS 'Time zone the water gauge station belongs to UTC+X [min], disregarding daylight-saving time.';


--
-- Name: COLUMN hydropoint.river_basin; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint.river_basin IS 'Name of river basin to which meteorological station belongs';


--
-- Name: hydropoint_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hydropoint_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hydropoint_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hydropoint_id_seq OWNED BY public.hydropoint.id;


--
-- Name: hydropoint_observed_property; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hydropoint_observed_property (
    observed_propertyid integer NOT NULL,
    mpointid integer NOT NULL,
    last_update timestamp(6) without time zone,
    min_value numeric(20,10),
    min_value_time timestamp(6) without time zone,
    max_value numeric(20,10),
    max_value_time timestamp(6) without time zone
);


--
-- Name: COLUMN hydropoint_observed_property.last_update; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint_observed_property.last_update IS 'Time of last update of this parameter at this water gauge station (UTC)';


--
-- Name: COLUMN hydropoint_observed_property.min_value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint_observed_property.min_value IS 'Minimum value of parameter in time series measured at this water gauge station';


--
-- Name: COLUMN hydropoint_observed_property.min_value_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint_observed_property.min_value_time IS 'Time at which minimum value was measured (UTC)';


--
-- Name: COLUMN hydropoint_observed_property.max_value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint_observed_property.max_value IS 'Maximum value of parameter in time series measured at this water gauge station';


--
-- Name: COLUMN hydropoint_observed_property.max_value_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydropoint_observed_property.max_value_time IS 'Time at which maximum value was measured (UTC)';


--
-- Name: hydrostation_classification; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hydrostation_classification (
    id integer NOT NULL,
    value character varying(255) NOT NULL
);


--
-- Name: COLUMN hydrostation_classification.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydrostation_classification.id IS 'Identifier';


--
-- Name: COLUMN hydrostation_classification.value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.hydrostation_classification.value IS 'String to describe current classification of water gauge station within hydrological network (e.g. “project station”, “basic-network station”)';


--
-- Name: hydrostation_classification_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hydrostation_classification_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hydrostation_classification_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hydrostation_classification_id_seq OWNED BY public.hydrostation_classification.id;


--
-- Name: meteo_observed_property; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meteo_observed_property (
    id integer NOT NULL,
    symbol character varying(32) NOT NULL,
    type smallint NOT NULL,
    description character varying(64) NOT NULL,
    unit character varying(12) NOT NULL
);


--
-- Name: COLUMN meteo_observed_property.symbol; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_observed_property.symbol IS 'Abbreviation of observed property (e.g. “P_total_daily” for daily total of precipitation)';


--
-- Name: COLUMN meteo_observed_property.type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_observed_property.type IS 'Real time: 0, processed: 1';


--
-- Name: COLUMN meteo_observed_property.description; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_observed_property.description IS 'Human readable description of observed property (e.g. “Daily total of precipitation”)';


--
-- Name: COLUMN meteo_observed_property.unit; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_observed_property.unit IS 'Unit of parameter, e.g. “mm”';


--
-- Name: meteo_observed_property_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.meteo_observed_property_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meteo_observed_property_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.meteo_observed_property_id_seq OWNED BY public.meteo_observed_property.id;


--
-- Name: meteo_result; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meteo_result (
    id bigint NOT NULL,
    meteo_time_seriesid bigint NOT NULL,
    "time" timestamp(6) without time zone NOT NULL,
    value double precision NOT NULL,
    created_at timestamp(6) without time zone NOT NULL,
    is_forecast boolean DEFAULT false
);


--
-- Name: COLUMN meteo_result."time"; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_result."time" IS 'Phenomenon timestamp of measured property (UTC)';


--
-- Name: COLUMN meteo_result.value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_result.value IS 'Value of the measured property, NULL for not available';


--
-- Name: meteo_result_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.meteo_result_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meteo_result_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.meteo_result_id_seq OWNED BY public.meteo_result.id;


--
-- Name: meteo_time_series; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meteo_time_series (
    id bigint NOT NULL,
    meteopointid integer NOT NULL,
    meteo_observed_propertyid integer NOT NULL,
    phenomenon_time_begin timestamp(6) without time zone,
    phenomenon_time_end timestamp(6) without time zone,
    result_time timestamp(6) without time zone NOT NULL
);


--
-- Name: COLUMN meteo_time_series.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_time_series.id IS 'Identifier';


--
-- Name: COLUMN meteo_time_series.phenomenon_time_begin; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_time_series.phenomenon_time_begin IS 'Starting phenomenon time of time series (UTC)';


--
-- Name: COLUMN meteo_time_series.phenomenon_time_end; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_time_series.phenomenon_time_end IS 'End phenomenon time of time series (UTC)';


--
-- Name: COLUMN meteo_time_series.result_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteo_time_series.result_time IS 'Result time, when time series was processed (UTC)';


--
-- Name: meteo_time_series_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.meteo_time_series_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meteo_time_series_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.meteo_time_series_id_seq OWNED BY public.meteo_time_series.id;


--
-- Name: meteopoint; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meteopoint (
    id integer NOT NULL,
    meteostation_classificationid integer,
    operatorid integer,
    eucd_pst character varying(64) NOT NULL,
    ncd_pst character varying(64) NOT NULL,
    vertical_reference character varying(32),
    long numeric(20,10),
    lat numeric(20,10),
    z numeric(20,10),
    maplong numeric(20,10),
    maplat numeric(20,10),
    country character varying(2) NOT NULL,
    name character varying(128) NOT NULL,
    location character varying(255),
    altitude numeric(20,10),
    start_time timestamp(6) without time zone,
    end_time timestamp(6) without time zone,
    utc_offset integer,
    river_basin character varying(64)
);


--
-- Name: COLUMN meteopoint.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.id IS 'Identifier of the water gauge station';


--
-- Name: COLUMN meteopoint.eucd_pst; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.eucd_pst IS 'International code of meteorological station (Link to DanubeGIS database). [country] & [NCD_PST] & “_METEO”';


--
-- Name: COLUMN meteopoint.ncd_pst; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.ncd_pst IS 'National code of meteorological station';


--
-- Name: COLUMN meteopoint.vertical_reference; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.vertical_reference IS 'Reference Vertical Datum identifier, e.g. European Vertical Reference Frame 2007 (EVRF2007)';


--
-- Name: COLUMN meteopoint.long; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.long IS 'Coordinates of meteorological station: EPSG 4326 (WGS 84) Longitude [°]';


--
-- Name: COLUMN meteopoint.lat; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.lat IS 'Coordinates of meteorological station: EPSG 4326 (WGS 84) Latitude [°]';


--
-- Name: COLUMN meteopoint.z; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.z IS 'Coordinates of meteorological station: Height [m]';


--
-- Name: COLUMN meteopoint.maplong; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.maplong IS 'Longitude of meteorological station for display on map';


--
-- Name: COLUMN meteopoint.maplat; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.maplat IS 'Lattude of meteorological station for display on map';


--
-- Name: COLUMN meteopoint.country; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.country IS 'Country code of meteorological station ISO 3166-1 ALPHA-2 (e.g. “DE”)';


--
-- Name: COLUMN meteopoint.name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.name IS 'Locally used name of meteorological station';


--
-- Name: COLUMN meteopoint.location; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.location IS 'Closest commune or landmark';


--
-- Name: COLUMN meteopoint.altitude; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.altitude IS 'Gravity-related altitude of the zero level of the gauge above the sea level [m]. Same as gauge_zero for water gauge stations.';


--
-- Name: COLUMN meteopoint.start_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.start_time IS 'Starting time of time series measurements on this meteorological station (UTC)';


--
-- Name: COLUMN meteopoint.end_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.end_time IS 'Ending time of time series measurements on this meteorological station (UTC)';


--
-- Name: COLUMN meteopoint.utc_offset; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.utc_offset IS 'Time zone the meteorological station belongs to UTC+X [min], disregarding daylight-saving time.';


--
-- Name: COLUMN meteopoint.river_basin; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint.river_basin IS 'Name of river basin to which meteorological station belongs';


--
-- Name: meteopoint_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.meteopoint_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meteopoint_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.meteopoint_id_seq OWNED BY public.meteopoint.id;


--
-- Name: meteopoint_observed_property; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meteopoint_observed_property (
    meteo_observed_propertyid integer NOT NULL,
    meteopointid integer NOT NULL,
    last_update timestamp(6) without time zone,
    min_value numeric(20,10),
    min_value_time timestamp(6) without time zone,
    max_value numeric(20,10),
    max_value_time timestamp(6) without time zone
);


--
-- Name: COLUMN meteopoint_observed_property.last_update; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint_observed_property.last_update IS 'Time of last update of this parameter at this meteorological station (UTC)';


--
-- Name: COLUMN meteopoint_observed_property.min_value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint_observed_property.min_value IS 'Minimum value of parameter in time series measured at this meteorological station';


--
-- Name: COLUMN meteopoint_observed_property.min_value_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint_observed_property.min_value_time IS 'Time at which minimum value was measured (UTC)';


--
-- Name: COLUMN meteopoint_observed_property.max_value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint_observed_property.max_value IS 'Maximum value of parameter in time series measured at this meteorological station';


--
-- Name: COLUMN meteopoint_observed_property.max_value_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteopoint_observed_property.max_value_time IS 'Time at which maximum value was measured (UTC)';


--
-- Name: meteostation_classification; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meteostation_classification (
    id integer NOT NULL,
    value character varying(255) NOT NULL
);


--
-- Name: COLUMN meteostation_classification.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteostation_classification.id IS 'Identifier';


--
-- Name: COLUMN meteostation_classification.value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meteostation_classification.value IS 'String to describe current classification of meteorological station within meteorological network (e.g. “project station”, “basic-network station”)';


--
-- Name: meteostation_classification_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.meteostation_classification_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meteostation_classification_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.meteostation_classification_id_seq OWNED BY public.meteostation_classification.id;


--
-- Name: monitoring_point; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.monitoring_point (
    id integer NOT NULL,
    station_classificationid integer NOT NULL,
    operatorid integer NOT NULL,
    eucd_pst character varying(64) NOT NULL,
    ncd_pst character varying(64) NOT NULL,
    vertical_reference character varying(32) NOT NULL,
    long double precision NOT NULL,
    lat double precision NOT NULL,
    z double precision NOT NULL,
    maplong numeric(18,14),
    maplat numeric(18,14),
    country character varying(2) NOT NULL,
    name character varying(128) NOT NULL,
    location character varying(255) NOT NULL,
    river_kilometer double precision NOT NULL,
    catchment_area double precision NOT NULL,
    altitude double precision NOT NULL,
    start_time timestamp(6) without time zone NOT NULL,
    end_time timestamp(6) without time zone NOT NULL,
    utc_offset integer NOT NULL,
    river_basin character varying(64) NOT NULL
);


--
-- Name: COLUMN monitoring_point.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.id IS 'Identifier of the water gauge station';


--
-- Name: COLUMN monitoring_point.eucd_pst; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.eucd_pst IS 'International code of meteorological station (Link to DanubeGIS database). [country] & [NCD_PST] & “_METEO”';


--
-- Name: COLUMN monitoring_point.ncd_pst; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.ncd_pst IS 'National code of meteorological station';


--
-- Name: COLUMN monitoring_point.vertical_reference; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.vertical_reference IS 'Reference Vertical Datum identifier, e.g. European Vertical Reference Frame 2007 (EVRF2007)';


--
-- Name: COLUMN monitoring_point.long; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.long IS 'Coordinates of meteorological station: EPSG 4326 (WGS 84) Longitude [°]';


--
-- Name: COLUMN monitoring_point.lat; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.lat IS 'Coordinates of meteorological station: Height [m]';


--
-- Name: COLUMN monitoring_point.z; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.z IS 'Coordinates of water gauge station: Height [m]';


--
-- Name: COLUMN monitoring_point.maplong; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.maplong IS 'Longitude of meteorological station for display on map';


--
-- Name: COLUMN monitoring_point.maplat; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.maplat IS 'Lattude of meteorological station for display on map';


--
-- Name: COLUMN monitoring_point.country; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.country IS 'Country code of meteorological station ISO 3166-1 ALPHA-2 (e.g. “DE”)';


--
-- Name: COLUMN monitoring_point.name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.name IS 'Locally used name of meteorological station';


--
-- Name: COLUMN monitoring_point.location; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.location IS 'Closest commune or landmark';


--
-- Name: COLUMN monitoring_point.river_kilometer; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.river_kilometer IS 'Location at river the water gauge station is located, distance from mouth';


--
-- Name: COLUMN monitoring_point.catchment_area; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.catchment_area IS 'Drainage basin area of water gauge station [km²]';


--
-- Name: COLUMN monitoring_point.altitude; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.altitude IS 'Gravity-related altitude of the zero level of the gauge above the sea level [m]. Same as gauge_zero for water gauge stations.';


--
-- Name: COLUMN monitoring_point.start_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.start_time IS 'Starting time of time series measurements on this meteorological station (UTC)';


--
-- Name: COLUMN monitoring_point.end_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.end_time IS 'Ending time of time series measurements on this meteorological station (UTC)';


--
-- Name: COLUMN monitoring_point.utc_offset; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.utc_offset IS 'Time zone the meteorological station belongs to UTC+X [min], disregarding daylight-saving time.';


--
-- Name: COLUMN monitoring_point.river_basin; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.monitoring_point.river_basin IS 'Name of river basin to which meteorological station belongs';


--
-- Name: monitoring_point_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.monitoring_point_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: monitoring_point_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.monitoring_point_id_seq OWNED BY public.monitoring_point.id;


--
-- Name: operator; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.operator (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(255) NOT NULL,
    url character varying(255),
    other_info character varying(255)
);


--
-- Name: COLUMN operator.name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.operator.name IS 'Name of organization which operates water gauge station';


--
-- Name: COLUMN operator.address; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.operator.address IS 'Address of operating organization';


--
-- Name: COLUMN operator.email; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.operator.email IS 'Email address of operating organization';


--
-- Name: COLUMN operator.phone; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.operator.phone IS 'Phone number of operating organization';


--
-- Name: COLUMN operator.url; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.operator.url IS 'Website of operating organization';


--
-- Name: COLUMN operator.other_info; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.operator.other_info IS 'Information on the operator not fitting in above fields';


--
-- Name: operator_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.operator_groups (
    groupsid integer NOT NULL,
    operatorid integer NOT NULL
);


--
-- Name: TABLE operator_groups; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.operator_groups IS 'Connection table between operator and groups. Operators could have more than one groups, but groups should be unique in this table';


--
-- Name: operator_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.operator_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: operator_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.operator_id_seq OWNED BY public.operator.id;


--
-- Name: operator_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.operator_users (
    usersid integer NOT NULL,
    operatorid integer NOT NULL
);


--
-- Name: TABLE operator_users; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.operator_users IS 'Connection table between operator and users. Operators could have more than one users, but users should be unique in this table';


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id integer NOT NULL,
    name character varying(255) NOT NULL
);


--
-- Name: COLUMN permissions.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.permissions.id IS 'Permission id';


--
-- Name: COLUMN permissions.name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.permissions.name IS 'Permission unique name';


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: public_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.public_keys (
    id integer NOT NULL,
    usersid integer NOT NULL,
    public_key text NOT NULL,
    revoked boolean DEFAULT false NOT NULL,
    revoked_at timestamp without time zone
);


--
-- Name: COLUMN public_keys.id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.public_keys.id IS 'Internal id of public key';


--
-- Name: COLUMN public_keys.public_key; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.public_keys.public_key IS 'The public key';


--
-- Name: public_keys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.public_keys_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: public_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.public_keys_id_seq OWNED BY public.public_keys.id;


--
-- Name: riverbank; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.riverbank (
    id integer NOT NULL,
    value character varying(255) NOT NULL
);


--
-- Name: COLUMN riverbank.value; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.riverbank.value IS 'String to describe side of river “left” / ”right” / ”bridge” in downstream direction.';


--
-- Name: riverbank_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.riverbank_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: riverbank_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.riverbank_id_seq OWNED BY public.riverbank.id;


--
-- Name: user_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_permissions (
    permissionsid integer NOT NULL,
    usersid integer NOT NULL
);


--
-- Name: TABLE user_permissions; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.user_permissions IS 'Enabled permissions for users';


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id integer NOT NULL,
    name character varying(255),
    username character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255),
    loggedin_at timestamp without time zone,
    deleted_at timestamp without time zone
);


--
-- Name: COLUMN users.name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.users.name IS 'The name of the adminstration user';


--
-- Name: COLUMN users.username; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.users.username IS 'The username for login';


--
-- Name: COLUMN users.email; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.users.email IS 'E-mail address of admin user';


--
-- Name: COLUMN users.password; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.users.password IS 'Hashed password';


--
-- Name: COLUMN users.loggedin_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.users.loggedin_at IS 'Date and time of last login';


--
-- Name: COLUMN users.deleted_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.users.deleted_at IS 'Mark deleted users with this field (soft-delete)';


--
-- Name: users_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users_groups (
    usersid integer NOT NULL,
    groupsid integer NOT NULL
);


--
-- Name: TABLE users_groups; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.users_groups IS 'Users-groups assignment (many-to-many)';


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: warning_level; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warning_level (
    warning_level integer NOT NULL,
    mpointid integer NOT NULL,
    water_level numeric(20,10) NOT NULL
);


--
-- Name: warning_level_warning_level_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.warning_level_warning_level_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: warning_level_warning_level_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.warning_level_warning_level_seq OWNED BY public.warning_level.warning_level;


--
-- Name: waterbody; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.waterbody (
    european_river_code character varying(64) NOT NULL,
    cname character varying(255) NOT NULL
);


--
-- Name: COLUMN waterbody.european_river_code; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.waterbody.european_river_code IS 'International code of river or canal to which the water gauge station belongs. Same as River.EUCD_RIV in DanubeGIS.';


--
-- Name: COLUMN waterbody.cname; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.waterbody.cname IS 'Human readable name of the water body, part of river, etc. the code refers to';


--
-- Name: discharge_measurement id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discharge_measurement ALTER COLUMN id SET DEFAULT nextval('public.discharge_measurement_id_seq'::regclass);


--
-- Name: discharge_measurement_equipment id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discharge_measurement_equipment ALTER COLUMN id SET DEFAULT nextval('public.discharge_measurement_equipment_id_seq'::regclass);


--
-- Name: event_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_logs ALTER COLUMN id SET DEFAULT nextval('public.event_logs_id_seq'::regclass);


--
-- Name: groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.groups ALTER COLUMN id SET DEFAULT nextval('public.groups_id_seq'::regclass);


--
-- Name: hydro_observed_property id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_observed_property ALTER COLUMN id SET DEFAULT nextval('public.hydro_observed_property_id_seq'::regclass);


--
-- Name: hydro_result id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_result ALTER COLUMN id SET DEFAULT nextval('public.hydro_result_id_seq'::regclass);


--
-- Name: hydro_time_series id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_time_series ALTER COLUMN id SET DEFAULT nextval('public.hydro_time_series_id_seq'::regclass);


--
-- Name: hydropoint id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint ALTER COLUMN id SET DEFAULT nextval('public.hydropoint_id_seq'::regclass);


--
-- Name: hydrostation_classification id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydrostation_classification ALTER COLUMN id SET DEFAULT nextval('public.hydrostation_classification_id_seq'::regclass);


--
-- Name: meteo_observed_property id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_observed_property ALTER COLUMN id SET DEFAULT nextval('public.meteo_observed_property_id_seq'::regclass);


--
-- Name: meteo_result id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_result ALTER COLUMN id SET DEFAULT nextval('public.meteo_result_id_seq'::regclass);


--
-- Name: meteo_time_series id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_time_series ALTER COLUMN id SET DEFAULT nextval('public.meteo_time_series_id_seq'::regclass);


--
-- Name: meteopoint id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteopoint ALTER COLUMN id SET DEFAULT nextval('public.meteopoint_id_seq'::regclass);


--
-- Name: meteostation_classification id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteostation_classification ALTER COLUMN id SET DEFAULT nextval('public.meteostation_classification_id_seq'::regclass);


--
-- Name: monitoring_point id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitoring_point ALTER COLUMN id SET DEFAULT nextval('public.monitoring_point_id_seq'::regclass);


--
-- Name: operator id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operator ALTER COLUMN id SET DEFAULT nextval('public.operator_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: public_keys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.public_keys ALTER COLUMN id SET DEFAULT nextval('public.public_keys_id_seq'::regclass);


--
-- Name: riverbank id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.riverbank ALTER COLUMN id SET DEFAULT nextval('public.riverbank_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: warning_level warning_level; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warning_level ALTER COLUMN warning_level SET DEFAULT nextval('public.warning_level_warning_level_seq'::regclass);


--
-- Name: discharge_measurement_equipment discharge_measurement_equipment_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discharge_measurement_equipment
    ADD CONSTRAINT discharge_measurement_equipment_pkey PRIMARY KEY (id);


--
-- Name: discharge_measurement discharge_measurement_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discharge_measurement
    ADD CONSTRAINT discharge_measurement_pkey PRIMARY KEY (id);


--
-- Name: event_logs event_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_logs
    ADD CONSTRAINT event_logs_pkey PRIMARY KEY (id);


--
-- Name: group_permissions group_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.group_permissions
    ADD CONSTRAINT group_permissions_pkey PRIMARY KEY (permissionsid, groupsid);


--
-- Name: groups groups_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_name_key UNIQUE (name);


--
-- Name: groups groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (id);


--
-- Name: hydro_observed_property hydro_observed_property_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_observed_property
    ADD CONSTRAINT hydro_observed_property_pkey PRIMARY KEY (id);


--
-- Name: hydro_observed_property hydro_observed_property_symbol_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_observed_property
    ADD CONSTRAINT hydro_observed_property_symbol_key UNIQUE (symbol);


--
-- Name: hydro_result hydro_result_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_result
    ADD CONSTRAINT hydro_result_pkey PRIMARY KEY (id);


--
-- Name: hydro_time_series hydro_time_series_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_time_series
    ADD CONSTRAINT hydro_time_series_pkey PRIMARY KEY (id);


--
-- Name: hydropoint hydropoint_eucd_wgst_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint
    ADD CONSTRAINT hydropoint_eucd_wgst_key UNIQUE (eucd_wgst);


--
-- Name: hydropoint_observed_property hydropoint_observed_property_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint_observed_property
    ADD CONSTRAINT hydropoint_observed_property_pk UNIQUE (observed_propertyid, mpointid);


--
-- Name: hydropoint hydropoint_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint
    ADD CONSTRAINT hydropoint_pkey PRIMARY KEY (id);


--
-- Name: hydrostation_classification hydrostation_classification_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydrostation_classification
    ADD CONSTRAINT hydrostation_classification_pkey PRIMARY KEY (id);


--
-- Name: meteo_observed_property meteo_observed_property_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_observed_property
    ADD CONSTRAINT meteo_observed_property_pkey PRIMARY KEY (id);


--
-- Name: meteo_result meteo_result_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_result
    ADD CONSTRAINT meteo_result_pkey PRIMARY KEY (id);


--
-- Name: meteo_time_series meteo_time_series_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_time_series
    ADD CONSTRAINT meteo_time_series_pkey PRIMARY KEY (id);


--
-- Name: meteopoint meteopoint_eucd_pst_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteopoint
    ADD CONSTRAINT meteopoint_eucd_pst_key UNIQUE (eucd_pst);


--
-- Name: meteopoint_observed_property meteopoint_observed_property_pk; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteopoint_observed_property
    ADD CONSTRAINT meteopoint_observed_property_pk UNIQUE (meteo_observed_propertyid, meteopointid);


--
-- Name: meteopoint meteopoint_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteopoint
    ADD CONSTRAINT meteopoint_pkey PRIMARY KEY (id);


--
-- Name: meteostation_classification meteostation_classification_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteostation_classification
    ADD CONSTRAINT meteostation_classification_pkey PRIMARY KEY (id);


--
-- Name: monitoring_point monitoring_point_eucd_pst_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitoring_point
    ADD CONSTRAINT monitoring_point_eucd_pst_key UNIQUE (eucd_pst);


--
-- Name: monitoring_point monitoring_point_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitoring_point
    ADD CONSTRAINT monitoring_point_pkey PRIMARY KEY (id);


--
-- Name: operator_groups operator_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operator_groups
    ADD CONSTRAINT operator_groups_pkey PRIMARY KEY (groupsid, operatorid);


--
-- Name: operator operator_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operator
    ADD CONSTRAINT operator_pkey PRIMARY KEY (id);


--
-- Name: operator_users operator_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operator_users
    ADD CONSTRAINT operator_users_pkey PRIMARY KEY (usersid, operatorid);


--
-- Name: permissions permissions_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_key UNIQUE (name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: public_keys public_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.public_keys
    ADD CONSTRAINT public_keys_pkey PRIMARY KEY (id);


--
-- Name: riverbank riverbank_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.riverbank
    ADD CONSTRAINT riverbank_pkey PRIMARY KEY (id);


--
-- Name: riverbank riverbank_value_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.riverbank
    ADD CONSTRAINT riverbank_value_key UNIQUE (value);


--
-- Name: user_permissions user_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_permissions
    ADD CONSTRAINT user_permissions_pkey PRIMARY KEY (permissionsid, usersid);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users_groups users_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users_groups
    ADD CONSTRAINT users_groups_pkey PRIMARY KEY (usersid, groupsid);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: warning_level warning_level_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warning_level
    ADD CONSTRAINT warning_level_pkey PRIMARY KEY (warning_level);


--
-- Name: waterbody waterbody_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.waterbody
    ADD CONSTRAINT waterbody_pkey PRIMARY KEY (european_river_code);


--
-- Name: hydropoint_country; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hydropoint_country ON public.hydropoint USING btree (country);


--
-- Name: hydropoint_ncd_wgst; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hydropoint_ncd_wgst ON public.hydropoint USING btree (ncd_wgst);


--
-- Name: hydropoint_vertical_reference; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hydropoint_vertical_reference ON public.hydropoint USING btree (vertical_reference);


--
-- Name: meteopoint_country; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX meteopoint_country ON public.meteopoint USING btree (country);


--
-- Name: meteopoint_ncd_pst; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX meteopoint_ncd_pst ON public.meteopoint USING btree (ncd_pst);


--
-- Name: meteopoint_vertical_reference; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX meteopoint_vertical_reference ON public.meteopoint USING btree (vertical_reference);


--
-- Name: monitoring_point_country; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX monitoring_point_country ON public.monitoring_point USING btree (country);


--
-- Name: monitoring_point_ncd_pst; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX monitoring_point_ncd_pst ON public.monitoring_point USING btree (ncd_pst);


--
-- Name: monitoring_point_vertical_reference; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX monitoring_point_vertical_reference ON public.monitoring_point USING btree (vertical_reference);


--
-- Name: users_username; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX users_username ON public.users USING btree (username);


--
-- Name: discharge_measurement fkdischarge_146694; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discharge_measurement
    ADD CONSTRAINT fkdischarge_146694 FOREIGN KEY (operatorid) REFERENCES public.operator(id);


--
-- Name: discharge_measurement fkdischarge_237635; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discharge_measurement
    ADD CONSTRAINT fkdischarge_237635 FOREIGN KEY (mpointid) REFERENCES public.hydropoint(id);


--
-- Name: discharge_measurement fkdischarge_808880; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discharge_measurement
    ADD CONSTRAINT fkdischarge_808880 FOREIGN KEY (discharge_measurement_equipmentid) REFERENCES public.discharge_measurement_equipment(id);


--
-- Name: event_logs fkevent_logs578586; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_logs
    ADD CONSTRAINT fkevent_logs578586 FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: event_logs fkevent_logs900211; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_logs
    ADD CONSTRAINT fkevent_logs900211 FOREIGN KEY (operator_id) REFERENCES public.operator(id);


--
-- Name: group_permissions fkgroup_perm470913; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.group_permissions
    ADD CONSTRAINT fkgroup_perm470913 FOREIGN KEY (permissionsid) REFERENCES public.permissions(id);


--
-- Name: group_permissions fkgroup_perm520880; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.group_permissions
    ADD CONSTRAINT fkgroup_perm520880 FOREIGN KEY (groupsid) REFERENCES public.groups(id);


--
-- Name: hydro_result fkhydro_resu859671; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_result
    ADD CONSTRAINT fkhydro_resu859671 FOREIGN KEY (time_seriesid) REFERENCES public.hydro_time_series(id);


--
-- Name: hydro_time_series fkhydro_time174251; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_time_series
    ADD CONSTRAINT fkhydro_time174251 FOREIGN KEY (observed_propertyid) REFERENCES public.hydro_observed_property(id);


--
-- Name: hydro_time_series fkhydro_time663317; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydro_time_series
    ADD CONSTRAINT fkhydro_time663317 FOREIGN KEY (mpointid) REFERENCES public.hydropoint(id);


--
-- Name: hydropoint fkhydropoint103092; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint
    ADD CONSTRAINT fkhydropoint103092 FOREIGN KEY (bankid) REFERENCES public.riverbank(id);


--
-- Name: hydropoint fkhydropoint321157; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint
    ADD CONSTRAINT fkhydropoint321157 FOREIGN KEY (station_classificationid) REFERENCES public.hydrostation_classification(id);


--
-- Name: hydropoint_observed_property fkhydropoint365425; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint_observed_property
    ADD CONSTRAINT fkhydropoint365425 FOREIGN KEY (observed_propertyid) REFERENCES public.hydro_observed_property(id);


--
-- Name: hydropoint fkhydropoint519479; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint
    ADD CONSTRAINT fkhydropoint519479 FOREIGN KEY (waterbodyeuropean_river_code) REFERENCES public.waterbody(european_river_code);


--
-- Name: hydropoint_observed_property fkhydropoint591372; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint_observed_property
    ADD CONSTRAINT fkhydropoint591372 FOREIGN KEY (mpointid) REFERENCES public.hydropoint(id);


--
-- Name: hydropoint fkhydropoint850016; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hydropoint
    ADD CONSTRAINT fkhydropoint850016 FOREIGN KEY (operatorid) REFERENCES public.operator(id);


--
-- Name: meteo_result fkmeteo_resu773961; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_result
    ADD CONSTRAINT fkmeteo_resu773961 FOREIGN KEY (meteo_time_seriesid) REFERENCES public.meteo_time_series(id);


--
-- Name: meteo_time_series fkmeteo_time621587; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_time_series
    ADD CONSTRAINT fkmeteo_time621587 FOREIGN KEY (meteopointid) REFERENCES public.meteopoint(id);


--
-- Name: meteo_time_series fkmeteo_time714612; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteo_time_series
    ADD CONSTRAINT fkmeteo_time714612 FOREIGN KEY (meteo_observed_propertyid) REFERENCES public.meteo_observed_property(id);


--
-- Name: meteopoint fkmeteopoint181681; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteopoint
    ADD CONSTRAINT fkmeteopoint181681 FOREIGN KEY (operatorid) REFERENCES public.operator(id);


--
-- Name: meteopoint fkmeteopoint29156; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteopoint
    ADD CONSTRAINT fkmeteopoint29156 FOREIGN KEY (meteostation_classificationid) REFERENCES public.meteostation_classification(id);


--
-- Name: meteopoint_observed_property fkmeteopoint4068; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteopoint_observed_property
    ADD CONSTRAINT fkmeteopoint4068 FOREIGN KEY (meteo_observed_propertyid) REFERENCES public.meteo_observed_property(id);


--
-- Name: meteopoint_observed_property fkmeteopoint667867; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meteopoint_observed_property
    ADD CONSTRAINT fkmeteopoint667867 FOREIGN KEY (meteopointid) REFERENCES public.meteopoint(id);


--
-- Name: monitoring_point fkmonitoring210523; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitoring_point
    ADD CONSTRAINT fkmonitoring210523 FOREIGN KEY (operatorid) REFERENCES public.operator(id);


--
-- Name: monitoring_point fkmonitoring739382; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitoring_point
    ADD CONSTRAINT fkmonitoring739382 FOREIGN KEY (station_classificationid) REFERENCES public.hydrostation_classification(id);


--
-- Name: operator_groups fkoperator_g537201; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operator_groups
    ADD CONSTRAINT fkoperator_g537201 FOREIGN KEY (operatorid) REFERENCES public.operator(id);


--
-- Name: operator_groups fkoperator_g645286; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operator_groups
    ADD CONSTRAINT fkoperator_g645286 FOREIGN KEY (groupsid) REFERENCES public.groups(id);


--
-- Name: operator_users fkoperator_u193208; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operator_users
    ADD CONSTRAINT fkoperator_u193208 FOREIGN KEY (usersid) REFERENCES public.users(id);


--
-- Name: operator_users fkoperator_u846668; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operator_users
    ADD CONSTRAINT fkoperator_u846668 FOREIGN KEY (operatorid) REFERENCES public.operator(id);


--
-- Name: user_permissions fkuser_permi47096; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_permissions
    ADD CONSTRAINT fkuser_permi47096 FOREIGN KEY (usersid) REFERENCES public.users(id);


--
-- Name: user_permissions fkuser_permi642438; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_permissions
    ADD CONSTRAINT fkuser_permi642438 FOREIGN KEY (permissionsid) REFERENCES public.permissions(id);


--
-- Name: users_groups fkusers_grou369620; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users_groups
    ADD CONSTRAINT fkusers_grou369620 FOREIGN KEY (usersid) REFERENCES public.users(id);


--
-- Name: users_groups fkusers_grou914995; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users_groups
    ADD CONSTRAINT fkusers_grou914995 FOREIGN KEY (groupsid) REFERENCES public.groups(id);


--
-- Name: public_keys fkuserspublickey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.public_keys
    ADD CONSTRAINT fkuserspublickey FOREIGN KEY (usersid) REFERENCES public.users(id);


--
-- Name: warning_level fkwarning_le889936; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warning_level
    ADD CONSTRAINT fkwarning_le889936 FOREIGN KEY (mpointid) REFERENCES public.hydropoint(id);


--
-- PostgreSQL database dump complete
--

