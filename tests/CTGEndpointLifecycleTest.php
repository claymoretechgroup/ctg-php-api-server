<?php
declare(strict_types=1);


use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;
use CTG\ApiServer\CTGEndpoint;
use CTG\ApiServer\CTGCorsPolicy;
use CTG\ApiServer\CTGValidator;
use CTG\ApiServer\CTGRequest;
use CTG\ApiServer\CTGResponse;
use CTG\ApiServer\CTGServerError;

$pipelines = [];

// Lifecycle/integration tests for CTGEndpoint::run()
// Uses a testable subclass that overrides platform-specific I/O methods
// so the 8-step lifecycle can be exercised without an HTTP server.


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
    protected function _getContentLength(): ?int { return $this->_testBody !== '' ? strlen($this->_testBody) : null; }
    protected function _readBodyWithLimit(int $limit): string|false {
        if (strlen($this->_testBody) > $limit) { return false; }
        return $this->_testBody;
    }
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

$pipelines[] = CTGTest::init('CORS — wildcard origin sends Access-Control-Allow-Origin: *')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('GET');
        $ep->run();
        return $ep->getCapturedHeaders();
    })
    ->assert('has wildcard ACAO', fn(CTGTestState $state) => $state->getSubject()['Access-Control-Allow-Origin'] ?? null, CTGTestPredicates::equals('*'))
    ;

$pipelines[] = CTGTest::init('CORS — specific origin match sends origin + Vary')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('has matching origin', fn(CTGTestState $state) => $state->getSubject()['Access-Control-Allow-Origin'] ?? null, CTGTestPredicates::equals('https://app.example.com'))
    ->assert('has Vary: Origin', fn(CTGTestState $state) => $state->getSubject()['Vary'] ?? null, CTGTestPredicates::equals('Origin'))
    ;

// ═══════════════════════════════════════════════════════════════
// STEP 2 — OPTIONS PREFLIGHT
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('OPTIONS — responds 204 with no body')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('OPTIONS');
        return runAndCapture($ep);
    })
    ->assert('status is 204', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(204))
    ->assert('body is empty', fn(CTGTestState $state) => $state->getSubject()['body'], CTGTestPredicates::equals(''))
    ->assert('has CORS header', fn(CTGTestState $state) => isset($state->getSubject()['headers']['Access-Control-Allow-Origin']), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('OPTIONS — auth-protected endpoint still returns 204 (no auth check)')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '123']);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
        $ep->withRequest('OPTIONS');
        return runAndCapture($ep);
    })
    ->assert('status is 204', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(204))
    ;

// ═══════════════════════════════════════════════════════════════
// STEP 3 — BODY SIZE CHECK
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('body size — POST exceeding max_body_size returns 413')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint(['max_body_size' => 10]);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]));
        $ep->withRequest('POST', [], [], str_repeat('x', 20), 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 413', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(413))
    ->assert('envelope success false', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isFalse())
    ->assert('type is PAYLOAD_TOO_LARGE', fn(CTGTestState $state) => $state->getSubject()['json']['result']['type'] ?? null, CTGTestPredicates::equals('PAYLOAD_TOO_LARGE'))
    ;

$pipelines[] = CTGTest::init('body size — POST under max_body_size proceeds normally')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint(['max_body_size' => 100]);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('POST', [], [], '{"name":"hi"}', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ->assert('envelope success true', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('body size — GET with no body and max_body_size configured is fine')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint(['max_body_size' => 10]);
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['ok' => true]));
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

// ═══════════════════════════════════════════════════════════════
// STEP 4 — BODY PARSE
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('body parse — valid JSON parsed and handler receives params')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('name is Alice', fn(CTGTestState $state) => $state->getSubject()['name'] ?? null, CTGTestPredicates::equals('Alice'))
    ;

$pipelines[] = CTGTest::init('body parse — invalid JSON returns 400 INVALID_BODY')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]));
        $ep->withRequest('POST', [], [], '{bad json', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(400))
    ->assert('type is INVALID_BODY', fn(CTGTestState $state) => $state->getSubject()['json']['result']['type'] ?? null, CTGTestPredicates::equals('INVALID_BODY'))
    ;

$pipelines[] = CTGTest::init('body parse — form-urlencoded body is decoded')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('name is Bob', fn(CTGTestState $state) => $state->getSubject()['name'] ?? null, CTGTestPredicates::equals('Bob'))
    ;

$pipelines[] = CTGTest::init('body parse — text/plain with non-empty body returns 400 INVALID_CONTENT_TYPE')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]));
        $ep->withRequest('POST', [], [], 'hello world', 'text/plain');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(400))
    ->assert('type is INVALID_CONTENT_TYPE', fn(CTGTestState $state) => $state->getSubject()['json']['result']['type'] ?? null, CTGTestPredicates::equals('INVALID_CONTENT_TYPE'))
    ;

$pipelines[] = CTGTest::init('body parse — GET with empty body produces empty body map')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('params is null (no params declared)', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('body parse — Content-Type with charset parameter is accepted')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('name is Alice', fn(CTGTestState $state) => $state->getSubject()['name'] ?? null, CTGTestPredicates::equals('Alice'))
    ;

// ═══════════════════════════════════════════════════════════════
// STEP 5 — METHOD MATCH
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('method match — GET to POST-only endpoint returns 405')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]));
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 405', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(405))
    ->assert('type is METHOD_NOT_ALLOWED', fn(CTGTestState $state) => $state->getSubject()['json']['result']['type'] ?? null, CTGTestPredicates::equals('METHOD_NOT_ALLOWED'))
    ->assert('Allow header present', fn(CTGTestState $state) => isset($state->getSubject()['headers']['Allow']), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('method match — POST to GET+POST endpoint executes handler')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['method' => 'get']))
           ->POST(fn(CTGRequest $req) => CTGResponse::json(['method' => 'post']));
        $ep->withRequest('POST', [], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ->assert('handler executed', fn(CTGTestState $state) => $state->getSubject()['json']['result']['method'] ?? null, CTGTestPredicates::equals('post'))
    ;

$pipelines[] = CTGTest::init('method match — HEAD request to endpoint with HEAD bound executes')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->HEAD(fn(CTGRequest $req) => CTGResponse::json(['method' => 'head']));
        $ep->withRequest('HEAD');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

// ═══════════════════════════════════════════════════════════════
// STEP 6 — AUTH GATE
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('auth — GET without auth, POST with auth — GET succeeds without token')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '123']);
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['public' => true]))
           ->POST(fn(CTGRequest $req) => CTGResponse::json(['private' => true]), ['auth' => true]);
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ->assert('public data returned', fn(CTGTestState $state) => $state->getSubject()['json']['result']['public'] ?? null, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('auth — POST with auth, valid token executes handler with claims')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('claims has sub', fn(CTGTestState $state) => $state->getSubject()['sub'] ?? null, CTGTestPredicates::equals('42'))
    ->assert('claims has role', fn(CTGTestState $state) => $state->getSubject()['role'] ?? null, CTGTestPredicates::equals('admin'))
    ;

$pipelines[] = CTGTest::init('auth — POST with auth, missing Authorization header returns 401')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '123']);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
        $ep->withRequest('POST', [], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 401', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(401))
    ->assert('success is false', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('auth — POST with auth, invalid token (verifier throws) returns 401')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->onAuth(function(string $token) {
            throw new \RuntimeException('Invalid token');
        });
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
        $ep->withRequest('POST', ['authorization' => 'Bearer bad-token'], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 401', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(401))
    ->assert('success is false', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('auth — POST with auth, empty token after trim returns 401')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->onAuth(fn(string $token) => ['sub' => '123']);
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]), ['auth' => true]);
        $ep->withRequest('POST', ['authorization' => 'Bearer    '], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 401', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(401))
    ;

$pipelines[] = CTGTest::init('auth — case-insensitive Bearer prefix accepted')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('claims has sub', fn(CTGTestState $state) => $state->getSubject()['sub'] ?? null, CTGTestPredicates::equals('user1'))
    ;

$pipelines[] = CTGTest::init('auth — BEARER uppercase prefix accepted')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('claims has sub', fn(CTGTestState $state) => $state->getSubject()['sub'] ?? null, CTGTestPredicates::equals('user2'))
    ;

$pipelines[] = CTGTest::init('auth — auth:true without onAuth throws developer error')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('throws', fn(CTGTestState $state) => str_contains($state->getSubject(), 'threw'), CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// STEP 7 — VALIDATE
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('validate — required param present and valid, handler receives value')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('received Alice', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('Alice'))
    ;

$pipelines[] = CTGTest::init('validate — required param missing returns 400 with "Required"')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]))
           ->requiredBodyParam('name', CTGValidator::string());
        $ep->withRequest('POST', [], [], '{}', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(400))
    ->assert('success is false', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isFalse())
    ->assert('name error is Required', fn(CTGTestState $state) => $state->getSubject()['json']['result']['name'] ?? null, CTGTestPredicates::equals('Required'))
    ;

$pipelines[] = CTGTest::init('validate — optional param missing with default, handler receives default')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('received default viewer', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('viewer'))
    ;

$pipelines[] = CTGTest::init('validate — optional param missing without default (PATCH), field absent')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('name key absent', fn(CTGTestState $state) => array_key_exists('name', $state->getSubject() ?? []), CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('validate — param fails validation returns 400 with field error')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]))
           ->requiredBodyParam('email', CTGValidator::email());
        $ep->withRequest('POST', [], [], '{"email":"not-an-email"}', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(400))
    ->assert('success is false', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isFalse())
    ->assert('email field has error', fn(CTGTestState $state) => isset($state->getSubject()['json']['result']['email']), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('validate — multiple invalid params returns all field errors')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json([]))
           ->requiredBodyParam('name', CTGValidator::string())
           ->requiredBodyParam('email', CTGValidator::email());
        $ep->withRequest('POST', [], [], '{}', 'application/json');
        return runAndCapture($ep);
    })
    ->assert('status is 400', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(400))
    ->assert('name error present', fn(CTGTestState $state) => isset($state->getSubject()['json']['result']['name']), CTGTestPredicates::isTrue())
    ->assert('email error present', fn(CTGTestState $state) => isset($state->getSubject()['json']['result']['email']), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('validate — undeclared body fields are stripped')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('name present', fn(CTGTestState $state) => $state->getSubject()['name'] ?? null, CTGTestPredicates::equals('Alice'))
    ->assert('extra stripped', fn(CTGTestState $state) => array_key_exists('extra', $state->getSubject() ?? []), CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('validate — int query param from string is coerced to integer')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('received integer 5', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(5))
    ->assert('type is int', fn(CTGTestState $state) => is_int($state->getSubject()), CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// STEP 8 — HANDLER
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('handler — Response::json wraps in success envelope')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->GET(fn(CTGRequest $req) => CTGResponse::json(['id' => 1, 'name' => 'Alice']));
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ->assert('success is true', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isTrue())
    ->assert('result has data', fn(CTGTestState $state) => $state->getSubject()['json']['result']['name'] ?? null, CTGTestPredicates::equals('Alice'))
    ;

$pipelines[] = CTGTest::init('handler — Response::json with custom status')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json(['id' => 99], 201));
        $ep->withRequest('POST', [], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('status is 201', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(201))
    ->assert('success is true', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('handler — Response::json with custom headers')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->POST(fn(CTGRequest $req) => CTGResponse::json(['id' => 99], 201, ['Location' => '/users/99']));
        $ep->withRequest('POST', [], [], '', '');
        return runAndCapture($ep);
    })
    ->assert('Location header set', fn(CTGTestState $state) => $state->getSubject()['headers']['Location'] ?? null, CTGTestPredicates::equals('/users/99'))
    ;

$pipelines[] = CTGTest::init('handler — Response::noContent returns 204 with no body')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->DELETE(fn(CTGRequest $req) => CTGResponse::noContent());
        $ep->withRequest('DELETE');
        return runAndCapture($ep);
    })
    ->assert('status is 204', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(204))
    ->assert('body is empty', fn(CTGTestState $state) => $state->getSubject()['body'], CTGTestPredicates::equals(''))
    ;

$pipelines[] = CTGTest::init('handler — throws ServerError returns error envelope with correct status')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->GET(function(CTGRequest $req) {
            throw CTGServerError::notFound('User not found');
        });
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 404', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(404))
    ->assert('success is false', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isFalse())
    ->assert('type is NOT_FOUND', fn(CTGTestState $state) => $state->getSubject()['json']['result']['type'] ?? null, CTGTestPredicates::equals('NOT_FOUND'))
    ->assert('message present', fn(CTGTestState $state) => $state->getSubject()['json']['result']['message'] ?? null, CTGTestPredicates::equals('User not found'))
    ;

$pipelines[] = CTGTest::init('handler — throws generic Exception returns 500, original message not exposed')
    ->stage('execute', function(CTGTestState $state) {
        $ep = makeEndpoint();
        $ep->GET(function(CTGRequest $req) {
            throw new \RuntimeException('secret database error details');
        });
        $ep->withRequest('GET');
        return runAndCapture($ep);
    })
    ->assert('status is 500', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(500))
    ->assert('success is false', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isFalse())
    ->assert('type is INTERNAL_ERROR', fn(CTGTestState $state) => $state->getSubject()['json']['result']['type'] ?? null, CTGTestPredicates::equals('INTERNAL_ERROR'))
    ->assert('original message not exposed', fn(CTGTestState $state) => str_contains($state->getSubject()['json']['result']['message'] ?? '', 'secret'), CTGTestPredicates::isFalse())
    ;

// ═══════════════════════════════════════════════════════════════
// COMBINED LIFECYCLE
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('full flow — CORS + auth + validation + handler returns data with CORS headers')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('status is 201', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(201))
    ->assert('success is true', fn(CTGTestState $state) => $state->getSubject()['json']['success'] ?? null, CTGTestPredicates::isTrue())
    ->assert('name in result', fn(CTGTestState $state) => $state->getSubject()['json']['result']['name'] ?? null, CTGTestPredicates::equals('Alice'))
    ->assert('email in result', fn(CTGTestState $state) => $state->getSubject()['json']['result']['email'] ?? null, CTGTestPredicates::equals('alice@example.com'))
    ->assert('created_by from claims', fn(CTGTestState $state) => $state->getSubject()['json']['result']['created_by'] ?? null, CTGTestPredicates::equals('42'))
    ->assert('CORS origin header', fn(CTGTestState $state) => $state->getSubject()['headers']['Access-Control-Allow-Origin'] ?? null, CTGTestPredicates::equals('https://app.example.com'))
    ->assert('Vary header', fn(CTGTestState $state) => $state->getSubject()['headers']['Vary'] ?? null, CTGTestPredicates::equals('Origin'))
    ;

return $pipelines;
