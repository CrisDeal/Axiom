<?php
namespace Axiom\Exceptions;

use Axiom\Http\Router\Request;
use Axiom\Http\Router\Response;
use Throwable;

/**
 * ErrorHandler
 *
 * Maneja todas las excepciones y errores no capturados de la aplicación.
 * Se registra en PHP mediante register() y debe llamarse en el bootstrap
 * antes de instanciar el Router.
 *
 * Distingue entre errores de cliente (4xx) y errores de servidor (5xx),
 * ocultando detalles internos en producción y detectando automáticamente
 * si el cliente espera JSON o HTML.
 *
 * Uso en bootstrap.php:
 *   $errorHandler = new ErrorHandler($request, $response);
 *   $errorHandler->register();
 */
class ErrorHandler {

    /**
     * @param Request  $request  Instancia compartida con el Router.
     * @param Response $response Instancia compartida con el Router.
     */
    public function __construct(
        private Request $request,
        private Response $response
    ) {}

    /**
     * Registra el handler en el sistema de errores de PHP.
     * Debe llamarse una sola vez en el bootstrap de la aplicación,
     * antes de cualquier otra inicialización.
     *
     * Cubre tres tipos de fallo:
     *   - Excepciones no capturadas (set_exception_handler)
     *   - Errores de PHP convertidos a excepciones (set_error_handler)
     *   - Errores fatales que PHP no propaga como excepciones (register_shutdown_function)
     */
    public function register(): void {
        set_exception_handler([$this, 'handle']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Punto central de manejo de excepciones.
     * Llamado automáticamente por PHP para excepciones no capturadas,
     * y manualmente por el Router para excepciones dentro del dispatch.
     *
     * - Errores 4xx: muestra el mensaje de la excepción al cliente.
     * - Errores 5xx: oculta el detalle interno y loguea la excepción.
     *
     * @param Throwable $exception Excepción a manejar.
     */
    public function handle(Throwable $exception): void {
        $statusCode = $exception instanceof HttpException
            ? $exception->getStatusCode()
            : 500;

        $isClientError = $statusCode >= 400 && $statusCode < 500;

        // En errores de servidor ocultamos el detalle al cliente
        // pero lo registramos internamente para debugging.
        // $message = $isClientError
        //     ? $exception->getMessage()
        //     : 'Error interno del servidor. Intente más tarde.';
        // Permitir mensajes personalizados en algunas excepciones de servidor
        if (
            $isClientError || 
            $exception instanceof ServiceUnavailableException || 
            $exception instanceof HttpClientException
        ) {
            $message = $exception->getMessage();
        } else {
            $message = 'Error interno del servidor. Intente más tarde.';
        }

        if(!$isClientError) {
            error_log(
                $exception->getMessage() 
                . " in " . $exception->getFile()
                . ":" . $exception->getLine()
            );
        }

        if($exception instanceof HttpClientException) {
            error_log(
                'error de cliente HTTP'
            );
        }

        // Si la respuesta ya fue enviada no podemos hacer nada más —
        // solo registramos el error para no romper la salida existente.
        if ($this->response->hasBeenSent()) {
            error_log('ErrorHandler: la respuesta ya fue enviada. No se puede enviar el error.');
            return;
        }

        if($this->request->wantsJson() || !isset($this->response->viewsPath)) {
            $this->response->json([
                'success' => false,
                'message' => $message,
                'code'    => $statusCode,
                'meta'    => [
                    'timestamp' => gmdate("Y-m-d\TH:i:s\Z"),
                    'path'      => $this->request->url,
                    'method'    => $this->request->method,
                    'requestId' => bin2hex(random_bytes(8))
                ]
            ], $statusCode);
            return;
        }

        // Fallback si la vista de error también falla —
        // el error handler no puede romper la aplicación.
        try {
            $this->response->render('error/ErrorPage', [
                'statusCode' => $statusCode,
                'message'    => $message,
            ]);
        } catch (Throwable) {
            // Fallback si la vista falla
            http_response_code($statusCode);

            echo "<!DOCTYPE html>";
            echo "<html lang='es'>";
            echo "<head>";
            echo "  <meta charset='UTF-8'>";
            echo "  <title>Error $statusCode</title>";
            echo "  <style>
                    body {
                        margin: 0;
                        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                        background: linear-gradient(135deg, #f0f4f8, #d9e2ec);
                        color: #333;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                    }
                    .card {
                        background: #fff;
                        padding: 40px;
                        border-radius: 12px;
                        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
                        max-width: 500px;
                        text-align: center;
                    }
                    h1 {
                        font-size: 2.5em;
                        margin-bottom: 20px;
                        color: #e63946;
                    }
                    p {
                        font-size: 1.1em;
                        margin-bottom: 15px;
                    }
                    .btn {
                        display: inline-block;
                        margin-top: 20px;
                        padding: 12px 24px;
                        background: #1d3557;
                        color: #fff;
                        text-decoration: none;
                        border-radius: 6px;
                        transition: background 0.3s ease;
                    }
                    .btn:hover {
                        background: #457b9d;
                    }
                    </style>";
            echo "</head>";
            echo "<body>";
            echo "  <div class='card'>";
            echo "    <h1>Error " . htmlspecialchars($statusCode, ENT_QUOTES, 'UTF-8') . "</h1>";
            echo "    <p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
            echo "    <a href='/pages/TiposDeUsuarios/Usuario/index.php' class='btn'>Volver al inicio</a>";
            echo "  </div>";
            echo "</body>";
            echo "</html>";

        }
    }

    /**
     * Convierte errores de PHP en excepciones para que pasen
     * por el mismo flujo que las excepciones normales.
     *
     * PHP emite errores (E_WARNING, E_NOTICE, etc.) que por defecto
     * no se pueden capturar con try/catch — este handler los convierte
     * en ErrorException para que handle() los procese uniformemente.
     *
     * @throws \ErrorException
     */
    public function handleError1(int $severity, string $message, string $file, int $line): bool {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    public function handleError(int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false; // respetar @
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Captura errores fatales que ocurren durante el shutdown de PHP.
     * PHP no propaga errores fatales (E_ERROR, E_PARSE, E_COMPILE_ERROR)
     * como excepciones — este handler los detecta al final de la ejecución
     * y los pasa a handle() para una respuesta consistente.
     */
    public function handleShutdown(): void {
        $error = error_get_last();

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if ($error && in_array($error['type'], $fatalTypes)) {
            $this->handle(new \ErrorException(
                $error['message'],
                0,
                $error['type'], 
                $error['file'],
                $error['line']
            ));
        }
    }
}