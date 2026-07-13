<?php

namespace App\Controllers\Admin;

use App\Models\ValuationMessage;
use Whis\Http\Controller;
use Whis\Http\Request;

class ValuationMessages extends Controller
{
    public function index()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        return view('pages/admin/valuation-messages', 'Mensajes de valuación', [
            'user'     => auth(),
            'messages' => ValuationMessage::forAdmin(),
            'stats'    => ValuationMessage::stats(),
        ], 'layouts/admin/layout');
    }

    public function show(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $message = ValuationMessage::findArray($id);

        if (! $message) {
            return redirect('/admin/valuacion/mensajes');
        }

        if (($message['status'] ?? '') === ValuationMessage::STATUS_NEW) {
            ValuationMessage::updateById($id, [
                'status'  => ValuationMessage::STATUS_READ,
                'read_at' => date('Y-m-d H:i:s'),
            ]);

            $message = ValuationMessage::findArray($id) ?: $message;
        }

        return view('pages/admin/valuation-message', 'Mensaje de valuación', [
            'user'    => auth(),
            'message' => $message,
        ], 'layouts/admin/layout');
    }

    public function update(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $message = ValuationMessage::findArray($id);

        if (! $message) {
            return $this->jsonError('La solicitud de valuación no existe.', [], 404);
        }

        $input = $request->data();
        $status = trim((string) ($input['status'] ?? ($message['status'] ?? ValuationMessage::STATUS_READ)));
        $priority = trim((string) ($input['priority'] ?? ($message['priority'] ?? ValuationMessage::PRIORITY_NORMAL)));

        if (! in_array($status, ValuationMessage::allowedStatuses(), true)) {
            return $this->jsonError('Estado inválido.', ['status' => 'Selecciona un estado válido.'], 422);
        }

        if (! in_array($priority, ValuationMessage::allowedPriorities(), true)) {
            return $this->jsonError('Prioridad inválida.', ['priority' => 'Selecciona una prioridad válida.'], 422);
        }

        $payload = [
            'status'   => $status,
            'priority' => $priority,
        ];

        $payload = $this->applyTimelineFields($message, $payload, $status);

        try {
            ValuationMessage::updateById($id, $payload);

            return $this->jsonSuccess('Solicitud de valuación actualizada correctamente.', [
                'redirect' => '/admin/valuacion/mensajes/' . $id,
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo actualizar la solicitud de valuación.', [], 500);
        }
    }

    public function markRead(Request $request, int $id)
    {
        return $this->quickStatus($id, ValuationMessage::STATUS_READ, 'Solicitud marcada como leída.');
    }

    public function markInProgress(Request $request, int $id)
    {
        return $this->quickStatus($id, ValuationMessage::STATUS_IN_PROGRESS, 'Solicitud marcada en seguimiento.');
    }

    public function markAnswered(Request $request, int $id)
    {
        return $this->quickStatus($id, ValuationMessage::STATUS_ANSWERED, 'Solicitud marcada como respondida.');
    }

    public function archive(Request $request, int $id)
    {
        return $this->quickStatus($id, ValuationMessage::STATUS_ARCHIVED, 'Solicitud archivada correctamente.');
    }

    public function spam(Request $request, int $id)
    {
        return $this->quickStatus($id, ValuationMessage::STATUS_SPAM, 'Solicitud marcada como spam.');
    }

    public function delete(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        if (! ValuationMessage::findArray($id)) {
            return redirect('/admin/valuacion/mensajes');
        }

        try {
            ValuationMessage::deleteById($id);
        } catch (\Throwable $th) {
            return redirect('/admin/valuacion/mensajes');
        }

        return redirect('/admin/valuacion/mensajes');
    }

    public function destroy(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        if (! ValuationMessage::findArray($id)) {
            return $this->jsonError('La solicitud de valuación no existe.', [], 404);
        }

        try {
            ValuationMessage::deleteById($id);

            return $this->jsonSuccess('Solicitud de valuación eliminada correctamente.', [
                'redirect' => '/admin/valuacion/mensajes',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar la solicitud de valuación.', [], 500);
        }
    }

    private function quickStatus(int $id, string $status, string $messageText)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $message = ValuationMessage::findArray($id);

        if (! $message) {
            return $this->jsonError('La solicitud de valuación no existe.', [], 404);
        }

        $payload = $this->applyTimelineFields($message, ['status' => $status], $status);

        try {
            ValuationMessage::updateById($id, $payload);

            return $this->jsonSuccess($messageText, [
                'redirect' => '/admin/valuacion/mensajes',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo actualizar la solicitud de valuación.', [], 500);
        }
    }

    private function applyTimelineFields(array $message, array $payload, string $status): array
    {
        if (empty($message['read_at']) && $status !== ValuationMessage::STATUS_NEW) {
            $payload['read_at'] = date('Y-m-d H:i:s');
        }

        if ($status === ValuationMessage::STATUS_IN_PROGRESS && empty($message['assigned_to'])) {
            $payload['assigned_to'] = $this->userId(auth());
        }

        if ($status === ValuationMessage::STATUS_ANSWERED && empty($message['answered_at'])) {
            $payload['answered_at'] = date('Y-m-d H:i:s');
        }

        if ($status === ValuationMessage::STATUS_ARCHIVED && empty($message['archived_at'])) {
            $payload['archived_at'] = date('Y-m-d H:i:s');
        }

        return $payload;
    }

    private function denyPageUnlessAdmin()
    {
        if (isGuest()) {
            return redirect('/login');
        }

        if (! $this->currentUserIsAdmin()) {
            return redirect('/admin');
        }

        return null;
    }

    private function denyJsonUnlessAdmin()
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        if (! $this->currentUserIsAdmin()) {
            return $this->jsonError('No tienes permisos para administrar solicitudes de valuación.', 403);
        }

        return null;
    }

    private function currentUserIsAdmin(): bool
    {
        return strtolower((string) $this->currentUserValue('role', '')) === 'admin';
    }

    private function currentUserValue(string $key, mixed $default = null): mixed
    {
        $user = auth();

        if (! $user) {
            return $default;
        }

        if (is_array($user)) {
            return $user[$key] ?? $default;
        }

        if (is_object($user)) {
            if (method_exists($user, 'toArray')) {
                $data = $user->toArray();

                if (is_array($data) && array_key_exists($key, $data)) {
                    return $data[$key];
                }
            }

            try {
                return $user->{$key} ?? $default;
            } catch (\Throwable $th) {
                return $default;
            }
        }

        return $default;
    }

    private function userId(mixed $user): ?int
    {
        if (is_array($user) && isset($user['id'])) {
            return (int) $user['id'];
        }

        if (is_object($user)) {
            if (method_exists($user, 'toArray')) {
                $data = $user->toArray();

                if (is_array($data) && isset($data['id'])) {
                    return (int) $data['id'];
                }
            }

            try {
                return isset($user->id) ? (int) $user->id : null;
            } catch (\Throwable $th) {
                return null;
            }
        }

        return null;
    }
}
