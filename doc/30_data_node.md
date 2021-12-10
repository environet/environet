# Data node

## Overview
A *data node* is designed to be run at a data source with the purpose of gathering metering point measurements stored in some third party format, such as a plain text file, spreadsheet or web resource.  

It transforms these data to a format compatible with the Environet *distribution node* API, and uploads the results to a distribution node.  

The gathering and uploading of data is accomplished by way of uploader plugin configurations, which can then be run by calling a script, typically by a cron job.

Before a data node can start uploading measurements, the metering points and observable properties (for which the plugin will upload measurements) have to be configured beforehand on the Environet distribution node that the plugin will be uploading to.  
   
An API user with will also need to be configured, to authenticate upload requests.  

## Prepare distribution node to receive data

You can set the following up if you have access to the [distribution node admin panel](25_admin_user_manual.md).

**API user**  
  
  Configure a new or existing user with **public ssl key** for . Click [here](#ssl-key-pair-generation-tool) if you need help creating SSL keys.
  Take note of the **username**, you are going to need it later - along with the **private key** - to configure your data node.

**Observed properties**  
  
  Check that the distribution node has an *observed property* corresponding to each type of data to be uploaded.
  Take note of the **symbol** value of these for later.

**Monitoring points**
  
  Finally, define the monitoring points for which data will be uploaded.  
  **You will have to link observed properties to each monitoring point as well.**

## Setup steps

Before configuring the data node, you need to install the Environet project. The steps to install dependencies and download the Environet source itself, are outlined in the [setup](11_setup.md) section of this document.

**Configure data directory**
  
If your data node will be reading data from a file or directory on the system where the node is running, you will have to configure the LOCAL_DATA_DIR environment variable with the path to the directory where the data will be found.  
If the data node is going to access the measurements over a network connection, you can skip this step.

- Create an `.env` file by copying `.env.example`
- Uncomment the line containing LOCAL_DATA_DIR (by deleting the # character from the beginning of the line)
- Enter the path to the data directory. For example:
  On a system where the measurements are stored in csv files in the `/var/measurements` directory, the line would read:`LOCAL_DATA_DIR=/var/measurements`

## Creating configurations
    
Run `./environet data plugin create` to start an interactive script, that will guide you through creating an uploader plugin configuration.  

Generated configurations will be saved to the `/conf/plugins/configurations` folder, with the filename provided at the end of the process.  

## Running a configuration

Run `./environet data plugin run [configuration name]` to run an uploader plugin configuration. (If you want to run regularly, you should set up a cron job to execute this command at regular intervals.)

## SSL key pair generation tool
To generate an ssl key pair, you can run the command `./environet data tool keygen`.  
Private keys should be placed in the `conf/plugins/credentials` directory, which is where the keygen tool will place them, by default.  

## Uploader plugin configuration files (conversion filters)

The purpose of the conversion filters is to provide a translation from the data format of the data pro-vider to the common data format of HyMeDES EnviroNet platform.
In general, there are the following ways to provide the data: via an FTP-server, via a web API, via HTTP or via a local file stored on the data node. The data is encoded in CSV-file or XML-file.
The country-specific settings for data conversion (conversion filters) are done via a basic configuration text file with keyword value pairs and optionally two JSON files. The JSON files are referred to in the basic configuration file. In most cases, the JSON configuration files are not needed.

There are two options to provide the data: Pushing the data (option A) or pulling the data (option B). In the case of option A, a data node is running on a server of the data provider. It regularly accesses the data files and sends them to HyMeDES EnviroNet. In option B, HyMeDES EnviroNet accesses a server of the data provider and pulls data files from it.

In both cases, filter configuration files are identical. The only difference is that the configuration file for option A resides on a server of the data provider and can be edited locally, while in option B, the configuration is hosted by HyMeDES EnviroNet. In the latter case, updates of configuration files have to be sent to the host of the HyMeDES EnviroNet to get in effect. The central server of the HyMeDES EnviroNet is called distribution node.
For most configurations, only the basic configuration file is needed. If data file format is XML, the FORMATS json configuration file is used to describe tag names and hierarchy of the XML file. If moni-toring point or observed property is specified in the URL or in data file name, or if data is provided within a ZIP archive, a CONVERSIONS json configuration file is needed. Both files are referred to from within the basic configuration file.
Required files for different use cases are depicted in the following table:

|| Basic configuration file | FORMATS json file | CONVERSION json file |
| :---: | :---: | :---: | :---: |
| CSV data file format | yes | | |
| XML data file format | yes | yes | yes |
| Static URL / file names | yes | | yes |
| Dynamic URL / file names | yes | | yes |
| Data files in ZIP | yes | | yes |

### Basic configuration file
The basic configuration text files are located where the Data Node was installed to in sub-folder conf/plugins/configuration. In the basic configuration file, the way of the transport (called transport layer) is specified (FTP, HTTP, or a local file) and the format (called parser layer) of data file (CSV or XML).

The configuration files have always three sections which configure the properties of the three layers:
* Transport layer: Gets the data from local / remote file, or web API, etc.
* Parser layer: Processes the received data to the format which will be compatible with the API endpoint of the distribution node.
* API client layer: Sends the data to the distribution node.

The format of the configuration file follows the standards of ini-files as documented here: [https://en.wikipedia.org/wiki/INI_file](https://en.wikipedia.org/wiki/INI_file)
It must contain three sections, for transport, parser, and API client layers. So, the basic structure is like this:
```
[transport]
property = value

[parser]
property = value

[apiClient]
property = value
```
A typical example of a basic configuration file for a data node which acquires CSV files from an FTP server is shown in the following. Access information is specified in section “[transport]”, and file format in section “[parser]”. In section “[apiClient]”, the access to upload data to HyMeDES EnviroNet is spec-ified. In the following sections, all parameters are described in detail.
```
[transport]
className = Environet\Sys\Plugins\Transports\FtpTransport 
host = "XX.XX.XXX.X" 
secure = "0" 
username = "XXXX" 
password = "XXXX" 
path = "HYDRO_DATA" 
filenamePattern = "HO_*.csv" 
newestFileOnly = "1" 

[parser] 
className = Environet\Sys\Plugins\Parsers\CsvParser 
csvDelimiter = "," 
nHeaderSkip = 1 
mPointIdCol = 0 
timeCol = 3 
timeFormat = dmY H:i 
properties[] = "h;5" 

[apiClient] 
className = Environet\Sys\Plugins\ApiClient 
apiAddress = https://xxx.xxx.xx/ 
apiUsername = username 
privateKeyPath = username_private_key.pem
```
For a web API which uses data files in XML format a typical example is:
```
[transport] 
className = Environet\Sys\Plugins\Transports\HttpTransport 
conversionsFilename = "ABC-conversions.json" 
username = "YYYY" 
password = "YYYY" 

[parser] 
className = Environet\Sys\Plugins\Parsers\XmlParser 
timeZone = Europe/Berlin 
separatorThousands = "" 
separatorDecimals = "," 
formatsFilename = "ABC-formats.json" 

[apiClient] 
className = Environet\Sys\Plugins\ApiClient 
apiAddress = https://xxx.xxx.xx 
apiUsername = username2 
privateKeyPath = username2_private_key.pem
```
In this case, additional JSON configuration files are needed and referred to for accessing the web API and to specify the XML format.
In the following sections the properties of the three sections of the basic configuration files are de-scribed in detail.

#### Transport layer properties
Common properties:
* _className_ (required): The FQCN (fully qualified class name) of the PHP class which repre-sents the layer. For example: Environet\Sys\Plugins\Transports\FtpTransport

##### HttpTransport

Takes the data from an HTTP source. It has two modes. In manual mode the transporter works based on a fixed URL, and in conversion mode the URL is built based on the CONVERSIONS json configuration file.
* _url_ (required in “manual” mode): The URL of source, if not defined in JSON configuration file
* _isIndex_ (optional): 1, if the source is only an index page which contains links to the files. 0, if the source is the file itself
* _indexRegexPattern_ (optional): If isIndex is 1, this is the regular expression pattern which finds the links to the data files
* _conversionsFilename_ (required in “conversion” mode): The file name of the CONVERSIONS json file, relative to the path of the configuration folder.
* _username_ (optional): Authorization username to access the source
* _password_ (optional): Authorization password to access to source

##### LocalFileTransport

Takes the data from a file which is on the same file system as the data node
* _path_ (required): The absolute path to the data file

##### LocalDirectoryTransport

Takes the data from files under a directory, which is on the same file system as the data node
* _path_ (required): The absolute path to the directory

##### FtpTransport

Takes the data from a remote FTP server
* _host_ (required): Host of the FTP server
* _secure_ (required): 1, if the connection can be secured by SSL, otherwise 0
* _port_ (optional): Port of the FTP server, if non-standard
* _username_ (required): FTP authentication username
* _password_ (required): FTP authentication password
* _path_ (required): The path of the directory which contains the data files, relative to the root of the FTP connection.
* _filenamePattern_ (required): Pattern of the filenames which should be processed by the transport. Asterisk (```*```) characters can be used for variable parts of the filename
* _newestFileOnly_ (required): If 1, only the newest file (by date) will be transported
* _conversionsFilename_ (required): If the layer has a conversion specification file, this is the file name of the CONVERSIONS json file, relative to the path of the configuration folder.
* _lastNDaysOnly_ (optional): Use only files with modification time newer than or equal N days from current day.

#### Parser layer properties

Common properties:
* _timeZone_ (required): A valid timezone, in which the data is stored in the source. The times will be converted to UTC before the API client layer. Possible values: [https://www.php.net/manual/en/timezones.php](https://www.php.net/manual/en/timezones.php)

##### CsvParser

For files which are in CSV format
* _csvDelimiter_ (required): The character which separates values from each other
* _nHeaderSkip_ (optional): Number of lines which will be skipped before data
* _mPointIdCol_ (required): Number of column (zero based) which contains the ID of moni-toring point
* _timeCol_ (required, if time is in a column): Number of column (zero based) which contains the time
* _skipValue_ (optional): A specific value which should be parsed as a non-existent value
* _timeFormat_ (required, if time is in a column): Format of time if in a column
* _timeInFilenameFormat_ (required, if time is in filename): If defined, the time should be parsed from the filename, and not from a column
* _properties[]_ (required): The sign (abbreviation) of the observed property, and the col-umn number (zero based) in which the property value can be found. The name and the value must be separated by “;”. Example: “h;6”. This property can be defined multiple times, one per property
* _propertyLevel_ (required): In case of “column” the values of an observed property have their own column in the files. In case of “row” the rows have a column containing observed property symbols that specify which symbol the value belongs to.
* _conversionsFilename_ (optional): If the layer has a conversion specification file, this contains the path to the CONVERSIONS json file
* _propertySymbolColumn_ (required, if propertyLevel is row): Number of column which contains the symbol of the property
* _propertyValueColumn_ (required, if propertyLevel is row): Number of column which contains the value of the property

##### XmlParser

For files which are in XML format
* _separatorThousands_ (optional): The thousands separator of values in XML file
* _separatorDecimals_ (optional): The decimal separator of values in XML file
* _formatsFilename_ (required): The filename which contains the format specification of XML file.
* _skipEmptyValueTag_ (optional): If 1, the empty value tags will be skipped. If 0, these empty value tag will be processed as a zero value

##### JsonParser
For files which are in json format
* _monitoringPointId_ (required): Id of monitoring point of the data in json file
* _propertySymbol_ (required): Observed property symbol of the data in json file

#### API client layer properties

##### ApiClient

Data of target distribution node
* _apiAddress_ (required): Host of distribution node
* _apiUsername_ (required): Username for upload to distribution node
* _privateKeyPath_ (required): Path to private key

### JSON configuration files

In the FORMATS json file the format specifications for XML data files are defined. The observed prop-erty names used in it refer to the variable definitions specified in the CONVERSIONS json file. The CONVERSIONS json file defines the variables for monitoring point, observed property or time intervals for use in the URL, file names and/or in the data file itself. The CONVERSIONS json file is required if there is a complex structure of the URL to access data files, filenames containing variable parts, or zipped data. For example, if the identifier of the monitoring point is coded within the filename or the URL, a CONVERSIONS json file is required.

JSON is a simple standardized format to easily define structured data. Arrays (lists of entries) are de-fined with brackets like [ “a”, “b”, “c” ] and objects with curly braces. Objects have properties, and the properties have values. For example, the following defines an object with the property “Property1”, which has value “Example” and a property named “Property2” which has value “a value”: { “Prop-erty1”: “Example”, “Property2”: “a value” }
In the following the format of the FORMATS file and the CONVERSIONS file are described in detail.

#### FORMATS-file: Format Specification for XML data files

The Format Specifications mainly defines the tag hierarchy in the XML data file for the entities moni-toring point, observed properties and date specifications.
The json is an array of objects. Each object has the following properties:
* Parameter
* Value
* Unit
* Attribute
* optional
* Tag Hierarchy

There may be as many entries in the array as needed. The property “Parameter” determines the entity the information in the object is about. It may be one of the strings “MonitoringPoint”, “ObservedProp-ertyValue”, “ObservedPropertySymbol” and the date specific entities “Year”, “Month”, “Day”, “Hour”, “Minute”, “Date”, “Time” and “DateTime”, depending in which way the date is given in the XML file (in a single XML tag or in multiple separate tags)

The property “Tag Hierarchy” is the path to the information specified by “Parameter”. It is an array of strings containing the tags names that need to be traversed in the specified order to get to the desired information.

The following is an example of part of a data file of the German hydrological service, LfU. The monitor-ing point id is available by the tag hierarchy “hnd-daten”, “messstelle”, “nummer”. Tag hierarchy strings are given without angle brackets. In this example, date is given separately in the tags “jahr” (year), “monat” (month), “tag” (day), “stunde” (hour) and “minute” (minute).
```xml
<hnd-daten> 
  <messstelle> 
    <nummer>10026301</nummer> 
    <messwert> 
      <datum> 
        <jahr>2020</jahr> 
        <monat>06</monat> 
        <tag>09</tag> 
        <stunde>00</stunde> 
        <minute>00</minute> 
      </datum> 
      <wert>87,2</wert> 
    </messwert> 
    <!-- more data skipped in this example--> 
  </messstelle> 
</hnd-daten>
```
The property “Attribute” is used if the desired value is not enclosed in the tag, but it is an attribute of the tag. In this case, “Attribute” is the name of the attribute, else an empty string.

The property “optional” is boolean (so it may have the values true and false) and specifies whether the entry is optional or not. The meaning of the other properties “Value” and “Unit” depends on the prop-erty “Parameter” and is described in the following sections.

A corresponding example for the configuration to parse the XML format of LfU is shown here:
```json
[
  { 
    "Parameter": "MonitoringPoint", 
  "Value": "MPID", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "nummer" ]
  }, 
  { 
    "Parameter": "Year", 
  "Value": "Y", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "messwert", "datum", "jahr" ] 
  }, 
  { 
    "Parameter": "Month", 
  "Value": "m", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "messwert", "datum", "monat" ] 
  }, 
  { 
    "Parameter": "Day", 
  "Value": "d", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "messwert", "datum", "tag" ] 
  }, 
  { 
    "Parameter": "Hour", 
  "Value": "H", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "messwert", "datum", "stunde" ] 
  }, 
  { 
    "Parameter": "Minute", 
  "Value": "i", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "messwert", "datum", "minute" ]
  }, 
  { 
    "Parameter": "ObservedPropertyValue", 
  "Value": "h", 
  "Unit": "cm", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "messwert", "wert" ] 
  }, 
  { 
    "Parameter": "ObservedPropertyValue", 
    "Value": "Q", 
  "Unit": "m3/s", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "messwert", "wert" ] 
  }, 
  { 
    "Parameter": "ObservedPropertyValue", 
  "Value": "P_total_hourly", 
  "Unit": "mm", 
  "Attribute": "", 
  "Tag Hierarchy": [ "hnd-daten", "messstelle", "messwert", "wert" ] 
  } 
]
```

#### Date specifications
A date specification has the property “Parameter” set to “Year”, “Month”, “Day”, “Hour”, “Minute”, “Date”, “Time” or “DateTime”, depending on the exact information specified. For date fields, “Value” is the format of the given date. For example, a datetime format would be “Y-m-d H:i:s” and would describe “2020-01-31 23:40:41”.

| Character | Meaning | Example |
| :---: | :--: | :---: |
| Y | 4-digit year | 2020 |
| y | 2-digit year | 20 |
| m | Month with leading zeros, from 01 to 12 | 01 |
| n | Month without leading zeros, from 1 to 12 | 1 |
| M | Month, short textual representation, Jan through Dec | Jan |
| d | Day of the month with leading zeros, 01 to 31 | 09 |
| j | Day of the month without leading zeros, 1 to 31 | 9 |
| H | Hour with leading zeros, 24-hour format, 01 through 23 | 05 |
| G | Hour without leading zeros, 24-hour format, 1 through 23 | 5 |
| i | Minutes with leading zeros, 00 to 59 | 04 |
| s | Seconds with leading zeros, 00 to 59 | 03 |

##### Observed property value specifications

Observed property value specifications have the property “Parameter” set to “ObservedProper-tyValue”. The property “Value” is the symbol of the observed property within notation of HyMeDES EnviroNet system. The value must match a registered observed property on the Distribution Node. Common observed property symbols are shown in Table 2. Please note that the symbols are case-sensitive.

Common symbols for observed properties in notation of HyMeDES EnviroNet 

| Symbol  | Meaning |
| :--: | :--: |
| h | Water level |
| Q | River discharge |
| tw | Water temperature |
| P_total_hourly | Total precipitation within an hour |
| P_total_daily | Total precipitation within a day |
| ta | Air temperature |
| p | Atmospheric pressure |

For observed property values, “Unit” is the unit in which the value is given. Recognized units are “cm”, “mm”, “m”, “m3/s”, and “°C”.

##### Monitoring point specifications

For monitoring point specifications, the attribute “Parameter” is “MonitoringPoint”. There need not be given any additional properties except the tag hierarchy, of course. If the property “Value” is given, it refers to the format of the monitoring point id as given in the monitoring point conversions in CONVERSIONS json file by specifying a variable name.

##### Observed property symbol specifications

In case the observed property symbol for a measurement section in the XML file is not fixed, but given dynamically in an own tag, it may be specified with an entry in which the property “Parameter” is “ObservedPropertySymbol”. The property “Value” in this case refers to the observed property conver-sion in CONVERSIONS json file by specifying a variable name.

#### CONVERSIONS-file: Conversions Specification

The basic idea is to generalize the URL pattern (whether it is an FTP server or a Web-API) by inserting variables. For example, if the measuring station is directly anchored in the URL, it is replaced by the variable [station]. With this method, data conversion from national data formats to the common data format HyMeDEM can be covered in all countries.
The CONVERSIONS json file may be specified in one of the following cases:
* More complex data access, for example a Web API where variables are needed to be filled in
* Access to data in zip files
* Need for Observable property symbol conversion (between data provider notation and Hy-MeDES EnviroNet notation)
* Need for Monitoring Point id conversions
Data access is specified by URL patterns with parameters and variable values which are filled in dy-namically depending on what to query.
The conversions are specified by translation tables and connected with a variable name to be used in an URL pattern or in an XML file if needed.
The CONVERSIONS json file contains an object with three properties:
* generalInformation
* monitoringPointConversions
* observedPropertyConversions
An example of a CONVERSIONS json file for XYZ is shown here:
```json
{ 
    "generalInformation": { 
        "URLPattern": "https://xyz.de/webservices/export.php?user=[USERNAME]&pw=[PASSWORD] &pgnr=[MPID]&werte=[OBS]&tage=1&modus=xml" 
  }, 
  "monitoringPointConversions": { 
    "MPID": "#" 
  }, 
  "observedPropertyConversions": { 
    "h": { 
      "OBS": "W" 
    }, 
    "Q": { 
      "OBS": "Q" 
    }, 
    "P_total_hourly": { 
      "OBS": "N" 
    } 
  } 
}
```

##### Data Access
The property “URLPattern” of the property “generalInformation” contains the URL pattern of the data access. In the URL, parameters that vary, such as the measuring station or the observable, are replaced by variables. Variable names are enclosed in square brackets [ ] and will be replaced by the variable definition on runtime. As an example, XYZ is used.
The URL pattern for getting the data from the server is:
```
https://xyz.de/webservices/ex-port.php?user=[USERNAME]&pw=[PASSWORD]&pgnr=[MPID]&werte=[OBS]&tage=1&modus=xml
```
Username and password are predefined variables. The values for them are speci-fied in basic configuration file. The elements highlighted in brackets are variables which will be replaced on run-time when data is acquired.

The definitions in which way the variables have to be replaced are specified in the CONVERSIONS json file (see below). It is possible to freely define names for variables like [OBS], [OBS2] or even [Observable property name 1] as long as there will be an assignment made in the CONVERSIONS json file for this variable.

For example, if the real time values of water level (HyMeDES EnviroNet symbol “h”, XYZ symbol “W”) is to be retrieved for station 10026301 from XYZ, the software has to call
```
https://xyz.de/webservices/export.php?user=exam-pleUser&pw=examplePassword&pgnr=10026301&werte=W&tage=1&modus=xml
```

The station name [MPID] will be replaced by the national station number. In other countries, the na-tional number may also be padded with zeros or preceded by a letter. It is specified using the “moni-toringPointConversions” property of the CONVERSIONS json file.

The [OBS] variable is the placeholder for the observed property in our example. It is specified using the “observedPropertyConversions” property of the CONVERSIONS json file.
The observed parameter or the measuring stations can also be coded with several input values in the URL. Then several variables with different input are assigned. A more complicated example of a URL pattern can be found in the appendix.

##### Monitoring Point ID conversions

In the “monitoringPointConversions” section the variable names for the monitoring point are given. The variable names are properties of the “monitoringPointConversions” property. They specify a value pattern. For example, to ensure that a code always has 5 digits, ##### is specified as value pattern. If the real code has fewer digits, the ##### are filled up with zeros from the beginning. If the code should be used as-is, just a single # is entered.

##### Observed property symbol conversions
In the “observedPropertyConversions” property the variable names for the observed property symbols are specified. The property in this section consists of the observed property symbol name in HyMeDES EnviroNet notation. E.g. water level is denoted by “h”. Multiple variables may be defined all meaning “h” but with a different value. In the example of XYZ, the variable “OBS” is defined to be resolved to “W” if water level should be queried, because the XYZ calls water level “W” in its API. If in a different context the water level is called differently, a further variable for “h” may be defined with a different translation.

##### More complex example of a CONVERSIONS json file
As another example the URL of the German Meteorological Service is described. This example is more complex, because the observables are coded several times with different inputs and data file is within a zip file. Filenames within a zip file are appended to the URL using the pipe symbol (“|”).
The URL pattern for getting the data from the server is:
````
https://opendata.dwd.de/climate_environment/CDC/
observations_germany/climate/[INT1]/[OBS2]/recent/
[INT3]_[OBS1]_[MPID1]_akt.zip|produkt_[OBS3]_[INT2]_*_[MPID1].txt
```

The elements highlighted in brackets are again variables which will be replaced on run-time when data is acquired.

The [OBS1], [OBS2], [OBS3] variables are all different placeholders for the observed property in the example. In this rather complicated example this is necessary because the observed property precipi-tation is coded in three different ways in the URL: [OBS1] has to be replaced by “RR”, [OBS2] by “pre-cipitation”, [OBS3] by “rr”.

For example, if the real time values of hourly precipitation (HyMeDES EnviroNet symbol “P_total_hourly”) is to be retrieved for station 164 from the German Weather Forecasting Service DWD, the software has to call
```
https://opendata.dwd.de/climate_environment/CDC/
observations_germany/climate/hourly/precipitation/recent/
stundenwerte_RR_00164_akt.zip|produkt_rr_stunde_*_00164.txt
```

In the example the [INT1] variable stands for the interval and will be replaced in the URL by “hourly”.

The time interval is also coded in different ways in the same URL-call: [INT] is replaced by “hourly”, [INT2] by “stunde”. [INT3] is replaced by “stundenwerte”.

The station name [MPID1] will be replaced by the national station number 164 padded with zeros. In the following, the corresponding CONFIGURATION json file is shown. This example does not need a FORMATS json file, because files are served in CSV format.
```json
{ 
  "generalInformation": { 
    "URLPattern": "https://opendata.dwd.de/climate_environment/
        CDC/observations_germany/climate/[INT1]/[OBS2]/
        recent/[INT3]_[OBS1]_[MPID1]_akt.zip|produkt_
        [OBS3]_[INT2]_*_[MPID1].txt" 
  }, 
  "monitoringPointConversions": { 
    "MPID1": "#####", 
    "MPID2": "#" 
  }, 
  "observedPropertyConversions": { 
    "P_total_hourly": { 
      "OBS1": "RR", 
      "OBS2": "precipitation", 
      "OBS3": "rr", 
      "INT1": "hourly", 
      "INT2": "stunde", 
      "INT3": "stundenwerte" 
    }, 
    "ta": { 
      "OBS1": "TU", 
      "OBS2": "air_temperature", 
      "OBS3": "tu", 
      "INT1": "hourly", 
      "INT2": "stunde", 
      "INT3": "stundenwerte" 
    } 
  } 
}
```