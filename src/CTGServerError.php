<?php
declare(strict_types=1);

namespace CTG\ApiServer;

class CTGServerError extends \Exception
{
    // ── Constants ──────────────────────────────────────────────

    const TYPES = [
        'NOT_FOUND'            => 1000,
        'FORBIDDEN'            => 1001,
        'CONFLICT'             => 1002,
        'INVALID'              => 1003,
        'METHOD_NOT_ALLOWED'   => 1004,
        'PAYLOAD_TOO_LARGE'    => 1005,
        'INVALID_CONTENT_TYPE' => 1006,
        'INVALID_BODY'         => 1007,
        'INTERNAL_ERROR'       => 2000,
    ];

    // ── Instance Properties ───────────────────────────────────

    public readonly string $type;
    public readonly string $msg;
    public readonly int $httpStatus;
    public readonly mixed $details;

    // ── Constructor ───────────────────────────────────────────

    // CONSTRUCTOR :: STRING, STRING, INT, MIXED -> $this
    public function __construct(
        string $type,
        string $message = '',
        int $httpStatus = 500,
        mixed $details = null
    ) {
        $this->type = $type;
        $this->msg = $message;
        $this->httpStatus = $httpStatus;
        $this->details = $details;
        $intCode = self::TYPES[$type] ?? 0;
        parent::__construct($message, $intCode);
    }

    // ── Instance Methods ──────────────────────────────────────

    // :: VOID -> INT
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    // :: BOOL -> ARRAY
    public function toResult(bool $exposeDetails = false): array
    {
        $result = [
            'type'    => $this->type,
            'message' => $this->msg,
        ];
        if ($exposeDetails) {
            $result['details'] = $this->details;
        }
        return $result;
    }

    // :: VOID -> VOID
    public function send(): void
    {
        http_response_code($this->httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'result'  => $this->toResult(),
        ]);
    }

    // ── Static Methods ────────────────────────────────────────

    // :: ?STRING, MIXED -> ctgServerError
    public static function notFound(?string $message = null, mixed $details = null): static
    {
        return new static('NOT_FOUND', $message ?? 'Resource not found', 404, $details);
    }

    // :: ?STRING, MIXED -> ctgServerError
    public static function forbidden(?string $message = null, mixed $details = null): static
    {
        return new static('FORBIDDEN', $message ?? 'Forbidden', 403, $details);
    }

    // :: ?STRING, MIXED -> ctgServerError
    public static function conflict(?string $message = null, mixed $details = null): static
    {
        return new static('CONFLICT', $message ?? 'Conflict', 409, $details);
    }

    // :: ?STRING, MIXED -> ctgServerError
    public static function invalid(?string $message = null, mixed $details = null): static
    {
        return new static('INVALID', $message ?? 'Validation failed', 422, $details);
    }

    // :: ?STRING, MIXED -> ctgServerError
    public static function internal(?string $message = null, mixed $details = null): static
    {
        return new static('INTERNAL_ERROR', $message ?? 'Internal error', 500, $details);
    }
}
