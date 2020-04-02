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

Schema of the database can be found here: [Database structure](21_database_structure.md)

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
The detailed documentation of this endpoint can found here: [Upload API documentation](22_api_upload.md)

### Download

With this endpoint clients can query data from the distribution node.
This endpoint is available only after authentication, and authorization, so only permitted users can run queries. 
The response can be filtered by date, monitoring point type, and some other properties.  
The detailed documentation of this endpoint can found here: [Download API documentation](23_api_download.md)
