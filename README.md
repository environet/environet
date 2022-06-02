<a name="01_doc"></a>

# Environet Documentation

This document is the documentation of the Environet system.

* [System requirements](#10_system_requirements)
* [Setup](#11_setup)
* [Distribution node](#20_distribution_node)
    * [Overview](#20_distribution_node)
    * [Setup](#21_setup)
    * [Database structure](#22_database_structure)
    * [Upload API](#23_api_upload)
    * [Download API](#24_api_download)
    * [Monitoring point API](#24_api_monitoring_points)
    * [Administration area](#25_0_admin_user_manual)
        * [General](#25_1_admin_general)
        * [Auth pages](#25_2_auth)
        * [Dashboard](#25_3_dashboard)
        * [Users](#25_4_users)
        * [Groups](#25_5_groups)
        * [Operators](#25_6_operators)
        * [Hydro](#25_7_1_hydro)
            * [Hydro monitoring points](#25_7_2_hydro_monitoring_point)
            * [Hydro observed properties](#25_7_3_hydro_observed_properties)
            * [Hydro river](#25_7_4_hydro_river)
            * [Hydro station classification](#25_7_5_hydro_station_classification)
            * [Hydro results](#25_7_6_hydro_results)
        * [Meteo](#25_8_1_meteo)
            * [Meteo monitoring points](#25_8_2_meteo_monitoring_point)
            * [Meteo observed properties](#25_8_3_meteo_observed_properties)
            * [Meteo station classification](#25_8_4_meteo_station_classification)
            * [Meteo results](#25_8_5_meteo_results)
        * [Measurement access rules](#25_9_measurement_access_rules)
        * [Upload missing/processed data](#25_10_upload_data)
        
* [Data node](#30_data_node)
    * [Overview](#30_data_node)
    * [Setup](#30_data_node)
* [SSL keypair generation guide](#41_key_gen)
* [Other Environet tools](#51_tools)


<a name="10_system_requirements"></a>

# System requirements

* **OS**: Linux operation system, Ubuntu 18+ or Debian 9+
* **Docker Engine**: 19+
* **Docker Compose**: 1.25+
* **RAM**: at least 2GB
* **HDD**: at least 1GB

<a name="11_setup"></a>

# Setup

This guide assumes you are installing Environet in a Linux environment.

## Install the Docker Engine and Docker Compose


### Docker Engine
The docker documentation has lots of helpful information, along with distribution specific installation instructions. If you are new to Docker, we recommend reading the [overview](https://docs.docker.com/install/) first.  
To see distribution specific installation instructions, navigate to Docker Engine > Linux > Your distribution in the [documentation](https://docs.docker.com/install/).

Be sure to follow the [post installation steps](https://docs.docker.com/install/linux/linux-postinstall/) to allow using docker as a non-root user.

You can verify that Docker is installed correctly by running:  
`$ docker run hello-world`  
If should output a friendly message to the terminal.

### Docker Compose
Setting up Compose is a simpler process, which is described in detail on [this page](https://docs.docker.com/compose/install/#install-compose-on-linux-systems).  
It involves downloading the docker-compose binary from github and setting executable permissions on it.

You can verify that Docker Compose is installed correctly by running:  
`$ docker-compose --version`  
It should output the currently installed version number.

## Get the source

You will need to have Git installed to be able to download the project source, and to receive updates later. It is easiest to install Git on Linux using the preferred package manager of your Linux distribution. See the [Git downloads page](https://git-scm.com/download/linux) for details.

Checkout the Environet docker repository
  - Navigate to the directory where you would like to install environet  
  - Run `$ git clone https://github.com/environet/environet-docker.git --recurse-submodules`  
    
   By default, the files will be downloaded to a directory named `environet-docker`, you can specify a custom name by providing a third argument to the command, e.g.:  
   `$ git clone https://github.com/environet/environet-docker.git my_directory --recurse-submodules`
   
   Note: If you cloned the repository without the `--recurse-submodules` flag, you need to run `git submodule init` and `git submodule update`, to get the src files checked out.

Change to the directory you checked the code out to, and you should be ready to proceed with the setup.  

  If you are installing a data node, refer to the data node [setup instructions](#30_data_node)  

  If you are installing a distribution node, refer to the distribution node [setup instructions](#21_setup)
  
## Getting updates and maintenance

The `environet` cli script is a wrapper for some docker containers managed with docker compose. After first starting a *dist* or *data* node, these services will start automatically after a system reboot.  
To stop and start them manually, you may run `./environet data up` or `./environet data down` (`./environet dist up` and `./environet dist down` in case of a distribution node).  

To get the latest version, simply run `git pull` in the repository folder.  

Depending on the git version it can be possible to run the following command to update the submodule (src folder) too:
`git submodule update --init --recursive --remote`

If a Dockerfile of a container has been changed in the new version, after `./environet dist/data down` but before `./environet dist/data up` it's necessary to run `./environet dist/data build`.

## Linux with UFW security settings

If the nodes are hosted on a linux system, which using UFW firewall, there are some additional steps to make it secure, and do not open unneccessary ports.
The recommended solution is to make some modification on UFW rules. The description of the compatibility problem, and the solution can be found here: [https://github.com/chaifeng/ufw-docker](https://github.com/chaifeng/ufw-docker)


<a name="20_distribution_node"></a>

# Distribution node

## Overview

A Distribution node, in it’s minimal form, is a service aspect that’s able to maintain the central database and service API requests. 
In the first phase the domain of the distribution node is: [https://environet.environ.hu](https://environet.environ.hu)

## Central database

The distribution node has a database backend. It contains:
* Data and configuration of hydro and meteo points
* Measurement data tables of these monitoring points
* User and access (ACL) tables (users, groups, permissions)
* System runtime data

Schema of the database can be found here: [Database structure](#21_database_structure)

## Administration area

On the administration area, administrators can maintain:
* The data and configuration of monitoring points and observed properties
* Operators
* Users, groups, and their permissions (ACL)
* Public keys of operator-users

The url of the administration area: https://distribution-node.com/admin


## API endpoints

### Upload

With this endpoint data nodes can upload data of some monitoring points.  
The detailed documentation of this endpoint can found here: [Upload API documentation](#22_api_upload)

### Download

With this endpoint clients can query data from the distribution node.
This endpoint is available only after authentication, and authorization, so only permitted users can run queries. 
The response can be filtered by date, monitoring point type, and some other properties.  
The detailed documentation of this endpoint can found here: [Download API documentation](#23_api_download)


<a name="21_setup"></a>

# Setup

1. Install the environet project. Refer to [Setup](#11_setup).

2. Create the .env file in the root folder, with copying the .env.example.minimal file. This file contains the mandatory properties for the successful install.
   
   `cp .env.example.minimal .env`
   
   Before next command it is possible the customize the database name, database username, and database password in the new .env file.

3. Create a distribution node configuration
   `./environet dist install`
   
4. Initialize database, and create admin user
   `./environet dist database init`

After going through these steps, the distribution node should be up and running. You can access the admin panel at YOUR_IP/admin.

# Updates

After updating your deployment, you need to run `./environet dist database migrate`, to run any database migrations that might be included in the update.  

<a name="22_database_structure"></a>

# Database structure

## Database engine

* Required database engine is [PostgreSQL](https://www.postgresql.org/)
* Version compatibility: 12+

## Schema diagram

![Schema diagram images](resources/model.jpg)

## Schema sql structure

[Link to sql file](resources/schema.sql)

<a name="23_api_upload"></a>

# Upload API documentation

## URL & METHOD

* **Method**: `POST`
* **URL**: `https://domain.com/upload`

---

## Headers

| Header name | Content |
| --- | --- |
| Authorization | Signature (See below) |
| Content-Type | application/xml |

### Signature header:

The pattern of the header: `Signature keyId="[username]",algorithm="rsa-sha256",signature="[signature]"`

The `keyId` is the username of the uploader user.
The `signature` part is the base64 encoded openssl signature which was created with the user's private key 
from the from the body of the response, which is the XML data.

---

## Body

The body of the response is the XML data.  
The xml must be valid agains the environet's upload api schema: [environet.xsd](resources/environet.xsd)  
The public url of this schema is: `https://distribution-node.com/schemas/environet.xsd`

Sample input XML:

```
<?xml version="1.0" encoding="UTF-8"?>
<environet:UploadData xmlns:environet="environet">
	<environet:MonitoringPointId>EUCD</environet:MonitoringPointId>
	<environet:Property>
		<environet:PropertyId>water_level</environet:PropertyId>
		<environet:TimeSeries>
			<environet:Point>
				<environet:PointTime>2020-02-25T00:00:00+01:00</environet:PointTime>
				<environet:PointValue>22</environet:PointValue>
			</environet:Point>
			<environet:Point>
				<environet:PointTime>2020-02-26T00:01:00+01:00</environet:PointTime>
				<environet:PointValue>23</environet:PointValue>
			</environet:Point>
			<environet:Point>
				<environet:PointTime>2020-02-27T00:02:00+01:00</environet:PointTime>
				<environet:PointValue>24</environet:PointValue>
			</environet:Point>
		</environet:TimeSeries>
	</environet:Property>
</environet:UploadData>
```

--- 

## Repsonses

### Success
* **Status code**: 200
* **Content-type**: application/xml
* **Body**: `empty`
* **Description**: Upload was successful, the data has been successfully processed.

### Invalid request
* **Status code**: 400
* **Content-type**: application/xml
* **Body**: XML: `environet:ErrorResponse`
* **Description**: Input or processing error during the upload process. The reponse in an error xml which is valid agains environet' upload api schema: [environet.xsd](resources/environet.xsd)
* **Body example**:
	 
	```xml
	<?xml version="1.0" encoding="UTF-8"?>
	<environet:ErrorResponse xmlns:environet="environet">
		<environet:Error>
			<environet:ErrorCode>101</environet:ErrorCode>
			<environet:ErrorMessage>Error</environet:ErrorMessage>
		</environet:Error>
	</environet:ErrorResponse>
	```
* **Error codes**
    * `101`: Unknown error 
	* `102`: Server error
	* `201`: Authorization header is missing
	* `202`: Username is empty
	* `203`: User not found with username
	* `204`: Invalid Authorization header
	* `205`: Action not permitted
	* `206`: Public key for user not found
	* `301`: Signature is invalid
	* `302`: Xml syntax is invalid
	* `303`: Xml is invalid against schema
	* `401`: Error during processing data
	* `402`: Monitoring point not found with the given identifier
	* `403`: Property for the selected monitoring point not found, or not allowed
	* `404`: Could not initialize time series for monitoring point and property
	
	
### Server error
* **Status code**: 500
* **Content-type**: application/xml
* **Body**: XML: `environet:ErrorResponse`
* **Description**: Unidentified error during request. The response is an xml which is valid agains environet's upload api schema: [environet.xsd](resources/environet.xsd)
* **Body example**:
	 
	```xml
	<?xml version="1.0" encoding="UTF-8"?>
	<environet:ErrorResponse xmlns:environet="environet">
		<environet:Error>
			<environet:ErrorCode>500</environet:ErrorCode>
			<environet:ErrorMessage>Error</environet:ErrorMessage>
		</environet:Error>
	</environet:ErrorResponse>
	```

<a name="24_api_download"></a>

# Download API documentation

## URL & METHOD

* **Method**: `GET`
* **URL**: `https://domain.com/download`

---

## Query parameters

* **token**: `required` A random string from which the signature is generated. This is required for authentication, the server verifies the signature in the `Authorization` header based on this token.
* **type**: The type of the monitoring points. The value must be one of the following: `hydro` | `meteo`
* **start**: Minimum date of time series. Date format: [ISO 8601](https://www.iso.org/iso-8601-date-and-time-format.html)
* **end**:  Maximum date of time series. Date format: [ISO 8601](https://www.iso.org/iso-8601-date-and-time-format.html)
* **country[]**: Query time series only for monitoring points from the given countries. Country code format: [ISO 3166-1 - alpha-2](https://www.iso.org/iso-3166-country-codes.html)
* **symbol[]**: Query time series only of the given observed properties.
* **point[]**: Query time series only of the given points.

## Headers

| Header name | Content |
| --- | --- |
| Authorization | Signature (See below) |

### Signature header:

The pattern of the header: `Signature keyId="[username]",algorithm="rsa-sha256",signature="[signature]"`

The `keyId` is the username of the user who wants to query data.
The `signature` part is the base64 encoded openssl signature which was created with the user's private key from the token in the query parameters.

## Repsonses

### Success
* **Status code**: 200
* **Content-type**: application/xml
* **Body**: XML: `wml2:Collection`
* **Description**: Measurement data in WaterML2.0 comaptible XML format: http://www.opengis.net/waterml/2.0

### Invalid request
* **Status code**: 400
* **Content-type**: application/xml
* **Body**: XML: `environet:ErrorResponse`
* **Description**: The query request is invalid. The response is an xml which is valid agains environet's api schema: [environet.xsd](resources/environet.xsd)
* **Body example**:
	 
	```xml
	<?xml version="1.0" encoding="UTF-8"?>
	<environet:ErrorResponse xmlns:environet="environet">
		<environet:Error>
			<environet:ErrorCode>101</environet:ErrorCode>
			<environet:ErrorMessage>Error</environet:ErrorMessage>
		</environet:Error>
	</environet:ErrorResponse>
	```
* **Error codes**
	* `101`: Unknown error 
	* `102`: Server error
	* `201`: Authorization header is missing
	* `202`: Username is empty
	* `203`: User not found with username
	* `204`: Invalid Authorization header
	* `205`: Action not permitted
	* `206`: Public key for user not found
	* `207`: Request token not found
	* `301`: Signature is invalid
	* `302`: Observation point type is missing
	* `303`: Observation point type is invalid
	* `304`: Start time filter value is invalid
	* `305`: End time filter value is invalid

### Server error
* **Status code**: 500
* **Content-type**: application/xml
* **Body**: XML: `environet:ErrorResponse`
* **Description**: Unidentified error during request. The response is an xml which is valid agains environet's api schema: [environet.xsd](resources/environet.xsd)
* **Body example**:
	 
	```xml
	<?xml version="1.0" encoding="UTF-8"?>
	<environet:ErrorResponse xmlns:environet="environet">
		<environet:Error>
			<environet:ErrorCode>500</environet:ErrorCode>
			<environet:ErrorMessage>Error</environet:ErrorMessage>
		</environet:Error>
	</environet:ErrorResponse>
	```


<a name="24_api_monitoring_points"></a>

# Monitoring points API documentation

## URL & METHOD

* **Method**: `GET`
* **URL**: `https://domain.com/api/monitoring-points`

---

## Query parameters

* **token**: `required` A random string from which the signature is generated. This is required for authentication, the server verifies the signature in the `Authorization` header based on this token.

## Headers

| Header name | Content |
| --- | --- |
| Authorization | Signature (See below) |

### Signature header:

The pattern of the header: `Signature keyId="[username]",algorithm="rsa-sha256",signature="[signature]"`

The `keyId` is the username of the user who wants to query data.
The `signature` part is the base64 encoded openssl signature which was created with the user's private key from the token in the query parameters.

## Repsonses

### Success
* **Status code**: 200
* **Content-type**: application/json
* **Body**: `JSON`
* **Description**: Request is OK, list the hydro and meteo points of user
* **Body example**:

    ```json
    {
        "hydro": [
            {
                "id": 1,
                "station_classificationid": 1,
                "operatorid": 1,
                "bankid": 1,
                "eucd_riv": "river",
                "eucd_wgst": "ABC123",
                "ncd_wgst": "ABC123",
                "vertical_reference": "Vertical reference",
                "long": "1.0000000000",
                "lat": "1.0000000000",
                "z": "1.0000000000",
                "maplong": "1.0000000000",
                "maplat": "1.0000000000",
                "country": "CountryCode",
                "name": "MPoint",
                "location": "Location",
                "river_kilometer": "1.0000000000",
                "catchment_area": "1.0000000000",
                "gauge_zero": "1.0000000000",
                "start_time": "2020-01-01 00:00:00",
                "end_time": "2020-05-01 00:00:00",
                "utc_offset": 0,
                "river_basin": "Basin",
                "observed_properties": [
                    "hydro_property"
                ]
            }
        ],
        "meteo": [
            {
                "id": 1,
                "meteostation_classificationid": 1,
                "operatorid": 1,
                "eucd_pst": "DEF456",
                "ncd_pst": "DEF456",
                "vertical_reference": "Vertical reference",
                "long": "1.0000000000",
                "lat": "1.0000000000",
                "z": "1.0000000000",
                "maplong": "1.0000000000",
                "maplat": "1.0000000000",
                "country": "CountryCode",
                "name": "Name",
                "location": "Location",
                "altitude": "1.0000000000",
                "start_time": "2020-01-01 00:00:00",
                "end_time": "2020-05-01 00:00:00",
                "utc_offset": 0,
                "river_basin": "Basin",
                "observed_properties": [
                    "meteo_property"
                ]
            }
        ]
    }
	```

### Invalid request
* **Status code**: 400
* **Content-type**: application/json
* **Body**: `JSON`
* **Description**: The query request is invalid. The response is a json object with the error message, under error key.
* **Body example**:
	 
	```json
    {
     "error": "Error message"
    }
	```

### Server error
* **Status code**: 500
* **Content-type**: application/json
* **Body**: `JSON`
* **Description**: Unidentified error during request. The response is a json object with the error message, under error key.
* **Body example**:
	 
	```json
	{
     "error": "Error message"
    }
	```


<a name="25_0_admin_user_manual"></a>

# Admin user manual

This document's goals to represents the different parts of the administration area to help to understand how it works. Help you to see through how works the specified relationships and how you can handle them.










<a name="25_10_upload_data"></a>

## Upload missing and processed data

Upload missing data: `/admin/missing-data`

Upload processed data: `/admin/processed-data`

### Concept of the rules

The two pages "Upload missing data" and "Upload processed data" are similar in functionality.

On both pages you can upload multiple CSV files in a pre-defined format to upload missing or processed data for a monitoring point.
A file contains data for a single monitoring point with multiple properties and data. The sample csv format is downloadable on the pages. 
A user can upload data only for allowed monitoring point, if the user is not a super administrator. 
This uploader in the background will call the standard [upload api](#23_api_upload), so all validation of this endpoint will work on this uploader too.

The error/success messages will be separated per file, so if a file is invalid, you have to fix and upload only that file.


<a name="25_1_admin_general"></a>

## General information
Good to know, that on each list page, there are on the top right a search field. In the following each sections you can see a "searchable" part, where you can find in what kind of fields can the system search on the specified page.


<a name="25_2_auth"></a>

## Auth pages

#### Login page

Path: `/admin/login`

Here you have to type your credentials data to login into the administration area. 
If you have no login credentials yet, you have to gain access from your webmaster.

#### Logout
You can logout if you click the exit icon on the top right of the page.

Path: `/admin/logout`

<a name="25_3_dashboard"></a>

## Dashboard

Path: `/admin`

<a name="25_4_users"></a>

## Users
Here you can handle the users who has already added to the system.

Path: `/admin/users`

Searchable: name, username, email

**There are three types of user:**
-  Distribution node 
-  Client node
-  Users who can manage the system in the admin area

All of them are listed in the users grid. 

Each user, except the user type users have to have public key attached to themselves, because of they are communicate on API channel and it necessary to their authentication.

#### New user

You can add new user if you click the "Add user" button on the top left of the users list page.

Path: `/admin/users/add`

On the user's creating page, you have to fill the following mandatory fields:
- name
- email - it must be unique
- username - it must be unique
- permission or group

The public key field is necessary only if we want to create a client or distrubition node user. In other case if you would like to create a "system manager" user, you have to fill to password field instead of public key.

If you wouldn't like to assign a specified permission to the user, you must assign the user to a group, they will inherits the group's permissions.


#### Updating user

You can select a user to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/users/edit?id=[user identifier]`

You can update all user datas that you gave on the creating page except the username field. 

#### User deleting

You can delete a user if you click the "Trash" icon at the end of the specific row. If you clicked, it shows a confirm window, where you have to approve the deleting. In fact the deleted user will never physically deleted, we have to keep its datas by security reasons.

Path: `/admin/users/delete?id=[user identifier]`

<a name="25_5_groups"></a>

## Groups
Here you can handle the groups what has already added to the  system.

Path: `/admin/groups`

Searchable: name

#### New group

You can add new group if you click the "Add group" button on the top left of the group list page.

Path: `/admin/groups/add`

On the group's creating page you have to fill the name of the group and you have to assign permission to the specified group. On the creating page, you can assign only one permission, but later you have possibility to add more of it.

#### Updating group

You can select a group to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/groups/edit?id=[group identifier]`

Here you can change the name of the group and you can assign more permission to it.

#### Group deleting

You can delete a group if you click the "Trash" icon at the end of the specific row. If you clicked, it shows a confirm window, where you have to approve the deleting. **If any user or operator have already assigned to the specified group, the delete operation cannot be performed.**
First time you have to detach these relations and after that you can delete the group.

Path: `/admin/groups/delete?id=[group identifier]`

<a name="25_6_operators"></a>

## Operators
Here you can handle the operators what has already added to the  system.

Path: `/admin/operators`

Searchable: name, address, email

#### New operator

You can add new operator if you click the "Add operator" button on the top left of the operator's list page.

Path: `/admin/operators/add`

On the operators creating page, you have to fill the following mandatory fields:

Operator data
 - name
 - phone
 - e-mail
 - url

User data
- name
- username
- email

The user data is necessary because of each operator has to be assigned to at least one user.

#### Updating operator

You can select a operator to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/operators/edit?id=[operator identifier]`

Here you can change all of operator's data and you can assign  more users or groups to the specific operator.

#### Operator show page

You can select a operator to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/operators/show?id=[operator identifier]`

Here you can see the stored data of the operators and its relations to direction of users and groups.

#### Operator deleting

You cannot delete any operator.

<a name="25_7_1_hydro"></a>

## Hydro

<a name="25_7_2_hydro_monitoring_point"></a>

### Hydro monitoring point
Here you can handle the monitoring points what has already added to the system.

Path: `/admin/hydro/monitoring-points`

Searchable: EUCD RIV, country, name, location

#### New monitoring point

You can add new monitoring point if you click the "Add monitoring point" button on the top left of the hydro monitoring point list page.

Path: `/admin/hydro/monitoring-points/add`

On the monitoring point creating page, you have to fill the following mandatory fields:
- name
- classification
- operator
- riverbank
- river
- observed properties

#### Updating monitoring point

You can select a monitoring point to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/hydro/monitoring-points/edit?id=[monitoring point identifier]`

Here you can change all of monitoring point's data and you can assign more observed property to the specific monitoring point.

#### Monitoring point show page

You can select a monitoring point to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/hydro/monitoring-points/show?id=[monitoring point identifier]`

Here you can see the stored data of the monitoring point and its relations to direction of station classification, operator, river and observed property.

#### Monitoring point deleting

You cannot delete any monitoring point.

<a name="25_7_3_hydro_observed_properties"></a>

### Hydro observed properties
Here you can handle the observed properties what has already added to the system.

An observed property describes, what kind of property can be measured by a monitoring point.

Path: `/admin/hydro/observed-properties`

Searchable: symbol, description

#### New observed property

You can add new observed property if you click the "Add observed property" button on the top left of the hydro observed property list page.

Path: `/admin/hydro/observed-properties/add`

On the observed property creating page, you have to fill to following mandatory fields:
- symbol
- description
- type - Processed, or real time data - default is real time

#### Updating observed property

You can select an observed property to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/hydro/observed-properties/edit?id=[observed property identifier]`

Here you can change all of observed property's data.

#### Observed property show page

You can select an observed property to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/hydro/observed-properties/show?id=[observed property identifier]`

Here you can see the stored data of an observed property.

#### Observed property deleting

You cannot delete any observed property.

<a name="25_7_4_hydro_river"></a>

### River
Here you can handle the rivers what has already added to the system.

Path: `/admin/hydro/rivers`

Searchable: EUCD RIV

#### New river

You can add new river if you click the "Add river" button on the top left of the river list page.

Path: `/admin/hydro/rivers/add`

On the river's creating page, you have to fill to following mandatory fields:
- cname
- EUCD RIV

#### Updating river

You can select a river to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/hydro/rivers/edit?id=[river identifier]`

Here you can change the cname of the selected river.

#### River show page

You can select a river to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/hydro/rivers/show?id=[river identifier]`

Here you can see the stored data of a river.

#### River deleting

You cannot delete any river.

<a name="25_7_5_hydro_station_classification"></a>

### Station classifications
Here you can handle the classifications of a specified station what has already added to the system.

Path: `/admin/hydro/station-classifications`

Searchable: value

#### New classification

You can add new station classification if you click the "Add station classification" button on the top left of the classification's list page.

Path: `/admin/hydro/station-classifications/add`

On the classification's creating page, you have to fill to following mandatory fields:
- value

#### Updating classification

You can select a station classification to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/hydro/station-classifications/edit?id=[classification identifier]`

Here you can change the value of the selected station classification.

#### Classification show page

You can select a station classification to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/hydro/station-classifications/show?id=[classification identifier]`

Here you can see the stored data of a station classification.

#### Classification deleting

You cannot delete any classification.

<a name="25_7_6_hydro_results"></a>

### Hydro results
Here you can see the results of the different monitoring point, what has arrived via API.

Path: `/admin/hydro/results`

Searchable: name, symbol

<a name="25_8_1_meteo"></a>

## Meteo

<a name="25_8_2_meteo_monitoring_point"></a>

### Meteo monitoring point
Here you can handle the monitoring points what has already added to the system.

Path: `/admin/meteo/monitoring-points`

Searchable: country, name, location

#### New monitoring point

You can add new monitoring point if you click the "Add monitoring point" button on the top left of the meteo monitoring point list page.

Path: `/admin/meteo/monitoring-points/add`

On the monitoring point creating page, you have to fill the following mandatory fields:

- name
- classification
- operator
- observed properties


#### Updating monitoring point

You can select a monitoring point to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/meteo/monitoring-points/edit?id=[monitoring point identifier]`

Here you can change all of monitoring point's data and you can assign more observed property to the specific monitoring point.

#### Monitoring point show page

You can select a monitoring point to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/meteo/monitoring-points/show?id=[monitoring point identifier]`

Here you can see the stored data of the monitoring point and its relations to direction of station classification, operator and observed property.

#### Monitoring point deleting

You cannot delete any monitoring point.

<a name="25_8_3_meteo_observed_properties"></a>

### Observed properties
Here you can handle the observed properties what has already added to the system.

An observed property describes, what kind of property can be measured by a monitoring point.

Path: `/admin/meteo/observed-properties`

Searchable: symbol, description

#### New observed property

You can add new observed property if you click the "Add observed property" button on the top left of the meteo observed property list page.

Path: `/admin/meteo/observed-properties/add`

On the observed property creating page, you have to fill to following mandatory fields:

- symbol
- description
- type - Processed, or real time data - default is real time

#### Updating observed property

You can select an observed property to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/meteo/observed-properties/edit?id=[observed property identifier]`

Here you can change all of observed property's data.

#### Observed property show page

You can select an observed property to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/meteo/observed-properties/show?id=[observed property identifier]`

Here you can see the stored data of an observed property.

#### Observed property deleting

You cannot delete any observed property.

<a name="25_8_4_meteo_station_classification"></a>

### Station classifications
Here you can handle the classifications of a specified station what has already added to the system.

Path: `/admin/meteo/station-classifications`

Searchable: value

#### New classification

You can add new station classification if you click the "Add station classification" button on the top left of the classification's list page.

Path: `/admin/meteo/station-classifications/add`

On the classification's creating page, you have to fill to following mandatory fields:
- value

#### Updating classification

You can select a station classification to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/meteo/station-classifications/edit?id=[classification identifier]`

Here you can change the value of the selected station classification.

#### Classification show page

You can select a station classification to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/meteo/station-classifications/show?id=[classification identifier]`

Here you can see the stored data of a station classification.

#### Classification deleting

You cannot delete any classification.

<a name="25_8_5_meteo_results"></a>

### Meteo results
Here you can see the results of the different monitoring point, what has arrived via API.

Path: `/admin/meteo/results`

Searchable: name, symbol

<a name="25_9_measurement_access_rules"></a>

## Measurement access rules


### Concept of the rules

The access rules controls the time interval in which the data will be available for a user under the [download API](#24_api_download) request. 

The rules have three required dimensions: 
* **Monitoring point** - the rule is for all data of the given monitoring point 
* **Observed property** - the rule is for all data of the observed property of the monitoring point
* **User groups** - the rule will be applied if the requesting user is under these groups
* **Operator** - If an operator creates the rule, these property will be set automatically. An administrator can set it manually. 
This property controls which rule will be visible under operator's access rule list, and important in case of wildcard (- ALL -) rules. 
If the operator parameter is set, the "ALL" option will be applied only for points/properties under the given operator.

A rule matches, and will be checked during a download, if **all** of the conditions are met:
* The group of user (who do the request) is in one of the groups of the rule
* The result is related to the selected operator 
* The result is related to the selected operator's selected monitoring points (in case of "ALL" all monitoring points will be selected)
* The result is related to the selected observed properties (in case of "ALL" all properties will be selected)

If the rule matches for a [download API](#24_api_download) request (so the __user__ getting data for the __monitoring point__ and the __observed property__), the user can retrieve data only in the given time period.
If multiple rules match, only those results will be return which fit in all of the intervals.
This period's end is the current date, and the start is controlled by the `years`, `months` and `days` paramter of the access rule.

The rules will be "merged" before checking user's access level.

#### Rule list

On the list page all rules are visible, with dimensions.

Path: `/admin/measurement-access-rules`

#### New access rule

You can add new access rule if you click the "Add rule" button on the top left of the rules list page.

Path: `/admin/measurements-access-rules/add`

On the rules's creating page, you have to fill the following mandatory fields:
- Operator: Field is visible only for administrators, for operators it's auto-selected
- Monitoring point selector - Select some or all monitoring points
- Observed property selector - Select some or all observed properties
- Groups - Multiple user groups can be selected
- At least one of the time interval fields: years, months, days

#### Updating access rule

You can select a rule to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/measurements-access-rules/edit?id=[rule identifier]`

You can update all rule datas that you gave on the creating page. 

#### Rule deleting

You can delete a rule if you click the "Trash" icon at the end of the specific row. If you clicked, it shows a confirm window, where you have to approve the deleting.

Path: `/admin/measurements-access-rules/delete?id=[rule identifier]`

<a name="30_data_node"></a>

# Data node

## Overview
A *data node* is designed to be run at a data source with the purpose of gathering metering point measurements stored in some third party format, such as a plain text file, spreadsheet or web resource.  

It transforms these data to a format compatible with the Environet *distribution node* API, and uploads the results to a distribution node.  

The gathering and uploading of data is accomplished by way of uploader plugin configurations, which can then be run by calling a script, typically by a cron job.

Before a data node can start uploading measurements, the metering points and observable properties (for which the plugin will upload measurements) have to be configured beforehand on the Environet distribution node that the plugin will be uploading to.  
   
An API user with will also need to be configured, to authenticate upload requests.  

## Prepare distribution node to receive data

You can set the following up if you have access to the [distribution node admin panel](#25_admin_user_manual).

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

Before configuring the data node, you need to install the Environet project. The steps to install dependencies and download the Environet source itself, are outlined in the [setup](#11_setup) section of this document.

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

```
https://opendata.dwd.de/climate_environment/CDC/observations_germany/climate/[INT1]/[OBS2]/recent/[INT3]_[OBS1]_[MPID1]_akt.zip|produkt_[OBS3]_[INT2]_*_[MPID1].txt
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
    "URLPattern": "https://opendata.dwd.de/climate_environment/CDC/observations_germany/climate/[INT1]/[OBS2]/recent/[INT3]_[OBS1]_[MPID1]_akt.zip|produkt_[OBS3]_[INT2]_*_[MPID1].txt" 
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

<a name="41_key_gen"></a>

# SSL key pair generation guide

## Windows
It is recommended to use the following:

[itefix.net OpenSSL tool for Windows](https://itefix.net/openssl-tool)

Download and extract it to an optional place. In the package, you can find the executable file at the: 
*bin/openssl.exe*

If you open the exe, it will prompt a command window, wherein you have to type the following lines:

**1.** Private key generation:  
`genrsa -out private.pem 2048`
  
**2.** Public key generation from private key:  
`rsa -in private.pem -out public.pem -outform PEM -pubout`

## Linux

You have to download one from the following link:

[https://www.openssl.org/source/](https://www.openssl.org/source/)

After that you have to extract and install it.

Here you can find a detailed description about the installation:

[https://www.tecmint.com/install-openssl-from-source-in-centos-ubuntu/](https://www.tecmint.com/install-openssl-from-source-in-centos-ubuntu/)

After the installation you have to run these commands:

**1.** Private key generation:  
`openssl genrsa -out private.pem 2048`
  
**2.** Public key generation from private key:  
`openssl rsa -in private.pem -out public.pem -outform PEM -pubout`


<a name="51_tools"></a>

# Other Environet tools

Useful CLI tools which are available through the './environet' wrapper script.

### Data node cleanup

Command: `./environet data cleanup`

Description:
Cleanup data node, delete unnecessary, old files.

### Generating keypair

Command: `./environet dist|data tool keygen`

Description:
With this command you gen generate a openssl keypair with an interactive process. The command will ask for:
* Destination folder of the key files
* Prefix for the key file names

Based on these data a keypair will be generated, and stored in the destination folder.

### Sign content

Command: `./environet dist|data tool sign`

Description:
With this command you gen sign a content (a string, or a file) with a private key. The command will ask for:
* Relative path of private key file
* How do you want to enter the content (file, or pasted string)
* The file path (in case of 'file' mode) or the raw string (in case of 'pasted string' mode)
* Generate md5 hash before sigining, or not

Based on the input data a base64 encoded signature will be generated, which can be used in the Authorization header of any api request.

### Exporting database

Command: `./environet dist database export`

Description:
This commands exports the database content of the distribution node to a file under data/export folder. The filename of the exported file will be written to the console after successful export.

### Generating HTML documentation and merged markdown

Command: `./environet dist generate-merged-html`

Description:
During development, when markdown documentation has been changed, it's necessary to generate updated HTML documentation and merged markdown file.
It's possible with this command.



