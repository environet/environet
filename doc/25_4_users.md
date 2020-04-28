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