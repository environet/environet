# Admin user manual

This document's goals to represents the different parts of the administration area to help to understand how it works. Help you to see through how works the specified relationships and how you can handle them.

### General information
Good to know, that on each list page, there are on the top right a search field. In the following each sections you can see a "searchable" part, where you can find in what kind of fields can the system search on the specified page.

### Login page

You can reach this interface if you navigate your browser to the following path:
- /admin/login

Here you have to type your credentials data to login into the administration area. 
If you have no login credentials yet, you have to gain access from your webmaster.

### Logout
You can logout if you click the exit icon on the top right of the page.

Path: /admin/logout

### Dashboard
Path: /admin

### Users
Here you can handle the users who has already added to the system.

Path: /admin/users

Searchable: name, username, email

**There are three types of user:**
-  Distribution node 
-  Client node
-  Users who can manage the system in the admin area

All of them are listed in the users grid. 

Each user, except the user type users have to have public key attached to themselves, because of they are communicate on API channel and it necessary to their authentication.

**New user**

You can add new user if you click the "Add user" button on the top left of the users list page.

Path: /admin/users/add

On the user's creating page, you have to fill the following mandatory fields:
- name
- email - it must be unique
- username - it must be unique
- permission or group

The public key field is necessary only if we want to create a client or distrubition node user. In other case if you would like to create a "system manager" user, you have to fill to password field instead of public key.

If you wouldn't like to assign a specified permission to the user, you must assign the user to a group, they will inherits the group's permissions.


**Updating user**

You can select a user to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/users/edit?id=[user identifier]

You can update all user datas that you gave on the creating page except the username field. 

**User deleting**

You can delete a user if you click the "Trash" icon at the end of the specific row. If you clicked, it shows a confirm window, where you have to approve the deleting. In fact the deleted user will never physically deleted, we have to keep its datas by security reasons.

Path: /admin/users/delete?id=[user identifier]

### Groups
Here you can handle the groups what has already added to the  system.

Path: /admin/groups

Searchable: name

**New group**

You can add new group if you click the "Add group" button on the top left of the group list page.

Path: /admin/groups/add

On the group's creating page you have to fill the name of the group and you have to assign permission to the specified group. On the creating page, you can assign only one permission, but later you have possibility to add more of it.

**Updating group**

You can select a group to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/groups/edit?id=[group identifier]

Here you can change the name of the group and you can assign more permission to it.

**Group deleting**

You can delete a group if you click the "Trash" icon at the end of the specific row. If you clicked, it shows a confirm window, where you have to approve the deleting. **If any user or operator have already assigned to the specified group, the delete operation cannot be performed.**
First time you have to detach these relations and after that you can delete the group.

Path: /admin/groups/delete?id=[group identifier]

### Data providers
Here you can handle the data providers what has already added to the  system.

Path: /admin/data-providers

Searchable: name, address, email

**New data provider**

You can add new data provider if you click the "Add data provider" button on the top left of the data provider's list page.

Path: /admin/data-providers/add

On the data providers creating page, you have to fill the following mandatory fields:

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

**Updating data provider**

You can select a data provider to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/data-providers/edit?id=[data-provider identifier]

Here you can change all of operator's data and you can assign  more users or groups to the specific dataprovider.

**Data provider show page**

You can select a data provider to show if you click the "Eye" icon at the end of the specific row.

Path: /admin/data-providers/show?id=[data-provider identifier]

Here you can see the stored data of the data providers and its relations to direction of users and groups.

**Data provider deleting**

You cannot delete any data provider.

## Hydro

### Hydro monitoring point
Here you can handle the monitoring points what has already added to the system.

Path: /admin/hydro/monitoring-points

Searchable: european river code, country, name, location

**New monitoring point**

You can add new monitoring point if you click the "Add monitoring point" button on the top left of the hydro monitoring point list page.

Path: /admin/hydro/monitoring-points/add

On the monitoring point creating page, you have to fill the following mandatory fields:
- name
- classification
- operator
- riverbank
- waterbody
- observed properties

**Updating monitoring point**

You can select a monitoring point to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/hydro/monitoring-points/edit?id=[monitoring point identifier]

Here you can change all of monitoring point's data and you can assign more observed property to the specific monitoring point.

**Monitoring point show page**

You can select a monitoring point to show if you click the "Eye" icon at the end of the specific row.

Path: /admin/hydro/monitoring-points/show?id=[monitoring point identifier]

Here you can see the stored data of the monitoring point and its relations to direction of station classification, operator, waterbody and observed property.

**Monitoring point deleting**

You cannot delete any monitoring point.

### Hydro observed properties
Here you can handle the observed properties what has already added to the system.

An observed property describes, what kind of property can be measured by a monitoring point.

Path: /admin/hydro/observed-properties

Searchable: symbol, description

**New observed property**

You can add new observed property if you click the "Add observed property" button on the top left of the hydro observed property list page.

Path: /admin/hydro/observed-properties/add

On the observed property creating page, you have to fill to following mandatory fields:
- symbol
- description

**Updating observed property**

You can select an observed property to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/hydro/observed-properties/edit?id=[observed property identifier]

Here you can change all of observed property's data.

**Observed property show page**

You can select an observed property to show if you click the "Eye" icon at the end of the specific row.

Path: /admin/hydro/observed-properties/show?id=[observed property identifier]

Here you can see the stored data of an observed property.

**Observed property deleting**

You cannot delete any observed property.

### Waterbody
Here you can handle the waterbodies what has already added to the system.

Path: /admin/hydro/waterbodies

Searchable: european river code

**New waterbody**

You can add new waterbody if you click the "Add waterbody" button on the top left of the waterbody list page.

Path: /admin/hydro/waterbodies/add

On the waterbody's creating page, you have to fill to following mandatory fields:
- cname
- european river code

**Updating waterbody**

You can select a waterbody to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/hydro/waterbodies/edit?id=[waterbody identifier]

Here you can change the cname of the selected waterbody.

**Waterbody show page**

You can select a waterbody to show if you click the "Eye" icon at the end of the specific row.

Path: /admin/hydro/waterbodies/show?id=[waterbody identifier]

Here you can see the stored data of a waterbody.

**Waterbody deleting**

You cannot delete any waterbody.

### Station classifications
Here you can handle the classifications of a specified station what has already added to the system.

Path: /admin/hydro/station-classifications

Searchable: value

**New classification**

You can add new station classification if you click the "Add station classification" button on the top left of the classification's list page.

Path: /admin/hydro/station-classifications/add

On the classification's creating page, you have to fill to following mandatory fields:
- value

**Updating classification**

You can select a station classification to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/hydro/station-classifications/edit?id=[classification identifier]

Here you can change the value of the selected station classification.

**Classification show page**

You can select a station classification to show if you click the "Eye" icon at the end of the specific row.

Path: /admin/hydro/station-classifications/show?id=[classification identifier]

Here you can see the stored data of a station classification.

**Classification deleting**

You cannot delete any classification.

### Hydro results
Here you can see the results of the different monitoring point, what has arrived via API.

Path: /admin/hydro/results

Searchable: name, symbol


## Meteo
### Meteo monitoring point
Here you can handle the monitoring points what has already added to the system.

Path: /admin/meteo/monitoring-points

Searchable: country, name, location

**New monitoring point**

You can add new monitoring point if you click the "Add monitoring point" button on the top left of the meteo monitoring point list page.

Path: /admin/meteo/monitoring-points/add

On the monitoring point creating page, you have to fill the following mandatory fields:

- name
- classification
- operator
- observed properties


**Updating monitoring point**

You can select a monitoring point to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/meteo/monitoring-points/edit?id=[monitoring point identifier]

Here you can change all of monitoring point's data and you can assign more observed property to the specific monitoring point.

**Monitoring point show page**

You can select a monitoring point to show if you click the "Eye" icon at the end of the specific row.

Path: /admin/meteo/monitoring-points/show?id=[monitoring point identifier]

Here you can see the stored data of the monitoring point and its relations to direction of station classification, operator and observed property.

**Monitoring point deleting**

You cannot delete any monitoring point.

### Observed properties
Here you can handle the observed properties what has already added to the system.

An observed property describes, what kind of property can be measured by a monitoring point.

Path: /admin/meteo/observed-properties

Searchable: symbol, description

**New observed property**

You can add new observed property if you click the "Add observed property" button on the top left of the meteo observed property list page.

Path: /admin/meteo/observed-properties/add

On the observed property creating page, you have to fill to following mandatory fields:

- symbol
- description

**Updating observed property**

You can select an observed property to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/meteo/observed-properties/edit?id=[observed property identifier]

Here you can change all of observed property's data.

**Observed property show page**

You can select an observed property to show if you click the "Eye" icon at the end of the specific row.

Path: /admin/meteo/observed-properties/show?id=[observed property identifier]

Here you can see the stored data of an observed property.

**Observed property deleting**

You cannot delete any observed property.

### Station classifications
Here you can handle the classifications of a specified station what has already added to the system.

Path: /admin/meteo/station-classifications

Searchable: value

**New classification**

You can add new station classification if you click the "Add station classification" button on the top left of the classification's list page.

Path: /admin/meteo/station-classifications/add

On the classification's creating page, you have to fill to following mandatory fields:
- value

**Updating classification**

You can select a station classification to update if you click the "Pencil" icon at the end of the specific row.

Path: /admin/meteo/station-classifications/edit?id=[classification identifier]

Here you can change the value of the selected station classification.

**Classification show page**

You can select a station classification to show if you click the "Eye" icon at the end of the specific row.

Path: /admin/meteo/station-classifications/show?id=[classification identifier]

Here you can see the stored data of a station classification.

**Classification deleting**

You cannot delete any classification.

### Meteo results
Here you can see the results of the different monitoring point, what has arrived via API.

Path: /admin/meteo/results

Searchable: name, symbol
