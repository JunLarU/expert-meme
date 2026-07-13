<?php

namespace Whis\Http;

use InvalidArgumentException;

class Controller
{
    protected array $middlewares = [];

    public function middlewares(): array
    {
        return $this->middlewares;
    }

    public function setMiddlewares(array $middlewares): self
    {
        $resolved = [];

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof Middleware) {
                $resolved[] = $middleware;
                continue;
            }

            if (is_string($middleware) && class_exists($middleware)) {
                $instance = new $middleware();

                if (! $instance instanceof Middleware) {
                    throw new InvalidArgumentException(
                        "Middleware [{$middleware}] must implement " . Middleware::class . '.'
                    );
                }

                $resolved[] = $instance;
                continue;
            }

            $name = is_string($middleware) ? $middleware : get_debug_type($middleware);

            throw new InvalidArgumentException("Invalid middleware [{$name}].");
        }

        $this->middlewares = $resolved;

        return $this;
    }

    protected function expectsJson(?Request $request = null): bool
    {
        $request = $request ?? request();

        return $request->expectsJson();
    }

    protected function validationError(
        ?Request $request,
        array $errors,
        string $message = 'Revisa los campos marcados en rojo.',
        int $status = 422
    ): Response {
        $request = $request ?? request();

        if ($this->expectsJson($request)) {
            return Response::json([
                'ok'      => false,
                'message' => $message,
                'errors'  => $errors,
            ])->setStatus($status)->noStore();
        }

        return back()->withErrors($errors, $status);
    }

    protected function jsonError(
        string $message = 'Ocurrió un error.',
        array|int $errors = [],
        int $status = 400
    ): Response {
        /*
         * Compatibilidad con:
         *
         * $this->jsonError('No autorizado.', 401);
         *
         * y también:
         *
         * $this->jsonError('Datos inválidos.', [
         *     'name' => 'El nombre es obligatorio.',
         * ], 422);
         */
        if (is_int($errors)) {
            $status = $errors;
            $errors = [];
        }

        return Response::json([
            'ok'      => false,
            'message' => $message,
            'errors'  => $errors,
        ])->setStatus($status)->noStore();
    }

    protected function jsonSuccess(
        string $message = 'Operación realizada correctamente.',
        array $data = [],
        int $status = 200
    ): Response {
        return Response::json([
            'ok'      => true,
            'message' => $message,
            ...$data,
        ])->setStatus($status);
    }

    protected function getInputData(): array
    {
        $contentType = (string) (
            $_SERVER['CONTENT_TYPE']
            ?? $_SERVER['HTTP_CONTENT_TYPE']
            ?? ''
        );

        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');

            if (!is_string($raw) || trim($raw) === '') {
                return [];
            }

            $json = json_decode($raw, true);

            return is_array($json) ? $json : [];
        }

        return array_merge(
            is_array($_GET ?? null) ? $_GET : [],
            is_array($_POST ?? null) ? $_POST : []
        );
    }
}