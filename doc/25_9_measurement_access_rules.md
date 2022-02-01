## Measurement access rules


### Concept of the rules

The access rules controls the time interval in which the data will be available for a user under the [download API](24_api_download.md) request. 

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

If the rule matches for a [download API](24_api_download.md) request (so the __user__ getting data for the __monitoring point__ and the __observed property__), the user can retrieve data only in the given time period.
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