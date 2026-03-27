<?php
declare(strict_types=1);

namespace CTG\ApiServer;

class CTGCorsPolicy
{
    // ── Instance Properties ───────────────────────────────────

    private string|array|null $_origins = null;
    private ?array $_methods = null;
    private ?array $_headers = null;
    private ?array $_exposedHeaders = null;
    private ?bool $_credentials = null;
    private ?int $_maxAge = null;

    // ── Constructor ───────────────────────────────────────────

    // CONSTRUCTOR :: VOID -> $this
    public function __construct()
    {
    }

    // ── Instance Methods ──────────────────────────────────────

    // :: STRING|ARRAY -> $this
    public function origins(string|array $origins): static
    {
        $this->_origins = $origins;
        return $this;
    }

    // :: STRING|ARRAY -> $this
    public function methods(string|array $methods): static
    {
        $this->_methods = is_string($methods) ? [$methods] : $methods;
        return $this;
    }

    // :: STRING|ARRAY -> $this
    public function headers(string|array $headers): static
    {
        $this->_headers = is_string($headers) ? [$headers] : $headers;
        return $this;
    }

    // :: STRING|ARRAY -> $this
    public function exposedHeaders(string|array $headers): static
    {
        $this->_exposedHeaders = is_string($headers) ? [$headers] : $headers;
        return $this;
    }

    // :: BOOL -> $this
    public function credentials(bool $allow): static
    {
        $this->_credentials = $allow;
        return $this;
    }

    // :: INT -> $this
    public function maxAge(int $seconds): static
    {
        $this->_maxAge = $seconds;
        return $this;
    }

    // :: VOID -> $this
    public function validate(): static
    {
        // Origins required
        if ($this->_origins === null) {
            throw new \RuntimeException('CORS: origins required');
        }
        if (is_string($this->_origins) && $this->_origins === '') {
            throw new \RuntimeException('CORS: origins must not be empty');
        }
        if (is_array($this->_origins) && count($this->_origins) === 0) {
            throw new \RuntimeException('CORS: origins must not be empty');
        }

        // Wildcard + credentials
        if ($this->_origins === '*' && $this->_credentials === true) {
            throw new \RuntimeException('CORS: wildcard origin cannot be used with credentials');
        }

        // Valid max age
        if ($this->_maxAge !== null && $this->_maxAge < 0) {
            throw new \RuntimeException('CORS: maxAge must be non-negative');
        }

        return $this;
    }

    // :: VOID -> ARRAY
    public function export(): array
    {
        $this->validate();

        $result = [];
        $result['origins'] = $this->_origins;

        if ($this->_methods !== null) {
            $result['methods'] = $this->_methods;
        }
        if ($this->_headers !== null) {
            $result['headers'] = $this->_headers;
        }
        if ($this->_exposedHeaders !== null) {
            $result['exposedHeaders'] = $this->_exposedHeaders;
        }
        if ($this->_credentials !== null) {
            $result['credentials'] = $this->_credentials;
        }
        if ($this->_maxAge !== null) {
            $result['maxAge'] = $this->_maxAge;
        }

        return $result;
    }

    // :: STRING, STRING -> ARRAY
    // Returns an array of header name => value pairs for CORS resolution.
    // Used by CTGEndpoint to send headers via its own _sendHeader method.
    public function resolveHeaders(string $origin, string $requestMethod): array
    {
        $headers = [];

        // Origin matching
        if ($this->_origins === '*') {
            $headers['Access-Control-Allow-Origin'] = '*';
        } elseif (is_array($this->_origins)) {
            if (in_array($origin, $this->_origins, true)) {
                $headers['Access-Control-Allow-Origin'] = $origin;
                $headers['Vary'] = 'Origin';
            }
        } elseif (is_string($this->_origins) && $this->_origins === $origin) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Vary'] = 'Origin';
        }

        // Credentials
        if ($this->_credentials === true) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        // Exposed headers
        if ($this->_exposedHeaders !== null && count($this->_exposedHeaders) > 0) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', $this->_exposedHeaders);
        }

        // Preflight-specific headers
        if (strtoupper($requestMethod) === 'OPTIONS') {
            if ($this->_methods !== null && count($this->_methods) > 0) {
                $headers['Access-Control-Allow-Methods'] = implode(', ', $this->_methods);
            }
            if ($this->_headers !== null && count($this->_headers) > 0) {
                $headers['Access-Control-Allow-Headers'] = implode(', ', $this->_headers);
            }
            if ($this->_maxAge !== null) {
                $headers['Access-Control-Max-Age'] = (string) $this->_maxAge;
            }
        }

        return $headers;
    }

    // :: VOID -> VOID
    public function resolve(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $headers = $this->resolveHeaders($origin, $requestMethod);
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    // ── Static Methods ────────────────────────────────────────

    // :: VOID -> ctgCorsPolicy
    public static function init(): static
    {
        return new static();
    }

    // :: ARRAY -> ctgCorsPolicy
    // Reconstructs a CTGCorsPolicy from an exported array.
    public static function fromArray(array $data): static
    {
        $policy = new static();
        if (isset($data['origins'])) {
            $policy->origins($data['origins']);
        }
        if (isset($data['methods'])) {
            $policy->methods($data['methods']);
        }
        if (isset($data['headers'])) {
            $policy->headers($data['headers']);
        }
        if (isset($data['exposedHeaders'])) {
            $policy->exposedHeaders($data['exposedHeaders']);
        }
        if (isset($data['credentials'])) {
            $policy->credentials($data['credentials']);
        }
        if (isset($data['maxAge'])) {
            $policy->maxAge($data['maxAge']);
        }
        return $policy;
    }
}
