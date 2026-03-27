<?php
declare(strict_types=1);

namespace CTG\ApiServer;

class CTGRequest
{
    // ── Instance Properties ───────────────────────────────────

    private readonly string $_method;
    private readonly ?array $_params;
    private readonly array $_headers;
    private readonly ?string $_token;
    private readonly ?array $_claims;

    // ── Constructor ───────────────────────────────────────────

    // CONSTRUCTOR :: STRING, ?ARRAY, ARRAY, ?STRING, ?ARRAY -> $this
    public function __construct(
        string $method,
        ?array $params,
        array $headers,
        ?string $token,
        ?array $claims
    ) {
        $this->_method = $method;
        $this->_params = $params;
        $this->_headers = $headers;
        $this->_token = $token;
        $this->_claims = $claims;
    }

    // ── Instance Methods ──────────────────────────────────────

    // :: VOID -> STRING
    public function method(): string
    {
        return $this->_method;
    }

    // :: ?STRING -> MIXED
    public function params(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->_params;
        }
        if ($this->_params === null) {
            return null;
        }
        return $this->_params[$key] ?? null;
    }

    // :: ?STRING -> MIXED
    public function headers(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->_headers;
        }
        return $this->_headers[$key] ?? null;
    }

    // :: VOID -> ?STRING
    public function token(): ?string
    {
        return $this->_token;
    }

    // :: ?STRING -> MIXED
    public function claims(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->_claims;
        }
        if ($this->_claims === null) {
            return null;
        }
        return $this->_claims[$key] ?? null;
    }
}
