# CTGValidator

Two-phase validator with composable prep and check pipelines. Prep
coerces the value to the target type; check validates predicates on
the prepped value. Type factories provide common validators with
automatic string coercion for query parameters.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _preps | [CALLABLE] | Ordered list of prep functions for type coercion |
| _checks | [CALLABLE] | Ordered list of check functions for predicate validation |

---

## Construction

### CONSTRUCTOR :: ARRAY -> ctgValidator

Creates a new validator. If the config contains `prep`, it is pushed
onto the prep pipeline. If it contains `check`, it is pushed onto the
check pipeline.

```php
$v = new CTGValidator([
    'prep' => fn($v) => trim($v),
    'check' => fn($v) => strlen($v) <= 255,
]);
```

### CTGValidator.init :: ARRAY -> ctgValidator

Static factory method.

```php
$v = CTGValidator::init([
    'prep' => fn($v) => strtolower(trim($v)),
]);
```

---

## Instance Methods

### ctgValidator.addPrep :: CALLABLE -> SELF

Pushes a prep function onto the pipeline. The function receives the
current value and returns the transformed value. Functions execute in
the order they were added. Chainable.

```php
$v = CTGValidator::string()
    ->addPrep(fn($v) => strtolower(trim($v)));
```

### ctgValidator.addCheck :: CALLABLE -> SELF

Pushes a check function onto the pipeline. The function receives the
prepped value and must return a boolean. All checks must return true
for validation to pass. Chainable.

```php
$v = CTGValidator::int()
    ->addCheck(fn($v) => $v > 0)
    ->addCheck(fn($v) => $v <= 1000);
```

### ctgValidator.prep :: MIXED -> MIXED

Runs the value through all prep functions in order. Each receives the
output of the previous. Returns the value as-is if no prep functions
are stored. Throws `CTGValidationError` with code `PREP_FAILED` if
any prep function throws.

```php
$prepped = $v->prep('  Hello  ');
// "hello" (if prep trims and lowercases)
```

### ctgValidator.check :: MIXED -> BOOL

Runs the value through all check functions in order. All must return
true. Returns true if no check functions are stored. Does not throw.

```php
$valid = $v->check('hello');
// true
```

### ctgValidator.run :: MIXED -> MIXED

Calls `prep()` then `check()`. If check returns false, throws
`CTGValidationError` with code `CHECK_FAILED`. Returns the prepped
value on success.

```php
$result = CTGValidator::int()->run('42');
// 42 (integer)
```

---

## Type Factories

All type factories accept an optional config array with `prep` and/or
`check` keys to compose additional functions onto the base validator.

### CTGValidator.string :: ARRAY -> ctgValidator

Non-empty string validator. No prep. Check: `is_string` and not empty.

```php
$v = CTGValidator::string();
$v = CTGValidator::string(['check' => fn($v) => strlen($v) <= 255]);
```

### CTGValidator.string_empty :: ARRAY -> ctgValidator

String validator that allows empty strings. No prep. Check: `is_string`.

```php
$v = CTGValidator::string_empty();
```

### CTGValidator.int :: ARRAY -> ctgValidator

Integer validator with automatic string coercion. Prep: coerces
numeric strings matching `/^-?\d+$/` to int. Check: `is_int`.

```php
$v = CTGValidator::int();
$v = CTGValidator::int(['check' => fn($v) => $v > 0]);
```

### CTGValidator.float :: ARRAY -> ctgValidator

Float validator with automatic string and integer coercion. Prep:
coerces ints and numeric strings to float. Check: `is_float`.

```php
$v = CTGValidator::float();
```

### CTGValidator.bool :: ARRAY -> ctgValidator

Boolean validator with string coercion. Prep: coerces `"true"` and
`"false"` (case-insensitive) to boolean. Check: `is_bool`.

```php
$v = CTGValidator::bool();
```

### CTGValidator.boolint :: ARRAY -> ctgValidator

Integer boolean (1/0) validator. Prep: coerces `"1"` and `"0"` strings
to integers. Check: value is `1` or `0`.

```php
$v = CTGValidator::boolint();
```

### CTGValidator.array :: ARRAY -> ctgValidator

Array validator. No prep. Check: `is_array`.

```php
$v = CTGValidator::array();
```

### CTGValidator.email :: ARRAY -> ctgValidator

Email validator built on `string()`. Additional check:
`filter_var($value, FILTER_VALIDATE_EMAIL)`.

```php
$v = CTGValidator::email();
```

### CTGValidator.url :: ARRAY -> ctgValidator

URL validator built on `string()`. Additional check:
`filter_var($value, FILTER_VALIDATE_URL)`.

```php
$v = CTGValidator::url();
```

### CTGValidator.uuid :: ARRAY -> ctgValidator

UUID validator built on `string()`. Additional check: matches the
standard UUID v4 pattern (case-insensitive).

```php
$v = CTGValidator::uuid();
```

### CTGValidator.date :: ARRAY -> ctgValidator

Date string validator built on `string()`. Additional check:
`strtotime($value) !== false`.

```php
$v = CTGValidator::date();
```

---

## Composition Examples

Compose constraints onto type factories for endpoint-specific
validation:

```php
// Positive integer with upper bound
$pageValidator = CTGValidator::int()
    ->addCheck(fn($v) => $v > 0)
    ->addCheck(fn($v) => $v <= 500);

// Trimmed, lowercased string with length constraint
$usernameValidator = CTGValidator::string()
    ->addPrep(fn($v) => strtolower(trim($v)))
    ->addCheck(fn($v) => strlen($v) >= 3 && strlen($v) <= 32);

// Use with config shorthand
$shortName = CTGValidator::string([
    'prep' => fn($v) => trim($v),
    'check' => fn($v) => strlen($v) <= 100,
]);
```
