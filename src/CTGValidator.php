<?php
declare(strict_types=1);

namespace CTG\ApiServer;

class CTGValidator
{
    // ── Instance Properties ───────────────────────────────────

    private array $_preps = [];
    private array $_checks = [];

    // ── Constructor ───────────────────────────────────────────

    // CONSTRUCTOR :: ARRAY -> $this
    public function __construct(array $config = [])
    {
        if (isset($config['prep'])) {
            $this->_preps[] = $config['prep'];
        }
        if (isset($config['check'])) {
            $this->_checks[] = $config['check'];
        }
    }

    // ── Instance Methods ──────────────────────────────────────

    // :: CALLABLE -> $this
    public function addPrep(callable $fn): static
    {
        $this->_preps[] = $fn;
        return $this;
    }

    // :: CALLABLE -> $this
    public function addCheck(callable $fn): static
    {
        $this->_checks[] = $fn;
        return $this;
    }

    // :: MIXED -> MIXED
    public function prep(mixed $value): mixed
    {
        try {
            foreach ($this->_preps as $fn) {
                $value = $fn($value);
            }
            return $value;
        } catch (CTGValidationError $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CTGValidationError('PREP_FAILED', $e->getMessage());
        }
    }

    // :: MIXED -> BOOL
    public function check(mixed $value): bool
    {
        foreach ($this->_checks as $fn) {
            if (!$fn($value)) {
                return false;
            }
        }
        return true;
    }

    // :: MIXED -> MIXED
    public function run(mixed $value): mixed
    {
        $prepped = $this->prep($value);
        if (!$this->check($prepped)) {
            throw new CTGValidationError('CHECK_FAILED');
        }
        return $prepped;
    }

    // ── Static Methods ────────────────────────────────────────

    // :: ARRAY -> ctgValidator
    public static function init(array $config = []): static
    {
        return new static($config);
    }

    // :: ARRAY -> ctgValidator
    public static function string(array $config = []): static
    {
        $v = new static();
        $v->addCheck(fn($val) => is_string($val) && $val !== '');
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function string_empty(array $config = []): static
    {
        $v = new static();
        $v->addCheck(fn($val) => is_string($val));
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function int(array $config = []): static
    {
        $v = new static();
        $v->addPrep(function ($val) {
            if (is_int($val)) {
                return $val;
            }
            if (is_string($val) && preg_match('/^-?\d+$/', $val) === 1) {
                return (int) $val;
            }
            throw new CTGValidationError('PREP_FAILED', 'Expected integer');
        });
        $v->addCheck(fn($val) => is_int($val));
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function float(array $config = []): static
    {
        $v = new static();
        $v->addPrep(function ($val) {
            if (is_float($val)) {
                return $val;
            }
            if (is_int($val)) {
                return (float) $val;
            }
            if (is_string($val) && is_numeric($val)) {
                return (float) $val;
            }
            throw new CTGValidationError('PREP_FAILED', 'Expected float');
        });
        $v->addCheck(fn($val) => is_float($val));
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function bool(array $config = []): static
    {
        $v = new static();
        $v->addPrep(function ($val) {
            if (is_bool($val)) {
                return $val;
            }
            if (is_string($val)) {
                $lower = strtolower($val);
                if ($lower === 'true') {
                    return true;
                }
                if ($lower === 'false') {
                    return false;
                }
            }
            throw new CTGValidationError('PREP_FAILED', 'Expected boolean');
        });
        $v->addCheck(fn($val) => is_bool($val));
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function boolint(array $config = []): static
    {
        $v = new static();
        $v->addPrep(function ($val) {
            if ($val === 1 || $val === 0) {
                return $val;
            }
            if ($val === '1') {
                return 1;
            }
            if ($val === '0') {
                return 0;
            }
            throw new CTGValidationError('PREP_FAILED', 'Expected 0 or 1');
        });
        $v->addCheck(fn($val) => $val === 1 || $val === 0);
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function array(array $config = []): static
    {
        $v = new static();
        $v->addCheck(fn($val) => is_array($val));
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function email(array $config = []): static
    {
        $v = static::string();
        $v->addCheck(fn($val) => filter_var($val, FILTER_VALIDATE_EMAIL) !== false);
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function url(array $config = []): static
    {
        $v = static::string();
        $v->addCheck(fn($val) => filter_var($val, FILTER_VALIDATE_URL) !== false);
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function uuid(array $config = []): static
    {
        $v = static::string();
        $v->addCheck(fn($val) => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $val) === 1);
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }

    // :: ARRAY -> ctgValidator
    public static function date(array $config = []): static
    {
        $v = static::string();
        $v->addCheck(fn($val) => strtotime($val) !== false);
        if (isset($config['prep'])) {
            $v->addPrep($config['prep']);
        }
        if (isset($config['check'])) {
            $v->addCheck($config['check']);
        }
        return $v;
    }
}
