<?php
namespace Axiom\Http\Router;

use Axiom\Exceptions\HttpException;

/**
 * Response
 *
 * Representa la respuesta HTTP que el servidor envía al cliente.
 * Gestiona headers, status codes, respuestas JSON y renderizado de vistas.
 *
 * Garantiza que la respuesta solo se envíe una vez mediante el flag $sent.
 * Cualquier intento de enviar una segunda respuesta lanza una excepción.
 *
 * Uso básico:
 *   $response->json(['success' => true]);
 *   $response->render('home/Index');
 *   $response->render('emails/Welcome', [], null);  // sin layout
 *   $response->redirect('/login');
 *   $response->status(204);
 */
class Response {

    /**
     * Indica si la respuesta ya fue enviada.
     * Protege contra doble envío accidental.
     */
    private bool $sent = false;
    
    /**
     * Ruta base donde viven los archivos de vista (.php).
     * Puede ser null si la app solo usa JSON (modo API puro).
     */
    public ?string $viewsPath;

    /**
     * Headers HTTP personalizados a incluir en la respuesta.
     * Se envían justo antes de emitir el body.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * @param string|null $viewsPath Ruta absoluta al directorio de vistas.
     *                               Opcional — puede ser null en apps API puras.
     */
    public function __construct(?string $viewsPath = null) {
        $this->viewsPath = $viewsPath !== null
            ? rtrim($viewsPath, '/\\')
            : null;
    }

    /**
     * Indica si la respuesta ya fue enviada.
     */
    public function hasBeenSent() : bool {
        return $this->sent;
    }

    /**
     * Agrega un header HTTP personalizado a la respuesta.
     * Debe llamarse antes de json(), render() o status().
     *
     * Ejemplo:
     *   $response->withHeader('Cache-Control', 'no-store');
     *   $response->withHeader('X-Api-Version', '1.0');
     *
     * @param  string   $name   Nombre del header. Ejemplo: 'Cache-Control'
     * @param  string   $value  Valor del header.
     * @return static           Retorna la instancia para encadenamiento.
     */
    public function withHeader(string $name, string $value): static {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Envía una respuesta de texto plano o HTML.
     *
     * @param string $content Texto o HTML a enviar.
     * @param int $statusCode HTTP status code. Por defecto 200.
     */
    public function send(string $content, int $statusCode = 200): void {
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
     * Envía una respuesta con solo un status code y sin body.
     * Útil para respuestas 204 No Content, 304 Not Modified, etc.
     *
     * Ejemplo:
     *   $response->status(204); // recurso eliminado, sin contenido
     *
     * @param int $code HTTP status code.
     */
    public function status(int $code): void {
        $this->guardAgainstDoubleSend();
        $this->sent = true;

        $this->sendHeaders();
        http_response_code($code);
    }

    /**
     * Redirige al cliente a otra URL.
     *
     * Ejemplo:
     *   $response->redirect('/login');
     *   $response->redirect('/dashboard', 301); // redirección permanente
     *
     * @param string $url  URL destino.
     * @param int    $code Status code. Por defecto 302 (temporal).
     */
    public function redirect(string $url, int $code = 302): void {
        $this->guardAgainstDoubleSend();
        $this->sent = true;

        $this->sendHeaders();
        http_response_code($code);
        header("Location: $url");
    }

    /**
     * Renderiza una vista PHP con datos opcionales.
     *
     * Por defecto envuelve la vista en MainLayout.php.
     * Pasa null en $layout para renderizar sin layout — útil para
     * vistas de error, emails, o respuestas parciales.
     *
     * Ejemplo:
     *   $response->render('users/Profile', ['user' => $user]);
     *   $response->render('emails/Welcome', ['name' => 'Ana'], null);
     *   $response->render('dashboard/Home', [], 'layout/AdminLayout');
     *
     * @param string      $view   Ruta relativa a la vista desde viewsPath, sin .php
     * @param array       $data   Variables disponibles dentro de la vista.
     * @param string|null $layout Ruta relativa al layout desde viewsPath, sin .php
     *                            Usa null para renderizar sin layout.
     *
     * @throws HttpException Si viewsPath no fue configurado.
     * @throws HttpException Si la vista o el layout no existen.
     */
    public function render(string $view, array $data = [], ?string $layout = null) : void{
        $this->guardAgainstDoubleSend();

        if($this->viewsPath === null) {
            throw new HttpException('viewsPath no configurado - no se pueden renderizar vistas.', 500);
        }

        $this->sent = true;

        $viewPath = "{$this->viewsPath}/{$view}.php";
        if (!file_exists($viewPath)) {
            throw new HttpException("Vista '$view' no encontrada en '$viewPath'", 500);
        }

        $this->sendHeaders();
        header('Content-Type: text/html; charset=UTF-8');

        // Extrae $data como variables locales disponibles en la vista.
        extract($data, EXTR_SKIP);
        
        // Captura el output de la vista en $content.
        // El try/finally garantiza que el buffer siempre se cierre,
        // incluso si la vista lanza una excepción.
        try {
            ob_start();
            include $viewPath;
            $content = ob_get_clean();
        } finally {
            // Si ob_get_clean() no se ejecutó, limpia el buffer manualmente.
            if(ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // Si no se especifica layout, emite la vista directamente.
        if($layout === null) {
            echo $content;
            return;
        }

        // Renderiza el layout - espera la variable $content definida arriba.
        $layoutPath = "{$this->viewsPath}/{$layout}.php";
        if(!file_exists($layoutPath)) {
            throw new HttpException("Layout no encontrado en '$layoutPath'", 500);
        }

        include $layoutPath;
    }

    /**
     * Envía una respuesta JSON.
     *
     * Ejemplo:
     *   $response->json(['success' => true, 'data' => $users]);
     *   $response->json(['message' => 'No autorizado'], 401);
     *
     * @param array $data       Datos a serializar como JSON.
     * @param int   $statusCode HTTP status code. Por defecto 200.
     *
     * @throws HttpException Si los datos no pueden serializarse a JSON.
     */
    public function json(array $data, int $statusCode = 200) : void {
        $this->guardAgainstDoubleSend();
        $this->sent = true;

        $this->sendHeaders();
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($statusCode);

        try {
            echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch(\JsonException $e) {
            throw new HttpException('Failed to encode JSON response', 500);
        }
    }

    /**
     * Envía todos los headers personalizados registrados con withHeader().
     * Se llama internamente justo antes de emitir cualquier respuesta.
     */
    private function sendHeaders(): void {
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Lanza una excepción si la respuesta ya fue enviada.
     * Se llama al inicio de cada método público que emite una respuesta.
     *
     * @throws HttpException
     */
    private function guardAgainstDoubleSend(): void {
        if ($this->sent) {
            throw new HttpException('La respuesta ya fue enviada.', 500);
        }
    }
}