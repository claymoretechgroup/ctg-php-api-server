<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\ApiServer\CTGEndpoint;
use CTG\ApiServer\CTGCorsPolicy;
use CTG\ApiServer\CTGValidator;
use CTG\ApiServer\CTGRequest;
use CTG\ApiServer\CTGResponse;
use CTG\ApiServer\CTGServerError;

// Lifecycle/integration tests for CTGEndpoint::run()
// Uses a testable subclass that overrides platform-specific I/O methods
// so the 8-step lifecycle can be exercised without an HTTP server.

$config = ['output' => 'console'];

// ═══════════════════════════════════════════════════════════════
// TESTABLE SUBCLASS
// ═══════════════════════════════════════════════════════════════

class TestEndpoint extends CTGEndpoint {

    // ── Injected request data ─────────────────────────────────
    private string $_testMethod = 'GET';
    private array $_testHeaders = [];
    private array $_testQuery = [];
    private string $_testBody = '';
    private string $_testContentType = '';

    // :: STRING, ARRAY, ARRAY, STRING, STRING -> static
    public function withRequest(
        string $method,
        array $headers = [],
        array $query = [],
        string $body = '',
        string $contentType = ''
    ): static {
        $this->_testMethod = $method;
        $this->_testHeaders = $headers;
        $this->_testQuery = $query;
        $this->_testBody = $body;
        $this->_testContentType = $contentType;
        return $this;
    }

    // ── Captured response data ────────────────────────────────
    private int $_capturedStatus = 200;
    private array $_capturedHeaders = [];
    private string $_capturedBody = '';

    // :: VOID -> INT
    public function getCapturedStatus(): int { return $this->_capturedStatus; }

    // :: VOID -> ARRAY
    public function getCapturedHeaders(): array { return $this->_capturedHeaders; }

    // :: VOID -> STRING
    public function getCapturedBody(): string { return $this->_capturedBody; }

    // :: VOID -> ARRAY
    public function getCapturedJson(): array { return json_decode($this->_capturedBody, true) ?? []; }

    // ── Platform method overrides ─────────────────────────────
    protected function _getMethod(): string { return strtoupper($this->_testMethod); }
    protected function _getHeaders(): array { return $this->_testHeaders; }
    protected function _getQuery(): array { return $this->_testQuery; }
    protected function _getRawBody(): string { return $this->_testBody; }
    protected function _getContentType(): string { return $this->_testContentType; }
    protected function _sendStatus(int $code): void { $this->_capturedStatus = $code; }
    protected function _sendHeader(string $name, string $value): void { $this->_capturedHeaders[$name] = $value; }
    protected function _sendBody(string $body): void { $this->_capturedBody = $body; }
}

// ═══════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════

// :: VOID -> TestEndpoint
// Creates a basic TestEndpoint with wildcard CORS for reuse
function makeEndpoint(array $extraConfig = []): TestEndpoint {
    $base = [
        'cors' => CTGCorsPolicy::init()->origins('*')->export(),
        'cors_validated' => true,
    ];
    return TestEndpoint::init(array_merge($base, $extraConfig));
}

// :: TestEndpoint -> ARRAY
// Runs the endpoint and returns [status, headers, json]
function runAndCapture(TestEndpoint $ep): array {
    $ep->run();
    return [
        'status' => $ep->getCapturedStatus(),
        'headers' => $ep->getCapturedHeaders(),
        'json' => $ep->getCapturedJson(),
        'body' => $ep->getCapturedBody(),
    ];
}

// ═══════════════════════════════════════════════════════════════
// STEP 1 — CORS RESOLVE
// ═══════════════════════════════════════════════════════════════

CTGTest::init('CORS — wildcard origin sends Access-Control-Allow-Origin: *')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('GET');
        $ep->run();
        return $ep->getCapturedHeaders();
    })
    ->assert('has wildcard ACAO', fn($h) => $h['Access-Control-Allow-Origin'] ?? null, '*')
    ->start(null, $config);

CTGTest::init('CORS — specific origin match sends origin + Vary')
    ->stage('execute', function($_) {
        $ep = TestEndpoint::init([
            'cors' => CTGCorsPolicy::init()
                ->origins(['https://app.example.com', 'https://admin.example.com'])
                ->export(),
            'cors_validated' => true,
        ]);
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('GET', ['origin' => 'https://app.example.com']);
        $ep->run();
        return $ep->getCapturedHeaders();
    })
    ->assert('has matching origin', fn($h) => $h['Access-Control-Allow-Origin'] ?? null, 'https://app.example.com')
    ->assert('has Vary: Origin', fn($h) => $h['Vary'] ?? null, 'Origin')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STEP 2 — OPTIONS PREFLIGHT
// ═══════════════════════════════════════════════════════════════

CTGTest::init('OPTIONS — responds 204 with no body')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('OPTIONS');
        return runAndCapture($ep);
    })
    ->assert('status is 204', fn($r) => $r['status'], 204)
    ->assert('body is empty', fn($r) => $r['body'], '')
    ->assert('has CORS header', fn($r) => isset($r['headers']['Access-Control-Allow-Origin']), true)
    ->start(null, $config);

CTGTest::init('OPTIONS — auth-protected endpoint still returns 204 (no auth check)')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '123']);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
        $ep->withRequest('OPTIONS');
        return runAndCapture($ep);
    })
    ->assert('status is 204', fn($r) => $r['status'], 204)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STEP 3 — BODY SIZE CHECK
// ═══════════════════════════════════════════════════════════════

CTGTest::init('body size — POST exceeding max_body_size returns 413')
    ->stage('execute', function($_) {
        $ep = makeEndpoint(['max_body_size' => 10]);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]));
        $ep->withRequest('POST', [], [], str_repeat('x', 20), 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 413', fn($r) => $r['status'], 413)
    ->assert('envelope success false', fn($r) => $r['json']['success'] ?? null, false)
    ->assert('type is PAYLOAD_TOO_LARGE', fn($r) => $r['json']['result']['type'] ?? null, 'PAYLOAD_TOO_LARGE')
    ->start(null, $config);

CTGTest::init('body size — POST under max_body_size proceeds normally')
    ->stage('execute', function($_) {
        $ep = makeEndpoint(['max_body_size' => 100]);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('POST', [], [], '{"name":"hi"}', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->assert('envelope success true', fn($r) => $r['json']['success'] ?? null, true)
    ->start(null, $config);

CTGTest::init('body size — GET with no body and max_body_size configured is fine')
    ->stage('execute', function($_) {
        $ep = makeEndpoint(['max_body_size' => 10]);
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STEP 4 — BODY PARSE
// ═══════════════════════════════════════════════════════════════

CTGTest::init('body parse — valid JSON parsed and handler receives params')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params();
            return CTGResponse::json(['got' => $captured]);
        })
        ->requiredBodyParam('name', CTGValidator::string());
        $ep->withRequest('POST', [], [], '{"name":"Alice"}', 'application/json');
        $ep->run();
        return $captured;
    })
    ->assert('name is Alice', fn($p) => $p['name'] ?? null, 'Alice')
    ->start(null, $config);

CTGTest::init('body parse — invalid JSON returns 400 INVALID_BODY')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]));
        $ep->withRequest('POST', [], [], '{bad json', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn($r) => $r['status'], 400)
    ->assert('type is INVALID_BODY', fn($r) => $r['json']['result']['type'] ?? null, 'INVALID_BODY')
    ->start(null, $config);

CTGTest::init('body parse — form-urlencoded body is decoded')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params();
            return CTGResponse::json(['got' => $captured]);
        })
        ->requiredBodyParam('name', CTGValidator::string());
        $ep->withRequest('POST', [], [], 'name=Bob', 'application/x-www-form-urlencoded');
        $ep->run();
        return $captured;
    })
    ->assert('name is Bob', fn($p) => $p['name'] ?? null, 'Bob')
    ->start(null, $config);

CTGTest::init('body parse — text/plain with non-empty body returns 400 INVALID_CONTENT_TYPE')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]));
        $ep->withRequest('POST', [], [], 'hello world', 'text/plain');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn($r) => $r['status'], 400)
    ->assert('type is INVALID_CONTENT_TYPE', fn($r) => $r['json']['result']['type'] ?? null, 'INVALID_CONTENT_TYPE')
    ->start(null, $config);

CTGTest::init('body parse — GET with empty body produces empty body map')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->GET(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params();
            return CTGResponse::json(['ok' => true]);
        });
        $ep->withRequest('GET');
        $ep->run();
        return $captured;
    })
    ->assert('params is null (no params declared)', fn($p) => $p, null)
    ->start(null, $config);

CTGTest::init('body parse — Content-Type with charset parameter is accepted')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params();
            return CTGResponse::json(['ok' => true]);
        })
        ->requiredBodyParam('name', CTGValidator::string());
        $ep->withRequest('POST', [], [], '{"name":"Alice"}', 'application/json; charset=utf-8');
        $ep->run();
        return $captured;
    })
    ->assert('name is Alice', fn($p) => $p['name'] ?? null, 'Alice')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STEP 5 — METHOD MATCH
// ═══════════════════════════════════════════════════════════════

CTGTest::init('method match — GET to POST-only endpoint returns 405')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]));
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 405', fn($r) => $r['status'], 405)
    ->assert('type is METHOD_NOT_ALLOWED', fn($r) => $r['json']['result']['type'] ?? null, 'METHOD_NOT_ALLOWED')
    ->assert('Allow header present', fn($r) => isset($r['headers']['Allow']), true)
    ->start(null, $config);

CTGTest::init('method match — POST to GET+POST endpoint executes handler')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['method' => 'get']))
           ->POST(fn(CTGRequest $req) => CTGResponse::json(['method' => 'post']));
        $ep->withRequest('POST', [], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->assert('handler executed', fn($r) => $r['json']['result']['method'] ?? null, 'post')
    ->start(null, $config);

CTGTest::init('method match — HEAD request to endpoint with HEAD bound executes')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->HEAD(fn(CTGRequest $req) => CTGResponse::json(['method' => 'head']));
        $ep->withRequest('HEAD');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STEP 6 — AUTH GATE
// ═══════════════════════════════════════════════════════════════

CTGTest::init('auth — GET without auth, POST with auth — GET succeeds without token')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '123']);
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['public' => true]))
           ->POST(fn(CTGRequest $req) => CTGResponse::json(['private' => true]), ['auth' => true]);
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->assert('public data returned', fn($r) => $r['json']['result']['public'] ?? null, true)
    ->start(null, $config);

CTGTest::init('auth — POST with auth, valid token executes handler with claims')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '42', 'role' => 'admin']);
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->claims();
            return CTGResponse::json(['ok' => true]);
        }, ['auth' => true]);
        $ep->withRequest('POST', ['authorization' => 'Bearer valid-token-123'], [], '', '');
        $ep->run();
        return $captured;
    })
    ->assert('claims has sub', fn($c) => $c['sub'] ?? null, '42')
    ->assert('claims has role', fn($c) => $c['role'] ?? null, 'admin')
    ->start(null, $config);

CTGTest::init('auth — POST with auth, missing Authorization header returns 401')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '123']);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
        $ep->withRequest('POST', [], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 401', fn($r) => $r['status'], 401)
    ->assert('success is false', fn($r) => $r['json']['success'] ?? null, false)
    ->start(null, $config);

CTGTest::init('auth — POST with auth, invalid token (verifier throws) returns 401')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->onAuth(function(string $token) {
            throw new \RuntimeException('Invalid token');
        });
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
        $ep->withRequest('POST', ['authorization' => 'Bearer bad-token'], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 401', fn($r) => $r['status'], 401)
    ->assert('success is false', fn($r) => $r['json']['success'] ?? null, false)
    ->start(null, $config);

CTGTest::init('auth — POST with auth, empty token after trim returns 401')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '123']);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
        $ep->withRequest('POST', ['authorization' => 'Bearer    '], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 401', fn($r) => $r['status'], 401)
    ->start(null, $config);

CTGTest::init('auth — case-insensitive Bearer prefix accepted')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => 'user1']);
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->claims();
            return CTGResponse::json(['ok' => true]);
        }, ['auth' => true]);
        $ep->withRequest('POST', ['authorization' => 'bearer my-token'], [], '', '');
        $ep->run();
        return $captured;
    })
    ->assert('claims has sub', fn($c) => $c['sub'] ?? null, 'user1')
    ->start(null, $config);

CTGTest::init('auth — BEARER uppercase prefix accepted')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => 'user2']);
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->claims();
            return CTGResponse::json(['ok' => true]);
        }, ['auth' => true]);
        $ep->withRequest('POST', ['authorization' => 'BEARER my-token'], [], '', '');
        $ep->run();
        return $captured;
    })
    ->assert('claims has sub', fn($c) => $c['sub'] ?? null, 'user2')
    ->start(null, $config);

CTGTest::init('auth — auth:true without onAuth throws developer error')
    ->stage('execute', function($_) {
        try {
            $ep = makeEndpoint();
            $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
            $ep->withRequest('POST', ['authorization' => 'Bearer token'], [], '', '');
            $ep->run();
            return 'no exception';
        } catch (\Throwable $e) {
            return 'threw: ' . get_class($e);
        }
    })
    ->assert('throws', fn($r) => str_contains($r, 'threw'), true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STEP 7 — VALIDATE
// ═══════════════════════════════════════════════════════════════

CTGTest::init('validate — required param present and valid, handler receives value')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params('name');
            return CTGResponse::json(['ok' => true]);
        })
        ->requiredBodyParam('name', CTGValidator::string());
        $ep->withRequest('POST', [], [], '{"name":"Alice"}', 'application/json');
        $ep->run();
        return $captured;
    })
    ->assert('received Alice', fn($v) => $v, 'Alice')
    ->start(null, $config);

CTGTest::init('validate — required param missing returns 400 with "Required"')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]))
           ->requiredBodyParam('name', CTGValidator::string());
        $ep->withRequest('POST', [], [], '{}', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn($r) => $r['status'], 400)
    ->assert('success is false', fn($r) => $r['json']['success'] ?? null, false)
    ->assert('name error is Required', fn($r) => $r['json']['result']['name'] ?? null, 'Required')
    ->start(null, $config);

CTGTest::init('validate — optional param missing with default, handler receives default')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params('role');
            return CTGResponse::json(['ok' => true]);
        })
        ->bodyParam('role', CTGValidator::string(), 'viewer');
        $ep->withRequest('POST', [], [], '{}', 'application/json');
        $ep->run();
        return $captured;
    })
    ->assert('received default viewer', fn($v) => $v, 'viewer')
    ->start(null, $config);

CTGTest::init('validate — optional param missing without default (PATCH), field absent')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->PATCH(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params();
            return CTGResponse::json(['ok' => true]);
        })
        ->bodyParam('name', CTGValidator::string());
        $ep->withRequest('PATCH', [], [], '{}', 'application/json');
        $ep->run();
        return $captured;
    })
    ->assert('name key absent', fn($p) => array_key_exists('name', $p ?? []), false)
    ->start(null, $config);

CTGTest::init('validate — param fails validation returns 400 with field error')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]))
           ->requiredBodyParam('email', CTGValidator::email());
        $ep->withRequest('POST', [], [], '{"email":"not-an-email"}', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn($r) => $r['status'], 400)
    ->assert('success is false', fn($r) => $r['json']['success'] ?? null, false)
    ->assert('email field has error', fn($r) => isset($r['json']['result']['email']), true)
    ->start(null, $config);

CTGTest::init('validate — multiple invalid params returns all field errors')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]))
           ->requiredBodyParam('name', CTGValidator::string())
           ->requiredBodyParam('email', CTGValidator::email());
        $ep->withRequest('POST', [], [], '{}', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn($r) => $r['status'], 400)
    ->assert('name error present', fn($r) => isset($r['json']['result']['name']), true)
    ->assert('email error present', fn($r) => isset($r['json']['result']['email']), true)
    ->start(null, $config);

CTGTest::init('validate — undeclared body fields are stripped')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->POST(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params();
            return CTGResponse::json(['ok' => true]);
        })
        ->requiredBodyParam('name', CTGValidator::string());
        $ep->withRequest('POST', [], [], '{"name":"Alice","extra":"evil"}', 'application/json');
        $ep->run();
        return $captured;
    })
    ->assert('name present', fn($p) => $p['name'] ?? null, 'Alice')
    ->assert('extra stripped', fn($p) => array_key_exists('extra', $p ?? []), false)
    ->start(null, $config);

CTGTest::init('validate — int query param from string is coerced to integer')
    ->stage('execute', function($_) {
        $captured = null;
        $ep = makeEndpoint();
        $ep->GET(function(CTGRequest $req) use (&$captured) {
            $captured = $req->params('page');
            return CTGResponse::json(['ok' => true]);
        })
        ->requiredQueryParam('page', CTGValidator::int());
        $ep->withRequest('GET', [], ['page' => '5']);
        $ep->run();
        return $captured;
    })
    ->assert('received integer 5', fn($v) => $v, 5)
    ->assert('type is int', fn($v) => is_int($v), true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STEP 8 — HANDLER
// ═══════════════════════════════════════════════════════════════

CTGTest::init('handler — Response::json wraps in success envelope')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['id' => 1, 'name' => 'Alice']));
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->assert('success is true', fn($r) => $r['json']['success'] ?? null, true)
    ->assert('result has data', fn($r) => $r['json']['result']['name'] ?? null, 'Alice')
    ->start(null, $config);

CTGTest::init('handler — Response::json with custom status')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json(['id' => 99], 201));
        $ep->withRequest('POST', [], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 201', fn($r) => $r['status'], 201)
    ->assert('success is true', fn($r) => $r['json']['success'] ?? null, true)
    ->start(null, $config);

CTGTest::init('handler — Response::json with custom headers')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json(['id' => 99], 201, ['Location' => '/users/99']));
        $ep->withRequest('POST', [], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('Location header set', fn($r) => $r['headers']['Location'] ?? null, '/users/99')
    ->start(null, $config);

CTGTest::init('handler — Response::noContent returns 204 with no body')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->DELETE(fn(CTGRequest $req) => CTGResponse::noContent());
        $ep->withRequest('DELETE');
        return runAndCapture($ep);
    })
    ->assert('status is 204', fn($r) => $r['status'], 204)
    ->assert('body is empty', fn($r) => $r['body'], '')
    ->start(null, $config);

CTGTest::init('handler — throws ServerError returns error envelope with correct status')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->GET(function(CTGRequest $req) {
            throw CTGServerError::notFound('User not found');
        });
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 404', fn($r) => $r['status'], 404)
    ->assert('success is false', fn($r) => $r['json']['success'] ?? null, false)
    ->assert('type is NOT_FOUND', fn($r) => $r['json']['result']['type'] ?? null, 'NOT_FOUND')
    ->assert('message present', fn($r) => $r['json']['result']['message'] ?? null, 'User not found')
    ->start(null, $config);

CTGTest::init('handler — throws generic Exception returns 500, original message not exposed')
    ->stage('execute', function($_) {
        $ep = makeEndpoint();
        $ep->GET(function(CTGRequest $req) {
            throw new \RuntimeException('secret database error details');
        });
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 500', fn($r) => $r['status'], 500)
    ->assert('success is false', fn($r) => $r['json']['success'] ?? null, false)
    ->assert('type is INTERNAL_ERROR', fn($r) => $r['json']['result']['type'] ?? null, 'INTERNAL_ERROR')
    ->assert('original message not exposed', fn($r) => str_contains($r['json']['result']['message'] ?? '', 'secret'), false)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// COMBINED LIFECYCLE
// ═══════════════════════════════════════════════════════════════

CTGTest::init('full flow — CORS + auth + validation + handler returns data with CORS headers')
    ->stage('execute', function($_) {
        $ep = TestEndpoint::init([
            'cors' => CTGCorsPolicy::init()
                ->origins(['https://app.example.com'])
                ->methods(['GET', 'POST'])
                ->headers(['Content-Type', 'Authorization'])
                ->export(),
            'cors_validated' => true,
        ]);
        $ep->onAuth(fn(string $token) => ['sub' => '42', 'role' => 'admin']);
        $ep->POST(function(CTGRequest $req) {
            $name = $req->params('name');
            $email = $req->params('email');
            $claims = $req->claims();
            return CTGResponse::json([
                'name' => $name,
                'email' => $email,
                'created_by' => $claims['sub'],
            ], 201);
        }, ['auth' => true])
        ->requiredBodyParam('name', CTGValidator::string())
        ->requiredBodyParam('email', CTGValidator::email())
        ->bodyParam('role', CTGValidator::string(), 'viewer');
        $ep->withRequest(
            'POST',
            [
                'origin' => 'https://app.example.com',
                'authorization' => 'Bearer valid-jwt-token',
            ],
            [],
            '{"name":"Alice","email":"alice@example.com"}',
            'application/json'
        );
        return runAndCapture($ep);
    })
    ->assert('status is 201', fn($r) => $r['status'], 201)
    ->assert('success is true', fn($r) => $r['json']['success'] ?? null, true)
    ->assert('name in result', fn($r) => $r['json']['result']['name'] ?? null, 'Alice')
    ->assert('email in result', fn($r) => $r['json']['result']['email'] ?? null, 'alice@example.com')
    ->assert('created_by from claims', fn($r) => $r['json']['result']['created_by'] ?? null, '42')
    ->assert('CORS origin header', fn($r) => $r['headers']['Access-Control-Allow-Origin'] ?? null, 'https://app.example.com')
    ->assert('Vary header', fn($r) => $r['headers']['Vary'] ?? null, 'Origin')
    ->start(null, $config);
