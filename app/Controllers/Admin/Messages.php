<?php

namespace App\Controllers\Admin;

use App\Models\Message;
use Whis\Http\Controller;
use Whis\Http\Request;

class Messages extends Controller
{
    public function index()
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $messages = Message::forAdmin();

        return view('pages/admin/messages', 'Mensajes', [
            'user'     => auth(),
            'messages' => $messages,
            'stats'    => Message::stats(),
        ], 'layouts/admin/layout');
    }

    public function show(int $id)
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $message = Message::findArray($id);

        if (! $message) {
            return redirect('/admin/mensajes');
        }

        if (($message['status'] ?? '') === Message::STATUS_NEW) {
            Message::updateById($id, [
                'status'  => Message::STATUS_READ,
                'read_at' => date('Y-m-d H:i:s'),
            ]);

            $message = Message::findArray($id) ?: $message;
        }

        return view('pages/admin/message', 'Mensaje', [
            'user'    => auth(),
            'message' => $message,
        ], 'layouts/admin/layout');
    }

    public function update(Request $request, int $id)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $message = Message::findArray($id);

        if (! $message) {
            return $this->jsonError('El mensaje no existe.', [], 404);
        }

        $input = $request->data();

        $status = trim((string) ($input['status'] ?? ($message['status'] ?? Message::STATUS_READ)));
        $priority = trim((string) ($input['priority'] ?? ($message['priority'] ?? Message::PRIORITY_NORMAL)));

        if (! in_array($status, Message::allowedStatuses(), true)) {
            return $this->jsonError('Estado inválido.', [
                'status' => 'Selecciona un estado válido.',
            ], 422);
        }

        if (! in_array($priority, Message::allowedPriorities(), true)) {
            return $this->jsonError('Prioridad inválida.', [
                'priority' => 'Selecciona una prioridad válida.',
            ], 422);
        }

        $payload = [
            'status'   => $status,
            'priority' => $priority,
        ];

        if (empty($message['read_at']) && $status !== Message::STATUS_NEW) {
            $payload['read_at'] = date('Y-m-d H:i:s');
        }

        if ($status === Message::STATUS_IN_PROGRESS && empty($message['assigned_to'])) {
            $payload['assigned_to'] = $this->userId(auth());
        }

        if ($status === Message::STATUS_ANSWERED && empty($message['answered_at'])) {
            $payload['answered_at'] = date('Y-m-d H:i:s');
        }

        if ($status === Message::STATUS_ARCHIVED && empty($message['archived_at'])) {
            $payload['archived_at'] = date('Y-m-d H:i:s');
        }

        try {
            Message::updateById($id, $payload);

            return $this->jsonSuccess('Mensaje actualizado correctamente.', [
                'redirect' => '/admin/mensajes/' . $id,
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo actualizar el mensaje.', [], 500);
        }
    }

    public function markRead(Request $request, int $id)
    {
        return $this->quickStatus($id, Message::STATUS_READ, 'Mensaje marcado como leído.');
    }

    public function markInProgress(Request $request, int $id)
    {
        return $this->quickStatus($id, Message::STATUS_IN_PROGRESS, 'Mensaje marcado en seguimiento.');
    }

    public function markAnswered(Request $request, int $id)
    {
        return $this->quickStatus($id, Message::STATUS_ANSWERED, 'Mensaje marcado como respondido.');
    }

    public function archive(Request $request, int $id)
    {
        return $this->quickStatus($id, Message::STATUS_ARCHIVED, 'Mensaje archivado correctamente.');
    }

    public function spam(Request $request, int $id)
    {
        return $this->quickStatus($id, Message::STATUS_SPAM, 'Mensaje marcado como spam.');
    }

    public function delete(int $id)
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $message = Message::findArray($id);

        if (! $message) {
            return redirect('/admin/mensajes');
        }

        try {
            Message::deleteById($id);
        } catch (\Throwable $th) {
            return redirect('/admin/mensajes');
        }

        return redirect('/admin/mensajes');
    }

    public function destroy(Request $request, int $id)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $message = Message::findArray($id);

        if (! $message) {
            return $this->jsonError('El mensaje no existe.', [], 404);
        }

        try {
            Message::deleteById($id);

            return $this->jsonSuccess('Mensaje eliminado correctamente.', [
                'redirect' => '/admin/mensajes',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar el mensaje.', [], 500);
        }
    }

    private function quickStatus(int $id, string $status, string $messageText)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $message = Message::findArray($id);

        if (! $message) {
            return $this->jsonError('El mensaje no existe.', [], 404);
        }

        $payload = [
            'status' => $status,
        ];

        if (empty($message['read_at']) && $status !== Message::STATUS_NEW) {
            $payload['read_at'] = date('Y-m-d H:i:s');
        }

        if ($status === Message::STATUS_IN_PROGRESS && empty($message['assigned_to'])) {
            $payload['assigned_to'] = $this->userId(auth());
        }

        if ($status === Message::STATUS_ANSWERED && empty($message['answered_at'])) {
            $payload['answered_at'] = date('Y-m-d H:i:s');
        }

        if ($status === Message::STATUS_ARCHIVED && empty($message['archived_at'])) {
            $payload['archived_at'] = date('Y-m-d H:i:s');
        }

        try {
            Message::updateById($id, $payload);

            return $this->jsonSuccess($messageText, [
                'redirect' => '/admin/mensajes',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo actualizar el mensaje.', [], 500);
        }
    }

    private function userId(mixed $user): ?int
    {
        if (! $user) {
            return null;
        }

        if (is_object($user) && isset($user->id)) {
            return (int) $user->id;
        }

        if (is_array($user) && isset($user['id'])) {
            return (int) $user['id'];
        }

        return null;
    }
}