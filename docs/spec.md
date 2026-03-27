# ctg-php-api-server — Library Specification

## Overview

A minimal PHP library for building RESTful API endpoints. Each endpoint
is a self-contained script — one file per URL — with the web server
handling routing via the filesystem. The library provides CORS policy
enforcement, pluggable bearer token authentication, two-phase request
validation with type coercion, and a uniform JSON response envelope.
Handlers receive a read-only request object containing only validated
parameters; they return response objects; the library owns all output.

No framework. No middleware stack. No router. Declare methods, declare
parameters, call `run()`, the library handles the rest.

---

## Design Principles

1. **Filesystem routing** — one PHP script per endpoint. The web server
   (Apache, nginx) maps URLs to files. The library has no router, no
   URL pattern matching, no path registration
2. **Fluent declaration** — bind HTTP methods, declare parameters, and
   call `run()` in a single chain. The builder is mutable — methods
   return `$this`
3. **Validation produces the request** — the `CTGRequest` object the
   handler receives is the product of validation, not raw input.
   `$_GET`, `$_POST`, `$_SERVER`, and `php://input` are read once
   internally and never exposed to handlers
4. **Auth is per-method opt-in** — register a verifier once via
   `onAuth()`, then opt in per HTTP method via `['auth' => true]`.
   Public and protected methods coexist on the same endpoint
5. **CORS first** — CORS headers are sent before any other logic,
   including error responses. The browser always gets CORS headers
6. **Uniform envelope** — every response is
   `{ "success": bool, "result": ... }`. The only exception is 204
   No Content, which has no body
7. **Prep then check** — validators coerce first (prep), then validate
   predicates (check). The handler always receives the target type.
   Query parameters arrive as strings in PHP — the prep phase handles
   coercion transparently
8. **Zero dependencies** — only PHP standard library. No Composer
   packages, no PSR implementations, no framework adapters

---

## Class Interface

```php
namespace CTG\ApiServer;

class CTGEndpoint
{
    // ─── Construction ──────────────────────────────────────

    // CONSTRUCTOR :: ARRAY -> $this
    public function __construct(array $config);

    // Static Factory Method :: ARRAY -> ctgEndpoint
    public static function init(array $config): static;

    // ─── Auth ──────────────────────────────────────────────

    // :: CALLABLE -> $this
    // Set the auth verifier for this endpoint
    public function onAuth(callable $verifier): static;

    // ─── HTTP Method Binding ───────────────────────────────

    // :: CALLABLE, ARRAY -> $this
    public function GET(callable $handler, array $config = []): static;

    // :: CALLABLE, ARRAY -> $this
    public function POST(callable $handler, array $config = []): static;

    // :: CALLABLE, ARRAY -> $this
    public function PUT(callable $handler, array $config = []): static;

    // :: CALLABLE, ARRAY -> $this
    public function PATCH(callable $handler, array $config = []): static;

    // :: CALLABLE, ARRAY -> $this
    public function DELETE(callable $handler, array $config = []): static;

    // :: CALLABLE, ARRAY -> $this
    public function HEAD(callable $handler, array $config = []): static;

    // ─── Parameter Declaration ─────────────────────────────

    // :: STRING, ctgValidator -> $this
    public function requiredBodyParam(string $name, CTGValidator $validator): static;

    // :: STRING, ctgValidator -> $this
    public function requiredQueryParam(string $name, CTGValidator $validator): static;

    // :: STRING, ctgValidator, MIXED -> $this
    public function bodyParam(string $name, CTGValidator $validator, mixed $default = null): static;

    // :: STRING, ctgValidator, MIXED -> $this
    public function queryParam(string $name, CTGValidator $validator, mixed $default = null): static;

    // ─── Lifecycle ─────────────────────────────────────────

    // :: VOID -> VOID
    public function run(): void;
}

class CTGCorsPolicy
{
    // ─── Construction ──────────────────────────────────────

    // CONSTRUCTOR :: VOID -> $this
    public function __construct();

    // Static Factory Method :: VOID -> ctgCorsPolicy
    public static function init(): static;

    // ─── Builder ───────────────────────────────────────────

    // :: STRING|ARRAY -> $this
    public function origins(string|array $origins): static;

    // :: STRING|ARRAY -> $this
    public function methods(string|array $methods): static;

    // :: STRING|ARRAY -> $this
    public function headers(string|array $headers): static;

    // :: STRING|ARRAY -> $this
    public function exposedHeaders(string|array $headers): static;

    // :: BOOL -> $this
    public function credentials(bool $allow): static;

    // :: INT -> $this
    public function maxAge(int $seconds): static;

    // ─── Operations ────────────────────────────────────────

    // :: VOID -> $this
    public function validate(): static;

    // :: VOID -> ARRAY
    public function export(): array;

    // :: VOID -> VOID
    public function resolve(): void;
}

class CTGValidator
{
    // ─── Construction ──────────────────────────────────────

    // CONSTRUCTOR :: ARRAY -> $this
    public function __construct(array $config = []);

    // Static Factory Method :: ARRAY -> ctgValidator
    public static function init(array $config = []): static;

    // ─── Composition ───────────────────────────────────────

    // :: CALLABLE -> $this
    public function addPrep(callable $fn): static;

    // :: CALLABLE -> $this
    public function addCheck(callable $fn): static;

    // ─── Execution ─────────────────────────────────────────

    // :: MIXED -> MIXED
    public function prep(mixed $value): mixed;

    // :: MIXED -> BOOL
    public function check(mixed $value): bool;

    // :: MIXED -> MIXED
    public function run(mixed $value): mixed;

    // ─── Type Factories ────────────────────────────────────

    // :: ARRAY -> ctgValidator
    public static function string(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function string_empty(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function int(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function float(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function bool(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function boolint(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function array(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function email(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function url(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function uuid(array $config = []): static;

    // :: ARRAY -> ctgValidator
    public static function date(array $config = []): static;
}

class CTGRequest
{
    // ─── Accessors ─────────────────────────────────────────

    // :: VOID -> STRING
    public function method(): string;

    // :: ?STRING -> MIXED
    public function params(?string $key = null): mixed;

    // :: ?STRING -> MIXED
    public function headers(?string $key = null): mixed;

    // :: VOID -> ?STRING
    public function token(): ?string;

    // :: ?STRING -> MIXED
    public function claims(?string $key = null): mixed;
}

class CTGResponse
{
    // ─── Factories ─────────────────────────────────────────

    // :: MIXED, INT, ARRAY -> ctgResponse
    public static function json(mixed $data, int $status = 200, array $headers = []): static;

    // :: ARRAY -> ctgResponse
    public static function noContent(array $headers = []): static;

    // ─── Output ────────────────────────────────────────────

    // :: VOID -> VOID
    public function send(): void;
}

class CTGServerError extends \Exception
{
    // ─── Constants ─────────────────────────────────────────

    const TYPES = [
        'NOT_FOUND'            => 1000,
        'FORBIDDEN'            => 1001,
        'CONFLICT'             => 1002,
        'INVALID'              => 1003,
        'METHOD_NOT_ALLOWED'   => 1004,
        'PAYLOAD_TOO_LARGE'    => 1005,
        'INVALID_CONTENT_TYPE' => 1006,
        'INVALID_BODY'         => 1007,
        'INTERNAL_ERROR'       => 2000,
    ];

    public readonly string $type;
    public readonly string $msg;
    public readonly int    $httpStatus;
    public readonly mixed  $details;

    // CONSTRUCTOR :: STRING, STRING, INT, MIXED -> $this
    public function __construct(
        string $type,
        string $message = '',
        int    $httpStatus = 500,
        mixed  $details = null
    );

    // ─── Methods ───────────────────────────────────────────

    // :: VOID -> VOID
    public function send(): void;

    // :: BOOL -> ARRAY
    public function toResult(bool $exposeDetails = false): array;

    // ─── Static Factories ──────────────────────────────────

    // :: ?STRING, MIXED -> ctgServerError
    public static function notFound(?string $message = null, mixed $details = null): static;

    // :: ?STRING, MIXED -> ctgServerError
    public static function forbidden(?string $message = null, mixed $details = null): static;

    // :: ?STRING, MIXED -> ctgServerError
    public static function conflict(?string $message = null, mixed $details = null): static;

    // :: ?STRING, MIXED -> ctgServerError
    public static function invalid(?string $message = null, mixed $details = null): static;

    // :: ?STRING, MIXED -> ctgServerError
    public static function internal(?string $message = null, mixed $details = null): static;
}

class CTGValidationError extends \Exception
{
    // ─── Constants ─────────────────────────────────────────

    const TYPES = [
        'PREP_FAILED'  => 3000,
        'CHECK_FAILED' => 3001,
    ];

    public readonly string $errorCode;
    public readonly mixed  $context;

    // CONSTRUCTOR :: STRING, STRING, MIXED -> $this
    public function __construct(
        string $code,
        string $message = '',
        mixed  $context = null
    );

    // ─── Methods ───────────────────────────────────────────

    // :: VOID -> STRING
    public function getErrorCode(): string;

    // :: VOID -> MIXED
    public function getContext(): mixed;
}
```

---

## CTGEndpoint

The core class. One instance per endpoint script. Declares which HTTP
methods the endpoint supports, what parameters each method expects,
and what handler logic runs.

### Constructor & Factory

```php
// Minimal — CORS policy required
$endpoint = CTGEndpoint::init([
    'cors' => CTGCorsPolicy::init()
        ->origins('*')
        ->methods(['GET', 'POST'])
        ->export(),
    'cors_validated' => true,
]);

// Full config
$endpoint = CTGEndpoint::init([
    'cors'                 => CTGCorsPolicy::init()
        ->origins(['https://app.example.com'])
        ->methods(['GET', 'POST', 'PUT', 'DELETE'])
        ->headers(['Content-Type', 'Authorization'])
        ->credentials(true),
    'max_body_size'        => 1048576,    // 1 MB
    'expose_error_details' => false,      // default
]);
```

#### Config Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `cors` | `CTGCorsPolicy\|array` | *required* | CORS policy instance or exported map |
| `cors_validated` | `bool` | `false` | If true, skip CORS validation (already validated via `export()`) |
| `max_body_size` | `int` | `0` | Max request body in bytes. 0 = no limit |
| `expose_error_details` | `bool` | `false` | Include `details` field in error responses |

#### Behavior

1. If `cors_validated` is absent or `false`, call `validate()` on the
   CORS config. If it is a plain array, construct a `CTGCorsPolicy`
   from it first. If invalid, throw immediately — no request is ever
   processed with a broken CORS policy.
2. If `cors_validated` is `true`, skip CORS validation entirely. This
   is the path used by `export()`.
3. Store `max_body_size` and `expose_error_details`.

### Auth Binding

```php
$endpoint->onAuth(function (string $token): array {
    // Verify JWT, return decoded claims
    $claims = verifyJWT($token);
    if (!$claims) {
        throw new \RuntimeException('Invalid token');
    }
    return $claims;
});
```

`onAuth` registers a callable with the signature
`fn(STRING) -> ARRAY`. The verifier receives the raw bearer token
string (trimmed). It must return decoded claims on success or throw
on failure. Only called at runtime for methods with `['auth' => true]`.

### HTTP Method Binding

```php
$endpoint
    ->GET(function (CTGRequest $req): CTGResponse {
        $page = $req->params('page');
        $items = fetchItems($page);
        return CTGResponse::json($items);
    })
    ->POST(function (CTGRequest $req): CTGResponse {
        $name = $req->params('name');
        $email = $req->params('email');
        $id = createUser($name, $email);
        return CTGResponse::json(['id' => $id], 201);
    }, ['auth' => true]);
```

Each method accepts:

- `$handler` — callable with signature `fn(ctgRequest) -> ctgResponse`
- `$config` — optional array with `auth` key

| Config Key | Type | Default | Description |
|------------|------|---------|-------------|
| `auth` | `bool` | `false` | Require authentication for this method |

Binds a handler for the given HTTP method. Returns `$this` for
chaining. Parameter declarations that follow attach to this method
until the next method binding call.

OPTIONS is not bindable — it is handled internally by CORS preflight.

### Parameter Declaration

```php
$endpoint
    ->POST(function (CTGRequest $req): CTGResponse {
        $name = $req->params('name');
        $email = $req->params('email');
        $role = $req->params('role');
        $id = createUser($name, $email, $role);
        return CTGResponse::json(['id' => $id], 201);
    }, ['auth' => true])
    ->requiredBodyParam('name', CTGValidator::string())
    ->requiredBodyParam('email', CTGValidator::email())
    ->bodyParam('role', CTGValidator::string(), 'viewer');
```

Parameter declarations attach to the most recently bound HTTP method.
Each declaration stores: name, validator, source (body or query),
required/optional flag, and default value (if optional).

#### Required Parameters

```php
// :: STRING, ctgValidator -> $this
$endpoint->requiredBodyParam('name', CTGValidator::string());
$endpoint->requiredQueryParam('id', CTGValidator::uuid());
```

If the field is absent from the request, validation fails with
`"Required"` for that field.

#### Optional Parameters

```php
// :: STRING, ctgValidator, MIXED -> $this
$endpoint->bodyParam('bio', CTGValidator::string_empty(), '');
$endpoint->queryParam('page', CTGValidator::int(), 1);
```

If the field is absent and a default was provided, the default is
applied. The default is **never** passed through the validator.

If the field is absent and **no default** was provided (only two
arguments), the field is omitted from the params map entirely — it
does not appear in `$req->params()`. This is important for PATCH
semantics where "field not sent" differs from "field sent as null."

```php
// Optional without default — PATCH semantics
$endpoint->bodyParam('name', CTGValidator::string());
```

In the handler, use `array_key_exists` to distinguish absent from null:

```php
$params = $req->params();
if (array_key_exists('name', $params)) {
    // Field was sent — update it
    $updates['name'] = $params['name'];
}
// Field not sent — don't touch it
```

#### Collision Detection

Duplicate parameter names within the same source (body or query) for
the same method throw immediately at declaration time:

```php
// Throws immediately — duplicate body param
$endpoint->POST($handler)
    ->requiredBodyParam('name', CTGValidator::string())
    ->bodyParam('name', CTGValidator::string_empty());
```

Cross-source collision (same name in both body and query) also throws
immediately:

```php
// Throws immediately — body/query collision
$endpoint->POST($handler)
    ->requiredBodyParam('id', CTGValidator::int())
    ->requiredQueryParam('id', CTGValidator::int());
```

### Run Lifecycle

```php
$endpoint->run();
```

Every request follows this exact 8-step sequence. Each step either
passes or short-circuits with an error response.

#### Step 1 — CORS Resolve

Call the CORS policy's `resolve()` method to send CORS headers. Always
runs first, unconditionally, on every request including error
responses.

PHP-specific: reads `$_SERVER['HTTP_ORIGIN']` for origin matching,
`$_SERVER['REQUEST_METHOD']` for preflight detection. Sends headers
via `header()`.

#### Step 2 — OPTIONS Preflight

If `$_SERVER['REQUEST_METHOD']` is `OPTIONS`, respond with
`http_response_code(204)` and stop. Nothing else executes. The CORS
headers from step 1 are already sent.

#### Step 3 — Body Size Check

If `max_body_size` is configured and greater than 0:

1. Check `$_SERVER['CONTENT_LENGTH']` if present. If it exceeds the
   limit, reject immediately without reading the body.
2. If `CONTENT_LENGTH` is absent, read `php://input` incrementally
   using `fopen` + `fread` in chunks, tracking accumulated size. If
   the accumulated size exceeds the limit, close the stream and reject.
3. **Buffer requirement:** When reading incrementally in step 2, buffer
   the bytes read so far. If the size check passes, pass the buffered
   bytes to step 4 for parsing. Do not read `php://input` a second
   time — PHP's `php://input` stream can only be read once.

If `max_body_size` is not configured, read the full body from
`php://input` via `file_get_contents('php://input')` and pass to
step 4.

Respond 413 on size exceeded:
```json
{ "success": false, "result": { "type": "PAYLOAD_TOO_LARGE", "message": "Request body exceeds maximum size" } }
```

#### Step 4 — Body Parse

Use the raw body string from step 3 (either buffered from incremental
read or from `file_get_contents`).

Extract the media type from `$_SERVER['CONTENT_TYPE']` by stripping
any parameters (e.g., `application/json; charset=utf-8` becomes
`application/json`). Use `explode(';', $contentType, 2)[0]` and
`trim()`. Match against the stripped media type:

- **`application/json`** — `json_decode($body, true, 64)` with depth
  limit of 64. If `json_last_error() !== JSON_ERROR_NONE`, respond 400:
  ```json
  { "success": false, "result": { "type": "INVALID_BODY", "message": "Malformed request body" } }
  ```
- **`application/x-www-form-urlencoded`** — `parse_str($body, $parsed)`.
  Note: PHP's `$_POST` superglobal already handles this for POST
  requests, but using `parse_str` on the raw input ensures consistent
  behavior across all HTTP methods.
- **Empty body** (GET, DELETE, HEAD, or empty Content-Length) — body
  map is `[]`.
- **Other Content-Type with non-empty body** — respond 400:
  ```json
  { "success": false, "result": { "type": "INVALID_CONTENT_TYPE", "message": "Unsupported content type" } }
  ```

#### Step 5 — Method Match

Find the handler bound to `strtoupper($_SERVER['REQUEST_METHOD'])`.
If no handler is registered for this method, respond 405 with an
`Allow` header listing the registered methods:

```php
header('Allow: ' . implode(', ', $registeredMethods));
http_response_code(405);
```

```json
{ "success": false, "result": { "type": "METHOD_NOT_ALLOWED", "message": "Method not allowed" } }
```

#### Step 6 — Auth Gate

If the matched method has `['auth' => true]` in its config:

1. If `onAuth` was never called, throw immediately — this is a
   developer misconfiguration error, not a 4xx response.
2. Extract the bearer token from `$_SERVER['HTTP_AUTHORIZATION']`
   (pattern: `Bearer <token>`, case-insensitive prefix match via
   `stripos`). Also check `apache_request_headers()` as a fallback
   since some SAPI configurations strip the Authorization header from
   `$_SERVER`.
3. Trim whitespace from the extracted token. If the token is missing
   or empty after trimming, respond 401:
   ```json
   { "success": false, "result": "Authorization token required" }
   ```
4. Call the verifier with the token string. If the verifier throws,
   respond 401 and stop.
5. On success, store the decoded claims on the request object.

If the matched method does not have `['auth' => true]`, skip entirely.

#### Step 7 — Validate

For each declared parameter on the matched method:

- **Present + declared** — call `$validator->run($rawValue)`. If `run`
  throws `CTGValidationError`, collect the error message keyed by
  field name.
- **Absent + required** — collect `"Required"` keyed by field name.
- **Absent + optional with default** — apply the default value. The
  default is never validated.
- **Absent + optional without default** — omit the field from the
  params map entirely.

Body parameters are read from the parsed body map (step 4). Query
parameters are read from `$_GET`.

If any errors were collected, respond 400:
```json
{ "success": false, "result": { "name": "Required", "email": "Failed validation: email" } }
```

Otherwise, construct a `CTGRequest` with all validated and defaulted
parameters merged into a single map. Undeclared fields are stripped.

#### Step 8 — Handler

Execute the matched handler with the validated `CTGRequest`. The
handler must return a `CTGResponse`. Call `send()` on the response.

- If the handler throws a `CTGServerError`, call `send()` on the
  error (which uses `expose_error_details` from config).
- If the handler throws any other `\Throwable`, wrap it as a
  `CTGServerError` with type `INTERNAL_ERROR` and status 500. The
  original error message is **never** exposed to the client.

### Full Endpoint Example

```php
<?php
// api/users.php
declare(strict_types=1);

use CTG\ApiServer\CTGEndpoint;
use CTG\ApiServer\CTGValidator;
use CTG\ApiServer\CTGRequest;
use CTG\ApiServer\CTGResponse;
use CTG\ApiServer\CTGServerError;

CTGEndpoint::init([
    'cors' => $sharedCorsPolicy,     // pre-exported from shared config
    'cors_validated' => true,
    'max_body_size' => 1048576,
])
->onAuth(function (string $token): array {
    return verifyJWT($token);
})

// GET /api/users — public, paginated list
->GET(function (CTGRequest $req): CTGResponse {
    $page = $req->params('page');
    $limit = $req->params('limit');
    $users = fetchUsers($page, $limit);
    return CTGResponse::json($users);
})
->queryParam('page', CTGValidator::int()->addCheck(fn($v) => $v > 0), 1)
->queryParam('limit', CTGValidator::int()->addCheck(fn($v) => $v >= 1 && $v <= 100), 20)

// POST /api/users — auth required, create user
->POST(function (CTGRequest $req): CTGResponse {
    $claims = $req->claims();
    if ($claims['role'] !== 'admin') {
        throw CTGServerError::forbidden('Admin access required');
    }
    $id = createUser($req->params('name'), $req->params('email'));
    return CTGResponse::json(['id' => $id], 201, ['Location' => "/api/users/{$id}"]);
}, ['auth' => true])
->requiredBodyParam('name', CTGValidator::string())
->requiredBodyParam('email', CTGValidator::email())
->bodyParam('role', CTGValidator::string(), 'viewer')

// DELETE /api/users — auth required
->DELETE(function (CTGRequest $req): CTGResponse {
    $id = $req->params('id');
    deleteUser($id);
    return CTGResponse::noContent();
}, ['auth' => true])
->requiredQueryParam('id', CTGValidator::uuid())

->run();
```

---

## CTGCorsPolicy

Builder for CORS policy configuration. Validates internal consistency
and resolves headers for incoming requests.

### Constructor & Factory

```php
// Empty policy
$cors = new CTGCorsPolicy();
$cors = CTGCorsPolicy::init();
```

Creates an empty CORS policy. All fields are unset until builder
methods are called.

### Builder Methods

All builder methods return `$this` for chaining.

```php
$cors = CTGCorsPolicy::init()
    ->origins(['https://app.example.com', 'https://admin.example.com'])
    ->methods(['GET', 'POST', 'PUT', 'DELETE'])
    ->headers(['Content-Type', 'Authorization'])
    ->exposedHeaders(['X-Request-Id'])
    ->credentials(true)
    ->maxAge(86400);
```

#### origins

```php
// :: STRING|ARRAY<STRING> -> $this
$cors->origins('*');
$cors->origins('https://app.example.com');
$cors->origins(['https://app.example.com', 'https://admin.example.com']);
```

Set allowed origins. Accepts `"*"` for wildcard.

#### methods

```php
// :: STRING|ARRAY<STRING> -> $this
$cors->methods('GET');
$cors->methods(['GET', 'POST', 'PUT', 'DELETE']);
```

Set allowed HTTP methods.

#### headers

```php
// :: STRING|ARRAY<STRING> -> $this
$cors->headers(['Content-Type', 'Authorization']);
```

Set allowed request headers the browser may send.

#### exposedHeaders

```php
// :: STRING|ARRAY<STRING> -> $this
$cors->exposedHeaders(['X-Request-Id', 'X-Total-Count']);
```

Set response headers the browser may read.

#### credentials

```php
// :: BOOL -> $this
$cors->credentials(true);
```

Whether to send `Access-Control-Allow-Credentials: true`.

#### maxAge

```php
// :: INT -> $this
$cors->maxAge(86400);
```

Preflight cache duration in seconds. Must be non-negative.

### validate()

```php
// :: VOID -> $this
$cors->validate();
```

Validates internal consistency. Throws on invalid combinations.
Returns `$this` for chaining.

| Rule | Condition | Error |
|------|-----------|-------|
| Origins required | Origins must be a non-empty string or non-empty list | Throws |
| Wildcard + credentials | `"*"` origin with `credentials(true)` | Throws |
| Valid methods | Methods must be valid HTTP method strings | Throws |
| Valid max age | Must be a non-negative integer | Throws |

### export()

```php
// :: VOID -> ARRAY
$policy = $cors->export();
```

Calls `validate()`, then returns the policy as a plain associative
array. The exported array can be passed to the `CTGEndpoint`
constructor with `'cors_validated' => true` to bypass validation.
This is the path for sharing a pre-validated policy across multiple
endpoints.

### resolve()

```php
// :: VOID -> VOID
$cors->resolve();
```

Applies the validated policy to the incoming request by sending CORS
headers via PHP's `header()` function.

Behavior:

1. Read origin from `$_SERVER['HTTP_ORIGIN']`.
2. If origin matches the allowed list (exact string comparison), send:
   ```php
   header("Access-Control-Allow-Origin: {$matchedOrigin}");
   header('Vary: Origin');
   ```
3. If wildcard, send:
   ```php
   header('Access-Control-Allow-Origin: *');
   ```
4. If credentials is enabled, send:
   ```php
   header('Access-Control-Allow-Credentials: true');
   ```
5. If exposed headers are configured, send:
   ```php
   header('Access-Control-Expose-Headers: ' . implode(', ', $exposedHeaders));
   ```
6. For OPTIONS requests (preflight), additionally send:
   ```php
   header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
   header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
   header('Access-Control-Max-Age: ' . $maxAge);
   ```

Origin matching is **exact string comparison**. No subdomain wildcards,
no regex, no partial matching. Port and protocol are part of the origin.

---

## CTGValidator

Two-phase validator with composable prep and check pipelines. Prep
coerces the value to the target type; check validates predicates on
the prepped value.

### Constructor & Factory

```php
// Empty validator
$v = new CTGValidator();
$v = CTGValidator::init();

// With initial prep and check
$v = CTGValidator::init([
    'prep' => fn($v) => trim($v),
    'check' => fn($v) => strlen($v) <= 255,
]);
```

If `prep` is provided, it is pushed onto the prep array. If `check` is
provided, it is pushed onto the check array.

### Composition

```php
// :: CALLABLE -> $this
$v->addPrep(fn($v) => strtolower(trim($v)));

// :: CALLABLE -> $this
$v->addCheck(fn($v) => strlen($v) >= 3);
```

`addPrep` pushes a prep function `fn(MIXED) -> MIXED` onto the prep
array. `addCheck` pushes a check function `fn(MIXED) -> BOOL` onto
the check array. Both return `$this` for chaining. Functions execute
in the order they were added.

### Execution

#### prep()

```php
// :: MIXED -> MIXED
$prepped = $v->prep($value);
```

Runs the value through all prep functions in order. Each receives the
output of the previous. Returns the value as-is if no prep functions
are stored. Throws `CTGValidationError` with code `PREP_FAILED` if
any prep function throws.

#### check()

```php
// :: MIXED -> BOOL
$valid = $v->check($value);
```

Runs the value through all check functions in order. All must return
`true`. Returns `true` if no check functions are stored. Does not
throw — returns a boolean.

#### run()

```php
// :: MIXED -> MIXED
$result = $v->run($value);
```

1. Call `prep($value)` to get the prepared value.
2. Call `check($preparedValue)`.
3. If check returns `false`, throw `CTGValidationError` with code
   `CHECK_FAILED`.
4. Return the prepared value.

### Basic Type Factories

All basic type factories accept an optional config array with `prep`
and/or `check` keys. If provided, the factory calls `addPrep` and/or
`addCheck` to chain the extras onto the base.

#### string

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::string();
$v = CTGValidator::string(['check' => fn($v) => strlen($v) <= 255]);
```

- Prep: none
- Check: `is_string($value) && $value !== ''`
- Purpose: non-empty string

#### string_empty

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::string_empty();
```

- Prep: none
- Check: `is_string($value)`
- Purpose: string where empty is acceptable

#### int

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::int();
$v = CTGValidator::int(['check' => fn($v) => $v > 0]);
```

- Prep: if `is_int($value)`, return as-is. If `is_string($value)` and
  matches `/^-?\d+$/`, coerce via `(int)`. Otherwise throw
  `PREP_FAILED`.
- Check: `is_int($value)`
- Purpose: integer with automatic string coercion for query parameters

#### float

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::float();
```

- Prep: if `is_float($value)`, return as-is. If `is_int($value)`,
  coerce to float via `(float)`. If `is_string($value)` and
  `is_numeric($value)`, coerce to float via `(float)`. Otherwise throw
  `PREP_FAILED`.
- Check: `is_float($value)`
- Purpose: float with automatic string and integer coercion. Always
  returns a float — `3` becomes `3.0`.

#### bool

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::bool();
```

- Prep: if `is_bool($value)`, return as-is. If `is_string($value)`
  and `strtolower($value) === 'true'`, return `true`. If
  `strtolower($value) === 'false'`, return `false`. Otherwise throw
  `PREP_FAILED`.
- Check: `is_bool($value)`
- Purpose: boolean with string coercion for query parameters

#### boolint

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::boolint();
```

- Prep: if `$value === 1 || $value === 0`, return as-is. If
  `$value === '1'`, return `1`. If `$value === '0'`, return `0`.
  Otherwise throw `PREP_FAILED`.
- Check: `$value === 1 || $value === 0`
- Purpose: integer boolean (1/0)

#### array

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::array();
```

- Prep: none
- Check: `is_array($value)`
- Purpose: array or list type

### Higher-Level Type Factories

Composite types built on the `string()` base. Each is a non-empty
string validator with an additional check composed via `addCheck`.

#### email

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::email();
```

- Built on: `string()`
- Additional check: `filter_var($value, FILTER_VALIDATE_EMAIL) !== false`

#### url

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::url();
```

- Built on: `string()`
- Additional check: `filter_var($value, FILTER_VALIDATE_URL) !== false`

#### uuid

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::uuid();
```

- Built on: `string()`
- Additional check:
  `preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1`

#### date

```php
// :: ARRAY -> ctgValidator
$v = CTGValidator::date();
```

- Built on: `string()`
- Additional check: `strtotime($value) !== false`

### Composition Pattern for Higher-Level Factories

```php
public static function email(array $config = []): static {
    $v = static::string();
    $v->addCheck(fn($val) => filter_var($val, FILTER_VALIDATE_EMAIL) !== false);
    if (isset($config['prep'])) {
        $v->addPrep($config['prep']);
    }
    if (isset($config['check'])) {
        $v->addCheck($config['check']);
    }
    return $v;
}
```

---

## CTGRequest

Read-only object constructed internally by the library after
validation succeeds (lifecycle step 7). Never constructed by
application code.

### Constructor (Internal)

```php
// Internal — not part of the public API
public function __construct(
    string $method,
    array  $params,
    array  $headers,
    ?string $token,
    ?array $claims
);
```

All properties are stored as `readonly`.

### Accessors

#### method()

```php
// :: VOID -> STRING
$method = $req->method();  // "GET", "POST", etc.
```

Returns the HTTP method as an uppercased string.

#### params()

```php
// :: ?STRING -> MIXED
$all = $req->params();           // full params map or null
$name = $req->params('name');    // single param value
```

Without key: returns the full associative array of validated
parameters (body and query merged). Returns `null` if no parameters
were declared for this method.

With key: returns the value for that parameter.

To distinguish absent from null, use `array_key_exists`:

```php
$params = $req->params();
if ($params !== null && array_key_exists('name', $params)) {
    // 'name' was sent and validated
}
```

This matters for PATCH semantics — an absent optional parameter
(no default) does not appear in the map at all.

#### headers()

```php
// :: ?STRING -> MIXED
$all = $req->headers();                     // all headers
$contentType = $req->headers('content-type'); // single header
```

Without key: returns all headers as a lowercase-keyed associative
array. With key: returns the value for that header.

PHP-specific: headers are extracted from `$_SERVER['HTTP_*']` entries,
with keys lowercased and underscores replaced with hyphens.

#### token()

```php
// :: VOID -> ?STRING
$token = $req->token();  // raw bearer token or null
```

Returns the raw bearer token string from the Authorization header,
or `null` if not present.

#### claims()

```php
// :: ?STRING -> MIXED
$all = $req->claims();          // full claims map or null
$sub = $req->claims('sub');     // single claim
```

Without key: returns the full decoded claims map (set after auth
verification), or `null` if auth was not performed.

With key: returns the value for that claim.

---

## CTGResponse

Immutable response object returned by handlers. The library calls
`send()` to emit output.

### json()

```php
// :: MIXED, INT, ARRAY -> ctgResponse
$response = CTGResponse::json(['id' => 1, 'name' => 'Alice']);
$response = CTGResponse::json(['id' => 1], 201);
$response = CTGResponse::json(['id' => 1], 201, ['Location' => '/users/1']);
```

Creates a JSON response. The data is wrapped in the success envelope:

```json
{ "success": true, "result": { "id": 1, "name": "Alice" } }
```

Custom headers are merged with the library's automatic headers
(`Content-Type`, CORS). Custom headers do not override automatic
headers.

### noContent()

```php
// :: ARRAY -> ctgResponse
$response = CTGResponse::noContent();
$response = CTGResponse::noContent(['X-Deleted-Id' => '42']);
```

Creates a 204 No Content response. No body, no envelope. This is the
only exception to the envelope rule.

### send()

```php
// :: VOID -> VOID
$response->send();
```

Emits the HTTP response:

```php
// For JSON responses:
http_response_code($this->_status);
header('Content-Type: application/json; charset=utf-8');
foreach ($this->_headers as $name => $value) {
    header("{$name}: {$value}");
}
echo json_encode(['success' => true, 'result' => $this->_data]);

// For 204 No Content:
http_response_code(204);
foreach ($this->_headers as $name => $value) {
    header("{$name}: {$value}");
}
// No body output
```

---

## CTGServerError

Typed error class for HTTP error responses. Extends `\Exception` so
it can be thrown by handlers and caught by the lifecycle.

### Constructor

```php
// CONSTRUCTOR :: STRING, STRING, INT, MIXED -> $this
$error = new CTGServerError('NOT_FOUND', 'User not found', 404);
$error = new CTGServerError('CONFLICT', 'Email already exists', 409, [
    'field' => 'email',
    'existing_id' => 42,
]);
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$type` | `string` | *required* | Error type code |
| `$message` | `string` | `''` | Human-readable message |
| `$httpStatus` | `int` | `500` | HTTP status code |
| `$details` | `mixed` | `null` | Structured error details |

The constructor looks up the type in the `TYPES` map to get the
integer exception code, then passes it to `parent::__construct()`.

### TYPES Map

```php
const TYPES = [
    'NOT_FOUND'            => 1000,
    'FORBIDDEN'            => 1001,
    'CONFLICT'             => 1002,
    'INVALID'              => 1003,
    'METHOD_NOT_ALLOWED'   => 1004,
    'PAYLOAD_TOO_LARGE'    => 1005,
    'INVALID_CONTENT_TYPE' => 1006,
    'INVALID_BODY'         => 1007,
    'INTERNAL_ERROR'       => 2000,
];
```

### send()

```php
// :: VOID -> VOID
$error->send();
```

Sends the error as an HTTP response:

```php
http_response_code($this->httpStatus);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'result' => $this->toResult($exposeDetails),
]);
```

The `$exposeDetails` flag comes from the endpoint's
`expose_error_details` config. The error must have access to this
setting — either passed at construction or set before `send()` is
called.

### toResult()

```php
// :: BOOL -> ARRAY
$result = $error->toResult();              // production
$result = $error->toResult(true);          // development
```

Returns the structured error data:

```php
// Default (exposeDetails = false):
['type' => 'NOT_FOUND', 'message' => 'User not found']

// With details exposed:
['type' => 'NOT_FOUND', 'message' => 'User not found', 'details' => [...]]
```

### Static Factories

```php
// :: ?STRING, MIXED -> ctgServerError
CTGServerError::notFound();                           // "Resource not found"
CTGServerError::notFound('User not found');
CTGServerError::notFound('User not found', ['id' => 42]);

CTGServerError::forbidden();                          // "Forbidden"
CTGServerError::forbidden('Admin access required');

CTGServerError::conflict();                           // "Conflict"
CTGServerError::conflict('Email already exists');

CTGServerError::invalid();                            // "Validation failed"
CTGServerError::invalid('Invalid date range');

CTGServerError::internal();                           // "Internal error"
CTGServerError::internal('Database connection failed');
```

| Factory | Type | HTTP Status | Default Message |
|---------|------|-------------|-----------------|
| `notFound` | `NOT_FOUND` | 404 | "Resource not found" |
| `forbidden` | `FORBIDDEN` | 403 | "Forbidden" |
| `conflict` | `CONFLICT` | 409 | "Conflict" |
| `invalid` | `INVALID` | 422 | "Validation failed" |
| `internal` | `INTERNAL_ERROR` | 500 | "Internal error" |

---

## CTGValidationError

Internal validation error thrown during the prep/check phases. Never
becomes an HTTP response directly — these are caught by the
validation step and collected as field-level errors.

### Constructor

```php
// CONSTRUCTOR :: STRING, STRING, MIXED -> $this
$error = new CTGValidationError('PREP_FAILED', 'Expected integer');
$error = new CTGValidationError('CHECK_FAILED', 'Must be positive', ['min' => 1]);
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$code` | `string` | *required* | `'PREP_FAILED'` or `'CHECK_FAILED'` |
| `$message` | `string` | `''` | Human-readable message |
| `$context` | `mixed` | `null` | Optional structured context |

The constructor looks up the code in the `TYPES` map to get the
integer exception code, then passes it to `parent::__construct()`.

### TYPES Map

```php
const TYPES = [
    'PREP_FAILED'  => 3000,
    'CHECK_FAILED' => 3001,
];
```

### Methods

```php
// :: VOID -> STRING
$code = $error->getErrorCode();    // "PREP_FAILED" or "CHECK_FAILED"

// :: VOID -> MIXED
$context = $error->getContext();   // structured context or null
```

---

## Response Envelopes

Every HTTP response follows a consistent envelope format. This
uniformity means clients can always check `success` first, then
inspect `result`.

### Success (200, 201, etc.)

```json
{
    "success": true,
    "result": { "id": 1, "name": "Alice" }
}
```

### No Content (204)

No body. This is the only exception to the envelope rule.

### Validation Failure (400)

```json
{
    "success": false,
    "result": {
        "name": "Required",
        "email": "Failed validation: email"
    }
}
```

All field errors are collected and returned together. The handler
never executes.

### Auth Failure (401)

```json
{
    "success": false,
    "result": "Authorization token required"
}
```

Note: the result is a string, not an object. This is intentional —
the auth failure has no field-level structure.

### Server Error (403, 404, 405, 409, 413, 422, 500)

```json
{
    "success": false,
    "result": {
        "type": "NOT_FOUND",
        "message": "User not found"
    }
}
```

With details exposed (development):

```json
{
    "success": false,
    "result": {
        "type": "NOT_FOUND",
        "message": "User not found",
        "details": { "id": 42 }
    }
}
```

### Untyped Error (Wrapped as 500)

```json
{
    "success": false,
    "result": {
        "type": "INTERNAL_ERROR",
        "message": "Internal error"
    }
}
```

The original error message is never exposed regardless of the
`expose_error_details` setting.

---

## Security Considerations

### CORS Origin Matching

Origin matching is exact string comparison. No subdomain wildcards
(`*.example.com`), no regex, no partial matching. The origin from
`$_SERVER['HTTP_ORIGIN']` must exactly match one of the strings in
the allowed origins list.

Port numbers are part of the origin:
`https://example.com:8443` does not match `https://example.com`.

Protocol is part of the origin:
`http://example.com` does not match `https://example.com`.

### Bearer Token Extraction

The Authorization header is parsed with case-insensitive prefix
matching (`Bearer `). Implementations must:

- Trim whitespace from the extracted token via `trim()`
- Reject tokens that are empty after trimming (respond 401)
- Not attempt to parse or validate the token format — that is the
  verifier's responsibility

### Error Detail Leakage

The `expose_error_details` config defaults to `false`. In production:

- Error responses include only `type` and `message`
- `details` is available server-side for logging
- Untyped errors are wrapped as `INTERNAL_ERROR` with generic message
  **regardless** of the `expose_error_details` setting

### Body Size Limits

The `max_body_size` config limits request body size in bytes. When
Content-Length is present, the check is instant. When absent, the body
is read incrementally from `php://input` and the read terminates as
soon as the accumulated size exceeds the limit.

### JSON Nesting Depth

`json_decode` is called with a depth limit of 64 to prevent stack
overflow or excessive memory consumption from deeply nested payloads.

### Query Parameter Array Injection

PHP auto-parses query parameters like `?a[]=1&a[]=2` into arrays in
`$_GET`. If a validator expects a string but receives an array from
`$_GET`, the prep phase will reject it (string prep on an array will
fail). Implementations should be aware of this PHP-specific behavior.

### Timing Attacks on Token Comparison

If the auth verifier compares tokens directly (API keys rather than
JWTs), it should use `hash_equals()` for constant-time comparison.
This is the verifier's responsibility, not the library's.

### Default Values Bypass Validation

Default values for optional parameters are never passed through the
validator. Developers must ensure defaults are type-correct.

### Production Deployment Requirements

The following are infrastructure-level requirements that sit outside
the library but must be present in any production deployment:

- **TLS termination:** All production traffic must be served over
  HTTPS. TLS termination may be at Apache/nginx or a load balancer.
  The library does not enforce this.
- **Rate limiting:** Request rate limiting must be enforced at the
  edge (reverse proxy, API gateway, or CDN). The library has no
  built-in rate limiting.
- **Auth failure throttling:** Failed authentication attempts should
  be rate-limited to prevent brute-force token guessing. This is
  typically handled by the auth verifier or infrastructure.
- **Request logging and audit:** All requests should be logged with
  method, path, status code, response time, and client identifier.
  Auth failures should be logged with additional context for security
  monitoring. Never log raw bearer tokens, API keys, or Authorization
  header values. Redact secrets and PII from request bodies before
  logging.
- **Error alerting:** 500-level errors should trigger operational
  alerts. The deployment must ensure full error details (including
  the `details` field) are logged server-side regardless of the
  `expose_error_details` setting. Logging is the host application's
  responsibility, not the library's.
- **`expose_error_details: false`:** Production endpoints must use
  the default `false` setting to prevent diagnostic data from reaching
  clients.
- **`max_body_size`:** Production endpoints should set a finite
  `max_body_size` value appropriate to the endpoint's purpose (e.g.,
  `1048576` for 1 MB). The default of `0` (no limit) is suitable for
  development but leaves a memory/DoS risk if deployed without
  configuration.

---

## File Structure

```
ctg-php-api-server/
├── composer.json
├── docs/
│   └── spec.md
├── src/
│   ├── CTGEndpoint.php
│   ├── CTGCorsPolicy.php
│   ├── CTGValidator.php
│   ├── CTGRequest.php
│   ├── CTGResponse.php
│   ├── CTGServerError.php
│   └── CTGValidationError.php
├── tests/
│   ├── CTGEndpointTest.php
│   ├── CTGCorsPolicyTest.php
│   ├── CTGValidatorTest.php
│   └── CTGServerErrorTest.php
└── staging/
```

---

## Implementation Order

Build in this order. Each step builds on the previous — no forward
dependencies.

1. **CTGValidationError** — standalone error class with `TYPES` map,
   `readonly` properties (`$errorCode`, `$context`), integer code
   passed to `parent::__construct()`. Two codes: `PREP_FAILED` (3000),
   `CHECK_FAILED` (3001).

2. **CTGServerError** — error class extending `\Exception` with
   `TYPES` map, `readonly` properties (`$type`, `$msg`, `$httpStatus`,
   `$details`), `send()` via `http_response_code()` + `header()` +
   `echo json_encode()`, `toResult()`, and static factories
   (`notFound`, `forbidden`, `conflict`, `invalid`, `internal`).

3. **CTGValidator** — constructor accepting optional `['prep' => ..., 'check' => ...]`.
   `init()` factory. `addPrep()`, `addCheck()`, `prep()`, `check()`,
   `run()`. Then basic type factories (`string`, `string_empty`, `int`,
   `float`, `bool`, `boolint`, `array`). Then higher-level factories
   (`email` with `filter_var`, `url` with `filter_var`, `uuid` with
   `preg_match`, `date` with `strtotime`). Ensure float factory always
   returns float — integer inputs are promoted via `(float)`.

4. **CTGCorsPolicy** — constructor, `init()`, builder methods
   (`origins`, `methods`, `headers`, `exposedHeaders`, `credentials`,
   `maxAge`), `validate()` with consistency rules, `export()` calling
   validate then returning array, `resolve()` reading
   `$_SERVER['HTTP_ORIGIN']` and sending headers via `header()`. Origin
   matching is exact string comparison.

5. **CTGResponse** — `json()` and `noContent()` static factories,
   `send()` using `http_response_code()`, `header()`, and
   `echo json_encode()`. 204 has no body.

6. **CTGRequest** — internal constructor accepting method, params,
   headers, token, claims. All `readonly` properties. Accessors:
   `method()`, `params()`, `headers()`, `token()`, `claims()`.
   `params()` returns `null` when no params declared; with key uses
   array lookup (absent keys are distinguishable from null via
   `array_key_exists`).

7. **CTGEndpoint** — constructor accepting config array, `init()`
   factory, config validation (CORS, max_body_size). `onAuth()`.
   HTTP method binding (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`,
   `HEAD`) with optional `['auth' => true]`. Parameter declaration
   methods (`requiredBodyParam`, `requiredQueryParam`, `bodyParam`,
   `queryParam`) with duplicate/collision detection at declaration
   time. Optional params without default omit key when absent.

8. **Run lifecycle** — the 8-step sequence in `CTGEndpoint::run()`:
   1. CORS resolve via `$_SERVER['HTTP_ORIGIN']`
   2. OPTIONS preflight via `$_SERVER['REQUEST_METHOD']`, respond 204
   3. Body size check via `$_SERVER['CONTENT_LENGTH']` or incremental
      `fread` from `php://input`
   4. Body parse via `json_decode` (depth 64) or `parse_str`, reading
      `$_SERVER['CONTENT_TYPE']`
   5. Method match via `strtoupper($_SERVER['REQUEST_METHOD'])`, 405
      with `Allow` header
   6. Auth gate via `$_SERVER['HTTP_AUTHORIZATION']` or
      `apache_request_headers()` fallback
   7. Validate — iterate declared params, collect errors, build params
      map from `$_GET` (query) and parsed body (body)
   8. Handler — execute, catch `CTGServerError` for typed errors, catch
      `\Throwable` and wrap as `INTERNAL_ERROR` for untyped errors

9. **Integration tests** — endpoint test scripts exercising the full
   lifecycle against the conformance test cases from the design doc.

---

## composer.json

```json
{
    "name": "ctg/php-api-server",
    "version": "1.0.0",
    "description": "RESTful API endpoint library with CORS, auth, and two-phase validation",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.1",
        "ext-json": "*"
    },
    "autoload": {
        "psr-4": {
            "CTG\\ApiServer\\": "src/"
        }
    }
}
```
