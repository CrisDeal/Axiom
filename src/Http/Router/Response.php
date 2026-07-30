<?php
namespace Axiom\Http\Router;

use Axiom\Exceptions\HttpException;

/**
 * HTTP Response
 * 
 * Se encarga de formatear y emitir la salida hacia el cliente.
 * Implementa un mecanismo de bloqueo (guard) para asegurar que una 
 * petición HTTP reciba una y solo una respuesta, previniendo estados corruptos.
 */
class Response {

    private bool $sent = false;
    public ?string $viewsPath;

    /**
     * @var array<string, string> Headers HTTP listos para enviarse.
     * Las claves se almacenan en minúsculas para evitar duplicidades accidentales.
     */
    private array $headers = [];

    public function __construct(?string $viewsPath = null) {
        $this->viewsPath = $viewsPath !== null
            ? rtrim($viewsPath, '/\\')
            : null;
    }

    /**
     * Permite al Router saber si el controlador actual finalizó su trabajo.
     */
    public function hasBeenSent() : bool {
        return $this->sent;
    }

    /**
     * Agrega un header HTTP personalizado.
     * Normaliza la clave a minúsculas para evitar colisiones (ej. Content-Type vs content-type).
     */
    public function withHeader(string $name, string $value): static {
        $this->headers[strtolower($name)] = $value;
        return $this;
    }

    /**
     * Envía una respuesta de texto plano o HTML crudo.
     */
    public function send(string $content, int $statusCode = 200): void 
    {
        $this->guardAgainstDoubleSend();
        $this->sent = true;

        $this->sendHeaders();
        // Solo establecemos el Content-Type si el usuario no lo definió manualmente con withHeader()
        if (!isset($this->headers['Content-Type']) && !isset($this->headers['content-type'])) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        http_response_code($statusCode);
        echo $content;
    }

    /**
     * Termina la petición enviando solo un código de estado (sin body).
     * Útil para respuestas 204 No Content o webhooks.
     */
    public function status(int $code): void 
    {
        $this->guardAgainstDoubleSend();
        $this->sent = true;

        $this->sendHeaders();
        http_response_code($code);
    }

    /**
     * Ejecuta una redirección HTTP. El script debe detenerse tras esto.
     */
    public function redirect(string $url, int $code = 302): void 
    {
        $this->guardAgainstDoubleSend();
        $this->sent = true;

        $this->sendHeaders();
        http_response_code($code);
        header("Location: $url");
    }

    /**
     * Motor de plantillas nativo basado en PHP.
     * Utiliza Output Buffering para procesar el archivo antes de enviarlo.
     */
    public function render(string $view, array $data = [], ?string $layout = null) : void
    {
        $this->guardAgainstDoubleSend();

        if($this->viewsPath === null) {
            throw new HttpException('viewsPath no configurado - no se pueden renderizar vistas.', 500);
        }

        $this->sent = true;

        $viewPath = "{$this->viewsPath}/{$view}.php";
        if (!file_exists($viewPath)) {
            throw new HttpException("Vista '$view' no encontrada en '$viewPath'", 500);
        }

        $this->withHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->sendHeaders();

        // Expone el array asociativo como variables individuales dentro del scope actual
        extract($data, EXTR_SKIP);
        
        try {
            // Inicia la captura de toda la salida (echo, HTML crudo) en memoria
            ob_start();
            include $viewPath;
            $contenido = ob_get_clean();
        } finally {
            // Safety Net: Si include lanza una excepción, limpiamos el buffer sucio 
            // para que no se imprima HTML roto junto con la pantalla de error.
            if(ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        if($layout === null) {
            echo $contenido;
            return;
        }

        $layoutPath = "{$this->viewsPath}/{$layout}.php";
        if(!file_exists($layoutPath)) {
            throw new HttpException("Layout no encontrado en '$layoutPath'", 500);
        }

        // El archivo layout.php debe hacer un `echo $contenido;` en su interior.
        include $layoutPath;
    }

    /**
     * Convierte un array asociativo a JSON y lo emite.
     */
    public function json(array $data, int $statusCode = 200) : void {
        $this->guardAgainstDoubleSend();
        $this->sent = true;

        $this->sendHeaders();
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($statusCode);

        try {
            // JSON_THROW_ON_ERROR evita retornos falsos (false) si hay datos binarios o malformados
            echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch(\JsonException $e) {
            throw new HttpException('Failed to encode JSON response', 500);
        }
    }

    /**
     * Emite los headers acumulados a PHP.
     */
    private function sendHeaders(): void {
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Previene colisiones fatal errors advirtiendo al desarrollador 
     * si intenta enviar más de una respuesta en el mismo ciclo.
     */
    private function guardAgainstDoubleSend(): void {
        if ($this->sent) {
            throw new HttpException('La respuesta ya fue enviada.', 500);
        }
    }
}