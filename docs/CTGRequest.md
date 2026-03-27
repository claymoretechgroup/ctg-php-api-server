# CTGRequest

Read-only request object constructed internally by `CTGEndpoint` after
validation succeeds. Contains the HTTP method, validated parameters,
request headers, and auth state. Never constructed by application code.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _method | STRING | HTTP method as an uppercased string (readonly) |
| _params | ?ARRAY | Validated parameters map, null if no parameters declared (readonly) |
| _headers | ARRAY | Request headers, lowercase-keyed (readonly) |
| _token | ?STRING | Raw bearer token string, null if no auth (readonly) |
| _claims | ?ARRAY | Decoded auth claims, null if no auth (readonly) |

---

## Instance Methods

### ctgRequest.method :: VOID -> STRING

Returns the HTTP method as an uppercased string.

```php
$method = $req->method();
// "GET"
```

### ctgRequest.params :: ?STRING -> MIXED

Without key: returns the full associative array of validated parameters
(body and query merged), or null if no parameters were declared for
this method. With key: returns the value for that parameter, or null
if the key is absent.

```php
$all = $req->params();
$name = $req->params('name');
```

For PATCH semantics where "field not sent" differs from "field sent as
null," use `array_key_exists` on the full map:

```php
$params = $req->params();
if ($params !== null && array_key_exists('name', $params)) {
    $updates['name'] = $params['name'];
}
```

### ctgRequest.headers :: ?STRING -> MIXED

Without key: returns all headers as a lowercase-keyed associative
array. With key: returns the value for that header, or null if absent.

```php
$all = $req->headers();
$contentType = $req->headers('content-type');
```

### ctgRequest.token :: VOID -> ?STRING

Returns the raw bearer token string, or null if authentication was
not required or no token was present.

```php
$token = $req->token();
```

### ctgRequest.claims :: ?STRING -> MIXED

Without key: returns the full decoded claims array from the auth
verifier, or null if no auth. With key: returns the value for that
claim, or null if absent.

```php
$claims = $req->claims();
$role = $req->claims('role');
```
