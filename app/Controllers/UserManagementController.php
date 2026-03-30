<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Core\Csrf;
use MediaFeedbackV2\Models\User;

class UserManagementController extends Controller
{
    public function index(): void
    {
        $admin = $this->requireAdmin();

        $this->render('users', [
            'title' => 'Benutzerverwaltung',
            'currentAdmin' => $admin,
            'users' => (new User($this->database))->all(),
            'userToken' => Csrf::token('users'),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->enforceCsrf('users');

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $role = (string) ($_POST['role'] ?? 'creator');

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Bitte gib einen Namen und eine gültige E-Mail-Adresse an.');
            $this->redirect('/users');
        }

        if (strlen($password) < 8) {
            $this->flash('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            $this->redirect('/users');
        }

        if ($password !== $passwordConfirmation) {
            $this->flash('error', 'Die beiden Passwörter stimmen nicht überein.');
            $this->redirect('/users');
        }

        if (!in_array($role, ['admin', 'creator'], true)) {
            $role = 'creator';
        }

        $userModel = new User($this->database);
        if ($userModel->findByEmail($email)) {
            $this->flash('error', 'Zu dieser E-Mail-Adresse existiert bereits ein Benutzer.');
            $this->redirect('/users');
        }

        $userModel->create($name, $email, $password, $role);
        $this->flash('success', 'Benutzer erfolgreich angelegt.');
        $this->redirect('/users');
    }

    public function delete(): void
    {
        $admin = $this->requireAdmin();
        $this->enforceCsrf('users');

        $userId = (int) ($_POST['user_id'] ?? 0);
        $userModel = new User($this->database);
        $user = $userModel->find($userId);

        if (!$user) {
            $this->flash('error', 'Der Benutzer wurde nicht gefunden.');
            $this->redirect('/users');
        }

        if ((int) $user['id'] === (int) $admin['id']) {
            $this->flash('error', 'Du kannst dich nicht selbst löschen.');
            $this->redirect('/users');
        }

        if (($user['role'] ?? null) === 'admin' && $userModel->countAdmins() <= 1) {
            $this->flash('error', 'Der letzte Administrator kann nicht gelöscht werden.');
            $this->redirect('/users');
        }

        $userModel->delete($userId);
        $this->flash('success', 'Benutzer gelöscht.');
        $this->redirect('/users');
    }
}
