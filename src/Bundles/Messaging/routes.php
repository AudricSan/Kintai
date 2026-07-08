<?php

declare(strict_types=1);

use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Bundles\Messaging\Controllers\Web\MessageController;
use kintai\Bundles\Messaging\Controllers\Web\MessageStreamController;
use kintai\Bundles\Messaging\Controllers\Api\MessageController as ApiMessageController;

/** @var kintai\Core\Router $router */
/** @var kintai\Core\Container $container */

// =============================================================================
// Messaging — SSE Stream
// =============================================================================

$router->get('/messages/{id}/stream', [MessageStreamController::class, 'stream'], middleware: [AuthMiddleware::class], name: 'messages.stream');

// =============================================================================
// Messaging — Routes Web Admin
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/messages/compose',                    [MessageController::class, 'messagesCompose'],       name: 'admin.messages.compose');
    $r->post('/messages',                           [MessageController::class, 'messagesSend'],          name: 'admin.messages.send');
    $r->get('/messages',                            [MessageController::class, 'messages'],               name: 'admin.messages');
    $r->get('/messages/{id}',                       [MessageController::class, 'messagesThread'],         name: 'admin.messages.thread');
    $r->post('/messages/{id}',                      [MessageController::class, 'messagesReply'],          name: 'admin.messages.reply');
    $r->post('/messages/{id}/delete',               [MessageController::class, 'messagesDeleteThread'],   name: 'admin.messages.delete_thread');
    $r->post('/messages/{id}/message/{mid}/delete', [MessageController::class, 'messagesDeleteMessage'],  name: 'admin.messages.delete_message');
}, middleware: [AuthMiddleware::class, AdminMiddleware::class]);

// =============================================================================
// Messaging — Routes Web Employé
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/messages/compose',                    [MessageController::class, 'messagesCompose'],       name: 'employee.messages.compose');
    $r->post('/messages',                           [MessageController::class, 'messagesSend'],          name: 'employee.messages.send');
    $r->get('/messages',                            [MessageController::class, 'messages'],               name: 'employee.messages');
    $r->get('/messages/{id}',                       [MessageController::class, 'messagesThread'],         name: 'employee.messages.thread');
    $r->post('/messages/{id}',                      [MessageController::class, 'messagesReply'],          name: 'employee.messages.reply');
    $r->post('/messages/{id}/delete',               [MessageController::class, 'messagesDeleteThread'],   name: 'employee.messages.delete_thread');
    $r->post('/messages/{id}/message/{mid}/delete', [MessageController::class, 'messagesDeleteMessage'],  name: 'employee.messages.delete_message');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// Messaging — Routes API
// =============================================================================

$router->get('/api/v1/messages/threads',                              [ApiMessageController::class, 'listThreads'],     name: 'api.v1.messages.threads.index');
$router->post('/api/v1/messages/threads',                             [ApiMessageController::class, 'createThread'],    name: 'api.v1.messages.threads.store');
$router->get('/api/v1/messages/threads/{id}/messages',                [ApiMessageController::class, 'listMessages'],    name: 'api.v1.messages.list');
$router->post('/api/v1/messages/threads/{id}/messages',               [ApiMessageController::class, 'addMessage'],      name: 'api.v1.messages.store');
$router->get('/api/v1/messages/threads/{id}/participants',            [ApiMessageController::class, 'listParticipants'], name: 'api.v1.messages.participants.index');
$router->post('/api/v1/messages/threads/{id}/participants',           [ApiMessageController::class, 'addParticipant'],   name: 'api.v1.messages.participants.store');
$router->get('/api/v1/messages/threads/{id}/participants/{user_id}',  [ApiMessageController::class, 'getParticipant'],   name: 'api.v1.messages.participants.show');
$router->get('/api/v1/messages/threads/{id}',                         [ApiMessageController::class, 'getThread'],        name: 'api.v1.messages.threads.show');
$router->delete('/api/v1/messages/threads/{id}',                      [ApiMessageController::class, 'deleteThread'],     name: 'api.v1.messages.threads.destroy');
$router->delete('/api/v1/messages/{id}',                              [ApiMessageController::class, 'deleteMessage'],    name: 'api.v1.messages.destroy');
