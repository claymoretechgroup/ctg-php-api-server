<?php
declare(strict_types=1);

namespace CTG\ApiServer;

class CTGEndpoint
{
    // ── Instance Properties ───────────────────────────────────

    private array $_cors;
    private int $_maxBodySize;
    private bool $_exposeErrorDetails;
    private mixed $_authVerifier = null;
    private array $_methods = [];
    private ?string $_currentMethod = null;

    // ── Constructor ───────────────────────────────────────────

    // CONSTRUCTOR :: ARRAY -> $this
    public function __construct(array $config)
    {
        $corsConfig = $config['cors'] ?? null;
        $corsValidated = $config['cors_validated'] ?? false;

        if ($corsConfig instanceof CTGCorsPolicy) {
            // CTGCorsPolicy instance — validate unless already validated
            if (!$corsValidated) {
                $corsConfig->validate();
            }
            $this->_cors = $corsConfig->export();
        } elseif (is_array($corsConfig)) {
            if (!$corsValidated) {
                // Reconstruct and validate
                $policy = CTGCorsPolicy::fromArray($corsConfig);
                $policy->validate();
                $this->_cors = $corsConfig;
            } else {
                $this->_cors = $corsConfig;
            }
        } else {
            throw new \RuntimeException('CTGEndpoint: cors config required');
        }

        $this->_maxBodySize = $config['max_body_size'] ?? 0;
        $this->_exposeErrorDetails = $config['expose_error_details'] ?? false;
    }

    // ── Instance Methods ──────────────────────────────────────

    // :: CALLABLE -> $this
    public function onAuth(callable $verifier): static
    {
        $this->_authVerifier = $verifier;
        return $this;
    }

    // :: CALLABLE, ARRAY -> $this
    public function GET(callable $handler, array $config = []): static
    {
        return $this->_bindMethod('GET', $handler, $config);
    }

    // :: CALLABLE, ARRAY -> $this
    public function POST(callable $handler, array $config = []): static
    {
        return $this->_bindMethod('POST', $handler, $config);
    }

    // :: CALLABLE, ARRAY -> $this
    public function PUT(callable $handler, array $config = []): static
    {
        return $this->_bindMethod('PUT', $handler, $config);
    }

    // :: CALLABLE, ARRAY -> $this
    public function PATCH(callable $handler, array $config = []): static
    {
        return $this->_bindMethod('PATCH', $handler, $config);
    }

    // :: CALLABLE, ARRAY -> $this
    public function DELETE(callable $handler, array $config = []): static
    {
        return $this->_bindMethod('DELETE', $handler, $config);
    }

    // :: CALLABLE, ARRAY -> $this
    public function HEAD(callable $handler, array $config = []): static
    {
        return $this->_bindMethod('HEAD', $handler, $config);
    }

    // :: STRING, ctgValidator -> $this
    public function requiredBodyParam(string $name, CTGValidator $validator): static
    {
        $this->_addParam($name, $validator, 'body', true, null, false);
        return $this;
    }

    // :: STRING, ctgValidator -> $this
    public function requiredQueryParam(string $name, CTGValidator $validator): static
    {
        $this->_addParam($name, $validator, 'query', true, null, false);
        return $this;
    }

    // :: STRING, ctgValidator, MIXED -> $this
    public function bodyParam(string $name, CTGValidator $validator, mixed $default = null): static
    {
        // Determine if a default was explicitly provided by checking argument count
        $hasDefault = func_num_args() >= 3;
        $this->_addParam($name, $validator, 'body', false, $default, $hasDefault);
        return $this;
    }

    // :: STRING, ctgValidator, MIXED -> $this
    public function queryParam(string $name, CTGValidator $validator, mixed $default = null): static
    {
        $hasDefault = func_num_args() >= 3;
        $this->_addParam($name, $validator, 'query', false, $default, $hasDefault);
        return $this;
    }

    // :: VOID -> VOID
    public function run(): void
    {
        $method = $this->_getMethod();
        $headers = $this->_getHeaders();
        $origin = $headers['origin'] ?? '';

        // ── Step 1: CORS Resolve ──────────────────────────────
        $corsPolicy = CTGCorsPolicy::fromArray($this->_cors);
        $corsHeaders = $corsPolicy->resolveHeaders($origin, $method);
        foreach ($corsHeaders as $name => $value) {
            $this->_sendHeader($name, $value);
        }

        // ── Step 2: OPTIONS Preflight ─────────────────────────
        if ($method === 'OPTIONS') {
            $this->_sendStatus(204);
            return;
        }

        // ── Step 3: Body Size Check ───────────────────────────
        $rawBody = '';
        if ($this->_maxBodySize > 0) {
            $rawBody = $this->_getRawBody();
            if (strlen($rawBody) > $this->_maxBodySize) {
                $this->_sendError(
                    new CTGServerError('PAYLOAD_TOO_LARGE', 'Request body exceeds maximum size', 413)
                );
                return;
            }
        } else {
            $rawBody = $this->_getRawBody();
        }

        // ── Step 4: Body Parse ────────────────────────────────
        $bodyMap = [];
        $contentType = $this->_getContentType();
        // Strip parameters from content type
        $mediaType = trim(explode(';', $contentType, 2)[0]);

        if ($rawBody !== '') {
            if ($mediaType === 'application/json') {
                $decoded = json_decode($rawBody, true, 64);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->_sendError(
                        new CTGServerError('INVALID_BODY', 'Malformed request body', 400)
                    );
                    return;
                }
                $bodyMap = is_array($decoded) ? $decoded : [];
            } elseif ($mediaType === 'application/x-www-form-urlencoded') {
                parse_str($rawBody, $bodyMap);
            } else {
                $this->_sendError(
                    new CTGServerError('INVALID_CONTENT_TYPE', 'Unsupported content type', 400)
                );
                return;
            }
        }

        // ── Step 5: Method Match ──────────────────────────────
        if (!isset($this->_methods[$method])) {
            $allowed = implode(', ', array_keys($this->_methods));
            $this->_sendHeader('Allow', $allowed);
            $this->_sendError(
                new CTGServerError('METHOD_NOT_ALLOWED', 'Method not allowed', 405)
            );
            return;
        }

        $methodConfig = $this->_methods[$method];
        $handler = $methodConfig['handler'];
        $authRequired = $methodConfig['auth'];
        $params = $methodConfig['params'];

        // ── Step 6: Auth Gate ─────────────────────────────────
        $token = null;
        $claims = null;

        if ($authRequired) {
            if ($this->_authVerifier === null) {
                throw new \RuntimeException('CTGEndpoint: auth required but no verifier registered via onAuth()');
            }

            // Extract bearer token from authorization header
            $authHeader = $headers['authorization'] ?? '';
            $token = null;

            if ($authHeader !== '' && stripos($authHeader, 'bearer ') === 0) {
                $token = trim(substr($authHeader, 7));
            }

            if ($token === null || $token === '') {
                $this->_sendStatus(401);
                $this->_sendHeader('Content-Type', 'application/json; charset=utf-8');
                $this->_sendBody(json_encode([
                    'success' => false,
                    'result'  => 'Authorization token required',
                ]));
                return;
            }

            try {
                $claims = ($this->_authVerifier)($token);
            } catch (\Throwable $e) {
                $this->_sendStatus(401);
                $this->_sendHeader('Content-Type', 'application/json; charset=utf-8');
                $this->_sendBody(json_encode([
                    'success' => false,
                    'result'  => 'Authorization token required',
                ]));
                return;
            }
        }

        // ── Step 7: Validate ──────────────────────────────────
        $queryMap = $this->_getQuery();
        $validatedParams = [];
        $errors = [];
        $hasParams = count($params) > 0;

        foreach ($params as $paramDef) {
            $name = $paramDef['name'];
            $validator = $paramDef['validator'];
            $source = $paramDef['source'];
            $required = $paramDef['required'];
            $default = $paramDef['default'];
            $hasDefault = $paramDef['hasDefault'];

            // Get raw value from source
            $sourceMap = $source === 'body' ? $bodyMap : $queryMap;
            $present = array_key_exists($name, $sourceMap);

            if ($present) {
                try {
                    $validatedParams[$name] = $validator->run($sourceMap[$name]);
                } catch (CTGValidationError $e) {
                    $errors[$name] = $e->getMessage() !== '' ? $e->getMessage() : 'Validation failed';
                }
            } elseif ($required) {
                $errors[$name] = 'Required';
            } elseif ($hasDefault) {
                $validatedParams[$name] = $default;
            }
            // else: optional without default — omit from params map
        }

        if (count($errors) > 0) {
            $this->_sendStatus(400);
            $this->_sendHeader('Content-Type', 'application/json; charset=utf-8');
            $this->_sendBody(json_encode([
                'success' => false,
                'result'  => $errors,
            ]));
            return;
        }

        // Build request
        $request = new CTGRequest(
            $method,
            $hasParams ? $validatedParams : null,
            $headers,
            $token,
            $claims
        );

        // ── Step 8: Handler ───────────────────────────────────
        try {
            $response = $handler($request);

            $this->_sendStatus($response->getStatus());
            if ($response->getBody() !== null) {
                $this->_sendHeader('Content-Type', 'application/json; charset=utf-8');
            }
            foreach ($response->getHeaders() as $name => $value) {
                $this->_sendHeader($name, $value);
            }
            if ($response->getBody() !== null) {
                $this->_sendBody($response->getBody());
            }
        } catch (CTGServerError $e) {
            $this->_sendError($e);
        } catch (\Throwable $e) {
            $this->_sendError(
                CTGServerError::internal()
            );
        }
    }

    // ── Protected Methods (overridable for testing) ───────────

    // :: VOID -> STRING
    protected function _getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    // :: VOID -> ARRAY
    protected function _getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    // :: VOID -> ARRAY
    protected function _getQuery(): array
    {
        return $_GET;
    }

    // :: VOID -> STRING
    protected function _getRawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    // :: VOID -> STRING
    protected function _getContentType(): string
    {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }

    // :: INT -> VOID
    protected function _sendStatus(int $code): void
    {
        http_response_code($code);
    }

    // :: STRING, STRING -> VOID
    protected function _sendHeader(string $name, string $value): void
    {
        header("{$name}: {$value}");
    }

    // :: STRING -> VOID
    protected function _sendBody(string $body): void
    {
        echo $body;
    }

    // ── Private Methods ───────────────────────────────────────

    // :: STRING, CALLABLE, ARRAY -> $this
    private function _bindMethod(string $method, callable $handler, array $config): static
    {
        $this->_methods[$method] = [
            'handler' => $handler,
            'auth'    => $config['auth'] ?? false,
            'params'  => [],
        ];
        $this->_currentMethod = $method;
        return $this;
    }

    // :: STRING, ctgValidator, STRING, BOOL, MIXED, BOOL -> VOID
    private function _addParam(
        string $name,
        CTGValidator $validator,
        string $source,
        bool $required,
        mixed $default,
        bool $hasDefault
    ): void {
        if ($this->_currentMethod === null) {
            throw new \RuntimeException('CTGEndpoint: parameter declared before any HTTP method binding');
        }

        $methodParams = &$this->_methods[$this->_currentMethod]['params'];

        // Check for collision — same name in any source
        foreach ($methodParams as $existing) {
            if ($existing['name'] === $name) {
                throw new \RuntimeException(
                    "CTGEndpoint: duplicate parameter '{$name}' on method {$this->_currentMethod}"
                );
            }
        }

        $methodParams[] = [
            'name'       => $name,
            'validator'  => $validator,
            'source'     => $source,
            'required'   => $required,
            'default'    => $default,
            'hasDefault' => $hasDefault,
        ];
    }

    // :: ctgServerError -> VOID
    private function _sendError(CTGServerError $error): void
    {
        $this->_sendStatus($error->httpStatus);
        $this->_sendHeader('Content-Type', 'application/json; charset=utf-8');
        $this->_sendBody(json_encode([
            'success' => false,
            'result'  => $error->toResult($this->_exposeErrorDetails),
        ]));
    }

    // ── Static Methods ────────────────────────────────────────

    // :: ARRAY -> ctgEndpoint
    public static function init(array $config): static
    {
        return new static($config);
    }
}
