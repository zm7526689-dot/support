<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/bootstrap.php';

$router = new Router();

// Auth
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index']);

// Customers
$router->get('/customers', [CustomersController::class, 'index']);
$router->get('/customers/create', [CustomersController::class, 'create']);
$router->post('/customers/create', [CustomersController::class, 'create']);
$router->get('/customers/edit', [CustomersController::class, 'edit']);
$router->post('/customers/edit', [CustomersController::class, 'edit']);
$router->post('/customers/delete', [CustomersController::class, 'delete']);

// Tickets
$router->get('/tickets', [TicketsController::class, 'index']);
$router->get('/tickets/create', [TicketsController::class, 'create']);
$router->post('/tickets/create', [TicketsController::class, 'create']);
$router->get('/tickets/assignForm', [TicketsController::class, 'assignForm']);
$router->post('/tickets/assign', [TicketsController::class, 'assign']);
$router->get('/tickets/show', [TicketsController::class, 'show']);

// Reports
$router->post('/reports/create', [ReportsController::class, 'create']);

// Knowledge
$router->post('/knowledge/promote', [KnowledgeController::class, 'promote']);

// Users
$router->get('/users', [UsersController::class, 'index']);
$router->get('/users/create', [UsersController::class, 'create']);
$router->post('/users/create', [UsersController::class, 'create']);
$router->get('/users/edit', [UsersController::class, 'edit']);
$router->post('/users/edit', [UsersController::class, 'edit']);
$router->post('/users/delete', [UsersController::class, 'delete']);
$router->get('/users/password', [UsersController::class, 'password']);
$router->post('/users/password', [UsersController::class, 'password']);

$router->dispatch();