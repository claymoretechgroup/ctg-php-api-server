# CTGServerError

Typed server error extending `\Exception`. Used by handlers to signal
domain-specific failures with structured error data. The library catches
these during the handler step and sends them as JSON responses in the
uniform envelope: `{ "success": false, "result": { "type": ..., "message": ... } }`.

### Error Codes

| Code | Type | HTTP Status | Description |
|------|------|-------------|-------------|
| 1000 | NOT_FOUND | 404 | Resource not found |
| 1001 | FORBIDDEN | 403 | Access denied |
| 1002 | CONFLICT | 409 | Resource conflict |
| 1003 | INVALID | 422 | Validation failed (domain-level) |
| 1004 | METHOD_NOT_ALLOWED | 405 | HTTP method not supported |
| 1005 | PAYLOAD_TOO_LARGE | 413 | Request body exceeds size limit |
| 1006 | INVALID_CONTENT_TYPE | 400 | Unsupported content type |
| 1007 | INVALID_BODY | 400 | Malformed request body |
| 2000 | INTERNAL_ERROR | 500 | Internal server error |

### Properties

| Property | Type | Description |
|----------|------|-------------|
| type | STRING | Error type string from the TYPES map (readonly) |
| msg | STRING | Human-readable error message (readonly) |
| httpStatus | INT | HTTP status code for the response (readonly) |
| details | MIXED | Additional error details, null by default (readonly) |

---

## Construction

### CONSTRUCTOR :: STRING, ?STRING, ?INT, ?MIXED -> ctgServerError

Creates a new server error. The type string is looked up in the TYPES
map to set the integer exception code. Defaults to status 500 if not
specified.

```php
throw new CTGServerError('NOT_FOUND', 'User not found', 404);
```

---

## Instance Methods

### ctgServerError.send :: VOID -> VOID

Sends the error as a JSON response directly via PHP's output functions.
Sets status code, content type, and echoes the JSON envelope.

```php
$error = CTGServerError::notFound('User not found');
$error->send();
```

### ctgServerError.toResult :: ?BOOL -> ARRAY

Returns the error as an associative array with `type` and `message`.
If `exposeDetails` is true, includes the `details` field.

```php
$result = $error->toResult(true);
// ['type' => 'NOT_FOUND', 'message' => 'User not found', 'details' => null]
```

### ctgServerError.getHttpStatus :: VOID -> INT

Returns the HTTP status code for this error.

```php
$status = $error->getHttpStatus();
// 404
```

---

## Static Factories

### CTGServerError.notFound :: ?STRING, ?MIXED -> ctgServerError

Creates a 404 NOT_FOUND error. Default message: "Resource not found".

```php
throw CTGServerError::notFound('User not found');
```

### CTGServerError.forbidden :: ?STRING, ?MIXED -> ctgServerError

Creates a 403 FORBIDDEN error. Default message: "Forbidden".

```php
throw CTGServerError::forbidden('Admin access required');
```

### CTGServerError.conflict :: ?STRING, ?MIXED -> ctgServerError

Creates a 409 CONFLICT error. Default message: "Conflict".

```php
throw CTGServerError::conflict('Email already registered');
```

### CTGServerError.invalid :: ?STRING, ?MIXED -> ctgServerError

Creates a 422 INVALID error. Default message: "Validation failed".

```php
throw CTGServerError::invalid('Age must be positive', ['field' => 'age']);
```

### CTGServerError.internal :: ?STRING, ?MIXED -> ctgServerError

Creates a 500 INTERNAL_ERROR. Default message: "Internal error". Used
by the library to wrap unexpected `\Throwable` exceptions from handlers.

```php
throw CTGServerError::internal();
```
