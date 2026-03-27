# CTGCorsPolicy

Builder for CORS policy configuration. Validates internal consistency
and resolves headers for incoming requests. Policies can be exported as
plain arrays for sharing across multiple endpoints.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _origins | STRING\|ARRAY\|NULL | Allowed origins: `"*"`, a single origin string, or an array of origins |
| _methods | ?ARRAY | Allowed HTTP methods |
| _headers | ?ARRAY | Allowed request headers the browser may send |
| _exposedHeaders | ?ARRAY | Response headers the browser may read |
| _credentials | ?BOOL | Whether to send Access-Control-Allow-Credentials |
| _maxAge | ?INT | Preflight cache duration in seconds |

---

## Construction

### CONSTRUCTOR :: VOID -> ctgCorsPolicy

Creates an empty CORS policy. All fields are unset until builder
methods are called.

```php
$cors = new CTGCorsPolicy();
```

### CTGCorsPolicy.init :: VOID -> ctgCorsPolicy

Static factory method.

```php
$cors = CTGCorsPolicy::init();
```

### CTGCorsPolicy.fromArray :: ARRAY -> ctgCorsPolicy

Reconstructs a `CTGCorsPolicy` from an exported array. Used internally
by `CTGEndpoint` to reconstruct policies from stored config.

```php
$cors = CTGCorsPolicy::fromArray([
    'origins' => ['https://app.example.com'],
    'methods' => ['GET', 'POST'],
    'credentials' => true,
]);
```

---

## Instance Methods

### ctgCorsPolicy.origins :: STRING|ARRAY -> SELF

Sets allowed origins. Accepts `"*"` for wildcard, a single origin
string, or an array of origin strings. Chainable.

```php
$cors->origins('*');
$cors->origins(['https://app.example.com', 'https://admin.example.com']);
```

### ctgCorsPolicy.methods :: STRING|ARRAY -> SELF

Sets allowed HTTP methods. A single string is normalized to a
one-element array. Chainable.

```php
$cors->methods(['GET', 'POST', 'PUT', 'DELETE']);
```

### ctgCorsPolicy.headers :: STRING|ARRAY -> SELF

Sets allowed request headers the browser may send. A single string is
normalized to a one-element array. Chainable.

```php
$cors->headers(['Content-Type', 'Authorization']);
```

### ctgCorsPolicy.exposedHeaders :: STRING|ARRAY -> SELF

Sets response headers the browser may read. A single string is
normalized to a one-element array. Chainable.

```php
$cors->exposedHeaders(['X-Request-Id', 'X-Total-Count']);
```

### ctgCorsPolicy.credentials :: BOOL -> SELF

Sets whether to send `Access-Control-Allow-Credentials: true`.
Cannot be used with wildcard origins. Chainable.

```php
$cors->credentials(true);
```

### ctgCorsPolicy.maxAge :: INT -> SELF

Sets the preflight cache duration in seconds. Must be non-negative.
Chainable.

```php
$cors->maxAge(86400);
```

### ctgCorsPolicy.validate :: VOID -> SELF

Validates internal consistency. Throws `\RuntimeException` on invalid
combinations. Returns `$this` for chaining.

| Rule | Condition | Error |
|------|-----------|-------|
| Origins required | Origins must be set and non-empty | Throws |
| Wildcard + credentials | `"*"` origin with `credentials(true)` | Throws |
| Valid max age | Must be non-negative if set | Throws |

```php
$cors->validate();
```

### ctgCorsPolicy.export :: VOID -> ARRAY

Calls `validate()`, then returns the policy as a plain associative
array. The exported array can be passed to the `CTGEndpoint` constructor
with `'cors_validated' => true` to bypass re-validation.

```php
$policy = CTGCorsPolicy::init()
    ->origins('*')
    ->methods(['GET', 'POST'])
    ->export();
```

### ctgCorsPolicy.resolveHeaders :: STRING, STRING -> ARRAY

Returns an associative array of CORS header name/value pairs for the
given origin and request method. Used by `CTGEndpoint` to send headers
via its own output methods. For OPTIONS requests, includes preflight
headers (Allow-Methods, Allow-Headers, Max-Age).

```php
$headers = $cors->resolveHeaders('https://app.example.com', 'OPTIONS');
// ['Access-Control-Allow-Origin' => 'https://app.example.com', 'Vary' => 'Origin', ...]
```

### ctgCorsPolicy.resolve :: VOID -> VOID

Reads the origin and method from `$_SERVER` and sends CORS headers
directly via PHP's `header()` function. Convenience method for
standalone use outside of `CTGEndpoint`.

```php
$cors->resolve();
```
