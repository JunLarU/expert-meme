<?php
namespace App\Controllers\Admin;

use App\Models\User;
use Whis\Cryptic\Hasher;
use Whis\Http\Controller;
use Whis\Http\Request;

class Users extends Controller
{
    public function index()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $users = User::forAdmin();

        return view('pages/admin/users', 'Usuarios', [
            'user'          => auth(),
            'users'         => $users,
            'stats'         => User::stats(),
            'currentUserId' => $this->currentUserId(),
        ], 'layouts/admin/layout');
    }

    public function create()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        return view('pages/admin/user-form', 'Nuevo usuario', [
            'user'      => auth(),
            'mode'      => 'create',
            'adminUser' => [
                'role' => User::ROLE_MANAGER,
            ],
        ], 'layouts/admin/layout');
    }

    public function edit(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $adminUser = User::findAdminArray($id);

        if (! $adminUser) {
            return redirect('/admin/usuarios');
        }

        return view('pages/admin/user-form', 'Editar usuario', [
            'user'          => auth(),
            'mode'          => 'edit',
            'adminUser'     => $adminUser,
            'currentUserId' => $this->currentUserId(),
        ], 'layouts/admin/layout');
    }

    public function store(Request $request, Hasher $hasher)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $payload = $this->payload($request);

        $errors = $this->validatePayload($payload, true);

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        if (User::emailExists($payload['email'])) {
            return $this->jsonError('Revisa los campos del formulario.', [
                'email' => 'Ya existe un usuario registrado con este correo.',
            ], 422);
        }

        try {
            User::create([
                'name'       => $payload['name'],
                'email'      => $payload['email'],
                'role'       => $payload['role'],
                'password'   => $hasher->hash($payload['password']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->jsonSuccess('Usuario creado correctamente.', [
                'redirect' => '/admin/usuarios',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo crear el usuario.', [
                'email' => 'Revisa que el correo no esté duplicado.',
            ], 500);
        }
    }

    public function update(Request $request, Hasher $hasher, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $current = User::findAdminArray($id);

        if (! $current) {
            return $this->jsonError('El usuario no existe.', [], 404);
        }

        $payload = $this->payload($request);

        $errors = $this->validatePayload($payload, false);

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        if (User::emailExists($payload['email'], $id)) {
            return $this->jsonError('Revisa los campos del formulario.', [
                'email' => 'Ya existe otro usuario registrado con este correo.',
            ], 422);
        }

        /*
         * Evita que el admin actual se quite a sí mismo el rol admin.
         */
        if ($id === $this->currentUserId() && $payload['role'] !== User::ROLE_ADMIN) {
            return $this->jsonError('No puedes quitarte tu propio rol de administrador.', [
                'role' => 'Tu propio usuario debe conservar el rol Admin.',
            ], 422);
        }

        $update = [
            'name'  => $payload['name'],
            'email' => $payload['email'],
            'role'  => $payload['role'],
        ];

        if ($payload['password'] !== '') {
            $update['password'] = $hasher->hash($payload['password']);
        }

        try {
            User::updateById($id, $update);

            return $this->jsonSuccess('Usuario actualizado correctamente.', [
                'redirect' => '/admin/usuarios',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo actualizar el usuario.', [], 500);
        }
    }

    public function delete(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $adminUser = User::findAdminArray($id);

        if (! $adminUser) {
            return redirect('/admin/usuarios');
        }

        if ($id === $this->currentUserId()) {
            return redirect('/admin/usuarios');
        }

        try {
            User::deleteById($id);
        } catch (\Throwable $th) {
            return redirect('/admin/usuarios');
        }

        return redirect('/admin/usuarios');
    }

    public function destroy(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $adminUser = User::findAdminArray($id);

        if (! $adminUser) {
            return $this->jsonError('El usuario no existe.', [], 404);
        }

        if ($id === $this->currentUserId()) {
            return $this->jsonError('No puedes eliminar tu propio usuario.', [], 422);
        }

        try {
            User::deleteById($id);

            return $this->jsonSuccess('Usuario eliminado correctamente.', [
                'redirect' => '/admin/usuarios',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar el usuario.', [], 500);
        }
    }

    private function payload(Request $request): array
    {
        $input = $request->data();

        return [
            'name'             => $this->str($input, 'name'),
            'email'            => strtolower($this->str($input, 'email')),
            'role'             => $this->str($input, 'role', User::ROLE_MANAGER),
            'password'         => (string) ($input['password'] ?? ''),
            'confirm_password' => (string) ($input['confirm_password'] ?? ''),
        ];
    }

    private function validatePayload(array $payload, bool $passwordRequired): array
    {
        $errors = [];

        if ($payload['name'] === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }

        if (mb_strlen($payload['name']) > 255) {
            $errors['name'] = 'El nombre no debe exceder 255 caracteres.';
        }

        if ($payload['email'] === '') {
            $errors['email'] = 'El correo electrónico es obligatorio.';
        } elseif (! filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Escribe un correo electrónico válido.';
        }

        if (! in_array($payload['role'], User::allowedAdminRoles(), true)) {
            $errors['role'] = 'Solo puedes crear usuarios Admin o Manager.';
        }

        $password = (string) ($payload['password'] ?? '');
        $confirm  = (string) ($payload['confirm_password'] ?? '');

        if ($passwordRequired && trim($password) === '') {
            $errors['password'] = 'La contraseña es obligatoria.';
        }

        if ($password !== '' && mb_strlen($password) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if ($password !== '' && $password !== $confirm) {
            $errors['confirm_password'] = 'Las contraseñas no coinciden.';
        }

        return $errors;
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
            return $this->jsonError('Solo un administrador puede gestionar usuarios.', [], 403);
        }

        return null;
    }

    private function currentUserIsAdmin(): bool
    {
        return $this->currentUserRole() === User::ROLE_ADMIN;
    }

    private function currentUserRole(): string
    {
        $role = $this->currentUserValue('role', '');

        return strtolower(trim((string) $role));
    }

    private function currentUserId(): int
    {
        return (int) $this->currentUserValue('id', 0);
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

            /*
         * No uses isset($user->role) con modelos mágicos.
         * Puede regresar false aunque __get sí pueda devolver el valor.
         */
            try {
                $value = $user->{$key};

                return $value ?? $default;
            } catch (\Throwable $th) {
                return $default;
            }
        }

        return $default;
    }

    private function str(array $input, string $key, mixed $default = ''): string
    {
        return trim((string) ($input[$key] ?? $default ?? ''));
    }
    public function profile()
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return redirect('/login');
        }

        $profileUser = User::findArray($userId);

        if (! $profileUser) {
            return redirect('/login');
        }

        return view('pages/admin/profile', 'Mi perfil', [
            'user'        => auth(),
            'profileUser' => $profileUser,
        ], 'layouts/admin/layout');
    }

    public function updateProfile(Request $request)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $this->jsonError('No se pudo identificar tu usuario.', [], 401);
        }

        $current = User::findArray($userId);

        if (! $current) {
            return $this->jsonError('Tu usuario no existe.', [], 404);
        }

        $input = $request->data();

        $name  = $this->str($input, 'name');
        $email = strtolower($this->str($input, 'email'));

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }

        if (mb_strlen($name) > 255) {
            $errors['name'] = 'El nombre no debe exceder 255 caracteres.';
        }

        if ($email === '') {
            $errors['email'] = 'El correo electrónico es obligatorio.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Escribe un correo electrónico válido.';
        }

        if (User::emailExists($email, $userId)) {
            $errors['email'] = 'Ya existe otro usuario registrado con este correo.';
        }

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        try {
            User::updateById($userId, [
                'name'  => $name,
                'email' => $email,

                /*
             * No se actualiza role desde perfil.
             */
            ]);

            $this->refreshAuthenticatedUser($userId);

            return $this->jsonSuccess('Perfil actualizado correctamente.', [
                'redirect' => '/admin/perfil',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo actualizar tu perfil.', [], 500);
        }
    }

    public function updatePassword(Request $request, Hasher $hasher)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $this->jsonError('No se pudo identificar tu usuario.', [], 401);
        }

        $currentUser = User::find($userId);

        if (! $currentUser) {
            return $this->jsonError('Tu usuario no existe.', [], 404);
        }

        $input = $request->data();

        $currentPassword = (string) ($input['current_password'] ?? '');
        $password        = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

        $errors = [];

        if (trim($currentPassword) === '') {
            $errors['current_password'] = 'Escribe tu contraseña actual.';
        }

        if (trim($password) === '') {
            $errors['password'] = 'Escribe tu nueva contraseña.';
        } elseif (mb_strlen($password) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (trim($confirmPassword) === '') {
            $errors['confirm_password'] = 'Confirma tu nueva contraseña.';
        }

        if ($password !== '' && $confirmPassword !== '' && $password !== $confirmPassword) {
            $errors['confirm_password'] = 'Las contraseñas no coinciden.';
        }

        $currentHash = '';

        try {
            $currentHash = (string) ($currentUser->password ?? '');
        } catch (\Throwable $th) {
            $currentHash = '';
        }

        if ($currentHash === '' || ! $hasher->verify($currentPassword, $currentHash)) {
            $errors['current_password'] = 'La contraseña actual no es correcta.';
        }

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        try {
            User::updateById($userId, [
                'password' => $hasher->hash($password),
            ]);

            $this->refreshAuthenticatedUser($userId);

            return $this->jsonSuccess('Contraseña actualizada correctamente.', [
                'redirect' => '/admin/perfil',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo actualizar tu contraseña.', [], 500);
        }
    }

    private function refreshAuthenticatedUser(int $id): void
    {
        try {
            $freshUser = User::find($id);

            if ($freshUser && method_exists($freshUser, 'login')) {
                $freshUser->login();
            }
        } catch (\Throwable $th) {
            /*
         * Si no se puede refrescar la sesión, no rompemos el request.
         * El cambio en BD ya quedó aplicado.
         */
        }
    }
}
