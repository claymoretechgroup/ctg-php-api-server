# ctg-php-api-server

`ctg-php-api-server` is a minimal PHP library for building RESTful API
endpoints. Each endpoint is a self-contained script — one file per URL —
with the web server handling routing via the filesystem. The library
provides CORS policy enforcement, pluggable bearer token authentication,
two-phase request validation with type coercion, and a uniform JSON
response envelope. No framework, no middleware stack, no router.

**Key Features:**

* **Filesystem routing** — one PHP script per endpoint, web server maps
  URLs to files
* **Fluent declaration** — bind HTTP methods, declare parameters, and
  call `run()` in a single chain
* **Two-phase validation** — prep coerces types, check validates
  predicates. Handlers always receive the target type
* **Per-method auth** — register a verifier once, opt in per HTTP method.
  Public and protected methods coexist on the same endpoint
* **CORS first** — CORS headers are sent before any other logic,
  including error responses
* **Uniform envelope** — every response is
  `{ "success": bool, "result": ... }`
* **Zero dependencies** — only PHP's standard library

## Install

Add the GitHub repository to your `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/claymoretechgroup/ctg-php-api-server" }
    ]
}
```

Then require the package:

```
composer require ctg/php-api-server
```

## Examples

### Basic Endpoint

Define a GET endpoint with a handler:

```php
use CTG\ApiServer\CTGEndpoint;
use CTG\ApiServer\CTGCorsPolicy;
use CTG\ApiServer\CTGRequest;
use CTG\ApiServer\CTGResponse;

CTGEndpoint::init([
    'cors' => CTGCorsPolicy::init()->origins('*'),
])
->GET(function (CTGRequest $req): CTGResponse {
    return CTGResponse::json(['status' => 'ok']);
})
->run();
```

### POST with Validation

Declare required and optional body parameters with type validators:

```php
use CTG\ApiServer\CTGValidator;

CTGEndpoint::init([
    'cors' => CTGCorsPolicy::init()->origins('*'),
])
->POST(function (CTGRequest $req): CTGResponse {
    $name = $req->params('name');
    $email = $req->params('email');
    $role = $req->params('role');
    $id = createUser($name, $email, $role);
    return CTGResponse::json(['id' => $id], 201);
})
->requiredBodyParam('name', CTGValidator::string())
->requiredBodyParam('email', CTGValidator::email())
->bodyParam('role', CTGValidator::string(), 'viewer')
->run();
```

### Mixed Auth

Public GET and protected POST on the same endpoint:

```php
CTGEndpoint::init([
    'cors' => CTGCorsPolicy::init()
        ->origins(['https://app.example.com'])
        ->methods(['GET', 'POST'])
        ->headers(['Content-Type', 'Authorization'])
        ->credentials(true),
])
->onAuth(function (string $token): array {
    return verifyJWT($token);
})

->GET(function (CTGRequest $req): CTGResponse {
    $page = $req->params('page');
    $users = fetchUsers($page);
    return CTGResponse::json($users);
})
->queryParam('page', CTGValidator::int(), 1)

->POST(function (CTGRequest $req): CTGResponse {
    $id = createUser($req->params('name'), $req->params('email'));
    return CTGResponse::json(['id' => $id], 201);
}, ['auth' => true])
->requiredBodyParam('name', CTGValidator::string())
->requiredBodyParam('email', CTGValidator::email())

->run();
```

### CORS Policy Configuration

Build and share a pre-validated CORS policy across endpoints:

```php
$cors = CTGCorsPolicy::init()
    ->origins(['https://app.example.com', 'https://admin.example.com'])
    ->methods(['GET', 'POST', 'PUT', 'DELETE'])
    ->headers(['Content-Type', 'Authorization'])
    ->exposedHeaders(['X-Request-Id'])
    ->credentials(true)
    ->maxAge(86400)
    ->export();

// In each endpoint file:
CTGEndpoint::init([
    'cors' => $cors,
    'cors_validated' => true,
]);
```

### Custom Validators with addCheck

Compose additional constraints onto type factories:

```php
CTGEndpoint::init(['cors' => $cors, 'cors_validated' => true])
->GET(function (CTGRequest $req): CTGResponse {
    $items = fetchItems($req->params('page'), $req->params('limit'));
    return CTGResponse::json($items);
})
->queryParam('page', CTGValidator::int()->addCheck(fn($v) => $v > 0), 1)
->queryParam('limit', CTGValidator::int()->addCheck(fn($v) => $v >= 1 && $v <= 100), 20)
->run();
```

### Error Handling in Handlers

Throw typed errors for domain-specific failure cases:

```php
use CTG\ApiServer\CTGServerError;

CTGEndpoint::init(['cors' => $cors, 'cors_validated' => true])
->DELETE(function (CTGRequest $req): CTGResponse {
    $user = findUser($req->params('id'));
    if (!$user) {
        throw CTGServerError::notFound('User not found');
    }
    if ($user['protected']) {
        throw CTGServerError::forbidden('Cannot delete protected user');
    }
    deleteUser($user['id']);
    return CTGResponse::noContent();
}, ['auth' => true])
->requiredQueryParam('id', CTGValidator::uuid())
->run();
```

## Class Documentation

* [CTGEndpoint](docs/CTGEndpoint.md) — endpoint builder and request lifecycle
* [CTGCorsPolicy](docs/CTGCorsPolicy.md) — CORS policy builder and resolver
* [CTGValidator](docs/CTGValidator.md) — two-phase validation with type coercion
* [CTGRequest](docs/CTGRequest.md) — read-only validated request object
* [CTGResponse](docs/CTGResponse.md) — JSON response envelope
* [CTGServerError](docs/CTGServerError.md) — typed server errors
* [CTGValidationError](docs/CTGValidationError.md) — validation pipeline errors

## Notice

`ctg-php-api-server` is under active development. The core API is stable.
