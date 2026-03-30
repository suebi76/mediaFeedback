<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Core\Csrf;
use MediaFeedbackV2\Models\Feedback;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $feedbacks = (new Feedback($this->database))->allForUser((int) $user['id']);

        $this->render('dashboard', [
            'title' => 'Dashboard',
            'feedbacks' => $feedbacks,
            'createToken' => Csrf::token('feedback_create'),
        ]);
    }
}