# Download API documentation

## URL & METHOD

* **Method**: `GET`
* **URL**: `https://domain.com/download`

---

## Query parameters

* **token**: `required` A random string from which the signature is generated. This is required for authentication, the server verifies the signature in the `Authorization` header based on this token.
* **type**: The type of the monitoring points. The value must be one of the following: `hydro` | `meteo`
* **start**: Minimum date of time series. Date format: [ISO 8601](https://www.iso.org/iso-8601-date-and-time-format.html)
* **end**:  Maximum date of time series. Date format: [ISO 8601](https://www.iso.org/iso-8601-date-and-time-format.html)
* **country[]**: Query time series only for monitoring points from the given countries. Country code format: [ISO 3166-1 - alpha-2](https://www.iso.org/iso-3166-country-codes.html)
* **symbol[]**: Query time series only of the given observed properties.
* **point[]**: Query time series only of the given points.

## Headers

| Header name    | Content                              |
|----------------|--------------------------------------|
| Authorization  | Signature (See below)                |
| X-Request-Attr | Request extra parameters (See below) |

### Signature header:

The pattern of the header: `Signature keyId="[username]",algorithm="rsa-sha256",signature="[signature]"`

The `keyId` is the username of the user who wants to query data.
The `signature` part is the base64 encoded openssl signature which was created with the user's private key from the token in the query parameters.

### Request attributes header

`X-Request-Attr` header can contain some extra metadata related to the download request. The header value format is: 
`key1 value1;key2 value2`
keys and values (key1, key2, value1, value2 in the above example) are base64 encoded strings. Keys and values are separated by a ` ` (space), and each part is separated by `;`

Example:
Data in json format
```json
{"foo": "bar", "test": "1234"}
```
Data in header value:
```text
Zm9v YmFy;dGVzdA== MTIzNA==
```



## Repsonses

### Success
* **Status code**: 200
* **Content-type**: application/xml
* **Body**: XML: `wml2:Collection`
* **Description**: Measurement data in WaterML2.0 comaptible XML format: http://www.opengis.net/waterml/2.0

### Invalid request
* **Status code**: 400
* **Content-type**: application/xml
* **Body**: XML: `environet:ErrorResponse`
* **Description**: The query request is invalid. The response is an xml which is valid agains environet's api schema: [environet.xsd](resources/environet.xsd)
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
	* `207`: Request token not found
	* `301`: Signature is invalid
	* `302`: Observation point type is missing
	* `303`: Observation point type is invalid
	* `304`: Start time filter value is invalid
	* `305`: End time filter value is invalid

### Server error
* **Status code**: 500
* **Content-type**: application/xml
* **Body**: XML: `environet:ErrorResponse`
* **Description**: Unidentified error during request. The response is an xml which is valid agains environet's api schema: [environet.xsd](resources/environet.xsd)
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
