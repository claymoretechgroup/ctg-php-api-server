<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\ApiServer\CTGServerError;

// Tests for CTGServerError — construction, toResult, static factories, throwability

$config = ['output' => 'console'];

// ── Construction ───────────────────────────────────────────────

CTGTest::init('construct with all parameters')
    ->stage('create', fn($_) => new CTGServerError('NOT_FOUND', 'User not found', 404, ['id' => 42]))
    ->assert('type is NOT_FOUND', fn($e) => $e->type, 'NOT_FOUND')
    ->assert('msg is set', fn($e) => $e->msg, 'User not found')
    ->assert('httpStatus is 404', fn($e) => $e->httpStatus, 404)
    ->assert('details is set', fn($e) => $e->details, ['id' => 42])
    ->assert('getCode returns 1000', fn($e) => $e->getCode(), 1000)
    ->assert('getMessage returns message', fn($e) => $e->getMessage(), 'User not found')
    ->start(null, $config);

CTGTest::init('construct with default message and status')
    ->stage('create', fn($_) => new CTGServerError('INTERNAL_ERROR'))
    ->assert('msg defaults to empty', fn($e) => $e->msg, '')
    ->assert('httpStatus defaults to 500', fn($e) => $e->httpStatus, 500)
    ->assert('details defaults to null', fn($e) => $e->details, null)
    ->assert('getCode returns 2000', fn($e) => $e->getCode(), 2000)
    ->start(null, $config);

CTGTest::init('construct with CONFLICT type')
    ->stage('create', fn($_) => new CTGServerError('CONFLICT', 'Email exists', 409))
    ->assert('type is CONFLICT', fn($e) => $e->type, 'CONFLICT')
    ->assert('getCode returns 1002', fn($e) => $e->getCode(), 1002)
    ->assert('httpStatus is 409', fn($e) => $e->httpStatus, 409)
    ->start(null, $config);

// ── getHttpStatus ──────────────────────────────────────────────

CTGTest::init('httpStatus is accessible')
    ->stage('create', fn($_) => new CTGServerError('FORBIDDEN', 'No access', 403))
    ->assert('httpStatus returns 403', fn($e) => $e->httpStatus, 403)
    ->start(null, $config);

// ── toResult ───────────────────────────────────────────────────

CTGTest::init('toResult — default (no details exposed)')
    ->stage('create', fn($_) => new CTGServerError('NOT_FOUND', 'User not found', 404, ['id' => 42]))
    ->assert('has type', fn($e) => $e->toResult()['type'], 'NOT_FOUND')
    ->assert('has message', fn($e) => $e->toResult()['message'], 'User not found')
    ->assert('no details key', fn($e) => array_key_exists('details', $e->toResult()), false)
    ->start(null, $config);

CTGTest::init('toResult(true) — details exposed')
    ->stage('create', fn($_) => new CTGServerError('NOT_FOUND', 'User not found', 404, ['id' => 42]))
    ->assert('has type', fn($e) => $e->toResult(true)['type'], 'NOT_FOUND')
    ->assert('has message', fn($e) => $e->toResult(true)['message'], 'User not found')
    ->assert('has details', fn($e) => $e->toResult(true)['details'], ['id' => 42])
    ->start(null, $config);

CTGTest::init('toResult(false) — details omitted')
    ->stage('create', fn($_) => new CTGServerError('CONFLICT', 'exists', 409, ['field' => 'email']))
    ->assert('no details key', fn($e) => array_key_exists('details', $e->toResult(false)), false)
    ->start(null, $config);

CTGTest::init('toResult — no details set, expose true')
    ->stage('create', fn($_) => new CTGServerError('INTERNAL_ERROR', 'fail', 500))
    ->assert('details is null when exposed', fn($e) => $e->toResult(true)['details'], null)
    ->start(null, $config);

// ── Extends Exception ──────────────────────────────────────────

CTGTest::init('is an Exception')
    ->stage('create', fn($_) => new CTGServerError('NOT_FOUND', 'test', 404))
    ->assert('instanceof Exception', fn($e) => $e instanceof \Exception, true)
    ->start(null, $config);

CTGTest::init('throwable and catchable by type')
    ->stage('execute', function($_) {
        try {
            throw new CTGServerError('FORBIDDEN', 'No access', 403);
        } catch (CTGServerError $e) {
            return $e->type;
        }
    })
    ->assert('caught by type', fn($r) => $r, 'FORBIDDEN')
    ->start(null, $config);

CTGTest::init('catchable as generic Exception')
    ->stage('execute', function($_) {
        try {
            throw new CTGServerError('INTERNAL_ERROR', 'boom', 500);
        } catch (\Exception $e) {
            return $e instanceof CTGServerError;
        }
    })
    ->assert('caught as Exception', fn($r) => $r, true)
    ->start(null, $config);

// ── TYPES map codes ────────────────────────────────────────────

CTGTest::init('all TYPES codes')
    ->stage('execute', fn($_) => [
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
    ->assert('NOT_FOUND', fn($r) => $r[0], 1000)
    ->assert('FORBIDDEN', fn($r) => $r[1], 1001)
    ->assert('CONFLICT', fn($r) => $r[2], 1002)
    ->assert('INVALID', fn($r) => $r[3], 1003)
    ->assert('METHOD_NOT_ALLOWED', fn($r) => $r[4], 1004)
    ->assert('PAYLOAD_TOO_LARGE', fn($r) => $r[5], 1005)
    ->assert('INVALID_CONTENT_TYPE', fn($r) => $r[6], 1006)
    ->assert('INVALID_BODY', fn($r) => $r[7], 1007)
    ->assert('INTERNAL_ERROR', fn($r) => $r[8], 2000)
    ->start(null, $config);

// ── Static Factories ───────────────────────────────────────────

// notFound
CTGTest::init('notFound — defaults')
    ->stage('create', fn($_) => CTGServerError::notFound())
    ->assert('type is NOT_FOUND', fn($e) => $e->type, 'NOT_FOUND')
    ->assert('msg is default', fn($e) => $e->msg, 'Resource not found')
    ->assert('httpStatus is 404', fn($e) => $e->httpStatus, 404)
    ->assert('details is null', fn($e) => $e->details, null)
    ->start(null, $config);

CTGTest::init('notFound — custom message and details')
    ->stage('create', fn($_) => CTGServerError::notFound('User not found', ['id' => 42]))
    ->assert('msg is custom', fn($e) => $e->msg, 'User not found')
    ->assert('details is set', fn($e) => $e->details, ['id' => 42])
    ->assert('httpStatus is 404', fn($e) => $e->httpStatus, 404)
    ->start(null, $config);

// forbidden
CTGTest::init('forbidden — defaults')
    ->stage('create', fn($_) => CTGServerError::forbidden())
    ->assert('type is FORBIDDEN', fn($e) => $e->type, 'FORBIDDEN')
    ->assert('msg is default', fn($e) => $e->msg, 'Forbidden')
    ->assert('httpStatus is 403', fn($e) => $e->httpStatus, 403)
    ->assert('details is null', fn($e) => $e->details, null)
    ->start(null, $config);

CTGTest::init('forbidden — custom message and details')
    ->stage('create', fn($_) => CTGServerError::forbidden('Admin required', ['role' => 'viewer']))
    ->assert('msg is custom', fn($e) => $e->msg, 'Admin required')
    ->assert('details is set', fn($e) => $e->details, ['role' => 'viewer'])
    ->start(null, $config);

// conflict
CTGTest::init('conflict — defaults')
    ->stage('create', fn($_) => CTGServerError::conflict())
    ->assert('type is CONFLICT', fn($e) => $e->type, 'CONFLICT')
    ->assert('msg is default', fn($e) => $e->msg, 'Conflict')
    ->assert('httpStatus is 409', fn($e) => $e->httpStatus, 409)
    ->assert('details is null', fn($e) => $e->details, null)
    ->start(null, $config);

CTGTest::init('conflict — custom message and details')
    ->stage('create', fn($_) => CTGServerError::conflict('Email exists', ['field' => 'email']))
    ->assert('msg is custom', fn($e) => $e->msg, 'Email exists')
    ->assert('details is set', fn($e) => $e->details, ['field' => 'email'])
    ->start(null, $config);

// invalid
CTGTest::init('invalid — defaults')
    ->stage('create', fn($_) => CTGServerError::invalid())
    ->assert('type is INVALID', fn($e) => $e->type, 'INVALID')
    ->assert('msg is default', fn($e) => $e->msg, 'Validation failed')
    ->assert('httpStatus is 422', fn($e) => $e->httpStatus, 422)
    ->assert('details is null', fn($e) => $e->details, null)
    ->start(null, $config);

CTGTest::init('invalid — custom message and details')
    ->stage('create', fn($_) => CTGServerError::invalid('Bad date range', ['start' => '2024-12-01']))
    ->assert('msg is custom', fn($e) => $e->msg, 'Bad date range')
    ->assert('details is set', fn($e) => $e->details, ['start' => '2024-12-01'])
    ->start(null, $config);

// internal
CTGTest::init('internal — defaults')
    ->stage('create', fn($_) => CTGServerError::internal())
    ->assert('type is INTERNAL_ERROR', fn($e) => $e->type, 'INTERNAL_ERROR')
    ->assert('msg is default', fn($e) => $e->msg, 'Internal error')
    ->assert('httpStatus is 500', fn($e) => $e->httpStatus, 500)
    ->assert('details is null', fn($e) => $e->details, null)
    ->start(null, $config);

CTGTest::init('internal — custom message and details')
    ->stage('create', fn($_) => CTGServerError::internal('DB failed', ['query' => 'SELECT']))
    ->assert('msg is custom', fn($e) => $e->msg, 'DB failed')
    ->assert('details is set', fn($e) => $e->details, ['query' => 'SELECT'])
    ->start(null, $config);

// ── Static factories are throwable ─────────────────────────────

CTGTest::init('factory result is throwable')
    ->stage('execute', function($_) {
        try {
            throw CTGServerError::notFound('gone');
        } catch (CTGServerError $e) {
            return $e->type . ':' . $e->httpStatus;
        }
    })
    ->assert('caught with correct type and status', fn($r) => $r, 'NOT_FOUND:404')
    ->start(null, $config);
