## Upload missing and processed data

Upload data: `/admin/upload-data`

### Concept of the rules

On the upload data page, you can upload multiple CSV files in a pre-defined format to upload missing or processed data for a monitoring point.
A file contains data for a single monitoring point with multiple properties and data. The sample CSV format is downloadable on the page. 
A user can upload data only for allowed monitoring points if the user is not a super administrator. 

This uploader in the background will call the standard [upload api](23_api_upload.md), so all validations of this endpoint will work on this uploader too.

### Step 1: Upload CSV files
In step 1, the uploaded CSV files are pre-processed, validated, and converted to XML format. These files are sent to the upload statistics endpoint, 
which returns statistics of the uploaded files, but without any operation on the distribution node.

### Step2: Confirm upload
With the confirmation of the statistics, all files will be uploaded to the distribution node, and the distribution node will process the files and save the data to the database.
The error/success messages will be separated per file, so if a file is invalid, you have to fix and upload only that file. 
The files with errors won't be uploaded to the distribution node after confirmation.

The dates in CSV files must be in UTC timezone.

