# Transacciones

Las transacciones permiten ejecutar varias operaciones sobre la base de datos como una **única unidad de trabajo**.

Esto resulta útil cuando varias operaciones dependen entre sí y deben completarse correctamente. Si todas las operaciones se ejecutan sin errores, Axiom confirma los cambios. Si alguna operación genera una excepción, Axiom revierte todos los cambios realizados dentro de la transacción.

## `transaction()`

El método `transaction()` permite ejecutar una función dentro de una transacción de base de datos.

Su uso básico es:

```php
User::transaction(function () {
    // Operaciones de base de datos
});
```

El método es estático y puede utilizarse desde cualquier modelo de Axiom:

```php
User::transaction(function () {
    // ...
});
```

La transacción utiliza la conexión configurada para el modelo que la inicia.

Por ejemplo, si `User` utiliza la conexión `default`, la transacción se ejecutará sobre esa conexión.

## Confirmar una transacción

Cuando la función proporcionada a `transaction()` termina correctamente, Axiom confirma automáticamente la transacción mediante `commit()`.

Por ejemplo:

```php
User::transaction(function () {
    $user = new User([
        'name' => 'Ana',
        'email' => 'ana@example.com'
    ]);

    $user->save();

    $profile = new Profile([
        'user_id' => $user->id
    ]);

    $profile->save();
});
```

Si ambas operaciones se ejecutan correctamente, Axiom confirma los cambios.

No es necesario llamar manualmente a `commit()`.

El flujo es:

```text
User::transaction()
        │
        ▼
beginTransaction()
        │
        ▼
Ejecutar función
        │
        ▼
¿Terminó correctamente?
        │
       Sí
        │
        ▼
     commit()
```

## Revertir una transacción

Si durante la ejecución de la función ocurre una excepción, Axiom ejecuta automáticamente `rollback()`.

Por ejemplo:

```php
User::transaction(function () {
    $user = new User([
        'name' => 'Ana',
        'email' => 'ana@example.com'
    ]);

    $user->save();

    throw new RuntimeException('No se pudo completar la operación');

    $profile = new Profile([
        'user_id' => $user->id
    ]);

    $profile->save();
});
```

Aunque el usuario se haya guardado antes de la excepción, la transacción revierte los cambios realizados dentro de ella.

El flujo en este caso es:

```text
User::transaction()
        │
        ▼
beginTransaction()
        │
        ▼
Ejecutar función
        │
        ▼
    Excepción
        │
        ▼
    rollback()
        │
        ▼
Excepción vuelve a lanzarse
```

> **Importante:** `transaction()` captura cualquier `Throwable`, ejecuta `rollback()` y vuelve a lanzar la excepción original. Por lo tanto, debes manejarla fuera de la transacción si necesitas controlar el error.

## Manejar errores

Puedes utilizar `try/catch` alrededor de la transacción para controlar los errores:

```php
try {
    User::transaction(function () {
        $user = new User([
            'name' => 'Ana',
            'email' => 'ana@example.com'
        ]);

        $user->save();

        $profile = new Profile([
            'user_id' => $user->id
        ]);

        $profile->save();
    });

    echo 'Operación completada correctamente';

} catch (Throwable $e) {
    echo 'No se pudo completar la operación';
}
```

Si alguna operación genera un error:

1. `transaction()` ejecuta `rollback()`.
2. La excepción vuelve a lanzarse.
3. El bloque `catch` puede manejarla.

## Operaciones dentro de una transacción

Dentro de `transaction()` puedes utilizar normalmente los métodos de persistencia de tus modelos:

```php
User::transaction(function () {
    $user = new User([
        'name' => 'Ana',
        'email' => 'ana@example.com'
    ]);

    $user->save();

    $user->role = 'admin';
    $user->save();

    $user->delete();
});
```

Todas las operaciones se ejecutan utilizando la misma transacción de la conexión configurada para el modelo que inició la transacción.

Esto permite agrupar operaciones como:

* Crear registros.
* Actualizar registros.
* Eliminar registros.
* Ejecutar consultas mediante los métodos disponibles del ORM.

## Ejemplo práctico

Supongamos que una aplicación necesita crear un usuario y su perfil.

Sin una transacción, podría ocurrir lo siguiente:

```text
Crear usuario       ✓
Crear perfil        ✗

Resultado:
Usuario creado
Perfil no creado
```

Esto podría dejar información incompleta en la base de datos.

Utilizando una transacción:

```php
User::transaction(function () {
    $user = new User([
        'name' => 'Ana',
        'email' => 'ana@example.com'
    ]);

    $user->save();

    $profile = new Profile([
        'user_id' => $user->id
    ]);

    $profile->save();
});
```

Si ambas operaciones terminan correctamente:

```text
Crear usuario       ✓
Crear perfil        ✓
        │
        ▼
     COMMIT
```

Si la creación del perfil genera una excepción:

```text
Crear usuario       ✓
Crear perfil        ✗
        │
        ▼
    ROLLBACK
        │
        ▼
Cambios revertidos
```

De esta manera, las operaciones pueden tratarse como una sola unidad de trabajo.

## Utilizar variables externas

La función proporcionada a `transaction()` puede utilizar variables externas mediante `use`:

```php
$user = new User([
    'name' => 'Ana',
    'email' => 'ana@example.com'
]);

$profile = new Profile();

User::transaction(function () use ($user, $profile) {
    $user->save();

    $profile->user_id = $user->id;
    $profile->save();
});
```

Esto resulta útil cuando los modelos u otros valores necesarios para la operación ya fueron creados fuera de la transacción.

## Valor de retorno

`transaction()` no devuelve el valor producido por la función.

Su retorno es:

```php
void
```

Por ejemplo:

```php
$result = User::transaction(function () {
    return 'resultado';
});

var_dump($result); // null
```

Si necesitas obtener un resultado de la operación, puedes utilizar una variable externa:

```php
$user = null;

User::transaction(function () use (&$user) {
    $user = new User([
        'name' => 'Ana',
        'email' => 'ana@example.com'
    ]);

    $user->save();
});

echo $user->id;
```

## Excepciones

`transaction()` no oculta las excepciones producidas durante la ejecución de la función.

Cuando ocurre un `Throwable`:

1. Se ejecuta `rollback()`.
2. Se vuelve a lanzar el mismo `Throwable`.
3. El código que llamó a `transaction()` puede manejarlo.

Por ejemplo:

```php
try {
    User::transaction(function () {
        // Operaciones...

        throw new RuntimeException('Error durante la transacción');
    });
} catch (Throwable $e) {
    echo $e->getMessage();
}
```

Esto permite que la aplicación decida cómo responder ante el error sin perder el comportamiento automático de `rollback()`.

## Resumen

Axiom proporciona `transaction()` para ejecutar varias operaciones de base de datos como una única unidad de trabajo.

```php
User::transaction(function () {
    // Operaciones
});
```

El comportamiento es:

| Situación                        | Acción                           |
| :------------------------------- | :------------------------------- |
| La función termina correctamente | `commit()`                       |
| La función genera un `Throwable` | `rollback()`                     |
| Después de `rollback()`          | El `Throwable` vuelve a lanzarse |
| Valor de retorno                 | `void`                           |

En general, utiliza `transaction()` cuando varias operaciones deben completarse conjuntamente y no quieres que solamente una parte de ellas quede almacenada en la base de datos.
