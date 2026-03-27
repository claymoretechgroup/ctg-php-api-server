<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\ApiServer\CTGValidationError;

// Tests for CTGValidationError — construction, accessors, throwability

$config = ['output' => 'console'];

// ── Construction with PREP_FAILED ──────────────────────────────

CTGTest::init('construct with PREP_FAILED code')
    ->stage('create', fn($_) => new CTGValidationError('PREP_FAILED', 'Expected integer'))
    ->assert('getCode returns 3000', fn($e) => $e->getCode(), 3000)
    ->assert('getErrorCode returns PREP_FAILED', fn($e) => $e->getErrorCode(), 'PREP_FAILED')
    ->assert('getMessage returns message', fn($e) => $e->getMessage(), 'Expected integer')
    ->start(null, $config);

CTGTest::init('construct with PREP_FAILED and context')
    ->stage('create', fn($_) => new CTGValidationError('PREP_FAILED', 'bad value', ['input' => 'abc']))
    ->assert('getContext returns context', fn($e) => $e->getContext(), ['input' => 'abc'])
    ->start(null, $config);

// ── Construction with CHECK_FAILED ─────────────────────────────

CTGTest::init('construct with CHECK_FAILED code')
    ->stage('create', fn($_) => new CTGValidationError('CHECK_FAILED', 'Must be positive'))
    ->assert('getCode returns 3001', fn($e) => $e->getCode(), 3001)
    ->assert('getErrorCode returns CHECK_FAILED', fn($e) => $e->getErrorCode(), 'CHECK_FAILED')
    ->assert('getMessage returns message', fn($e) => $e->getMessage(), 'Must be positive')
    ->start(null, $config);

CTGTest::init('construct with CHECK_FAILED and context')
    ->stage('create', fn($_) => new CTGValidationError('CHECK_FAILED', 'too small', ['min' => 1]))
    ->assert('getContext returns context', fn($e) => $e->getContext(), ['min' => 1])
    ->start(null, $config);

// ── Default message and null context ───────────────────────────

CTGTest::init('construct with default message')
    ->stage('create', fn($_) => new CTGValidationError('PREP_FAILED'))
    ->assert('getMessage returns empty string', fn($e) => $e->getMessage(), '')
    ->start(null, $config);

CTGTest::init('construct without context — getContext returns null')
    ->stage('create', fn($_) => new CTGValidationError('CHECK_FAILED', 'failed'))
    ->assert('getContext is null', fn($e) => $e->getContext(), null)
    ->start(null, $config);

// ── Extends Exception ──────────────────────────────────────────

CTGTest::init('is an Exception')
    ->stage('create', fn($_) => new CTGValidationError('PREP_FAILED', 'test'))
    ->assert('instanceof Exception', fn($e) => $e instanceof \Exception, true)
    ->start(null, $config);

CTGTest::init('is throwable and catchable')
    ->stage('execute', function($_) {
        try {
            throw new CTGValidationError('CHECK_FAILED', 'validation failed');
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('caught by type', fn($r) => $r, 'CHECK_FAILED')
    ->start(null, $config);

CTGTest::init('catchable as generic Exception')
    ->stage('execute', function($_) {
        try {
            throw new CTGValidationError('PREP_FAILED', 'bad');
        } catch (\Exception $e) {
            return $e instanceof CTGValidationError;
        }
    })
    ->assert('caught as Exception', fn($r) => $r, true)
    ->start(null, $config);
