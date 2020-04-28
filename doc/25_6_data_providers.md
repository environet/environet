## Data providers
Here you can handle the data providers what has already added to the  system.

Path: `/admin/data-providers`

Searchable: name, address, email

#### New data provider

You can add new data provider if you click the "Add data provider" button on the top left of the data provider's list page.

Path: `/admin/data-providers/add`

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

#### Updating data provider

You can select a data provider to update if you click the "Pencil" icon at the end of the specific row.

Path: `/admin/data-providers/edit?id=[data-provider identifier]`

Here you can change all of operator's data and you can assign  more users or groups to the specific dataprovider.

#### Data provider show page

You can select a data provider to show if you click the "Eye" icon at the end of the specific row.

Path: `/admin/data-providers/show?id=[data-provider identifier]`

Here you can see the stored data of the data providers and its relations to direction of users and groups.

#### Data provider deleting

You cannot delete any data provider.