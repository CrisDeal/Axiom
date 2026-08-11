# Persistencia

Los modelos de Axiom no solo permiten consultar registros. También puedes utilizarlos para **guardar los cambios realizados en la base de datos**.

Por ejemplo, puedes crear un modelo, asignarle algunos valores y guardarlo:

```php
$user = new User();

$user->name = 'Ana';
$user->email = 'ana@example.com';
$user->role = 'user';

$user->save();
```

Axiom se encarga de determinar qué operación debe realizar en la base de datos.

Los principales métodos para guardar y eliminar registros son:

|   Método   | Descripción                                   |
| :--------: | --------------------------------------------- |
| `create()` | Crea un nuevo registro en la base de datos.   |
| `update()` | Actualiza un registro existente.              |
|  `save()`  | Crea o actualiza el registro automáticamente. |
| `delete()` | Elimina el registro de la base de datos.      |

## Crear un registro

### `create()`

El método `create()` guarda el modelo como un **nuevo registro** en la base de datos.

Por ejemplo:

```php
$user = new User();

$user->name = 'Ana';
$user->email = 'ana@example.com';
$user->password = 'secret';
$user->role = 'user';

$user->create();
```

También puedes proporcionar los valores al momento de crear la instancia utilizando un arreglo asociativo. Axiom asignará cada valor del arreglo a la propiedad correspondiente del modelo.

Por ejemplo:

```php
$user = new User([
    'name' => 'Ana',
    'email' => 'ana@example.com'
]);

$user->save();
```

Esto es equivalente a:

```php
$user = new User();

$user->name = 'Ana';
$user->email = 'ana@example.com';

$user->save();
```

Axiom utilizará los valores del modelo para crear una nueva fila en la tabla `users`.

Si la base de datos genera automáticamente la llave primaria, Axiom obtiene ese valor y lo asigna al modelo después de crear el registro.

Por ejemplo, después de ejecutar:

```php
$user->create();
```

puedes acceder al `id` generado:

```php
echo $user->id;
```

Si la base de datos generó el valor `15`, el modelo tendrá:

```php
$user->id; // 15
```

> **Importante:** `create()` está pensado para crear un registro nuevo. Si el modelo ya tiene una llave primaria, utiliza `update()` o `save()` para actualizarlo.

## Actualizar un registro

### `update()`

El método `update()` guarda los cambios de un registro que ya existe en la base de datos.

Primero puedes obtener un usuario:

```php
$user = User::find(1);
```

Después puedes modificar sus valores:

```php
$user->name = 'Carlos';
$user->email = 'carlos@example.com';
```

Y finalmente guardar los cambios:

```php
$user->update();
```

Axiom utilizará la llave primaria del modelo para determinar qué registro debe actualizar.

Por ejemplo, si el usuario tiene:

```php
$user->id; // 1
```

la actualización se realizará sobre el registro cuyo `id` sea `1`.

Puedes modificar únicamente los valores que necesites:

```php
$user = User::find(1);

$user->role = 'admin';

$user->update();
```

El resto de los datos del registro permanecerán sin cambios.

> **Importante:** `update()` está pensado para modelos que representan un registro existente y que cuentan con una llave primaria.

## Crear o actualizar automáticamente

### `save()`

El método `save()` es una forma sencilla de guardar un modelo sin tener que decidir manualmente si debes utilizar `create()` o `update()`.

Axiom comprueba si el modelo tiene un valor para su llave primaria:

* **Si no tiene llave primaria:** crea un nuevo registro.
* **Si tiene llave primaria:** actualiza el registro existente.

Por ejemplo, para crear un nuevo usuario:

```php
$user = new User();

$user->name = 'Ana';
$user->email = 'ana@example.com';

$user->save();
```

Como el modelo todavía no tiene `id`, Axiom creará un nuevo registro.

Después de guardarlo, la llave primaria generada por la base de datos queda almacenada en el modelo:

```php
echo $user->id;
```

Si posteriormente modificas el mismo modelo:

```php
$user->role = 'admin';

$user->save();
```

ahora que el modelo tiene una llave primaria, Axiom actualizará el registro correspondiente en lugar de crear uno nuevo.

### ¿Cuándo utilizar `save()`?

`save()` resulta especialmente útil cuando no quieres preocuparte por determinar manualmente si un modelo es nuevo o ya existe.

Por ejemplo:

```php
$user = User::find(1);

$user->name = 'Carlos';
$user->save();
```

En este caso, `save()` detectará que el modelo tiene una llave primaria y realizará la actualización.

Para un modelo nuevo:

```php
$user = new User();

$user->name = 'Carlos';
$user->save();
```

Axiom detectará que todavía no tiene una llave primaria y creará el registro.

De esta forma, puedes utilizar el mismo método para ambos casos.

## Eliminar un registro

### `delete()`

El método `delete()` elimina de la base de datos el registro representado por el modelo.

Por ejemplo:

```php
$user = User::find(1);

$user->delete();
```

Axiom utilizará la llave primaria del modelo para identificar el registro que debe eliminar.

También puedes comprobar si el registro fue eliminado:

```php
$user = User::find(1);

if ($user !== null && $user->delete()) {
    echo 'Usuario eliminado';
}
```

`delete()` devuelve `true` cuando la operación afecta al menos un registro y `false` cuando no se eliminó ningún registro.

> **Importante:** `delete()` elimina el registro de la base de datos. El objeto que tienes en memoria no se elimina automáticamente, por lo que debes tenerlo en cuenta si continúas utilizándolo después de la operación.

## El ciclo de vida de un modelo

Los métodos anteriores pueden utilizarse juntos para representar el ciclo habitual de un registro.

### 1. Crear

```php
$user = new User();

$user->name = 'Ana';
$user->email = 'ana@example.com';

$user->save();
```

En este momento, Axiom crea un nuevo registro y asigna al modelo la llave primaria generada.

### 2. Consultar

```php
$user = User::find(1);
```

Ahora puedes trabajar con un registro existente.

### 3. Modificar

```php
$user->email = 'nuevo@example.com';
```

Los cambios se encuentran únicamente en el modelo mientras no los guardes.

### 4. Guardar

```php
$user->save();
```

Como el modelo ya tiene una llave primaria, Axiom actualiza el registro correspondiente.

### 5. Eliminar

```php
$user->delete();
```

El registro se elimina de la base de datos.

El flujo completo puede verse así:

```text
new User()
    │
    ▼
asignar valores
    │
    ▼
save()
    │
    ├── Sin llave primaria ──► CREATE
    │
    └── Con llave primaria ──► UPDATE
                                │
                                ▼
                              delete()
                                │
                                ▼
                             DELETE
```

## ¿Qué devuelve cada método?

Los métodos de persistencia devuelven un valor booleano que indica si la operación se ejecutó correctamente.

|   Método   | Resultado                                                            |
| :--------: | -------------------------------------------------------------------- |
| `create()` | `true` si la creación se ejecutó correctamente.                      |
| `update()` | `true` si la actualización se ejecutó correctamente.                 |
|  `save()`  | `true` si la creación o actualización se ejecutó correctamente.      |
| `delete()` | `true` si se eliminó al menos un registro; de lo contrario, `false`. |

En el caso de `create()`, además de devolver `true`, Axiom asigna al modelo la llave primaria generada por la base de datos.

## Resumen

Axiom proporciona cuatro métodos principales para guardar y eliminar registros:

* **`create()`** crea un nuevo registro.
* **`update()`** actualiza un registro existente.
* **`save()`** crea o actualiza automáticamente según el modelo tenga o no una llave primaria.
* **`delete()`** elimina el registro representado por el modelo.

Por ejemplo:

```php
// Crear
$user = new User();
$user->name = 'Ana';
$user->email = 'ana@example.com';
$user->save();

// Actualizar
$user->name = 'Ana García';
$user->save();

// Eliminar
$user->delete();
```

Con estos métodos, el modelo puede encargarse tanto de representar los datos de un registro como de guardar los cambios realizados sobre él.
