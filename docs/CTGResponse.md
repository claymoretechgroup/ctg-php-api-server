# CTGResponse

JSON response envelope. Constructed via static factories, not directly.
Wraps handler return values in the uniform
`{ "success": true, "result": ... }` envelope. The 204 No Content
response has no body.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _status | INT | HTTP status code |
| _body | ?STRING | JSON-encoded response body, null for no-content |
| _headers | ARRAY | Additional response headers |

---

## Static Methods

### CTGResponse.json :: MIXED, ?INT, ?ARRAY -> ctgResponse

Creates a JSON response. The data is wrapped in
`{ "success": true, "result": data }` and JSON-encoded. Defaults to
status 200. Optional headers are sent alongside the response.

```php
return CTGResponse::json(['id' => 42], 201, ['Location' => '/api/users/42']);
```

### CTGResponse.noContent :: ?ARRAY -> ctgResponse

Creates a 204 No Content response with no body. Optional headers are
sent alongside the response.

```php
return CTGResponse::noContent();
```

---

## Instance Methods

### ctgResponse.send :: VOID -> VOID

Sends the response directly via PHP's `http_response_code()`,
`header()`, and `echo`. Sets `Content-Type: application/json` when
a body is present.

```php
$response = CTGResponse::json(['ok' => true]);
$response->send();
```

### ctgResponse.getStatus :: VOID -> INT

Returns the HTTP status code.

```php
$status = $response->getStatus();
```

### ctgResponse.getBody :: VOID -> ?STRING

Returns the JSON-encoded response body, or null for no-content
responses.

```php
$body = $response->getBody();
```

### ctgResponse.getHeaders :: VOID -> ARRAY

Returns the additional response headers as an associative array.

```php
$headers = $response->getHeaders();
```
