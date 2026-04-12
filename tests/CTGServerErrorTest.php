<?php
declare(strict_types=1);


use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;
use CTG\ApiServer\CTGServerError;

$pipelines = [];

// Tests for CTGServerError — construction, toResult, static factories, throwability


// ── Construction ───────────────────────────────────────────────

$pipelines[] = CTGTest::init('construct with all parameters')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('NOT_FOUND', 'User not found', 404, ['id' => 42]))
    ->assert('type is NOT_FOUND', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('NOT_FOUND'))
    ->assert('msg is set', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('User not found'))
    ->assert('httpStatus is 404', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(404))
    ->assert('details is set', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::equals(['id' => 42]))
    ->assert('getCode returns 1000', fn(CTGTestState $state) => $state->getSubject()->getCode(), CTGTestPredicates::equals(1000))
    ->assert('getMessage returns message', fn(CTGTestState $state) => $state->getSubject()->getMessage(), CTGTestPredicates::equals('User not found'))
    ;

$pipelines[] = CTGTest::init('construct with default message and status')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('INTERNAL_ERROR'))
    ->assert('msg defaults to empty', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals(''))
    ->assert('httpStatus defaults to 500', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(500))
    ->assert('details defaults to null', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::isNull())
    ->assert('getCode returns 2000', fn(CTGTestState $state) => $state->getSubject()->getCode(), CTGTestPredicates::equals(2000))
    ;

$pipelines[] = CTGTest::init('construct with CONFLICT type')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('CONFLICT', 'Email exists', 409))
    ->assert('type is CONFLICT', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('CONFLICT'))
    ->assert('getCode returns 1002', fn(CTGTestState $state) => $state->getSubject()->getCode(), CTGTestPredicates::equals(1002))
    ->assert('httpStatus is 409', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(409))
    ;

// ── getHttpStatus ──────────────────────────────────────────────

$pipelines[] = CTGTest::init('httpStatus is accessible')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('FORBIDDEN', 'No access', 403))
    ->assert('httpStatus returns 403', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(403))
    ;

// ── toResult ───────────────────────────────────────────────────

$pipelines[] = CTGTest::init('toResult — default (no details exposed)')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('NOT_FOUND', 'User not found', 404, ['id' => 42]))
    ->assert('has type', fn(CTGTestState $state) => $state->getSubject()->toResult()['type'], CTGTestPredicates::equals('NOT_FOUND'))
    ->assert('has message', fn(CTGTestState $state) => $state->getSubject()->toResult()['message'], CTGTestPredicates::equals('User not found'))
    ->assert('no details key', fn(CTGTestState $state) => array_key_exists('details', $state->getSubject()->toResult()), CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('toResult(true) — details exposed')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('NOT_FOUND', 'User not found', 404, ['id' => 42]))
    ->assert('has type', fn(CTGTestState $state) => $state->getSubject()->toResult(true)['type'], CTGTestPredicates::equals('NOT_FOUND'))
    ->assert('has message', fn(CTGTestState $state) => $state->getSubject()->toResult(true)['message'], CTGTestPredicates::equals('User not found'))
    ->assert('has details', fn(CTGTestState $state) => $state->getSubject()->toResult(true)['details'], CTGTestPredicates::equals(['id' => 42]))
    ;

$pipelines[] = CTGTest::init('toResult(false) — details omitted')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('CONFLICT', 'exists', 409, ['field' => 'email']))
    ->assert('no details key', fn(CTGTestState $state) => array_key_exists('details', $state->getSubject()->toResult(false)), CTGTestPredicates::isFalse())
    ;

$pipelines[] = CTGTest::init('toResult — no details set, expose true')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('INTERNAL_ERROR', 'fail', 500))
    ->assert('details is null when exposed', fn(CTGTestState $state) => $state->getSubject()->toResult(true)['details'], CTGTestPredicates::isNull())
    ;

// ── Extends Exception ──────────────────────────────────────────

$pipelines[] = CTGTest::init('is an Exception')
    ->stage('create', fn(CTGTestState $state) => new CTGServerError('NOT_FOUND', 'test', 404))
    ->assert('instanceof Exception', fn(CTGTestState $state) => $state->getSubject() instanceof \Exception, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('throwable and catchable by type')
    ->stage('execute', function(CTGTestState $state) {
        try {
            throw new CTGServerError('FORBIDDEN', 'No access', 403);
        } catch (CTGServerError $e) {
            return $e->type;
        }
    })
    ->assert('caught by type', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('FORBIDDEN'))
    ;

$pipelines[] = CTGTest::init('catchable as generic Exception')
    ->stage('execute', function(CTGTestState $state) {
        try {
            throw new CTGServerError('INTERNAL_ERROR', 'boom', 500);
        } catch (\Exception $e) {
            return $e instanceof CTGServerError;
        }
    })
    ->assert('caught as Exception', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

// ── TYPES map codes ────────────────────────────────────────────

$pipelines[] = CTGTest::init('all TYPES codes')
    ->stage('execute', fn(CTGTestState $state) => [
        (new CTGServerError('NOT_FOUND'))->getCode(),
        (new CTGServerError('FORBIDDEN'))->getCode(),
        (new CTGServerError('CONFLICT'))->getCode(),
        (new CTGServerError('INVALID'))->getCode(),
        (new CTGServerError('METHOD_NOT_ALLOWED'))->getCode(),
        (new CTGServerError('PAYLOAD_TOO_LARGE'))->getCode(),
        (new CTGServerError('INVALID_CONTENT_TYPE'))->getCode(),
        (new CTGServerError('INVALID_BODY'))->getCode(),
        (new CTGServerError('INTERNAL_ERROR'))->getCode(),
    ])
    ->assert('NOT_FOUND', fn(CTGTestState $state) => $state->getSubject()[0], CTGTestPredicates::equals(1000))
    ->assert('FORBIDDEN', fn(CTGTestState $state) => $state->getSubject()[1], CTGTestPredicates::equals(1001))
    ->assert('CONFLICT', fn(CTGTestState $state) => $state->getSubject()[2], CTGTestPredicates::equals(1002))
    ->assert('INVALID', fn(CTGTestState $state) => $state->getSubject()[3], CTGTestPredicates::equals(1003))
    ->assert('METHOD_NOT_ALLOWED', fn(CTGTestState $state) => $state->getSubject()[4], CTGTestPredicates::equals(1004))
    ->assert('PAYLOAD_TOO_LARGE', fn(CTGTestState $state) => $state->getSubject()[5], CTGTestPredicates::equals(1005))
    ->assert('INVALID_CONTENT_TYPE', fn(CTGTestState $state) => $state->getSubject()[6], CTGTestPredicates::equals(1006))
    ->assert('INVALID_BODY', fn(CTGTestState $state) => $state->getSubject()[7], CTGTestPredicates::equals(1007))
    ->assert('INTERNAL_ERROR', fn(CTGTestState $state) => $state->getSubject()[8], CTGTestPredicates::equals(2000))
    ;

// ── Static Factories ───────────────────────────────────────────

// notFound
$pipelines[] = CTGTest::init('notFound — defaults')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::notFound())
    ->assert('type is NOT_FOUND', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('NOT_FOUND'))
    ->assert('msg is default', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('Resource not found'))
    ->assert('httpStatus is 404', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(404))
    ->assert('details is null', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('notFound — custom message and details')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::notFound('User not found', ['id' => 42]))
    ->assert('msg is custom', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('User not found'))
    ->assert('details is set', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::equals(['id' => 42]))
    ->assert('httpStatus is 404', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(404))
    ;

// forbidden
$pipelines[] = CTGTest::init('forbidden — defaults')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::forbidden())
    ->assert('type is FORBIDDEN', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('FORBIDDEN'))
    ->assert('msg is default', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('Forbidden'))
    ->assert('httpStatus is 403', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(403))
    ->assert('details is null', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('forbidden — custom message and details')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::forbidden('Admin required', ['role' => 'viewer']))
    ->assert('msg is custom', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('Admin required'))
    ->assert('details is set', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::equals(['role' => 'viewer']))
    ;

// conflict
$pipelines[] = CTGTest::init('conflict — defaults')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::conflict())
    ->assert('type is CONFLICT', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('CONFLICT'))
    ->assert('msg is default', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('Conflict'))
    ->assert('httpStatus is 409', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(409))
    ->assert('details is null', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('conflict — custom message and details')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::conflict('Email exists', ['field' => 'email']))
    ->assert('msg is custom', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('Email exists'))
    ->assert('details is set', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::equals(['field' => 'email']))
    ;

// invalid
$pipelines[] = CTGTest::init('invalid — defaults')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::invalid())
    ->assert('type is INVALID', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('INVALID'))
    ->assert('msg is default', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('Validation failed'))
    ->assert('httpStatus is 422', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(422))
    ->assert('details is null', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('invalid — custom message and details')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::invalid('Bad date range', ['start' => '2024-12-01']))
    ->assert('msg is custom', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('Bad date range'))
    ->assert('details is set', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::equals(['start' => '2024-12-01']))
    ;

// internal
$pipelines[] = CTGTest::init('internal — defaults')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::internal())
    ->assert('type is INTERNAL_ERROR', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('INTERNAL_ERROR'))
    ->assert('msg is default', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('Internal error'))
    ->assert('httpStatus is 500', fn(CTGTestState $state) => $state->getSubject()->httpStatus, CTGTestPredicates::equals(500))
    ->assert('details is null', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('internal — custom message and details')
    ->stage('create', fn(CTGTestState $state) => CTGServerError::internal('DB failed', ['query' => 'SELECT']))
    ->assert('msg is custom', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('DB failed'))
    ->assert('details is set', fn(CTGTestState $state) => $state->getSubject()->details, CTGTestPredicates::equals(['query' => 'SELECT']))
    ;

// ── Static factories are throwable ─────────────────────────────

$pipelines[] = CTGTest::init('factory result is throwable')
    ->stage('execute', function(CTGTestState $state) {
        try {
            throw CTGServerError::notFound('gone');
        } catch (CTGServerError $e) {
            return $e->type . ':' . $e->httpStatus;
        }
    })
    ->assert('caught with correct type and status', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('NOT_FOUND:404'))
    ;

$pipelines[] = CTGTest::init('construct — unknown type throws InvalidArgumentException')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            new CTGServerError('NONEXISTENT_TYPE', 'test');
            return 'no exception';
        } catch (\InvalidArgumentException $e) {
            return 'thrown';
        }
    })
    ->assert('throws', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('thrown'))
    ;

return $pipelines;
