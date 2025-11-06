<?php
require __DIR__ . '/../config/bootstrap.php';

$router = new Router();

// Auth
$router->get('/login', array(AuthController::class, 'login'));
$router->post('/login', array(AuthController::class, 'login'));
$router->get('/logout', array(AuthController::class, 'logout'));

// Dashboard
$router->get('/dashboard', array(DashboardController::class, 'index'));

// Customers
('/customers/edit', array(CustomersController::class, 'edit'));
('/customers/edit', array(CustomersController::class, 'edit'));
('/customers/delete', array(CustomersController::class, 'delete'));
$router->get('/customers', array(CustomersController::class, 'index'));
$router->get('/customers/create', array(CustomersController::class, 'create'));
$router->post('/customers/create', array(CustomersController::class, 'create'));
$router->post('/customers/import', array(CustomersController::class, 'import'));
$router->get('/customers/export', array(CustomersController::class, 'export'));

// Tickets
('/tickets/assignForm', array(TicketsController::class, 'assignForm'));
$router->get('/tickets', array(TicketsController::class, 'index'));
$router->get('/tickets/create', array(TicketsController::class, 'create'));
$router->post('/tickets/create', array(TicketsController::class, 'create'));
$router->post('/tickets/assign', array(TicketsController::class, 'assign'));
$router->get('/tickets/show', array(TicketsController::class, 'show'));

// Reports
$router->post('/reports/create', array(ReportsController::class, 'create'));

// Knowledge
$router->post('/knowledge/promote', array(KnowledgeController::class, 'promote'));

// Users
('/users/edit', array(UsersController::class, 'edit'));
('/users/edit', array(UsersController::class, 'edit'));
('/users/delete', array(UsersController::class, 'delete'));
$router->get('/users', array(UsersController::class, 'index'));
$router->get('/users/create', array(UsersController::class, 'create'));
$router->post('/users/create', array(UsersController::class, 'create'));
$router->get('/users/password', array(UsersController::class, 'password'));
$router->post('/users/password', array(UsersController::class, 'password'));

$router->dispatch();