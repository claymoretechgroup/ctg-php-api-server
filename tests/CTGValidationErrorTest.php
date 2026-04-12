<?php
declare(strict_types=1);


use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;
use CTG\ApiServer\CTGValidationError;

$pipelines = [];

// Tests for CTGValidationError — construction, accessors, throwability


// ── Construction with PREP_FAILED ──────────────────────────────

$pipelines[] = CTGTest::init('construct with PREP_FAILED code')
    ->stage('create', fn(CTGTestState $state) => new CTGValidationError('PREP_FAILED', 'Expected integer'))
    ->assert('getCode returns 3000', fn(CTGTestState $state) => $state->getSubject()->getCode(), CTGTestPredicates::equals(3000))
    ->assert('getErrorCode returns PREP_FAILED', fn(CTGTestState $state) => $state->getSubject()->getErrorCode(), CTGTestPredicates::equals('PREP_FAILED'))
    ->assert('getMessage returns message', fn(CTGTestState $state) => $state->getSubject()->getMessage(), CTGTestPredicates::equals('Expected integer'))
    ;

$pipelines[] = CTGTest::init('construct with PREP_FAILED and context')
    ->stage('create', fn(CTGTestState $state) => new CTGValidationError('PREP_FAILED', 'bad value', ['input' => 'abc']))
    ->assert('getContext returns context', fn(CTGTestState $state) => $state->getSubject()->getContext(), CTGTestPredicates::equals(['input' => 'abc']))
    ;

// ── Construction with CHECK_FAILED ─────────────────────────────

$pipelines[] = CTGTest::init('construct with CHECK_FAILED code')
    ->stage('create', fn(CTGTestState $state) => new CTGValidationError('CHECK_FAILED', 'Must be positive'))
    ->assert('getCode returns 3001', fn(CTGTestState $state) => $state->getSubject()->getCode(), CTGTestPredicates::equals(3001))
    ->assert('getErrorCode returns CHECK_FAILED', fn(CTGTestState $state) => $state->getSubject()->getErrorCode(), CTGTestPredicates::equals('CHECK_FAILED'))
    ->assert('getMessage returns message', fn(CTGTestState $state) => $state->getSubject()->getMessage(), CTGTestPredicates::equals('Must be positive'))
    ;

$pipelines[] = CTGTest::init('construct with CHECK_FAILED and context')
    ->stage('create', fn(CTGTestState $state) => new CTGValidationError('CHECK_FAILED', 'too small', ['min' => 1]))
    ->assert('getContext returns context', fn(CTGTestState $state) => $state->getSubject()->getContext(), CTGTestPredicates::equals(['min' => 1]))
    ;

// ── Default message and null context ───────────────────────────

$pipelines[] = CTGTest::init('construct with default message')
    ->stage('create', fn(CTGTestState $state) => new CTGValidationError('PREP_FAILED'))
    ->assert('getMessage returns empty string', fn(CTGTestState $state) => $state->getSubject()->getMessage(), CTGTestPredicates::equals(''))
    ;

$pipelines[] = CTGTest::init('construct without context — getContext returns null')
    ->stage('create', fn(CTGTestState $state) => new CTGValidationError('CHECK_FAILED', 'failed'))
    ->assert('getContext is null', fn(CTGTestState $state) => $state->getSubject()->getContext(), CTGTestPredicates::isNull())
    ;

// ── Extends Exception ──────────────────────────────────────────

$pipelines[] = CTGTest::init('is an Exception')
    ->stage('create', fn(CTGTestState $state) => new CTGValidationError('PREP_FAILED', 'test'))
    ->assert('instanceof Exception', fn(CTGTestState $state) => $state->getSubject() instanceof \Exception, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('is throwable and catchable')
    ->stage('execute', function(CTGTestState $state) {
        try {
            throw new CTGValidationError('CHECK_FAILED', 'validation failed');
        } catch (CTGValidationError $e) {
            return $e->getErrorCode();
        }
    })
    ->assert('caught by type', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CHECK_FAILED'))
    ;

$pipelines[] = CTGTest::init('catchable as generic Exception')
    ->stage('execute', function(CTGTestState $state) {
        try {
            throw new CTGValidationError('PREP_FAILED', 'bad');
        } catch (\Exception $e) {
            return $e instanceof CTGValidationError;
        }
    })
    ->assert('caught as Exception', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('construct — unknown code throws InvalidArgumentException')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            new CTGValidationError('NONEXISTENT_CODE', 'test');
            return 'no exception';
        } catch (\InvalidArgumentException $e) {
            return 'thrown';
        }
    })
    ->assert('throws', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('thrown'))
    ;

return $pipelines;
