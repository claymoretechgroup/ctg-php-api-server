<?php
declare(strict_types=1);

namespace CTG\ApiServer;

class CTGResponse
{
    // ── Instance Properties ───────────────────────────────────

    private int $_status;
    private ?string $_body;
    private array $_headers;

    // ── Constructor ───────────────────────────────────────────

    // CONSTRUCTOR :: INT, ?STRING, ARRAY -> $this
    private function __construct(int $status, ?string $body, array $headers = [])
    {
        $this->_status = $status;
        $this->_body = $body;
        $this->_headers = $headers;
    }

    // ── Instance Methods ──────────────────────────────────────

    // :: VOID -> INT
    public function getStatus(): int
    {
        return $this->_status;
    }

    // :: VOID -> ?STRING
    public function getBody(): ?string
    {
        return $this->_body;
    }

    // :: VOID -> ARRAY
    public function getHeaders(): array
    {
        return $this->_headers;
    }

    // :: VOID -> VOID
    public function send(): void
    {
        http_response_code($this->_status);
        if ($this->_body !== null) {
            header('Content-Type: application/json; charset=utf-8');
        }
        foreach ($this->_headers as $name => $value) {
            header("{$name}: {$value}");
        }
        if ($this->_body !== null) {
            echo $this->_body;
        }
    }

    // ── Static Methods ────────────────────────────────────────

    // :: MIXED, INT, ARRAY -> ctgResponse
    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $encoded = json_encode(['success' => true, 'result' => $data]);
        if ($encoded === false) {
            $encoded = '{"success":false,"result":{"type":"INTERNAL_ERROR","message":"Response encoding failed"}}';
            $status = 500;
        }
        return new static($status, $encoded, $headers);
    }

    // :: ARRAY -> ctgResponse
    public static function noContent(array $headers = []): static
    {
        return new static(204, null, $headers);
    }
}
