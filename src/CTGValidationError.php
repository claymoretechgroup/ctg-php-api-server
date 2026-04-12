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
        $intCode = self::TYPES[$code]
            ?? throw new \InvalidArgumentException("Unknown CTGValidationError code: {$code}");
        $this->_errorCode = $code;
        $this->_context = $context;
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
