<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Controllers;

use MediaFeedbackV2\Core\Controller;
use MediaFeedbackV2\Core\Csrf;
use MediaFeedbackV2\Core\Database;
use MediaFeedbackV2\Core\MigrationRunner;
use MediaFeedbackV2\Core\SystemCheck;
use MediaFeedbackV2\Models\User;

class SetupController extends Controller
{
    public function show(): void
    {
        $systemCheck = new SystemCheck();
        $this->render('setup', [
            'title' => 'mediaFeedback Setup',
            'systemCheck' => $systemCheck,
            'results' => $systemCheck->results(),
            'csrfToken' => Csrf::token('setup'),
        ]);
    }

    public function install(): void
    {
        $this->enforceCsrf('setup');

        $systemCheck = new SystemCheck();
        if (!$systemCheck->allGood()) {
            $this->flash('error', 'Die Systemprüfung ist noch nicht erfolgreich.');
            $this->redirect('/setup');
        }

        $appName = trim((string) ($_POST['app_name'] ?? 'mediaFeedback'));
        $adminName = trim((string) ($_POST['admin_name'] ?? 'Administrator'));
        $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
        $adminPassword = (string) ($_POST['admin_password'] ?? '');

        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPassword) < 8) {
            $this->flash('error', 'Bitte gib eine gültige Admin-E-Mail und ein Passwort mit mindestens 8 Zeichen an.');
            $this->redirect('/setup');
        }

        $databasePath = 'data/mediafeedback.sqlite';
        $config = "<?php\n\nreturn [\n    'app_name' => " . var_export($appName, true) . ",\n    'db_path' => " . var_export($databasePath, true) . ",\n    'installed_at' => " . var_export(date('c'), true) . ",\n];\n";
        file_put_contents(V2_CONFIG, $config);

        $database = new Database(V2_DATA . '/mediafeedback.sqlite');
        $runner = new MigrationRunner($database);
        $runner->run();

        $userModel = new User($database);
        $userModel->createOrUpdateAdmin($adminName, $adminEmail, $adminPassword);

        $this->flash('success', 'Setup abgeschlossen. Der Administrator wurde angelegt.');
        $this->redirect('/login');
    }
}
