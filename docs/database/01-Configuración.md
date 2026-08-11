# Configuración de Base de Datos

Si vienes de aplicaciones PHP tradicionales, es muy probable que estés acostumbrado a tener un archivo `conexion.php` que incluyes (`include` o `require`) al principio de cada página, o a escribir `mysqli_connect(...)` repetidas veces a lo largo de tu código.

Esto suele generar conexiones innecesarias que consumen recursos del servidor, credenciales esparcidas por el código y pantallas en blanco cuando la base de datos falla.

**Axiom** cambia este enfoque. Centraliza toda la configuración de la base de datos en un solo archivo y utiliza un concepto llamado **Lazy Loading (Carga Perezosa)**.

Esto significa que Axiom leerá tu configuración, pero **no establecerá una conexión con la base de datos hasta el momento exacto en que sea necesaria**. Si un usuario visita una página que no requiere acceso a la base de datos, el servidor no gastará recursos estableciendo conexiones innecesarias.

## El archivo de configuración

Para comenzar a utilizar la base de datos, únicamente necesitas definir las credenciales de conexión.

Axiom soporta de forma nativa los siguientes drivers:

* **PDO** — Recomendado.
* **MySQLi**.

Crea o edita el archivo `config/database.php` y retorna un arreglo con la siguiente estructura:

```php
<?php

return [
    // Define cuál de las conexiones registradas será la principal (por defecto)
    'default' => 'axiom_main',

    'connections' => [
        // Conexión principal usando PDO (Recomendado)
        'axiom_main' => [
            'driver'   => 'pdo',
            'host'     => '127.0.0.1',
            'user'     => 'root',
            'password' => 'tu_contraseña_segura',
            'database' => 'mi_aplicacion',
            'port'     => 3306,
            'charset'  => 'utf8mb4',
        ],

        // Conexión secundaria
        'analytics' => [
            'driver'   => 'mysqli',
            'host'     => '10.0.0.5',
            'user'     => 'data_reader',
            'password' => 'secret',
            'database' => 'app_metrics',
            'port'     => 3306,
        ],
    ],
];
```

### Conexión predeterminada

La propiedad `default` permite indicar cuál de las conexiones registradas será utilizada como conexión principal cuando no se especifique una conexión explícitamente.

```php
'default' => 'axiom_main',
```

El valor debe coincidir con una de las claves definidas dentro de `connections`.

### Múltiples conexiones

Axiom permite registrar múltiples conexiones a diferentes bases de datos dentro del mismo archivo de configuración.

Por ejemplo:

```php
'connections' => [
    'axiom_main' => [
        // ...
    ],

    'analytics' => [
        // ...
    ],
],
```

Esto resulta útil cuando una aplicación necesita trabajar con diferentes bases de datos, por ejemplo, una base de datos principal y otra destinada exclusivamente a estadísticas o análisis.

> **Tip de Axiom:** Se recomienda utilizar el driver `pdo`. PDO es una interfaz estándar de PHP para trabajar con diferentes motores de bases de datos y proporciona mecanismos robustos para trabajar con consultas preparadas.

## Opciones de configuración

Cada conexión puede definir las siguientes opciones:

|   Opción   | Descripción                                           | Ejemplo                |
| :--------: | ----------------------------------------------------- | ---------------------- |
|  `driver`  | Driver utilizado para establecer la conexión.         | `pdo`, `mysqli`        |
|   `host`   | Dirección del servidor de base de datos.              | `127.0.0.1`            |
|   `user`   | Usuario utilizado para autenticarse.                  | `root`                 |
| `password` | Contraseña del usuario.                               | `tu_contraseña_segura` |
| `database` | Nombre de la base de datos.                           | `mi_aplicacion`        |
|   `port`   | Puerto utilizado por el servidor de base de datos.    | `3306`                 |
|  `charset` | Codificación de caracteres utilizada por la conexión. | `utf8mb4`              |

> **Valores predeterminados:** Las opciones `port` y `charset` son opcionales. Si no se especifican dentro de una conexión, Axiom utilizará automáticamente los siguientes valores:
>
> |   Opción  | Valor predeterminado |
> | :-------: | :------------------: |
> |   `port`  |        `3306`        |
> | `charset` |       `utf8mb4`      |
>
> Por lo tanto, una conexión como la siguiente es válida:
>
> ```php
> 'axiom_main' => [
>     'driver'   => 'pdo',
>     'host'     => '127.0.0.1',
>     'user'     => 'root',
>     'password' => 'tu_contraseña_segura',
>     'database' => 'mi_aplicacion',
> ],
> ```
>
> En este caso, Axiom utilizará `3306` como puerto y `utf8mb4` como conjunto de caracteres.


## Manejo de Errores (Fail Fast)

En PHP tradicional, si la contraseña de la base de datos era incorrecta, el código podía continuar ejecutándose hasta producir errores en otras partes de la aplicación. Esto puede dificultar la identificación del problema y provocar errores poco claros.

Axiom utiliza una filosofía denominada **Fail Fast (Falla Rápido)**.

Si Axiom detecta un problema con la configuración o con el establecimiento de la conexión, detiene la ejecución inmediatamente y lanza una excepción clara, permitiendo identificar el problema lo antes posible.

Axiom puede detectar, entre otros, los siguientes problemas:

* **Faltan datos:** No se proporcionó alguna opción requerida como `host`, `user` o `database`.
* **La conexión no existe:** Se intenta utilizar una conexión que no está registrada dentro de `connections`.
* **Driver incorrecto:** Se especificó un driver que Axiom no soporta, por ejemplo, `mysql` en lugar de `pdo` o `mysqli`.
* **Credenciales rechazadas:** El servidor de base de datos rechazó la conexión debido a credenciales incorrectas u otro problema relacionado con el servidor.

En estos casos, Axiom lanzará una `RuntimeException` indicando el problema encontrado.

## Lazy Loading

Axiom utiliza **Lazy Loading** para evitar establecer conexiones a la base de datos que no sean necesarias.

La configuración puede cargarse durante el inicio de la aplicación sin que esto implique abrir inmediatamente una conexión con el servidor de base de datos.

La conexión se establece únicamente cuando alguna parte de la aplicación solicita utilizarla.

Esto permite evitar conexiones innecesarias, especialmente en solicitudes que no requieren acceso a la base de datos.

### Ejemplo conceptual

Una solicitud que únicamente renderiza una página estática no necesita establecer una conexión:

```text
Solicitud
   │
   ▼
Cargar configuración
   │
   ▼
¿Se necesita la base de datos?
   │
   ├── No ──► Finalizar solicitud
   │
   └── Sí
        │
        ▼
   Establecer conexión
        │
        ▼
   Ejecutar consulta
```

De esta manera, la aplicación no necesita crear conexiones manualmente ni mantener variables globales de conexión.

## Resumen

Una vez configurado `config/database.php`, Axiom se encarga de administrar el proceso de conexión de forma transparente.

No es necesario:

* Instanciar manualmente objetos de conexión en cada archivo.
* Mantener variables globales para la conexión.
* Incluir repetidamente archivos de conexión.
* Pasar objetos de conexión manualmente entre funciones.

La aplicación únicamente necesita solicitar la conexión cuando realmente necesite interactuar con la base de datos.
