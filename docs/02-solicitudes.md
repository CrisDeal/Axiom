# Solicitud HTTP (Request)

La clase `Request` encapsula toda la información de la solicitud HTTP entrante. Actúa como una capa de abstracción sobre las variables superglobales nativas de PHP (`$_GET`, `$_POST`, `$_SERVER`, `$_FILES`), ofreciendo una interfaz orientada a objetos, limpia y predecible.

## Acceso a la Petición

Axiom inyecta automáticamente la instancia de `Request` como el primer parámetro tanto en las funciones anónimas de tus rutas como en los métodos de tus controladores.

```php
// En una función anónima (Closure)
$app->get('/users', function($req, $res) {
    $metodo = $req->method;
});

// En un método de controlador
public function store($req, $res) {
    $datos = $req->body;
}
```

## Propiedades Públicas 
Puedes acceder directamente a las siguientes propiedades para inspeccionar la petición. Toda la información ya está sanitizada y normalizada.

| Propiedad  | Tipo     | Descripción                                                            | Origen / Ejemplo                     |
|------------|----------|------------------------------------------------------------------------|--------------------------------------|
| `$method`  | `string` | Método HTTP de la Solicitud en mayúsculas.                             | GET, POST, PUT, DELETE               |
| `$url`     | `string` | La ruta URL solicitada, omitiendo la query string.                     | /api/users                           |
| `$params`  | `array`  | Parámetros dinámicos extraídos de la ruta URL por el Router.           | En /users/{id}, `$req->params['id']` |
| `$query`   | `array`  | Arreglo con los parámetros de la URL (`?clave=valor`).                 | `$_GET`                              |
| `$body`    | `array`  | Arreglo con el cuerpo de la Solicitud (JSON decodificado o Form-Data). | `$_POST` o `php://input`             |
| `$files`   | `array`  | Arreglo con los archivos subidos en la Solicitud.                      | `$_FILES`                            |


## Métodos Útiles

### Obtención Unificada de Datos: input()
Si no te importa si un dato fue enviado a través de la URL (Query String) o en el cuerpo de la petición (Body), puedes utilizar el método `input()`. Este método buscará primero en el `$body` y, si no lo encuentra, buscará en `$query`.
```php
// Buscará el campo 'email', si no existe devolverá null
$email = $req->input('email');

// Buscará el campo 'role', si no existe devolverá 'guest' (valor por defecto)
$role = $req->input('role', 'guest');
```

### Lectura de Cabeceras (Headers): getHeader()
Permite recuperar el valor de una cabecera HTTP. La búsqueda es case-insensitive, lo que significa que no importa si escribes el nombre en mayúsculas o minúsculas; Axiom normaliza los nombres internamente.
```php
// Obtener:
$auth = $req->getHeader('Authorization');

// Es exactamente lo mismo que:
$auth = $req->getHeader('authorization');
```

### Detección de Cliente: wantsJson()
Devuelve `true` si el cliente o navegador que hizo la petición espera recibir una respuesta en formato JSON. Resulta muy útil para controladores mixtos que pueden devolver tanto vistas HTML como respuestas de API asi como para ejecutar logica condicional.

Este método evalúa la cabecera `Accept`, la cabecera `X-Requested-With` (común en llamadas Fetch/Axios) y el `Content-Type`.
```php
if ($req->wantsJson()) {
    // Retornar JSON
} else {
    // Retornar vista HTML
}
```


## Características Especiales del Framework

### Parseo Automático de JSON
Si una petición entrante incluye la cabecera `Content-Type: application/json`, Axiom leerá el cuerpo crudo de la petición y lo decodificará automáticamente. El resultado estará listo para usarse como un arreglo en `$req->body`. Si el cliente envía un JSON malformado, el framework interceptará el error y lanzará una excepción.

### Method Spoofing (Suplantación de Método)
Los formularios estándar en HTML únicamente soportan los métodos `GET` y `POST`. Para permitir que tu aplicación web envíe peticiones `PUT`, `PATCH` o `DELETE`, Axiom detectará automáticamente si envías un campo oculto llamado `_method` en tu formulario, o si el cliente envía la cabecera `X-HTTP-Method-Override`, y ajustará el valor de `$req->method` para coincidir.

Para actualizar un usuario, el formulario se envía por POST, pero se inyecta el método real que Axiom procesará:
```HTML
<form action="/users/42" method="POST">
    <!-- Axiom leerá este campo e interpretará la petición como PUT -->
    <input type="hidden" name="_method" value="PUT">
    
    <input type="text" name="name" value="Cristian">
    <button type="submit">Actualizar Usuario</button>
</form>
```

Del lado del enrutador, esta petición coincidirá perfectamente con la ruta registrada como `PUT`:
```php
$app->put('/users/{id}', [UserController::class, 'update']);
```