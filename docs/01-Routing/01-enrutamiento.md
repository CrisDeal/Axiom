# Enrutamiento (Router)

Si vienes de aplicaciones PHP tradicionales, probablemente estás acostumbrado a tener archivos separados para cada página (usuarios.php, productos.php) o un gran archivo lleno de condiciones if/switch.

El Enrutador (Router) de Axiom cambia esto. Centraliza todas las peticiones (URLs) en un solo lugar y decide a qué bloque de código enviarlas. Soporta rutas estáticas, parámetros dinámicos, agrupaciones y capas de seguridad (middlewares) de forma muy sencilla.

---

## Registro Básico de Rutas

Todas las rutas se definen a través de la instancia principal de tu aplicación ($app). Puedes registrar URLs para los diferentes verbos HTTP (GET, POST, PUT, DELETE) de dos maneras:

---

### Usando Funciones Anónimas (Closures)

Ideal para pruebas rápidas o endpoints muy sencillos. Axiom inyecta automáticamente dos variables: $req (los datos que entran) y $res (las herramientas para responder).
```php
$app->get('/ping', function($req, $res) {
    $res->send('¡Pong!');
});
```

Tip de Axiom: No te preocupes por las barras al final de la URL. Para Axiom, /usuarios y /usuarios/ son exactamente la misma ruta.

---

### Usando Controladores (Recomendado)

Para mantener tu código limpio y organizado, lo mejor es delegar la lógica a una clase dedicada (Controlador). Axiom se encargará de instanciar la clase automáticamente por ti.

```php
use App\Controllers\UserController;

// Sintaxis: [NombreDeLaClase::class, 'nombreDelMetodo']
$app->get('/users', [UserController::class, 'index']);
$app->post('/users', [UserController::class, 'store']);
```

---

## Rutas Dinámicas y Parámetros

Muchas veces la URL contiene información variable, como el ID de un producto. Puedes capturar estos segmentos envolviéndolos entre llaves `{}`. Los valores estarán disponibles dentro de `$req->params`.

```php
$app->get('/productos/{id}', function($req, $res) {
    $productoId = $req->params['id'];
    $res->send("Estás viendo el producto: " . $productoId);
});
```


### Restricciones en Parámetros (Filtros)

¿Qué pasa si alguien entra a `/productos/abc` en lugar de un número? Para evitar validaciones manuales aburridas, Axiom te permite aplicar restricciones directas en la URL usando la sintaxis `{parametro:filtro}`.

Filtros predefinidos:

- int: Solo acepta caracteres numéricos.
- string: Solo acepta letras.
- alnum: Acepta una combinación de letras y números.
- path: Cualquier ruta anidada después del slash

```php
// Solo coincidirá si el ID es numérico (ej. /usuarios/42)
// Si entran a /usuarios/hola, Axiom devolverá un error 404 (Not Found) automáticamente.
$app->get('/usuarios/{id:int}', [UserController::class, 'show']);
```

Si necesitas algo más específico, puedes escribir tu propia expresión regular directamente:

```php
// Solo acepta archivos que terminen en .pdf
$app->get('/archivos/{file:\w+\.pdf}', [FileController::class, 'download']);
```

---

## Middlewares (Filtros de Intercepción)

Piensa en los middlewares como "guardias de seguridad" que revisan la petición antes de que llegue a tu Controlador. Son perfectos para verificar si un usuario inició sesión, validar permisos o registrar logs.

Puedes asignar uno o más middlewares a una ruta encadenando el método ->middleware(). Axiom los ejecutará en el orden exacto en que los escribas.

```php
use App\Middlewares\AuthMiddleware;
use App\Middlewares\LogMiddleware;

// Opción A: Pasarlos todos juntos
$app->post('/admin/dashboard', [AdminController::class, 'index'])
    ->middleware(
        AuthMiddleware::class, 
        LogMiddleware::class
    );

// Opción B: Encadenar el método varias veces
$app->post('/admin/dashboard', [AdminController::class, 'index'])
    ->middleware(AuthMiddleware::class)
    ->middleware(LogMiddleware::class);
```

---

## Grupos de Rutas (Modularidad)
Cuando tu aplicación crezca, tener 50 rutas en tu index.php será un caos. Axiom te permite agrupar rutas bajo un mismo "prefijo" en archivos separados.

1. Crea un archivo para tu grupo (ej. routes/api.php):

```php
<?php
use Axiom\Http\Router\Router;
use App\Controllers\ApiController;
use App\Middlewares\ApiAuthMiddleware;

// Todas las rutas de este archivo comenzarán automáticamente con '/api'
$router = new Router('/api');

// Puedes aplicar un middleware a TODAS las rutas del grupo de un solo golpe
$router->globalMiddleware(ApiAuthMiddleware::class);

// Esta ruta en realidad será: GET /api/status
$router->get('/status', [ApiController::class, 'status']);
$router->get('/users', [ApiController::class, 'getUsers']);

// Retornamos el grupo configurado
return $router;
```

2. Móntalo en tu aplicación principal (index.php):

```php 
// Cargamos el archivo que acabamos de crear
$apiRoutes = require __DIR__ . '/routes/api.php';

// Montamos el grupo completo en la aplicación
$app->mount($apiRoutes);
```