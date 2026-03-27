# CTGValidationError

Validation pipeline error extending `\Exception`. Thrown by
`CTGValidator` when a prep function fails or a check predicate returns
false. Caught by `CTGEndpoint` during the validation step to collect
per-field error messages.

### Error Codes

| Code | Type | Description |
|------|------|-------------|
| 3000 | PREP_FAILED | A prep function threw during type coercion |
| 3001 | CHECK_FAILED | A check predicate returned false after successful prep |

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _errorCode | STRING | Error type string from the TYPES map (readonly) |
| _context | MIXED | Additional context data, null by default (readonly) |

---

## Construction

### CONSTRUCTOR :: STRING, ?STRING, ?MIXED -> ctgValidationError

Creates a new validation error. The code string is looked up in the
TYPES map to set the integer exception code. The message is passed to
the parent `\Exception`.

```php
throw new CTGValidationError('PREP_FAILED', 'Expected integer');
```

---

## Instance Methods

### ctgValidationError.getErrorCode :: VOID -> STRING

Returns the error type string (`PREP_FAILED` or `CHECK_FAILED`).

```php
$code = $error->getErrorCode();
// "PREP_FAILED"
```

### ctgValidationError.getContext :: VOID -> MIXED

Returns the additional context data, or null if none was provided.

```php
$context = $error->getContext();
```
