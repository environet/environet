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
* **Body**: XML: `environet:UploadStatistics`
* **Description**: Upload was successful, the data has been successfully processed. Response is the statistics output of the upload process.

### Invalid request

* **Status code**: 400
* **Content-type**: application/xml
* **Body**: XML: `environet:ErrorResponse`
* **Description**: Input or processing error during the upload process. The response in an error xml which is valid against environet's upload api schema: [environet.xsd](resources/environet.xsd)
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