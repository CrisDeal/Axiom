# Consultas

Una vez creado un modelo, puedes utilizarlo para consultar los registros de la tabla que representa.

Axiom proporciona varios métodos para realizar las consultas más comunes sin tener que escribir directamente las sentencias SQL.

Los principales métodos de consulta son:

|   Método  | Descripción                                                     |
| :-------: | --------------------------------------------------------------- |
|  `all()`  | Obtiene todos los registros de la tabla.                        |
|  `find()` | Busca un registro utilizando su llave primaria.                 |
| `where()` | Busca varios registros que cumplan determinadas condiciones.    |
| `first()` | Obtiene el primer registro que cumpla determinadas condiciones. |

Todos estos métodos trabajan con el modelo y devuelven instancias del mismo modelo en lugar de arreglos asociativos.

Por ejemplo, si tienes un modelo `User`, las consultas devolverán objetos `User`:

```php
$users = User::all();

foreach ($users as $user) {
    echo $user->name;
}
```

Esto permite trabajar con los resultados utilizando los mismos atributos y comportamientos que tiene el modelo.

## Obtener todos los registros

### `all()`

El método `all()` obtiene todos los registros de la tabla asociada al modelo.

```php
$users = User::all();
```

El resultado es un arreglo de objetos `User`:

```php
foreach ($users as $user) {
    echo $user->name;
}
```

Por ejemplo, si la tabla contiene:

```text
users
├── Ana
├── Carlos
└── Luis
```

`User::all()` devolverá los tres registros.

```php
$users = User::all();

echo $users[0]->name; // Ana
echo $users[1]->name; // Carlos
echo $users[2]->name; // Luis
```

> **Importante:** `all()` obtiene todos los registros de la tabla. Si la tabla contiene una gran cantidad de registros, considera utilizar una consulta más específica en lugar de cargar toda la tabla.

## Buscar por llave primaria

### `find()`

El método `find()` permite buscar un registro utilizando el valor de su llave primaria.

```php
$user = User::find(1);
```

En este ejemplo, Axiom buscará el registro cuyo `id` sea `1`.

El nombre de la llave primaria se obtiene de `$primaryKey`. Por lo tanto, si tu modelo utiliza una llave primaria diferente:

```php
protected static string $primaryKey = 'codigo_usuario';
```

`find()` utilizará `codigo_usuario` para realizar la búsqueda.

Si el registro existe, `find()` devuelve una instancia del modelo:

```php
$user = User::find(1);

echo $user->name;
```

Si no existe un registro con ese valor, el método devuelve `null`:

```php
$user = User::find(999);

if ($user === null) {
    echo 'Usuario no encontrado';
}
```

Esto permite comprobar fácilmente si la búsqueda encontró un resultado.

## Buscar registros con condiciones

### `where()`

El método `where()` permite buscar varios registros que cumplan una o más condiciones.

Por ejemplo, para obtener todos los usuarios con el rol `admin`:

```php
$users = User::where([
    'role' => 'admin',
]);
```

Puedes recorrer los resultados de la misma forma que con `all()`:

```php
foreach ($users as $user) {
    echo $user->name;
}
```

### Comparaciones

Cuando utilizas un valor directamente, Axiom utiliza una comparación de igualdad:

```php
$users = User::where([
    'role' => 'admin',
]);
```

Esto equivale conceptualmente a buscar:

```sql
WHERE role = 'admin'
```

También puedes especificar un operador:

```php
$users = User::where([
    'age' => ['>=', 18],
]);
```

En este caso, Axiom buscará usuarios cuya edad sea mayor o igual a `18`.

Los operadores disponibles son:

|  Operador  | Significado                        |
| :--------: | ---------------------------------- |
|     `=`    | Igual a                            |
|    `!=`    | Diferente de                       |
|     `<`    | Menor que                          |
|     `>`    | Mayor que                          |
|    `<=`    | Menor o igual que                  |
|    `>=`    | Mayor o igual que                  |
|   `LIKE`   | Coincide parcialmente con un texto |
| `NOT LIKE` | No coincide con un texto           |

Por ejemplo:

```php
$users = User::where([
    'age' => ['>=', 18],
]);
```

También puedes utilizar `LIKE` para realizar búsquedas de texto:

```php
$users = User::where([
    'name' => ['LIKE', '%Carlos%'],
]);
```

Esto buscará usuarios cuyo nombre contenga `Carlos`.

### Combinar varias condiciones

Puedes proporcionar varias condiciones dentro del mismo arreglo:

```php
$users = User::where([
    'role' => 'admin',
    'age'  => ['>=', 18],
]);
```

Cuando se proporcionan varias condiciones, Axiom requiere que **todas se cumplan**.

El ejemplo anterior equivale conceptualmente a:

```sql
WHERE role = 'admin'
  AND age >= 18
```

Por lo tanto, solo se devolverán los usuarios que sean administradores **y** tengan 18 años o más.

> **Importante:** Si utilizas un operador que no está permitido por Axiom, la consulta lanzará una `InvalidArgumentException`.

## Obtener el primer resultado

### `first()`

El método `first()` funciona de forma similar a `where()`, pero devuelve únicamente el primer registro que cumpla las condiciones.

Por ejemplo:

```php
$user = User::first([
    'email' => 'ana@example.com',
]);
```

Si existe un usuario con ese correo, recibirás una instancia de `User`:

```php
echo $user->name;
```

Si no existe ningún registro que cumpla la condición, `first()` devuelve `null`:

```php
$user = User::first([
    'email' => 'noexiste@example.com',
]);

if ($user === null) {
    echo 'Usuario no encontrado';
}
```

También puedes utilizar operadores:

```php
$user = User::first([
    'age' => ['>=', 18],
]);
```

En este caso, Axiom devolverá el primer usuario que tenga una edad de 18 años o más.

### Diferencia entre `where()` y `first()`

La principal diferencia está en la cantidad de resultados que devuelve cada método:

```php
$users = User::where([
    'role' => 'admin',
]);
```

`where()` devuelve un arreglo que puede contener varios usuarios.

En cambio:

```php
$user = User::first([
    'role' => 'admin',
]);
```

`first()` devuelve un solo usuario o `null` si no encuentra ninguno.

Puedes pensar en ellos de esta forma:

```text
where()
    └──► Usuario
    ├──► Usuario
    └──► Usuario

first()
    └──► Usuario
```

## ¿Qué devuelve cada método?

Los métodos de consulta devuelven objetos del modelo correspondiente:

|   Método  | Resultado                                             |
| :-------: | ----------------------------------------------------- |
|  `all()`  | Arreglo con todos los registros                       |
|  `find()` | Un modelo o `null`                                    |
| `where()` | Arreglo con los registros que cumplen las condiciones |
| `first()` | Un modelo o `null`                                    |

Por ejemplo:

```php
$users = User::where([
    'role' => 'admin',
]);

foreach ($users as $user) {
    echo $user->name;
}
```

Cada elemento de `$users` es una instancia de `User`, por lo que puedes acceder directamente a sus atributos:

```php
$user->name;
$user->email;
$user->role;
```

## Resumen

Axiom proporciona cuatro métodos principales para consultar registros:

* **`all()`** obtiene todos los registros de una tabla.
* **`find()`** busca un registro mediante su llave primaria.
* **`where()`** permite buscar varios registros utilizando condiciones.
* **`first()`** obtiene el primer registro que cumple determinadas condiciones.

Por ejemplo:

```php
// Todos los usuarios
$users = User::all();

// Usuario con ID 1
$user = User::find(1);

// Todos los administradores
$admins = User::where([
    'role' => 'admin',
]);

// Primer administrador
$admin = User::first([
    'role' => 'admin',
]);
```

Estos métodos permiten realizar las consultas más comunes utilizando el modelo, manteniendo las consultas SQL fuera del código de la aplicación.
