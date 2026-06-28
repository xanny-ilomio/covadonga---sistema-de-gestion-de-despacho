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
    'Database'          => $basePath . 'config/database.php',
    'Response'          => $basePath . 'helpers/Response.php',
    'JWTHelper'         => $basePath . 'helpers/JWTHelper.php',
    'AuthMiddleware'    => $basePath . 'middleware/AuthMiddleware.php',
    'User'              => $basePath . 'models/User.php',
    'Client'            => $basePath . 'models/Client.php',
    'Product'           => $basePath . 'models/Product.php',
    'Order'             => $basePath . 'models/Order.php',
    'Guide'             => $basePath . 'models/Guide.php',
    'Truck'            => $basePath . 'models/Truck.php',
    'Driver'           => $basePath . 'models/Driver.php',
    'AuthController'    => $basePath . 'controllers/AuthController.php',
    'UserController'    => $basePath . 'controllers/UserController.php',
    'ClientController'  => $basePath . 'controllers/ClientController.php',
    'CityController'    => $basePath . 'controllers/CityController.php',
    'ProductController' => $basePath . 'controllers/ProductController.php',
    'OrderController'   => $basePath . 'controllers/OrderController.php',
    'RouteController'   => $basePath . 'controllers/RouteController.php',
    'GuideController'   => $basePath . 'controllers/GuideController.php',
    'TruckController'  => $basePath . 'controllers/TruckController.php',
    'DriverController' => $basePath . 'controllers/DriverController.php',
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

    // Cities — solo lectura, para el dropdown al registrar clientes
    $resource === 'cities' && $method === 'GET' => (new CityController())->index(),
    $resource === 'cities' && $method === 'POST' => (new CityController())->store(),

    // States — para que despacho pueda asignarlos a rutas
    $resource === 'states' && $method === 'GET' => (new RouteController())->states(),
 
     // Products
    $resource === 'products' && $method === 'GET'    && $id === null => (new ProductController())->index(),
    $resource === 'products' && $method === 'GET'    && $id !== null => (new ProductController())->show($id),
    $resource === 'products' && $method === 'POST'                   => (new ProductController())->store(),
    $resource === 'products' && $method === 'PUT'    && $id !== null => (new ProductController())->update($id),
    $resource === 'products' && $method === 'DELETE' && $id !== null => (new ProductController())->destroy($id),
 
    // Orders
    $resource === 'orders' && $method === 'GET'  && $id === null => (new OrderController())->index(),
    $resource === 'orders' && $method === 'GET'  && $id !== null => (new OrderController())->show($id),
    $resource === 'orders' && $method === 'POST'                  => (new OrderController())->store(),
    $resource === 'orders' && $method === 'PUT'  && $id !== null && $subResource === 'weights'
        => (new OrderController())->updateWeights($id),
    $resource === 'orders' && $method === 'PUT'  && $id !== null && $subResource === 'status'
        => (new OrderController())->updateStatus($id),
 
    // Routes
    $resource === 'routes' && $method === 'GET'  && $id === null => (new RouteController())->index(),
    $resource === 'routes' && $method === 'GET'  && $id !== null => (new RouteController())->show($id),
    $resource === 'routes' && $method === 'POST'                  => (new RouteController())->store(),
    $resource === 'routes' && $method === 'PUT'  && $id !== null && $subResource === null
        => (new RouteController())->update($id),
    $resource === 'routes' && $method === 'PUT'  && $id !== null && $subResource === 'assign-state'
        => (new RouteController())->assignState($id),
 
    // Guides
    $resource === 'guides' && $method === 'GET'  && $id === null => (new GuideController())->index(),
    $resource === 'guides' && $method === 'GET'  && $id !== null => (new GuideController())->show($id),
    $resource === 'guides' && $method === 'POST'                  => (new GuideController())->store(),

    // Trucks
    $resource === 'trucks' && $method === 'GET'    && $id === null => (new TruckController())->index(),
    $resource === 'trucks' && $method === 'GET'    && $id !== null => (new TruckController())->show($id),
    $resource === 'trucks' && $method === 'POST'                   => (new TruckController())->store(),
    $resource === 'trucks' && $method === 'PUT'    && $id !== null => (new TruckController())->update($id),
    $resource === 'trucks' && $method === 'DELETE' && $id !== null => (new TruckController())->destroy($id),
    
    // Drivers
    $resource === 'drivers' && $method === 'GET'    && $id === null => (new DriverController())->index(),
    $resource === 'drivers' && $method === 'GET'    && $id !== null => (new DriverController())->show($id),
    $resource === 'drivers' && $method === 'POST'                   => (new DriverController())->store(),
    $resource === 'drivers' && $method === 'PUT'    && $id !== null => (new DriverController())->update($id),
    $resource === 'drivers' && $method === 'DELETE' && $id !== null => (new DriverController())->destroy($id),

    // Ruta no encontrada
    default => Response::notFound("Ruta [{$method} /{$resource}] no existe"),
};