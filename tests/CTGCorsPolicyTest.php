<?php
declare(strict_types=1);


use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;
use CTG\ApiServer\CTGCorsPolicy;

$pipelines = [];

// Tests for CTGCorsPolicy — construction, building, validation, export
// Note: resolve() tests are deferred — they require HTTP context (header() calls)


// ═══════════════════════════════════════════════════════════════
// CONSTRUCTION AND BUILDING
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('init — creates empty policy')
    ->stage('create', fn(CTGTestState $state) => CTGCorsPolicy::init())
    ->assert('is CTGCorsPolicy', fn(CTGTestState $state) => $state->getSubject() instanceof CTGCorsPolicy, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('fluent chaining — returns self')
    ->stage('create', fn(CTGTestState $state) => CTGCorsPolicy::init()
        ->origins('*')
        ->methods(['GET', 'POST'])
        ->headers(['Content-Type'])
        ->exposedHeaders(['X-Request-Id'])
        ->credentials(false)
        ->maxAge(3600))
    ->assert('is CTGCorsPolicy', fn(CTGTestState $state) => $state->getSubject() instanceof CTGCorsPolicy, CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// VALIDATION RULES
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('validate — wildcard + credentials throws')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGCorsPolicy::init()
                ->origins('*')
                ->credentials(true)
                ->validate();
            return 'no exception';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })
    ->assert('throws RuntimeException about wildcard+credentials', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::contains('wildcard origin cannot be used with credentials'))
    ;

$pipelines[] = CTGTest::init('validate — wildcard without credentials passes')
    ->stage('execute', fn(CTGTestState $state) => CTGCorsPolicy::init()
        ->origins('*')
        ->methods(['GET'])
        ->validate())
    ->assert('returns policy', fn(CTGTestState $state) => $state->getSubject() instanceof CTGCorsPolicy, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('validate — specific origins + credentials passes')
    ->stage('execute', fn(CTGTestState $state) => CTGCorsPolicy::init()
        ->origins(['https://a.com'])
        ->credentials(true)
        ->validate())
    ->assert('returns policy', fn(CTGTestState $state) => $state->getSubject() instanceof CTGCorsPolicy, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('validate — empty origins throws')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGCorsPolicy::init()
                ->origins('')
                ->validate();
            return 'no exception';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })
    ->assert('throws RuntimeException about empty origins', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::contains('origins must not be empty'))
    ;

$pipelines[] = CTGTest::init('validate — empty array origins throws')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGCorsPolicy::init()
                ->origins([])
                ->validate();
            return 'no exception';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })
    ->assert('throws RuntimeException about empty origins', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::contains('origins must not be empty'))
    ;

$pipelines[] = CTGTest::init('validate — negative maxAge throws')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGCorsPolicy::init()
                ->origins('*')
                ->maxAge(-1)
                ->validate();
            return 'no exception';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })
    ->assert('throws RuntimeException about negative maxAge', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::contains('maxAge must be non-negative'))
    ;

$pipelines[] = CTGTest::init('validate — zero maxAge passes')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('passes', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('passed'))
    ;

// ═══════════════════════════════════════════════════════════════
// EXPORT
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('export — returns array with origins key')
    ->stage('execute', fn(CTGTestState $state) => CTGCorsPolicy::init()
        ->origins('*')
        ->export())
    ->assert('is array', fn(CTGTestState $state) => is_array($state->getSubject()), CTGTestPredicates::isTrue())
    ->assert('has origins key', fn(CTGTestState $state) => array_key_exists('origins', $state->getSubject()), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('export — wildcard origin value')
    ->stage('execute', fn(CTGTestState $state) => CTGCorsPolicy::init()
        ->origins('*')
        ->export())
    ->assert('origins is *', fn(CTGTestState $state) => $state->getSubject()['origins'], CTGTestPredicates::equals('*'))
    ;

$pipelines[] = CTGTest::init('export — array origins preserved')
    ->stage('execute', fn(CTGTestState $state) => CTGCorsPolicy::init()
        ->origins(['https://a.com', 'https://b.com'])
        ->export())
    ->assert('origins list', fn(CTGTestState $state) => $state->getSubject()['origins'], CTGTestPredicates::equals(['https://a.com', 'https://b.com']))
    ;

$pipelines[] = CTGTest::init('export — includes methods')
    ->stage('execute', fn(CTGTestState $state) => CTGCorsPolicy::init()
        ->origins('*')
        ->methods(['GET', 'POST'])
        ->export())
    ->assert('has methods', fn(CTGTestState $state) => $state->getSubject()['methods'], CTGTestPredicates::equals(['GET', 'POST']))
    ;

$pipelines[] = CTGTest::init('export — includes all configured fields')
    ->stage('execute', fn(CTGTestState $state) => CTGCorsPolicy::init()
        ->origins(['https://a.com'])
        ->methods(['GET', 'POST'])
        ->headers(['Content-Type', 'Authorization'])
        ->exposedHeaders(['X-Request-Id'])
        ->credentials(true)
        ->maxAge(3600)
        ->export())
    ->assert('has origins', fn(CTGTestState $state) => $state->getSubject()['origins'], CTGTestPredicates::equals(['https://a.com']))
    ->assert('has methods', fn(CTGTestState $state) => $state->getSubject()['methods'], CTGTestPredicates::equals(['GET', 'POST']))
    ->assert('has headers', fn(CTGTestState $state) => $state->getSubject()['headers'], CTGTestPredicates::equals(['Content-Type', 'Authorization']))
    ->assert('has exposedHeaders', fn(CTGTestState $state) => $state->getSubject()['exposedHeaders'], CTGTestPredicates::equals(['X-Request-Id']))
    ->assert('has credentials', fn(CTGTestState $state) => $state->getSubject()['credentials'], CTGTestPredicates::isTrue())
    ->assert('has maxAge', fn(CTGTestState $state) => $state->getSubject()['maxAge'], CTGTestPredicates::equals(3600))
    ;

$pipelines[] = CTGTest::init('export — calls validate internally (wildcard + credentials throws)')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('throws', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('threw'))
    ;

// ═══════════════════════════════════════════════════════════════
// RESOLVE — DEFERRED
// ═══════════════════════════════════════════════════════════════

// Note: resolve() tests require HTTP context ($_SERVER, header()).
// These will be covered in integration tests.

return $pipelines;
