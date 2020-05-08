# Monitoring points API documentation

## URL & METHOD

* **Method**: `GET`
* **URL**: `https://domain.com/api/monitoring-points`

---

## Query parameters

* **token**: `required` A random string from which the signature is generated. This is required for authentication, the server verifies the signature in the `Authorization` header based on this token.

## Headers

| Header name | Content |
| --- | --- |
| Authorization | Signature (See below) |

### Signature header:

The pattern of the header: `Signature keyId="[username]",algorithm="rsa-sha256",signature="[signature]"`

The `keyId` is the username of the user who wants to query data.
The `signature` part is the base64 encoded openssl signature which was created with the user's private key from the token in the query parameters.

## Repsonses

### Success
* **Status code**: 200
* **Content-type**: application/json
* **Body**: `JSON`
* **Description**: Request is OK, list the hydro and meteo points of user
* **Body example**:

    ```json
    {
        "hydro": [
            {
                "id": 1,
                "station_classificationid": 1,
                "operatorid": 1,
                "bankid": 1,
                "waterbodyeuropean_river_code": "waterbody",
                "eucd_wgst": "ABC123",
                "ncd_wgst": "ABC123",
                "vertical_reference": "Vertical reference",
                "long": "1.0000000000",
                "lat": "1.0000000000",
                "z": "1.0000000000",
                "maplong": "1.0000000000",
                "maplat": "1.0000000000",
                "country": "CountryCode",
                "name": "MPoint",
                "location": "Location",
                "river_kilometer": "1.0000000000",
                "catchment_area": "1.0000000000",
                "gauge_zero": "1.0000000000",
                "start_time": "2020-01-01 00:00:00",
                "end_time": "2020-05-01 00:00:00",
                "utc_offset": 0,
                "river_basin": "Basin",
                "observed_properties": [
                    "hydro_property"
                ]
            }
        ],
        "meteo": [
            {
                "id": 1,
                "meteostation_classificationid": 1,
                "operatorid": 1,
                "eucd_pst": "DEF456",
                "ncd_pst": "DEF456",
                "vertical_reference": "Vertical reference",
                "long": "1.0000000000",
                "lat": "1.0000000000",
                "z": "1.0000000000",
                "maplong": "1.0000000000",
                "maplat": "1.0000000000",
                "country": "CountryCode",
                "name": "Name",
                "location": "Location",
                "altitude": "1.0000000000",
                "start_time": "2020-01-01 00:00:00",
                "end_time": "2020-05-01 00:00:00",
                "utc_offset": 0,
                "river_basin": "Basin",
                "observed_properties": [
                    "meteo_property"
                ]
            }
        ]
    }
	```

### Invalid request
* **Status code**: 400
* **Content-type**: application/json
* **Body**: `JSON`
* **Description**: The query request is invalid. The response is a json object with the error message, under error key.
* **Body example**:
	 
	```json
    {
     "error": "Error message"
    }
	```

### Server error
* **Status code**: 500
* **Content-type**: application/json
* **Body**: `JSON`
* **Description**: Unidentified error during request. The response is a json object with the error message, under error key.
* **Body example**:
	 
	```json
	{
     "error": "Error message"
    }
	```
