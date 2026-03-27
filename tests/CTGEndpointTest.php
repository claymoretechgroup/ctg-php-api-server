<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\ApiServer\CTGEndpoint;
use CTG\ApiServer\CTGCorsPolicy;
use CTG\ApiServer\CTGValidator;

// Tests for CTGEndpoint — construction, method binding, parameter declaration, collision detection
// Note: run() lifecycle tests are deferred — they require HTTP context

$config = ['output' => 'console'];

// ═══════════════════════════════════════════════════════════════
// CONSTRUCTION
// ═══════════════════════════════════════════════════════════════

CTGTest::init('init — with valid CORS config')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ]))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('init — with CTGCorsPolicy instance')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*'),
    ]))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('init — invalid CORS config throws at construction')
    ->stage('execute', function($_) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->credentials(true),
            ]);
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('init — cors_validated true skips validation')
    ->stage('execute', function($_) {
        try {
            // Pass a plain array with cors_validated — should not validate
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->export(),
                'cors_validated' => true,
            ]);
            return 'passed';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('passes', fn($r) => $r, 'passed')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// METHOD BINDING
// ═══════════════════════════════════════════════════════════════

CTGTest::init('GET — returns self for chaining')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])->GET(fn($req) => null))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('POST — returns self for chaining')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])->POST(fn($req) => null))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('POST with auth config — returns self')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])->POST(fn($req) => null, ['auth' => true]))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('multiple methods chain')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->GET(fn($req) => null)
        ->POST(fn($req) => null, ['auth' => true])
        ->PUT(fn($req) => null)
        ->PATCH(fn($req) => null)
        ->DELETE(fn($req) => null)
        ->HEAD(fn($req) => null))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// PARAMETER DECLARATIONS
// ═══════════════════════════════════════════════════════════════

CTGTest::init('requiredBodyParam — returns self')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->POST(fn($req) => null)
        ->requiredBodyParam('name', CTGValidator::string()))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('bodyParam with default — returns self')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->POST(fn($req) => null)
        ->bodyParam('page', CTGValidator::int(), 1))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('requiredQueryParam — returns self')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->GET(fn($req) => null)
        ->requiredQueryParam('id', CTGValidator::int()))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('queryParam with default — returns self')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->GET(fn($req) => null)
        ->queryParam('sort', CTGValidator::string(), 'id'))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

CTGTest::init('mixed params chain on method')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])
        ->POST(fn($req) => null, ['auth' => true])
        ->requiredBodyParam('name', CTGValidator::string())
        ->requiredBodyParam('email', CTGValidator::email())
        ->bodyParam('role', CTGValidator::string(), 'viewer'))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// COLLISION DETECTION
// ═══════════════════════════════════════════════════════════════

CTGTest::init('collision — cross-source body/query same name throws')
    ->stage('execute', function($_) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->export(),
                'cors_validated' => true,
            ])
                ->POST(fn($req) => null)
                ->requiredBodyParam('id', CTGValidator::int())
                ->requiredQueryParam('id', CTGValidator::int());
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws immediately', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('collision — duplicate body param same name throws')
    ->stage('execute', function($_) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->export(),
                'cors_validated' => true,
            ])
                ->POST(fn($req) => null)
                ->requiredBodyParam('name', CTGValidator::string())
                ->bodyParam('name', CTGValidator::string_empty());
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws immediately', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('collision — duplicate query param same name throws')
    ->stage('execute', function($_) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->export(),
                'cors_validated' => true,
            ])
                ->GET(fn($req) => null)
                ->requiredQueryParam('sort', CTGValidator::string())
                ->queryParam('sort', CTGValidator::string(), 'id');
            return 'no exception';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('throws immediately', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('no collision — same name on different methods is OK')
    ->stage('execute', function($_) {
        try {
            CTGEndpoint::init([
                'cors' => CTGCorsPolicy::init()->origins('*')->export(),
                'cors_validated' => true,
            ])
                ->GET(fn($req) => null)
                ->requiredQueryParam('id', CTGValidator::int())
                ->POST(fn($req) => null)
                ->requiredBodyParam('id', CTGValidator::int());
            return 'passed';
        } catch (\Exception $e) {
            return 'threw';
        }
    })
    ->assert('no collision across methods', fn($r) => $r, 'passed')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// AUTH BINDING
// ═══════════════════════════════════════════════════════════════

CTGTest::init('onAuth — returns self for chaining')
    ->stage('create', fn($_) => CTGEndpoint::init([
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ])->onAuth(fn(string $token) => ['sub' => '123']))
    ->assert('is CTGEndpoint', fn($e) => $e instanceof CTGEndpoint, true)
    ->start(null, $config);

// Auth misconfiguration (auth:true without onAuth) is tested in
// CTGEndpointLifecycleTest.php as a runtime behavior.
