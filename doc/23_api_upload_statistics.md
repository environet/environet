# Upload Statistics API documentation

## URL & METHOD

* **Method**: `POST`
* **URL**: `https://domain.com/upload/statistics`

---

## Headers

Same as in [Upload API](23_api_upload.md)

### Signature header:

Same as in [Upload API](23_api_upload.md)

---

## Body

Same as in [Upload API](23_api_upload.md)

--- 

## Responses

### Success

* **Status code**: 200
* **Content-type**: application/xml
* **Body**: XML: `environet:UploadStatistics`
* **Description**: Statistics of the upload, but with dry-run. Data is not saved on the upload endpoint, only the statistics are returned.

### Invalid request

Same as in [Upload API](23_api_upload.md)
	
### Server error

Same as in [Upload API](23_api_upload.md)
