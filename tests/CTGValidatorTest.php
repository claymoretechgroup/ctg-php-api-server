<?php
declare(strict_types=1);


use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;
use CTG\ApiServer\CTGValidator;
use CTG\ApiServer\CTGValidationError;

$pipelines = [];

// Tests for CTGValidator — construction, composition, type factories, higher-level validators


// ═══════════════════════════════════════════════════════════════
// CONSTRUCTION AND COMPOSITION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('init — no config creates empty validator')
    ->stage('create', fn(CTGTestState $state) => CTGValidator::init())
    ->assert('is CTGValidator', fn(CTGTestState $state) => $state->getSubject() instanceof CTGValidator, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('init — with prep and check config')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::init([
        'prep' => fn($v) => $v * 2,
        'check' => fn($v) => $v < 100,
    ])->run(10))
    ->assert('prep doubles, check passes, returns 20', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(20))
    ;

$pipelines[] = CTGTest::init('addPrep — chains prep functions')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::init()
        ->addPrep(fn($v) => $v + 1)
        ->addPrep(fn($v) => $v * 2)
        ->prep(5))
    ->assert('5 + 1 = 6, 6 * 2 = 12', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(12))
    ;

$pipelines[] = CTGTest::init('addCheck — chains check functions')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::init()
        ->addCheck(fn($v) => $v > 0)
        ->addCheck(fn($v) => $v < 100)
        ->check(50))
    ->assert('50 passes both checks', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('check — returns false when any check fails')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::init()
        ->addCheck(fn($v) => $v > 0)
        ->addCheck(fn($v) => $v < 10)
        ->check(50))
    ->assert('50 fails second check', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('prep — returns value as-is with no prep functions')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::init()->prep('hello'))
    ->assert('passthrough', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('hello'))
    ;

$pipelines[] = CTGTest::init('check — returns true with no check functions')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::init()->check('anything'))
    ->assert('passes', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('run — preps then checks, returns prepped value')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::init([
        'prep' => fn($v) => $v * 3,
        'check' => fn($v) => $v > 0,
    ])->run(5))
    ->assert('returns 15', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(15))
    ;

$pipelines[] = CTGTest::init('run — throws PREP_FAILED when prep fails')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::init([
                'prep' => function($v) { throw new \RuntimeException('bad'); },
            ])->run('x');
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('PREP_FAILED'))
    ;

$pipelines[] = CTGTest::init('run — throws CHECK_FAILED when check fails')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::init([
                'check' => fn($v) => false,
            ])->run('x');
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// STRING VALIDATOR
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('string — valid string passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::string()->run("hello"))
    ->assert('returns hello', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals("hello"))
    ;

$pipelines[] = CTGTest::init('string — empty string throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::string()->run("");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

$pipelines[] = CTGTest::init('string — integer throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::string()->run(42);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// STRING_EMPTY VALIDATOR
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('string_empty — empty string passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::string_empty()->run(""))
    ->assert('returns empty string', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(""))
    ;

$pipelines[] = CTGTest::init('string_empty — non-empty string passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::string_empty()->run("hello"))
    ->assert('returns hello', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals("hello"))
    ;

$pipelines[] = CTGTest::init('string_empty — integer throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::string_empty()->run(42);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// INT VALIDATOR
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('int — integer passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::int()->run(42))
    ->assert('returns 42', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(42))
    ;

$pipelines[] = CTGTest::init('int — string "42" coerced to 42')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::int()->run("42"))
    ->assert('returns 42', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(42))
    ;

$pipelines[] = CTGTest::init('int — string "-7" coerced to -7')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::int()->run("-7"))
    ->assert('returns -7', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(-7))
    ;

$pipelines[] = CTGTest::init('int — result is actually an int')
    ->stage('execute', fn(CTGTestState $state) => is_int(CTGValidator::int()->run("42")))
    ->assert('is_int true', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('int — string "abc" throws PREP_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::int()->run("abc");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('PREP_FAILED'))
    ;

$pipelines[] = CTGTest::init('int — float 3.5 throws PREP_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::int()->run(3.5);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('PREP_FAILED'))
    ;

$pipelines[] = CTGTest::init('int — string "3.5" throws PREP_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::int()->run("3.5");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('PREP_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// FLOAT VALIDATOR
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('float — float passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::float()->run(3.14))
    ->assert('returns 3.14', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(3.14))
    ;

$pipelines[] = CTGTest::init('float — int promoted to float')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::float()->run(3))
    ->assert('returns 3.0', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(3.0))
    ;

$pipelines[] = CTGTest::init('float — int promoted result is_float')
    ->stage('execute', fn(CTGTestState $state) => is_float(CTGValidator::float()->run(3)))
    ->assert('is_float true', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('float — string "3.14" coerced to float')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::float()->run("3.14"))
    ->assert('returns 3.14', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(3.14))
    ;

$pipelines[] = CTGTest::init('float — string "3" coerced to float')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::float()->run("3"))
    ->assert('returns 3.0', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(3.0))
    ;

$pipelines[] = CTGTest::init('float — string "3" result is_float')
    ->stage('execute', fn(CTGTestState $state) => is_float(CTGValidator::float()->run("3")))
    ->assert('is_float true', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('float — string "abc" throws PREP_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::float()->run("abc");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('PREP_FAILED'))
    ;

$pipelines[] = CTGTest::init('float — result is always float type')
    ->stage('execute', fn(CTGTestState $state) => [
        is_float(CTGValidator::float()->run(3.14)),
        is_float(CTGValidator::float()->run(3)),
        is_float(CTGValidator::float()->run("3.14")),
        is_float(CTGValidator::float()->run("3")),
    ])
    ->assert('all are float', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals([true, true, true, true]))
    ;

// ═══════════════════════════════════════════════════════════════
// BOOL VALIDATOR
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('bool — true passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::bool()->run(true))
    ->assert('returns true', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('bool — false passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::bool()->run(false))
    ->assert('returns false', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('bool — string "true" coerced')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::bool()->run("true"))
    ->assert('returns true', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('bool — string "false" coerced')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::bool()->run("false"))
    ->assert('returns false', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('bool — string "TRUE" case-insensitive')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::bool()->run("TRUE"))
    ->assert('returns true', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('bool — string "FALSE" case-insensitive')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::bool()->run("FALSE"))
    ->assert('returns false', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('bool — string "yes" throws PREP_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::bool()->run("yes");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('PREP_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// BOOLINT VALIDATOR
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('boolint — 1 passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::boolint()->run(1))
    ->assert('returns 1', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(1))
    ;

$pipelines[] = CTGTest::init('boolint — 0 passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::boolint()->run(0))
    ->assert('returns 0', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(0))
    ;

$pipelines[] = CTGTest::init('boolint — string "1" coerced to 1')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::boolint()->run("1"))
    ->assert('returns 1', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(1))
    ;

$pipelines[] = CTGTest::init('boolint — string "0" coerced to 0')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::boolint()->run("0"))
    ->assert('returns 0', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(0))
    ;

$pipelines[] = CTGTest::init('boolint — string "2" throws PREP_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::boolint()->run("2");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('PREP_FAILED'))
    ;

$pipelines[] = CTGTest::init('boolint — boolean true throws PREP_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::boolint()->run(true);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('PREP_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// ARRAY VALIDATOR
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('array — array passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::array()->run([1, 2]))
    ->assert('returns array', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals([1, 2]))
    ;

$pipelines[] = CTGTest::init('array — empty array passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::array()->run([]))
    ->assert('returns empty array', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals([]))
    ;

$pipelines[] = CTGTest::init('array — string throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::array()->run("not array");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// HIGHER-LEVEL VALIDATORS
// ═══════════════════════════════════════════════════════════════

// ── email ──────────────────────────────────────────────────────

$pipelines[] = CTGTest::init('email — valid email passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::email()->run("user@example.com"))
    ->assert('returns email', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals("user@example.com"))
    ;

$pipelines[] = CTGTest::init('email — invalid email throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::email()->run("not-an-email");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

$pipelines[] = CTGTest::init('email — empty string throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::email()->run("");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED (empty fails string base)', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

// ── url ────────────────────────────────────────────────────────

$pipelines[] = CTGTest::init('url — valid URL passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::url()->run("https://example.com"))
    ->assert('returns URL', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals("https://example.com"))
    ;

$pipelines[] = CTGTest::init('url — invalid URL throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::url()->run("not a url");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

// ── uuid ───────────────────────────────────────────────────────

$pipelines[] = CTGTest::init('uuid — valid UUID passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::uuid()->run("550e8400-e29b-41d4-a716-446655440000"))
    ->assert('returns UUID', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals("550e8400-e29b-41d4-a716-446655440000"))
    ;

$pipelines[] = CTGTest::init('uuid — invalid UUID throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::uuid()->run("not-a-uuid");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

// ── date ───────────────────────────────────────────────────────

$pipelines[] = CTGTest::init('date — valid date passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::date()->run("2024-01-15"))
    ->assert('returns date string', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals("2024-01-15"))
    ;

$pipelines[] = CTGTest::init('date — invalid date throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::date()->run("not a date");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// COMPOSITION WITH addPrep/addCheck
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('int with addCheck — positive passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::int()->addCheck(fn($v) => $v > 0)->run("5"))
    ->assert('returns 5', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(5))
    ;

$pipelines[] = CTGTest::init('int with addCheck — negative throws CHECK_FAILED')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::int()->addCheck(fn($v) => $v > 0)->run("-3");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

$pipelines[] = CTGTest::init('string with addPrep — trim and lowercase')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::string()
        ->addPrep(fn($v) => strtolower(trim($v)))
        ->run("  HELLO  "))
    ->assert('returns hello', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals("hello"))
    ;

$pipelines[] = CTGTest::init('init with prep and check — passes')
    ->stage('execute', fn(CTGTestState $state) => CTGValidator::init([
        'prep' => fn($v) => $v * 2,
        'check' => fn($v) => $v < 100,
    ])->run(10))
    ->assert('returns 20', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(20))
    ;

$pipelines[] = CTGTest::init('init with prep and check — check fails')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGValidator::init([
                'prep' => fn($v) => $v * 2,
                'check' => fn($v) => $v < 100,
            ])->run(60);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED (60*2=120 > 100)', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

return $pipelines;
