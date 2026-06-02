<?php
#cargar .env
$envFile  = '/var/www/backend/.env';

if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

#clases
$basePath = '/var/www/backend/src/';


$classMap=[
    #Config
    'Database'        => $basePath . 'config/database.php',
    #Helpers
    'Response'        => $basePath . 'helpers/Response.php',
    'JWTHelper'       => $basePath . 'helpers/JWTHelper.php',
    #Middleware
    'AuthMiddleware'  => $basePath . 'middleware/AuthMiddleware.php',
    #Models
    'User'            => $basePath . 'models/User.php',
    'Client'          => $basePath . 'models/Client.php',
    'Product'         => $basePath . 'models/Product.php',
    'Order'           => $basePath . 'models/Order.php',
    #Controllers
    'AuthController'    => $basePath . 'controllers/AuthController.php',
    'UserController'    => $basePath . 'controllers/UserController.php',
    'ClientController'  => $basePath . 'controllers/ClientController.php',
    'ProductController' => $basePath . 'controllers/ProductController.php',
    'OrderController'   => $basePath . 'controllers/OrderController.php',
];

spl_autoload_register(function (string $class) use ($classMap){
    if(isset($classMap[$class])){
        require_once $classMap[$class];
    }
});

#CORS
require_once $basePath . 'config/cors.php';

#capture url and method
$method = $_SERVER['REQUEST_METHOD'];
$uri= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri= rtrim($uri, '/');
 
$uri = preg_replace('#^/api#', '', $uri);
 
$parts = array_values(array_filter(explode('/', $uri)));

$resource = $parts[0] ?? '';
$id = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
$subResource = $parts[2] ?? null; // para /orders/{id}/status

#ROUTING
match(true) {
    // Auth — única ruta sin middleware
    $resource === 'auth' && $parts[1] === 'login' && $method === 'POST'
        => (new AuthController())->login(),
 
    // Users
    $resource === 'users' && $method === 'GET' && $id !== null => (new UserController())->show($id),
 
    // Clients
    $resource === 'clients' && $method === 'GET'  && $id === null => (new ClientController())->index(),
    $resource === 'clients' && $method === 'GET'  && $id !== null => (new ClientController())->show($id),
    $resource === 'clients' && $method === 'POST'                  => (new ClientController())->store(),
    $resource === 'clients' && $method === 'PUT'  && $id !== null  => (new ClientController())->update($id),
    $resource === 'clients' && $method === 'DELETE' && $id !== null => (new ClientController())->destroy($id),
 
    // Products
    $resource === 'products' && $method === 'GET'  && $id === null => (new ProductController())->index(),
    $resource === 'products' && $method === 'GET'  && $id !== null => (new ProductController())->show($id),
    $resource === 'products' && $method === 'POST'                  => (new ProductController())->store(),
    $resource === 'products' && $method === 'PUT'  && $id !== null  => (new ProductController())->update($id),
    $resource === 'products' && $method === 'DELETE' && $id !== null => (new ProductController())->destroy($id),
 
    // Orders
    $resource === 'orders' && $method === 'GET'  && $id === null   => (new OrderController())->index(),
    $resource === 'orders' && $method === 'GET'  && $id !== null   => (new OrderController())->show($id),
    $resource === 'orders' && $method === 'POST'                    => (new OrderController())->store(),
    $resource === 'orders' && $method === 'PUT'  && $id !== null && $subResource === 'status'
        => (new OrderController())->updateStatus($id),
 
    // Ruta no encontrada
    default => Response::notFound("Ruta [{$method} /{$resource}] no existe"),
};