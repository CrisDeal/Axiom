# Respuesta HTTP ($res)

En el PHP tradicional, para enviar información al navegador utilizábamos un montón de funciones sueltas: `echo`, `print`, `header()`, `http_response_code()`, o `include`. Esto a menudo provocaba código desordenado y el temido error fatal: *"Cannot modify header information - headers already sent"*.

La clase `Response` de Axiom resuelve esto. Centraliza toda la salida de tu aplicación en un solo lugar. Actúa como un asistente que se encarga de formatear tus datos, preparar las cabeceras correctamente y enviarlas al navegador de forma segura.

---

## Acceso al objeto Response

Al igual que con la petición, Axiom inyecta automáticamente el objeto `Response` como el **segundo parámetro** en tus rutas y controladores (normalmente lo llamamos `$res`).

```php
$app->get('/saludo', function($req,$res) {
    // Usamos $res para responderle al cliente
});
```

---

## Tipos de Respuestas

Axiom incluye métodos rápidos para los tipos de respuesta más comunes en la web moderna.

### Texto plano o HTML crudo: `send()`
El equivalente moderno a usar `echo`. Envía el contenido al navegador y establece un código de estado (200 por defecto).

```php
public function index($req,$res) {
    // Respuesta exitosa normal (200 OK)
    $res->send('<h1>¡Hola Mundo!</h1>');

    // Respuesta con código de error HTTP (ej: 400 Bad Request)
    // $res->send('Faltan datos', 400);
}
```


### Respuestas para APIs: `json()`
En PHP nativo, devolver JSON implica configurar manualmente los headers, usar `json_encode` y hacer un `exit`;. Axiom lo hace en un solo paso, previniendo errores de caracteres y asegurando que el navegador sepa que está recibiendo un JSON.

```php
public function obtenerUsuario($req, $res) {
    $usuario = [
        'id' => 1,
        'nombre' => 'Cristian',
        'rol' => 'admin'
    ];

    // Axiom convierte el arreglo y configura el Content-Type automáticamente
    $res->json($usuario); 
}
```


### Redirecciones Seguras: `redirect()`

En lugar de escribir `header("Location: /login"); exit;`, utiliza el método `redirect()`. Axiom se encargará de establecer el código HTTP correcto (302 por defecto) y enviar la cabecera.

```php
public function guardar($req,$res) {
    // Lógica para guardar datos...

    // Redirigir al usuario al dashboard
    $res->redirect('/dashboard');
}
```


### Solo código de estado (Sin contenido): `status()`

A veces, como cuando eliminas un recurso o recibes un webhook, no necesitas enviar texto de vuelta, solo un código HTTP para avisar que todo salió bien (o mal).

```php
public function borrar($req,$res) {
    // 204 significa "No Content" (Todo bien, pero no hay nada que mostrar)
    $res->status(204); 
}
```

### Vistas y Plantillas: `render()`

Si estás construyendo una página web tradicional (no una API), querrás devolver archivos HTML/PHP completos. El método `render()` de Axiom carga tus archivos PHP de forma segura, utilizando "Output Buffering" para evitar que errores en la vista rompan la página a la mitad.

**Nota**: Para usar `render()`, tu aplicación debe haber configurado la ruta de las vistas (`views.path`) durante el arranque.

```php
<?php

// Archivo: /config/views.php

return [
    'path' => __DIR__ . '/../views/'
]
```


#### Renderizado Básico

Pasa el nombre de tu archivo (sin la extensión `.php`) y un arreglo asociativo con los datos que quieres que la vista utilice. Axiom convertirá las llaves del arreglo en variables reales (`$titulo`, `$usuarios`) dentro de la vista.

```php
// En tu controlador:
$res->render('usuarios/lista', [
    'titulo' => 'Lista de Usuarios',
    'usuarios' => ['Juan', 'Ana', 'Carlos']
]);
```

#### Usando un Layout (Plantilla Maestra)

En PHP legacy era común hacer `include 'header.php';` al principio y `include 'footer.php';` al final de cada archivo.

Axiom soporta Layouts. Puedes definir un archivo maestro (ej. `layout.php`) que contenga el diseño principal. Axiom procesará tu vista (`usuarios/lista`) y la inyectará dentro del layout en la variable $contenido.

En tu controlador:
```php

$res->render('usuarios/lista', ['titulo' => 'Inicio'], 'layout');
```

Y en tu archivo `views/layout.php`:
```html
<!DOCTYPE html>
<html>
<head>
    <title><?= $titulo ?></title>
</head>
<body>
    <header>Mi Menú</header>
    
    <main>
        <!-- Aquí Axiom inyectará automáticamente el HTML de tu vista -->
        <?= $contenido ?> 
    </main>

    <footer>Mi Pie de página</footer>
</body>
</html>
```

#### Modificando Cabeceras (Headers)

Si necesitas enviar cabeceras personalizadas (como tokens, directivas de caché o CORS), puedes usar `withHeader()` antes de enviar la respuesta final.

```php
$res->withHeader('X-Frame-Options', 'DENY')
    ->withHeader('Cache-Control', 'no-cache')
    ->send('Página segura');
```

Axiom normaliza los nombres, así que no te preocupes si escribes `x-frame-options` o `X-Frame-Options`, evitará duplicados automáticamente.

--- 

## La Regla de Oro: Una Sola Respuesta

En aplicaciones antiguas, era común que varios archivos hicieran `echo` a lo largo de la ejecución. En Axiom solo puedes enviar una respuesta por petición.

El objeto `Response` tiene un sistema de seguridad (Guard) interno. Si intentas enviar un JSON y luego hacer una redirección en el mismo controlador, Axiom detendrá la ejecución y lanzará un error: `"La respuesta ya fue enviada"`.

Correcto:
```php
if ($error) {
    return $res->redirect('/login'); // El return detiene el código aquí
}
$res->send('Bienvenido');
```

Incorrecto (Lanzará error 500):
```php
if ($error) {$res->redirect('/login');
}
// El código sigue ejecutándose y trata de enviar otra respuesta
$res->send('Bienvenido');
```
