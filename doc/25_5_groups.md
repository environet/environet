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

You can delete a group if you click the "Trash" icon at the end of the specific row. If you clicked, it shows a confirm window, where you have to approve the deleting. **If any user have already assigned to the specified group, the delete operation cannot be performed.**
First time you have to detach these relations and after that you can delete the group.

Path: `/admin/groups/delete?id=[group identifier]`