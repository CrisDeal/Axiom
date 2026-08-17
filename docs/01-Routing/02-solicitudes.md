# Solicitud HTTP ($req)

Si vienes de PHP tradicional, seguramente estás acostumbrado a buscar información dispersa en variables globales como `$_GET`, `$_POST`, `$_SERVER` o `$_FILES`. 

La clase `Request` de Axiom termina con ese desorden. Encapsula toda la información de la solicitud HTTP entrante en un solo objeto. Actúa como una capa protectora: toda la información ya está limpiada, normalizada y lista para usarse mediante una interfaz orientada a objetos muy predecible.

> **Seguridad (Solo lectura):** Para evitar bugs donde un fragmento de código modifica los datos accidentalmente, casi todas las propiedades del `Request` son de solo lectura (`readonly`). Una vez que la petición entra al framework, su información básica no puede ser alterada.

---

## Acceso a la Petición

No necesitas instanciar esta clase manualmente. Axiom inyecta automáticamente la instancia de `Request` como el **primer parámetro** tanto en las funciones anónimas de tus rutas como en los métodos de tus controladores.

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

---

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
| `$headers` | `array`  | Arreglo con todas las cabeceras HTTP recibidas, en minúsculas.         | `$_SERVER['HTTP_...']`               |

---

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
// Obtener el token de autorización:
$auth = $req->getHeader('Authorization');

// Es exactamente lo mismo que:
$auth = $req->getHeader('authorization');
```


### Detección de Cliente: `wantsJson()`

Devuelve `true` si el cliente (el navegador o la app) que hizo la petición espera recibir una respuesta en formato JSON. 

Resulta muy útil para controladores mixtos que pueden devolver tanto vistas HTML como respuestas de API asi como para ejecutar logica condicional. 

Este método evalúa la cabecera `Accept`, la cabecera `X-Requested-With` (común en llamadas Fetch/Axios) y el `Content-Type`.

```php
if ($req->wantsJson()) {
    // Retornar JSON
} else {
    // Retornar vista HTML
}
```

---

## Características Especiales del Framework

### Parseo Automático de JSON

En PHP legacy, leer un JSON enviado vía API es un proceso manual y propenso a errores (`json_decode(file_get_contents('php://input'))`).

Si una petición entrante incluye la cabecera `Content-Type: application/json`, Axiom leerá el cuerpo crudo de la petición y lo decodificará automáticamente. El resultado estará listo para usarse como un arreglo en `$req->body`. Si el cliente envía un JSON malformado, el framework interceptará el error y lanzará una excepción segura.


### Method Spoofing (Suplantación de Método)

Los formularios estándar en HTML únicamente soportan los métodos `GET` y `POST`. Para permitir que tu aplicación web envíe peticiones RESTful correctas (`PUT`, `PATCH` o `DELETE`), Axiom detectará automáticamente si envías un campo oculto llamado `_method` en tu formulario, o si el cliente envía la cabecera `X-HTTP-Method-Override`, y ajustará el valor de `$req->method` para coincidir.

Por ejemplo, para actualizar un usuario, el formulario se envía por POST, pero se inyecta el método real que Axiom procesará:

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