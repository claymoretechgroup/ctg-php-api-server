# CTGEndpoint

The core class for building API endpoints. One instance per endpoint
script. Declares which HTTP methods the endpoint supports, what
parameters each method expects, and what handler logic runs. Calling
`run()` executes an 8-step request lifecycle.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _cors | ARRAY | Exported CORS policy configuration |
| _maxBodySize | INT | Maximum request body size in bytes, 0 for no limit |
| _exposeErrorDetails | BOOL | Whether to include details field in error responses |
| _authVerifier | ?CALLABLE | Auth verifier registered via onAuth |
| _methods | ARRAY | Registered HTTP method handlers and their configs |
| _currentMethod | ?STRING | Most recently bound HTTP method for parameter attachment |

---

## Construction

### CONSTRUCTOR :: ARRAY -> ctgEndpoint

Creates a new endpoint from a config array. The `cors` key is required
and must be a `CTGCorsPolicy` instance or an exported array. If
`cors_validated` is not true, the CORS config is validated immediately.
Throws on invalid CORS configuration.

```php
$endpoint = new CTGEndpoint([
    'cors' => CTGCorsPolicy::init()
        ->origins('*')
        ->methods(['GET', 'POST']),
    'max_body_size' => 1048576,
    'expose_error_details' => false,
]);
```

#### Config Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `cors` | ctgCorsPolicy\|ARRAY | *required* | CORS policy instance or exported map |
| `cors_validated` | BOOL | `false` | If true, skip CORS validation |
| `max_body_size` | INT | `0` | Max request body in bytes. 0 = no limit |
| `expose_error_details` | BOOL | `false` | Include `details` field in error responses |

### CTGEndpoint.init :: ARRAY -> ctgEndpoint

Static factory method. Returns `new static(...)` so subclasses inherit
the factory correctly.

```php
$endpoint = CTGEndpoint::init([
    'cors' => CTGCorsPolicy::init()->origins('*'),
]);
```

---

## Instance Methods

### ctgEndpoint.onAuth :: CALLABLE -> SELF

Registers the auth verifier for this endpoint. The callable receives
the raw bearer token string and must return decoded claims on success
or throw on failure. Only called at runtime for methods with
`['auth' => true]`. Chainable.

```php
$endpoint->onAuth(function (string $token): array {
    $claims = verifyJWT($token);
    if (!$claims) {
        throw new \RuntimeException('Invalid token');
    }
    return $claims;
});
```

### ctgEndpoint.GET :: CALLABLE, ?ARRAY -> SELF

Binds a handler for GET requests. The handler receives a `CTGRequest`
and must return a `CTGResponse`. The optional config array supports
`auth` (default false). Parameter declarations that follow attach to
this method until the next method binding call. Chainable.

```php
$endpoint->GET(function (CTGRequest $req): CTGResponse {
    return CTGResponse::json(['status' => 'ok']);
});
```

### ctgEndpoint.POST :: CALLABLE, ?ARRAY -> SELF

Binds a handler for POST requests. Same signature and behavior as `GET`.

```php
$endpoint->POST(function (CTGRequest $req): CTGResponse {
    $id = createUser($req->params('name'));
    return CTGResponse::json(['id' => $id], 201);
}, ['auth' => true]);
```

### ctgEndpoint.PUT :: CALLABLE, ?ARRAY -> SELF

Binds a handler for PUT requests. Same signature and behavior as `GET`.

```php
$endpoint->PUT(function (CTGRequest $req): CTGResponse {
    updateUser($req->params('id'), $req->params());
    return CTGResponse::json(['updated' => true]);
}, ['auth' => true]);
```

### ctgEndpoint.PATCH :: CALLABLE, ?ARRAY -> SELF

Binds a handler for PATCH requests. Same signature and behavior as `GET`.

```php
$endpoint->PATCH(function (CTGRequest $req): CTGResponse {
    patchUser($req->params('id'), $req->params());
    return CTGResponse::json(['patched' => true]);
}, ['auth' => true]);
```

### ctgEndpoint.DELETE :: CALLABLE, ?ARRAY -> SELF

Binds a handler for DELETE requests. Same signature and behavior as `GET`.

```php
$endpoint->DELETE(function (CTGRequest $req): CTGResponse {
    deleteUser($req->params('id'));
    return CTGResponse::noContent();
}, ['auth' => true]);
```

### ctgEndpoint.HEAD :: CALLABLE, ?ARRAY -> SELF

Binds a handler for HEAD requests. Same signature and behavior as `GET`.

```php
$endpoint->HEAD(function (CTGRequest $req): CTGResponse {
    return CTGResponse::noContent();
});
```

### ctgEndpoint.requiredBodyParam :: STRING, ctgValidator -> SELF

Declares a required body parameter on the most recently bound HTTP
method. If the field is absent from the request body, validation fails
with `"Required"` for that field. Throws if called before any method
binding or if the parameter name collides with an existing declaration.
Chainable.

```php
$endpoint->POST($handler)
    ->requiredBodyParam('name', CTGValidator::string())
    ->requiredBodyParam('email', CTGValidator::email());
```

### ctgEndpoint.requiredQueryParam :: STRING, ctgValidator -> SELF

Declares a required query parameter on the most recently bound HTTP
method. Same behavior as `requiredBodyParam` but reads from `$_GET`.
Chainable.

```php
$endpoint->DELETE($handler, ['auth' => true])
    ->requiredQueryParam('id', CTGValidator::uuid());
```

### ctgEndpoint.bodyParam :: STRING, ctgValidator, ?MIXED -> SELF

Declares an optional body parameter. If the field is absent and a
default was provided, the default is applied without validation. If no
default was provided, the field is omitted from the params map entirely.
Chainable.

```php
$endpoint->POST($handler)
    ->bodyParam('role', CTGValidator::string(), 'viewer')
    ->bodyParam('bio', CTGValidator::string_empty());
```

### ctgEndpoint.queryParam :: STRING, ctgValidator, ?MIXED -> SELF

Declares an optional query parameter. Same behavior as `bodyParam` but
reads from `$_GET`. Chainable.

```php
$endpoint->GET($handler)
    ->queryParam('page', CTGValidator::int(), 1)
    ->queryParam('limit', CTGValidator::int()->addCheck(fn($v) => $v >= 1 && $v <= 100), 20);
```

### ctgEndpoint.run :: VOID -> VOID

Executes the request lifecycle. Every request follows this 8-step
sequence, where each step either passes or short-circuits with an
error response:

1. **CORS Resolve** — send CORS headers unconditionally
2. **OPTIONS Preflight** — respond 204 and stop if OPTIONS
3. **Body Size Check** — reject if body exceeds `max_body_size`
4. **Body Parse** — decode JSON or form-urlencoded body
5. **Method Match** — find handler or respond 405
6. **Auth Gate** — verify bearer token if method requires auth
7. **Validate** — run all declared parameters through validators
8. **Handler** — execute the handler, catch errors

```php
CTGEndpoint::init(['cors' => CTGCorsPolicy::init()->origins('*')])
    ->GET(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]))
    ->run();
```
