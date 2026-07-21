# Enrutamiento (Router)

El enrutador de Axiom es el encargado de interceptar las peticiones HTTP y dirigirlas al bloque de código adecuado. Soporta rutas estáticas, parámetros dinámicos con validación mediante expresiones regulares, agrupaciones de rutas y middlewares.

## Registro Básico de Rutas

Las rutas se definen a través de la instancia principal de la aplicación (`$app`). Puedes registrar rutas para los verbos HTTP estándar utilizando funciones anónimas (closures) o apuntando directamente a un Controlador.

### Usando Funciones Anónimas
```php
$app->get('/ping', function($req,$res) {
    // La respuesta se maneja utilizando el objeto $res
});
```

### Usando Controladores (Recomendado)

Para mantener el código organizado, puedes delegar la lógica a una clase. Axiom instanciará automáticamente el controlador:

Clase  -> UserController::class 
Método -> index

```php
use App\Controllers\UserController;

$app->get('/users', [UserController::class, 'index']);
$app->post('/users', [UserController::class, 'store']);
```

### Parámetros Dinámicos
Puedes capturar segmentos de la URL definiéndolos entre llaves {}. Los valores capturados estarán disponibles dentro del objeto $req->params.

```php
$app->get('/productos/{id}', function($req,$res) {
    $productoId =$req->params['id'];
    // ...
});
```

### Usando Controladores (Recomendado)
Restricciones en Parámetros (Constraints)
Para evitar que una ruta procese datos incorrectos, Axiom permite aplicar restricciones directamente en la definición de la ruta usando la sintaxis {parametro:filtro}.

Filtros predefinidos:

- int: Solo acepta caracteres numéricos.
- string: Solo acepta letras.
- alnum: Acepta una combinación de letras y números.

```php
// Solo coincidirá si el ID es un número (ej. /usuarios/42)
// Si la URL es /usuarios/abc, el Router devolverá un error 404 (Not Found).
$app->get('/usuarios/{id:int}', [UserController::class, 'show']);
```

También es posible escribir una expresión regular personalizada directamente si el filtro no existe en el diccionario:

```php
$app->get('/archivos/{file:\w+\.pdf}', [FileController::class, 'download']);
```


### Middlewares
Los middlewares te permiten interceptar la petición antes de que llegue al controlador (útil para validación de sesiones, formateo de datos, etc.). Puedes encadenar el método ->middleware() inmediatamente después de definir una ruta.

```php
use App\Middlewares\AuthMiddleware;
use App\Middlewares\LogMiddleware;

$app->post('/admin/dashboard', [AdminController::class, 'index'])
    ->middleware(AuthMiddleware::class, LogMiddleware::class);

$app->post('/admin/dashboard', [AdminController::class, 'index'])
    ->middleware(AuthMiddleware::class);
    ->middleware(LogMiddleware::class);
```

Axiom ejecutará los middlewares en el orden exacto en el que fueron pasados.

### Grupos de Rutas.
Para aplicaciones con decenas de rutas, la mejor práctica es dividir las definiciones en archivos separados y "montarlas" en la aplicación principal utilizando la clase Router.

1. Archivo de rutas (ej. routes/api.php):

```php
use Axiom\Http\Router\Router;
use App\Controllers\ApiController;

$router = new Router();$router->get('/status', [ApiController::class, 'status']);
// ... más rutas del grupo

return $router;
```

2. Montaje en el punto de entrada (index.php):

```php 
$apiRoutes = require __DIR__ . '/../routes/api.php';

// Montar el grupo en la aplicación principal
$app->mount($apiRoutes);
```