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

The rules has one additional optional dimension:
* **Operator** - If an operator creates the rule, these property will be set automatically. An administrator can set is optionally. 
This property controls which rule will be visible under operator's acces rule list, and important in case of wildcard (*) rules. 
If the operator parameter is set, the wildcard (*) will be applied only for points/properties under the given operator.

If the rule matches for a [download API](#24_api_download) request (so the __user__ getting data for the __monitoring point__ and the __observed property__), the user can retrieve data only in the given time period.
This period's end is the current date, and the start is controlled by the `years`, `months` and `days` paramter of the access rule.

The monitoring point and the observed property can be a *, which will match all monitoring points (globally, or of an operator), or all properties. 

The rules will be "merged" before checking user's access level.

#### Rule list

On the list page all rules are visible, with the required and optional dimensions.

Path: `/admin/measurement-access-rules`

#### New access rule

You can add new access rule if you click the "Add rule" button on the top left of the rules list page.

Path: `/admin/measurements-access-rules/add`

On the rules's creating page, you have to fill the following mandatory fields:
- Monitoring point selector - The ID of the monitoring point, or * for all
- Observed property selector - The ID (symbol) of the monitoring point, or * for all
- Groups - Multiple user groups can be selected
- At least one of the time interval fields: years, months, days

Optionally you can select an operator, in this case wildcard (*) will be applied for items only under the give operator.

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


<a name="41_key_gen"></a>

# SSL key pair generation guide

## Windows
It is recommended to use the following:

[https://itefix.net/dl/free-software/openssl_tool_win_x64_1.0.0.zip](https://itefix.net/dl/free-software/openssl_tool_win_x64_1.0.0.zip)

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



