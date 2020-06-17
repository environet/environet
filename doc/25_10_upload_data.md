## Upload missing and processed data

Upload missing data: `/admin/missing-data`

Upload processed data: `/admin/processed-data`

### Concept of the rules

The two pages "Upload missing data" and "Upload processed data" are similar in functionality.

On both pages you can upload multiple CSV files in a pre-defined format to upload missing or processed data for a monitoring point.
A file contains data for a single monitoring point with multiple properties and data. The sample csv format is downloadable on the pages. 
A user can upload data only for allowed monitoring point, if the user is not a super administrator. 
This uploader in the background will call the standard [upload api](23_api_upload.md), so all validation of this endpoint will work on this uploader too.

The error/success messages will be separated per file, so if a file is invalid, you have to fix and upload only that file.
