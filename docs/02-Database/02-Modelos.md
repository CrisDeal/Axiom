# Modelos (Active Record)

En aplicaciones PHP tradicionales, es común interactuar directamente con la base de datos desde diferentes partes de la aplicación mediante consultas SQL y arreglos asociativos:

```php
// PHP tradicional
$resultado = mysqli_query(
    $conn,
    "SELECT * FROM usuarios WHERE id = 1"
);

$usuario = mysqli_fetch_assoc($resultado);

echo $usuario['nombre'];
```

Este enfoque funciona y puede ser perfectamente válido para aplicaciones pequeñas. Sin embargo, conforme una aplicación crece, mantener las consultas, conexiones y lógica relacionada con la base de datos distribuidas por diferentes archivos puede hacer que el código sea más difícil de mantener.

Axiom utiliza el patrón **Active Record** para proporcionar una forma más estructurada de trabajar con los datos.

En términos sencillos:

> **Cada tabla de la base de datos tiene un Modelo que representa sus registros dentro de la aplicación.**

Por ejemplo, si tienes una tabla `users`, puedes crear un modelo `User` que represente los registros de esa tabla:

```text
users
  │
  ├── Registro 1 ──► User
  ├── Registro 2 ──► User
  └── Registro 3 ──► User
```

El modelo se encarga de conocer la tabla a la que pertenece y proporciona la estructura necesaria para que Axiom pueda interactuar con sus registros.

## Creando tu primer Modelo

Para crear un modelo, crea una clase que extienda de `Axiom\ORM\ActiveRecord`.

Supongamos que tenemos una tabla llamada `users`. Su modelo podría definirse de la siguiente manera:

```php
<?php

namespace App\Models;

use Axiom\ORM\ActiveRecord;

class User extends ActiveRecord
{
    /**
     * El nombre de la tabla en la base de datos.
     */
    protected static string $table = 'users';

    /**
     * Columnas que pueden asignarse mediante los datos proporcionados
     * al modelo.
     */
    protected static array $columns = [
        'name',
        'email',
        'password',
        'role',
    ];
}
```

Con esta definición, `User` queda asociado con la tabla `users`.

A partir de este momento, Axiom puede utilizar el modelo para representar y manipular los registros de dicha tabla sin que tengas que definir manualmente la lógica de conexión en cada modelo.

## Propiedades de configuración

Axiom utiliza algunas convenciones para mantener los modelos sencillos. Estas convenciones pueden modificarse sobrescribiendo las siguientes propiedades estáticas:

|   Propiedad   |   Tipo   | Valor por defecto | Descripción                                                                                  |
| :-----------: | :------: | :---------------: | -------------------------------------------------------------------------------------------- |
|    `$table`   | `string` |        `''`       | Nombre de la tabla con la que trabajará el modelo.                                           |
|   `$columns`  |  `array` |        `[]`       | Lista de columnas que pueden asignarse mediante asignación masiva. Es obligatorio definirla. |
| `$primaryKey` | `string` |       `'id'`      | Nombre de la llave primaria de la tabla.                                                     |
|     `$db`     | `string` |    `'default'`    | Nombre de la conexión que utilizará el modelo, definida en `config/database.php`.            |

### `$table`

Define el nombre exacto de la tabla de la base de datos que representa el modelo.

```php
protected static string $table = 'users';
```

Si el modelo `User` utiliza la tabla `users`, la propiedad debe contener exactamente ese nombre.

### `$columns`

Define las columnas que pueden ser asignadas mediante los datos proporcionados al modelo.

```php
protected static array $columns = [
    'name',
    'email',
    'password',
    'role',
];
```

Esta propiedad es **obligatoria** y debe contener al menos una columna.

Axiom utiliza `$columns` para controlar qué datos pueden asignarse a una instancia del modelo. Las columnas incluidas en esta lista pueden recibir valores mediante el constructor o mediante una asignación directa. La llave primaria es una excepción y puede asignarse aunque no esté incluida en $columns.

Por ejemplo, si `name` y `email` están incluidos en `$columns`, puedes asignarlos al crear el modelo:

```php
$user = new User([
    'name'  => 'Ana',
    'email' => 'ana@example.com',
]);
```

También puedes asignarlos después de crear el modelo:

```php
$user->name = 'Carlos';
$user->email = 'carlos@example.com';
```

Si intentas asignar una columna que no está incluida en `$columns`, Axiom rechazará la operación:

```php
$user->is_admin = true;
```

En este caso, `is_admin` no está definida en `$columns`, por lo que Axiom lanzará una `InvalidArgumentException`.

> **Importante:** `$columns` controla qué datos pueden asignarse al modelo desde el código de la aplicación. No significa que el modelo solo pueda contener esas columnas.

Por ejemplo, una consulta a la base de datos puede devolver una columna que no esté incluida en `$columns`. Axiom podrá almacenar ese valor en el modelo aunque la columna no esté permitida para asignaciones posteriores.

Esto permite separar dos cosas:

* **Datos que el modelo puede recibir desde la aplicación:** controlados por `$columns`.
* **Datos que el modelo obtiene de la base de datos:** pueden incluir otras columnas.

Si un modelo no define `$columns` o deja la lista vacía, Axiom lanzará una `RuntimeException` al intentar crear una instancia del modelo.

### `$primaryKey`

Por defecto, Axiom asume que la llave primaria de la tabla se llama `id`.

```php
protected static string $primaryKey = 'id';
```

Si tu tabla utiliza un nombre diferente, puedes sobrescribir la propiedad:

```php
class Product extends ActiveRecord
{
    protected static string $table = 'products';

    protected static array $columns = [
        'title',
        'price',
    ];

    protected static string $primaryKey = 'codigo_producto';
}
```

No es necesario incluir la llave primaria dentro de `$columns`. Axiom permite gestionar la llave primaria internamente.

### `$db`

Define qué conexión registrada en `config/database.php` utilizará el modelo.

Por defecto, los modelos utilizan la conexión `default`:

```php
protected static string $db = 'default';
```

Si un modelo necesita utilizar otra conexión, puedes especificarla directamente:

```php
class Metric extends ActiveRecord
{
    protected static string $db = 'analytics';

    protected static string $table = 'page_views';

    protected static array $columns = [
        'url',
        'visits',
    ];
}
```

En este ejemplo, `Metric` utilizará la conexión `analytics` definida previamente en `config/database.php`.

## Protección contra Asignación Masiva

La propiedad `$columns` también cumple una función importante de seguridad.

Cuando se proporciona un arreglo de datos al modelo, Axiom verifica que cada atributo que se intenta asignar se encuentre dentro de la lista definida en `$columns`.

Por ejemplo:

```php
// Supongamos que:
// protected static array $columns = ['name', 'email'];

$user = new User([
    'name'  => 'Ana',
    'email' => 'ana@example.com',
]);
```

Ambos atributos están incluidos en `$columns`, por lo que la asignación es válida.

En cambio, si alguien intenta proporcionar un atributo que no está autorizado:

```php
$user = new User([
    'name'    => 'Ana',
    'is_admin' => true,
]);
```

Axiom rechazará la asignación y lanzará una `InvalidArgumentException`.

La misma protección se aplica cuando se intenta asignar un atributo directamente:

```php
$user->is_admin = true;
```

Si `is_admin` no forma parte de `$columns`, Axiom rechazará la asignación.

Esto evita que atributos que no forman parte de la lista permitida puedan ser asignados accidentalmente al modelo, especialmente cuando los datos provienen de formularios, peticiones HTTP o APIs.

> **Tip de Axiom:** La llave primaria, como `id`, se gestiona internamente y no necesita incluirse en `$columns`.

## Atributos Dinámicos

Los modelos de Axiom no necesitan declarar una propiedad PHP para cada columna de la tabla.

Puedes acceder y modificar los datos del modelo directamente utilizando el nombre de la columna:

```php
$user = new User();

$user->name = 'Carlos';
$user->email = 'carlos@mail.com';

echo $user->name;
```

No necesitas declarar previamente propiedades como:

```php
public string $name;
public string $email;
```

Axiom se encarga de almacenar y recuperar estos valores internamente, por lo que puedes trabajar con ellos como si fueran propiedades normales del objeto.

Las columnas que asignes de esta forma también deben estar incluidas en `$columns`. Si intentas asignar una columna que no esté definida allí, Axiom rechazará la asignación.

Por ejemplo:

```php
$user->is_admin = true;
```

Si `is_admin` no forma parte de `$columns`, la asignación no será permitida.


## Modelos y JSON

Los modelos de Axiom implementan la interfaz `JsonSerializable`, por lo que pueden serializarse directamente utilizando `json_encode()`.

Por ejemplo:

```php
$user = User::find(1);

echo json_encode($user);
```

El modelo puede convertirse directamente a JSON:

```json
{
    "id": 1,
    "name": "Ana",
    "email": "ana@example.com"
}
```

Esto resulta especialmente útil cuando un modelo forma parte de la respuesta de una API.

Por ejemplo, si utilizas el sistema de respuestas JSON de Axiom, puedes trabajar directamente con el modelo sin tener que convertir manualmente cada atributo:

```php
$res->json($user);
```

## Resumen

Un modelo en Axiom es la representación de una tabla de la base de datos dentro de tu aplicación.

Para crear uno, únicamente necesitas:

1. Crear una clase que extienda `ActiveRecord`.
2. Definir la tabla mediante `$table`.
3. Definir las columnas permitidas mediante `$columns`.

Por ejemplo:

```php
class User extends ActiveRecord
{
    protected static string $table = 'users';

    protected static array $columns = [
        'name',
        'email',
        'password',
        'role',
    ];
}
```

Las propiedades `$primaryKey` y `$db` son opcionales y permiten adaptar el modelo cuando la tabla utiliza una llave primaria diferente o cuando debe utilizar una conexión de base de datos específica.

De esta manera, el modelo concentra en un solo lugar la información necesaria para representar una tabla y trabajar con sus registros, manteniendo separada esta responsabilidad del resto de la aplicación.
