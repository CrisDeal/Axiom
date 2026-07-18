# Axiom Framework

Axiom es un marco de trabajo interno que define una guía técnica estructurada para la construcción de aplicaciones, proporcionando herramientas prefabricadas para estandarizar, facilitar y acelerar el trabajo de los desarrolladores.

## Requisitos del Sistema

Antes de iniciar un nuevo proyecto, asegúrate de que el servidor o entorno local cumpla con lo siguiente:

*   **PHP:** Version 8.1 o superior.
*   **Extensiones de Base de Datos:** Soporte para `PDO` y `mysqli`.
*   **Dependencias de red:** El entorno debe permitir la ejecución de `Guzzle` (Cliente HTTP).
*   **Composer:** Gestor de dependencias instalado.

## Guía de Instalación

Para implementar Axiom en un nuevo proyecto, sigue estos pasos:

### 1. Configuración de Composer
Crea un archivo `composer.json` en la raíz de tu nuevo proyecto. Aquí tienes una plantilla completa con la configuración mínima requerida. Solo asegúrate de cambiar el nombre y la descripción para que coincidan con tu aplicación:

```json
{
    "name": "empresa/nuevo-proyecto",
    "description": "Aplicación base utilizando Axiom Framework",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/CrisDeal/Axiom.git"
        }
    ],
    "require": {
        "axiom/framework": "^0.1.0"
    },
    "autoload": {
        "psr-4": {
            "NOMBRE_DE_TU_APP\\": "app/"
        }
    }
}
```

### 2. Descarga de Dependencias
Abre la terminal en la raíz del proyecto y ejecuta el instalador de dependencias. Esto generará la carpeta `vendor/` y el archivo `autoload.php`:

``` bash
composer install
```

### 3. Punto de Entrada (Bootstrapping)
Una vez instalado, crea el archivo principal de tu aplicación (`index.php` o `app.php`). Aquí es donde requieres el autoloader de Composer e inicializas el framework:

``` PHP
<?php 

require_once __DIR__ . '/vendor/autoload.php';

use \Axiom\Axiom;

// Inicializador de Axiom
$app = new Axiom();

// (Aquí se registrarán las rutas y configuraciones)
```

### 4. Configuración del Servidor (URL Rewriting)
Debido a la arquitectura del framework, todas las peticiones HTTP deben ser redirigidas al punto de entrada (index.php) para que el Router interno pueda procesarlas.

Para habilitar esto en el servidor, crea un archivo .htaccess en el mismo nivel que tu index.php con la siguiente configuración:


