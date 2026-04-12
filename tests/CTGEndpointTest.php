<?php
declare(strict_types=1);


use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;
use CTG\ApiServer\CTGEndpoint;
use CTG\ApiServer\CTGCorsPolicy;
use CTG\ApiServer\CTGValidator;

$pipelines = [];

// Tests for CTGEndpoint — construction, method binding, parameter declaration, collision detection
// Note: run() lifecycle tests are deferred — they require HTTP context


// ═══════════════════════════════════════════════════════════════
// CONSTRUCTION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('init — with valid CORS config')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ]))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('init — with CTGCorsPolicy instance')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*'),
    ]))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('init — invalid CORS config throws at construction')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->credentials(true),
            ]);
            return 'no exception';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })
    ->assert('throws RuntimeException about wildcard+credentials', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::contains('wildcard origin cannot be used with credentials'))
    ;

$pipelines[] = CTGTest::init('init — cors_validated true skips validation')
    ->stage('execute', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ]))
    ->assert('returns endpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// METHOD BINDING
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('GET — returns self for chaining')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])->GET(fn($req) => null))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('POST — returns self for chaining')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])->POST(fn($req) => null))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('POST with auth config — returns self')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])->POST(fn($req) => null, ['auth' => true]))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('multiple methods chain')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->GET(fn($req) => null)
        ->POST(fn($req) => null, ['auth' => true])
        ->PUT(fn($req) => null)
        ->PATCH(fn($req) => null)
        ->DELETE(fn($req) => null)
        ->HEAD(fn($req) => null))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// PARAMETER DECLARATIONS
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('requiredBodyParam — returns self')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->POST(fn($req) => null)
        ->requiredBodyParam('name', CTGValidator::string()))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('bodyParam with default — returns self')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->POST(fn($req) => null)
        ->bodyParam('page', CTGValidator::int(), 1))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('requiredQueryParam — returns self')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->GET(fn($req) => null)
        ->requiredQueryParam('id', CTGValidator::int()))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('queryParam with default — returns self')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->GET(fn($req) => null)
        ->queryParam('sort', CTGValidator::string(), 'id'))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('mixed params chain on method')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->POST(fn($req) => null, ['auth' => true])
        ->requiredBodyParam('name', CTGValidator::string())
        ->requiredBodyParam('email', CTGValidator::email())
        ->bodyParam('role', CTGValidator::string(), 'viewer'))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// COLLISION DETECTION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('collision — cross-source body/query same name throws')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->export(),
                'cors_validated' => true,
            ])
                ->POST(fn($req) => null)
                ->requiredBodyParam('id', CTGValidator::int())
                ->requiredQueryParam('id', CTGValidator::int());
            return 'no exception';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })
    ->assert('throws RuntimeException about duplicate param', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::contains('duplicate parameter'))
    ;

$pipelines[] = CTGTest::init('collision — duplicate body param same name throws')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->export(),
                'cors_validated' => true,
            ])
                ->POST(fn($req) => null)
                ->requiredBodyParam('name', CTGValidator::string())
                ->bodyParam('name', CTGValidator::string_empty());
            return 'no exception';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })
    ->assert('throws RuntimeException about duplicate param', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::contains('duplicate parameter'))
    ;

$pipelines[] = CTGTest::init('collision — duplicate query param same name throws')
    ->stage('execute', function(CTGTestState $state) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->export(),
                'cors_validated' => true,
            ])
                ->GET(fn($req) => null)
                ->requiredQueryParam('sort', CTGValidator::string())
                ->queryParam('sort', CTGValidator::string(), 'id');
            return 'no exception';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })
    ->assert('throws RuntimeException about duplicate param', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::contains('duplicate parameter'))
    ;

$pipelines[] = CTGTest::init('no collision — same name on different methods is OK')
    ->stage('execute', fn(CTGTestState $state) => CTGEndpoint::init([
            'cors' => CTGCorsPolicy::init()->origins('*')->export(),
            'cors_validated' => true,
        ])
            ->GET(fn($req) => null)
            ->requiredQueryParam('id', CTGValidator::int())
            ->POST(fn($req) => null)
            ->requiredBodyParam('id', CTGValidator::int()))
    ->assert('returns endpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// AUTH BINDING
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('onAuth — returns self for chaining')
    ->stage('create', fn(CTGTestState $state) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])->onAuth(fn(string $token) => ['sub' => '123']))
    ->assert('is CTGEndpoint', fn(CTGTestState $state) => $state->getSubject() instanceof CTGEndpoint, CTGTestPredicates::isTrue())
    ;

// Auth misconfiguration (auth:true without onAuth) is tested in
// CTGEndpointLifecycleTest.php as a runtime behavior.

return $pipelines;
