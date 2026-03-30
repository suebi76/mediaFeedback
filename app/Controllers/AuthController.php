<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Core\Auth;
use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Core\Csrf;

class AuthController extends Controller
{
    public function show(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }

        $this->render('login', [
            'title' => 'Anmelden',
            'csrfToken' => Csrf::token('login'),
        ]);
    }

    public function login(): void
    {
        $this->enforceCsrf('login');

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!Auth::attempt($this->database, $email, $password)) {
            $this->flash('error', 'Die Anmeldedaten sind ungültig.');
            $this->redirect('/login');
        }

        $this->flash('success', 'Willkommen bei mediaFeedback.');
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->enforceCsrf('logout');
        Auth::logout();
        $this->redirect('/login');
    }
}
