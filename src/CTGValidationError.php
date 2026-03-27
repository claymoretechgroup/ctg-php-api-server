<?php
declare(strict_types=1);

namespace CTG\ApiServer;

class CTGValidationError extends \Exception
{
    // ── Constants ──────────────────────────────────────────────

    const TYPES = [
        'PREP_FAILED'  => 3000,
        'CHECK_FAILED' => 3001,
    ];

    // ── Instance Properties ───────────────────────────────────

    public readonly string $_errorCode;
    public readonly mixed $_context;

    // ── Constructor ───────────────────────────────────────────

    // CONSTRUCTOR :: STRING, STRING, MIXED -> $this
    public function __construct(
        string $code,
        string $message = '',
        mixed $context = null
    ) {
        $this->_errorCode = $code;
        $this->_context = $context;
        $intCode = self::TYPES[$code] ?? 0;
        parent::__construct($message, $intCode);
    }

    // ── Instance Methods ──────────────────────────────────────

    // :: VOID -> STRING
    public function getErrorCode(): string
    {
        return $this->_errorCode;
    }

    // :: VOID -> MIXED
    public function getContext(): mixed
    {
        return $this->_context;
    }
}
