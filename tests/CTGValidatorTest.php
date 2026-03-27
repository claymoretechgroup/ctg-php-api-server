<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\ApiServer\CTGValidator;
use CTG\ApiServer\CTGValidationError;

// Tests for CTGValidator — construction, composition, type factories, higher-level validators

$config = ['output' => 'console'];

// ═══════════════════════════════════════════════════════════════
// CONSTRUCTION AND COMPOSITION
// ═══════════════════════════════════════════════════════════════

CTGTest::init('init — no config creates empty validator')
    ->stage('create', fn($_) => CTGValidator::init())
    ->assert('is CTGValidator', fn($v) => $v instanceof CTGValidator, true)
    ->start(null, $config);

CTGTest::init('init — with prep and check config')
    ->stage('execute', fn($_) => CTGValidator::init([
        'prep' => fn($v) => $v * 2,
        'check' => fn($v) => $v < 100,
    ])->run(10))
    ->assert('prep doubles, check passes, returns 20', fn($r) => $r, 20)
    ->start(null, $config);

CTGTest::init('addPrep — chains prep functions')
    ->stage('execute', fn($_) => CTGValidator::init()
        ->addPrep(fn($v) => $v + 1)
        ->addPrep(fn($v) => $v * 2)
        ->prep(5))
    ->assert('5 + 1 = 6, 6 * 2 = 12', fn($r) => $r, 12)
    ->start(null, $config);

CTGTest::init('addCheck — chains check functions')
    ->stage('execute', fn($_) => CTGValidator::init()
        ->addCheck(fn($v) => $v > 0)
        ->addCheck(fn($v) => $v < 100)
        ->check(50))
    ->assert('50 passes both checks', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('check — returns false when any check fails')
    ->stage('execute', fn($_) => CTGValidator::init()
        ->addCheck(fn($v) => $v > 0)
        ->addCheck(fn($v) => $v < 10)
        ->check(50))
    ->assert('50 fails second check', fn($r) => $r, false)
    ->start(null, $config);

CTGTest::init('prep — returns value as-is with no prep functions')
    ->stage('execute', fn($_) => CTGValidator::init()->prep('hello'))
    ->assert('passthrough', fn($r) => $r, 'hello')
    ->start(null, $config);

CTGTest::init('check — returns true with no check functions')
    ->stage('execute', fn($_) => CTGValidator::init()->check('anything'))
    ->assert('passes', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('run — preps then checks, returns prepped value')
    ->stage('execute', fn($_) => CTGValidator::init([
        'prep' => fn($v) => $v * 3,
        'check' => fn($v) => $v > 0,
    ])->run(5))
    ->assert('returns 15', fn($r) => $r, 15)
    ->start(null, $config);

CTGTest::init('run — throws PREP_FAILED when prep fails')
    ->stage('execute', function($_) {
        try {
            CTGValidator::init([
                'prep' => function($v) { throw new \RuntimeException('bad'); },
            ])->run('x');
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn($r) => $r, 'PREP_FAILED')
    ->start(null, $config);

CTGTest::init('run — throws CHECK_FAILED when check fails')
    ->stage('execute', function($_) {
        try {
            CTGValidator::init([
                'check' => fn($v) => false,
            ])->run('x');
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STRING VALIDATOR
// ═══════════════════════════════════════════════════════════════

CTGTest::init('string — valid string passes')
    ->stage('execute', fn($_) => CTGValidator::string()->run("hello"))
    ->assert('returns hello', fn($r) => $r, "hello")
    ->start(null, $config);

CTGTest::init('string — empty string throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::string()->run("");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

CTGTest::init('string — integer throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::string()->run(42);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STRING_EMPTY VALIDATOR
// ═══════════════════════════════════════════════════════════════

CTGTest::init('string_empty — empty string passes')
    ->stage('execute', fn($_) => CTGValidator::string_empty()->run(""))
    ->assert('returns empty string', fn($r) => $r, "")
    ->start(null, $config);

CTGTest::init('string_empty — non-empty string passes')
    ->stage('execute', fn($_) => CTGValidator::string_empty()->run("hello"))
    ->assert('returns hello', fn($r) => $r, "hello")
    ->start(null, $config);

CTGTest::init('string_empty — integer throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::string_empty()->run(42);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// INT VALIDATOR
// ═══════════════════════════════════════════════════════════════

CTGTest::init('int — integer passes')
    ->stage('execute', fn($_) => CTGValidator::int()->run(42))
    ->assert('returns 42', fn($r) => $r, 42)
    ->start(null, $config);

CTGTest::init('int — string "42" coerced to 42')
    ->stage('execute', fn($_) => CTGValidator::int()->run("42"))
    ->assert('returns 42', fn($r) => $r, 42)
    ->start(null, $config);

CTGTest::init('int — string "-7" coerced to -7')
    ->stage('execute', fn($_) => CTGValidator::int()->run("-7"))
    ->assert('returns -7', fn($r) => $r, -7)
    ->start(null, $config);

CTGTest::init('int — result is actually an int')
    ->stage('execute', fn($_) => is_int(CTGValidator::int()->run("42")))
    ->assert('is_int true', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('int — string "abc" throws PREP_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::int()->run("abc");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn($r) => $r, 'PREP_FAILED')
    ->start(null, $config);

CTGTest::init('int — float 3.5 throws PREP_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::int()->run(3.5);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn($r) => $r, 'PREP_FAILED')
    ->start(null, $config);

CTGTest::init('int — string "3.5" throws PREP_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::int()->run("3.5");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn($r) => $r, 'PREP_FAILED')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// FLOAT VALIDATOR
// ═══════════════════════════════════════════════════════════════

CTGTest::init('float — float passes')
    ->stage('execute', fn($_) => CTGValidator::float()->run(3.14))
    ->assert('returns 3.14', fn($r) => $r, 3.14)
    ->start(null, $config);

CTGTest::init('float — int promoted to float')
    ->stage('execute', fn($_) => CTGValidator::float()->run(3))
    ->assert('returns 3.0', fn($r) => $r, 3.0)
    ->start(null, $config);

CTGTest::init('float — int promoted result is_float')
    ->stage('execute', fn($_) => is_float(CTGValidator::float()->run(3)))
    ->assert('is_float true', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('float — string "3.14" coerced to float')
    ->stage('execute', fn($_) => CTGValidator::float()->run("3.14"))
    ->assert('returns 3.14', fn($r) => $r, 3.14)
    ->start(null, $config);

CTGTest::init('float — string "3" coerced to float')
    ->stage('execute', fn($_) => CTGValidator::float()->run("3"))
    ->assert('returns 3.0', fn($r) => $r, 3.0)
    ->start(null, $config);

CTGTest::init('float — string "3" result is_float')
    ->stage('execute', fn($_) => is_float(CTGValidator::float()->run("3")))
    ->assert('is_float true', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('float — string "abc" throws PREP_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::float()->run("abc");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn($r) => $r, 'PREP_FAILED')
    ->start(null, $config);

CTGTest::init('float — result is always float type')
    ->stage('execute', fn($_) => [
        is_float(CTGValidator::float()->run(3.14)),
        is_float(CTGValidator::float()->run(3)),
        is_float(CTGValidator::float()->run("3.14")),
        is_float(CTGValidator::float()->run("3")),
    ])
    ->assert('all are float', fn($r) => $r, [true, true, true, true])
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// BOOL VALIDATOR
// ═══════════════════════════════════════════════════════════════

CTGTest::init('bool — true passes')
    ->stage('execute', fn($_) => CTGValidator::bool()->run(true))
    ->assert('returns true', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('bool — false passes')
    ->stage('execute', fn($_) => CTGValidator::bool()->run(false))
    ->assert('returns false', fn($r) => $r, false)
    ->start(null, $config);

CTGTest::init('bool — string "true" coerced')
    ->stage('execute', fn($_) => CTGValidator::bool()->run("true"))
    ->assert('returns true', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('bool — string "false" coerced')
    ->stage('execute', fn($_) => CTGValidator::bool()->run("false"))
    ->assert('returns false', fn($r) => $r, false)
    ->start(null, $config);

CTGTest::init('bool — string "TRUE" case-insensitive')
    ->stage('execute', fn($_) => CTGValidator::bool()->run("TRUE"))
    ->assert('returns true', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('bool — string "FALSE" case-insensitive')
    ->stage('execute', fn($_) => CTGValidator::bool()->run("FALSE"))
    ->assert('returns false', fn($r) => $r, false)
    ->start(null, $config);

CTGTest::init('bool — string "yes" throws PREP_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::bool()->run("yes");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn($r) => $r, 'PREP_FAILED')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// BOOLINT VALIDATOR
// ═══════════════════════════════════════════════════════════════

CTGTest::init('boolint — 1 passes')
    ->stage('execute', fn($_) => CTGValidator::boolint()->run(1))
    ->assert('returns 1', fn($r) => $r, 1)
    ->start(null, $config);

CTGTest::init('boolint — 0 passes')
    ->stage('execute', fn($_) => CTGValidator::boolint()->run(0))
    ->assert('returns 0', fn($r) => $r, 0)
    ->start(null, $config);

CTGTest::init('boolint — string "1" coerced to 1')
    ->stage('execute', fn($_) => CTGValidator::boolint()->run("1"))
    ->assert('returns 1', fn($r) => $r, 1)
    ->start(null, $config);

CTGTest::init('boolint — string "0" coerced to 0')
    ->stage('execute', fn($_) => CTGValidator::boolint()->run("0"))
    ->assert('returns 0', fn($r) => $r, 0)
    ->start(null, $config);

CTGTest::init('boolint — string "2" throws PREP_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::boolint()->run("2");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn($r) => $r, 'PREP_FAILED')
    ->start(null, $config);

CTGTest::init('boolint — boolean true throws PREP_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::boolint()->run(true);
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws PREP_FAILED', fn($r) => $r, 'PREP_FAILED')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// ARRAY VALIDATOR
// ═══════════════════════════════════════════════════════════════

CTGTest::init('array — array passes')
    ->stage('execute', fn($_) => CTGValidator::array()->run([1, 2]))
    ->assert('returns array', fn($r) => $r, [1, 2])
    ->start(null, $config);

CTGTest::init('array — empty array passes')
    ->stage('execute', fn($_) => CTGValidator::array()->run([]))
    ->assert('returns empty array', fn($r) => $r, [])
    ->start(null, $config);

CTGTest::init('array — string throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::array()->run("not array");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// HIGHER-LEVEL VALIDATORS
// ═══════════════════════════════════════════════════════════════

// ── email ──────────────────────────────────────────────────────

CTGTest::init('email — valid email passes')
    ->stage('execute', fn($_) => CTGValidator::email()->run("user@example.com"))
    ->assert('returns email', fn($r) => $r, "user@example.com")
    ->start(null, $config);

CTGTest::init('email — invalid email throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::email()->run("not-an-email");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

CTGTest::init('email — empty string throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::email()->run("");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED (empty fails string base)', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

// ── url ────────────────────────────────────────────────────────

CTGTest::init('url — valid URL passes')
    ->stage('execute', fn($_) => CTGValidator::url()->run("https://example.com"))
    ->assert('returns URL', fn($r) => $r, "https://example.com")
    ->start(null, $config);

CTGTest::init('url — invalid URL throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::url()->run("not a url");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

// ── uuid ───────────────────────────────────────────────────────

CTGTest::init('uuid — valid UUID passes')
    ->stage('execute', fn($_) => CTGValidator::uuid()->run("550e8400-e29b-41d4-a716-446655440000"))
    ->assert('returns UUID', fn($r) => $r, "550e8400-e29b-41d4-a716-446655440000")
    ->start(null, $config);

CTGTest::init('uuid — invalid UUID throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::uuid()->run("not-a-uuid");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

// ── date ───────────────────────────────────────────────────────

CTGTest::init('date — valid date passes')
    ->stage('execute', fn($_) => CTGValidator::date()->run("2024-01-15"))
    ->assert('returns date string', fn($r) => $r, "2024-01-15")
    ->start(null, $config);

CTGTest::init('date — invalid date throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::date()->run("not a date");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// COMPOSITION WITH addPrep/addCheck
// ═══════════════════════════════════════════════════════════════

CTGTest::init('int with addCheck — positive passes')
    ->stage('execute', fn($_) => CTGValidator::int()->addCheck(fn($v) => $v > 0)->run("5"))
    ->assert('returns 5', fn($r) => $r, 5)
    ->start(null, $config);

CTGTest::init('int with addCheck — negative throws CHECK_FAILED')
    ->stage('execute', function($_) {
        try {
            CTGValidator::int()->addCheck(fn($v) => $v > 0)->run("-3");
            return 'no exception';
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('throws CHECK_FAILED', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

CTGTest::init('string with addPrep — trim and lowercase')
    ->stage('execute', fn($_) => CTGValidator::string()
        ->addPrep(fn($v) => strtolower(trim($v)))
        ->run("  HELLO  "))
    ->assert('returns hello', fn($r) => $r, "hello")
    ->start(null, $config);

CTGTest::init('init with prep and check — passes')
    ->stage('execute', fn($_) => CTGValidator::init([
        'prep' => fn($v) => $v * 2,
        'check' => fn($v) => $v < 100,
    ])->run(10))
    ->assert('returns 20', fn($r) => $r, 20)
    ->start(null, $config);

CTGTest::init('init with prep and check — check fails')
    ->stage('execute', function($_) {
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
    ->assert('throws CHECK_FAILED (60*2=120 > 100)', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);
