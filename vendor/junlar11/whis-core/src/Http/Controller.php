<?php
namespace Whis\Http;

class Controller
{
    protected array $middlewares = [];

    public function middlewares(): array
    {
        return $this->middlewares;
    }

    public function setMiddlewares(array $middlewares): self
    {
        $this->middlewares = array_map(
            fn($middleware) => new $middleware(),
            $middlewares
        );

        return $this;
    }

    protected function expectsJson(?Request $request = null): bool
    {
        $request = $request ?? request();

        return $request->expectsJson();
    }

    protected function getInputData(): array
    {
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');

        if (stripos($contentType, 'application/json') !== false) {
            $raw  = file_get_contents('php://input');
            $json = json_decode($raw ?: '', true);

            return is_array($json) ? $json : [];
        }

        return array_merge(
            is_array($_GET) ? $_GET : [],
            is_array($_POST) ? $_POST : []
        );
    }
}
