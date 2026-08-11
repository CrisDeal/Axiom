# Consultas SQL

Axiom permite ejecutar consultas SQL directamente mediante el método `rawQuery()`.

Este método está pensado para los casos en los que necesitas ejecutar una consulta que no puede expresarse fácilmente mediante los métodos de consulta disponibles en el ORM.

Por ejemplo:

```php
$users = User::rawQuery(
    'SELECT * FROM users WHERE role = ?',
    ['admin']
);
```

Axiom prepara y ejecuta la consulta utilizando la conexión configurada para el modelo.

## `rawQuery()`

El método `rawQuery()` recibe dos argumentos:

```php
User::rawQuery(string $query, array $params = []);
```

| Parámetro | Tipo     | Descripción                                                  |
| :-------- | :------- | :----------------------------------------------------------- |
| `$query`  | `string` | Consulta SQL que se desea ejecutar.                          |
| `$params` | `array`  | Valores que serán utilizados como parámetros de la consulta. |

El segundo parámetro es opcional:

```php
User::rawQuery('SELECT * FROM users');
```

También puedes proporcionar parámetros:

```php
User::rawQuery(
    'SELECT * FROM users WHERE role = ?',
    ['admin']
);
```

## Consultas `SELECT`

Cuando la consulta es un `SELECT`, `rawQuery()` devuelve un arreglo de modelos.

Por ejemplo:

```php
$users = User::rawQuery(
    'SELECT * FROM users WHERE role = ?',
    ['admin']
);
```

Cada resultado es convertido en una instancia del modelo:

```php
foreach ($users as $user) {
    echo $user->name;
}
```

Esto permite continuar trabajando con los resultados utilizando las propiedades y métodos del modelo.

Por ejemplo:

```php
$users = User::rawQuery(
    'SELECT * FROM users WHERE age >= ?',
    [18]
);

foreach ($users as $user) {
    echo $user->name;
}
```

Los resultados son equivalentes a los modelos obtenidos mediante los métodos de consulta de Axiom.

## Consultas de escritura

Las consultas que no son `SELECT` se consideran consultas de escritura y devuelven un valor booleano.

Esto incluye consultas como:

* `INSERT`
* `UPDATE`
* `DELETE`

Por ejemplo:

```php
$success = User::rawQuery(
    'UPDATE users SET role = ? WHERE id = ?',
    ['admin', 1]
);
```

Si la operación afecta al menos un registro, el resultado será `true`:

```php
if ($success) {
    echo 'Usuario actualizado';
}
```

Otro ejemplo utilizando `DELETE`:

```php
$success = User::rawQuery(
    'DELETE FROM users WHERE id = ?',
    [1]
);
```

## Parámetros de consulta

Puedes utilizar parámetros para proporcionar valores dinámicos a una consulta:

```php
$users = User::rawQuery(
    'SELECT * FROM users WHERE name = ? AND role = ?',
    ['Ana', 'admin']
);
```

Los valores se proporcionan en el mismo orden en el que aparecen los marcadores `?`:

```text
WHERE name = ? AND role = ?
       │              │
       │              └── 'admin'
       │
       └── 'Ana'
```

Esto permite separar la consulta SQL de los valores utilizados en ella.

> **Importante:** utiliza parámetros para los valores dinámicos de las consultas en lugar de concatenarlos directamente dentro del SQL.

Por ejemplo, evita:

```php
$name = 'Ana';

User::rawQuery(
    "SELECT * FROM users WHERE name = '$name'"
);
```

En su lugar, utiliza parámetros:

```php
User::rawQuery(
    'SELECT * FROM users WHERE name = ?',
    [$name]
);
```

## Consultas dentro de transacciones

`rawQuery()` también puede utilizarse dentro de una transacción.

Por ejemplo:

```php
User::transaction(function () {
    User::rawQuery(
        'UPDATE users SET role = ? WHERE id = ?',
        ['admin', 1]
    );

    User::rawQuery(
        'UPDATE users SET role = ? WHERE id = ?',
        ['admin', 2]
    );
});
```

Si ambas consultas terminan correctamente, la transacción se confirma.

Si alguna de ellas genera una excepción, la transacción se revierte.

## Consultas más complejas

Una de las principales ventajas de `rawQuery()` es que puedes utilizar SQL que no esté disponible mediante los métodos de consulta del ORM.

Por ejemplo:

```php
$users = User::rawQuery(
    'SELECT users.*, profiles.bio
     FROM users
     INNER JOIN profiles ON profiles.user_id = users.id
     WHERE users.role = ?
     ORDER BY users.name',
    ['admin']
);
```

Los resultados continuarán siendo instancias de `User`:

```php
foreach ($users as $user) {
    echo $user->name;
    echo $user->bio;
}
```

> **Importante:** cuando utilizas `rawQuery()` con un `SELECT`, los resultados se hidratan como instancias del modelo sobre el que se ejecuta el método. Por lo tanto, las columnas devueltas por la consulta deben ser compatibles con los atributos que esperas utilizar en dicho modelo.

## Consultas de inserción

También puedes utilizar `rawQuery()` para insertar registros directamente:

```php
$success = User::rawQuery(
    'INSERT INTO users (name, email) VALUES (?, ?)',
    ['Ana', 'ana@example.com']
);
```

El resultado será un booleano:

```php
if ($success) {
    echo 'Usuario creado';
}
```

A diferencia de `create()`, esta operación utiliza directamente el SQL proporcionado y no hidrata ni actualiza automáticamente una instancia de `User` con los valores generados por la base de datos.

## Consultas de actualización

Puedes ejecutar una actualización directamente mediante SQL:

```php
$success = User::rawQuery(
    'UPDATE users SET role = ? WHERE id = ?',
    ['admin', 1]
);
```

El resultado indica si la operación afectó al menos un registro.

```php
if ($success) {
    echo 'Registro actualizado';
}
```

## Consultas de eliminación

También puedes eliminar registros utilizando SQL:

```php
$success = User::rawQuery(
    'DELETE FROM users WHERE id = ?',
    [1]
);
```

Puedes comprobar el resultado:

```php
if ($success) {
    echo 'Usuario eliminado';
}
```

## Resultado de `rawQuery()`

El valor devuelto depende del tipo de consulta:

| Tipo de consulta | Retorno    |
| :--------------- | :--------- |
| `SELECT`         | `static[]` |
| `INSERT`         | `bool`     |
| `UPDATE`         | `bool`     |
| `DELETE`         | `bool`     |

Por ejemplo, un `SELECT` devuelve modelos:

```php
$users = User::rawQuery(
    'SELECT * FROM users',
);
```

Mientras que una operación de escritura devuelve un booleano:

```php
$success = User::rawQuery(
    'DELETE FROM users WHERE id = ?',
    [1]
);
```

La firma del método es:

```php
public static function rawQuery(
    string $query,
    array $params = []
): array|bool
```

## ¿Cuándo utilizar `rawQuery()`?

Utiliza `rawQuery()` cuando necesites ejecutar SQL directamente y los métodos de consulta de Axiom no sean suficientes para expresar la operación.

Por ejemplo:

```php
$users = User::rawQuery(
    'SELECT users.*, COUNT(orders.id) AS orders_count
     FROM users
     LEFT JOIN orders ON orders.user_id = users.id
     GROUP BY users.id'
);
```

Para operaciones habituales, se recomienda utilizar los métodos específicos del ORM cuando estén disponibles:

```php
$users = User::where([
    'role' => 'admin'
]);
```

Mientras que `rawQuery()` puede utilizarse para consultas que requieren mayor control sobre el SQL:

```php
$users = User::rawQuery(
    'SELECT ...
     FROM ...
     JOIN ...
     GROUP BY ...
     HAVING ...'
);
```

## Resumen

`rawQuery()` permite ejecutar SQL directamente desde un modelo de Axiom:

```php
User::rawQuery($query, $params);
```

El comportamiento depende de la consulta:

* **`SELECT`** → devuelve un arreglo de modelos.
* **`INSERT`** → devuelve `true` o `false`.
* **`UPDATE`** → devuelve `true` o `false`.
* **`DELETE`** → devuelve `true` o `false`.

Los valores dinámicos deben proporcionarse mediante el arreglo `$params`:

```php
User::rawQuery(
    'SELECT * FROM users WHERE email = ?',
    [$email]
);
```

De esta manera, `rawQuery()` proporciona una vía de escape para trabajar directamente con SQL sin abandonar el contexto del modelo de Axiom.
