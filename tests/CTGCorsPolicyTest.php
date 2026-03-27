<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\ApiServer\CTGCorsPolicy;

// Tests for CTGCorsPolicy — construction, building, validation, export
// Note: resolve() tests are deferred — they require HTTP context (header() calls)

$config = ['output' => 'console'];

// ═══════════════════════════════════════════════════════════════
// CONSTRUCTION AND BUILDING
// ═══════════════════════════════════════════════════════════════

CTGTest::init('init — creates empty policy')
    ->stage('create', fn($_) => CTGCorsPolicy::init())
    ->assert('is CTGCorsPolicy', fn($p) => $p instanceof CTGCorsPolicy, true)
    ->start(null, $config);

CTGTest::init('fluent chaining — returns self')
    ->stage('create', fn($_) => CTGCorsPolicy::init()
        ->origins('*')
        ->methods(['GET', 'POST'])
        ->headers(['Content-Type'])
        ->exposedHeaders(['X-Request-Id'])
        ->credentials(false)
        ->maxAge(3600))
    ->assert('is CTGCorsPolicy', fn($p) => $p instanceof CTGCorsPolicy, true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// VALIDATION RULES
// ═══════════════════════════════════════════════════════════════

CTGTest::init('validate — wildcard + credentials throws')
    ->stage('execute', function($_) {
        try {
            CTGCorsPolicy::init()
                ->origins('*')
                ->credentials(true)
                ->validate();
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('validate — wildcard without credentials passes')
    ->stage('execute', function($_) {
        try {
            CTGCorsPolicy::init()
                ->origins('*')
                ->methods(['GET'])
                ->validate();
            return 'passed';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('passes', fn($r) => $r, 'passed')
    ->start(null, $config);

CTGTest::init('validate — specific origins + credentials passes')
    ->stage('execute', function($_) {
        try {
            CTGCorsPolicy::init()
                ->origins(['https://a.com'])
                ->credentials(true)
                ->validate();
            return 'passed';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('passes', fn($r) => $r, 'passed')
    ->start(null, $config);

CTGTest::init('validate — empty origins throws')
    ->stage('execute', function($_) {
        try {
            CTGCorsPolicy::init()
                ->origins('')
                ->validate();
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('validate — empty array origins throws')
    ->stage('execute', function($_) {
        try {
            CTGCorsPolicy::init()
                ->origins([])
                ->validate();
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('validate — negative maxAge throws')
    ->stage('execute', function($_) {
        try {
            CTGCorsPolicy::init()
                ->origins('*')
                ->maxAge(-1)
                ->validate();
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('validate — zero maxAge passes')
    ->stage('execute', function($_) {
        try {
            CTGCorsPolicy::init()
                ->origins('*')
                ->maxAge(0)
                ->validate();
            return 'passed';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('passes', fn($r) => $r, 'passed')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// EXPORT
// ═══════════════════════════════════════════════════════════════

CTGTest::init('export — returns array with origins key')
    ->stage('execute', fn($_) => CTGCorsPolicy::init()
        ->origins('*')
        ->export())
    ->assert('is array', fn($r) => is_array($r), true)
    ->assert('has origins key', fn($r) => array_key_exists('origins', $r), true)
    ->start(null, $config);

CTGTest::init('export — wildcard origin value')
    ->stage('execute', fn($_) => CTGCorsPolicy::init()
        ->origins('*')
        ->export())
    ->assert('origins is *', fn($r) => $r['origins'], '*')
    ->start(null, $config);

CTGTest::init('export — array origins preserved')
    ->stage('execute', fn($_) => CTGCorsPolicy::init()
        ->origins(['https://a.com', 'https://b.com'])
        ->export())
    ->assert('origins list', fn($r) => $r['origins'], ['https://a.com', 'https://b.com'])
    ->start(null, $config);

CTGTest::init('export — includes methods')
    ->stage('execute', fn($_) => CTGCorsPolicy::init()
        ->origins('*')
        ->methods(['GET', 'POST'])
        ->export())
    ->assert('has methods', fn($r) => $r['methods'], ['GET', 'POST'])
    ->start(null, $config);

CTGTest::init('export — includes all configured fields')
    ->stage('execute', fn($_) => CTGCorsPolicy::init()
        ->origins(['https://a.com'])
        ->methods(['GET', 'POST'])
        ->headers(['Content-Type', 'Authorization'])
        ->exposedHeaders(['X-Request-Id'])
        ->credentials(true)
        ->maxAge(3600)
        ->export())
    ->assert('has origins', fn($r) => $r['origins'], ['https://a.com'])
    ->assert('has methods', fn($r) => $r['methods'], ['GET', 'POST'])
    ->assert('has headers', fn($r) => $r['headers'], ['Content-Type', 'Authorization'])
    ->assert('has exposedHeaders', fn($r) => $r['exposedHeaders'], ['X-Request-Id'])
    ->assert('has credentials', fn($r) => $r['credentials'], true)
    ->assert('has maxAge', fn($r) => $r['maxAge'], 3600)
    ->start(null, $config);

CTGTest::init('export — calls validate internally (wildcard + credentials throws)')
    ->stage('execute', function($_) {
        try {
            CTGCorsPolicy::init()
                ->origins('*')
                ->credentials(true)
                ->export();
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws', fn($r) => $r, 'threw')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// RESOLVE — DEFERRED
// ═══════════════════════════════════════════════════════════════

// Note: resolve() tests require HTTP context ($_SERVER, header()).
// These will be covered in integration tests.
