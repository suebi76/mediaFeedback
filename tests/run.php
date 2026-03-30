<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use MediaFeedbackV2\Activities\ActivityRegistry;
use MediaFeedbackV2\Activities\OpenQuestionActivity;
use MediaFeedbackV2\Activities\SingleChoiceActivity;
use MediaFeedbackV2\Controllers\ResultController;
use MediaFeedbackV2\Core\Database;
use MediaFeedbackV2\Core\MigrationRunner;
use MediaFeedbackV2\Core\SystemCheck;
use MediaFeedbackV2\Models\ActivityBlock;
use MediaFeedbackV2\Models\Answer;
use MediaFeedbackV2\Models\Feedback;
use MediaFeedbackV2\Models\Response;
use MediaFeedbackV2\Models\User;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tempFile(string $prefix): string
{
    $path = tempnam(sys_get_temp_dir(), $prefix);
    if ($path === false) {
        throw new RuntimeException('Temp file could not be created.');
    }
    return $path;
}

$results = [];

$test = static function (string $name, callable $callback) use (&$results): void {
    $callback();
    $results[] = $name;
};

$test('migrations create tables', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $count = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ('users','feedbacks','activity_blocks','responses','answers','media')")->fetchColumn();
    assertTrue($count === 6, 'Expected all core tables to exist.');
    @unlink($dbPath);
});

$test('legacy import is idempotent', static function (): void {
    $legacyPath = tempFile('mfv2-legacy-');
    $legacy = new PDO('sqlite:' . $legacyPath);
    $legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $legacy->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, password TEXT, role TEXT, created_at TEXT)');
    $legacy->exec("INSERT INTO users (name, email, password, role, created_at) VALUES ('Admin', 'admin@example.test', 'hash-1', 'admin', '2026-01-01T00:00:00+00:00')");
    $legacy->exec("INSERT INTO users (name, email, password, role, created_at) VALUES ('Creator', 'creator@example.test', 'hash-2', 'user', '2026-01-02T00:00:00+00:00')");

    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    $runner = new MigrationRunner($database);
    $runner->run();
    $runner->importLegacyUsers($legacyPath);
    $runner->importLegacyUsers($legacyPath);

    $pdo = $database->connect();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    assertTrue($count === 2, 'Expected imported users without duplicates.');

    @unlink($legacyPath);
    @unlink($dbPath);
});

$test('user model creates users and tracks admin count', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $users = new User($database);

    $firstAdminId = $users->create('Admin Eins', 'admin1@example.test', 'Passwort123', 'admin');
    $creatorId = $users->create('Creator Eins', 'creator1@example.test', 'Passwort123', 'creator');

    assertTrue($firstAdminId > 0, 'Expected first admin to be created.');
    assertTrue($creatorId > 0, 'Expected creator to be created.');
    assertTrue($users->countAdmins() === 1, 'Expected exactly one admin after creating one admin and one creator.');

    $users->create('Admin Zwei', 'admin2@example.test', 'Passwort123', 'admin');
    assertTrue($users->countAdmins() === 2, 'Expected admin count to increase when a second admin is created.');

    $users->delete($creatorId);
    assertTrue($users->find($creatorId) === null, 'Expected deleted creator to no longer exist.');

    @unlink($dbPath);
});

$test('user model normalizes email addresses for lookup', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $users = new User($database);

    $users->create('Normalisiert', 'Mixed.Case@Example.Test', 'Passwort123', 'creator');
    $user = $users->findByEmail('mixed.case@example.test');

    assertTrue($user !== null, 'Expected normalized email lookup to find the created user.');
    assertTrue(($user['email'] ?? null) === 'mixed.case@example.test', 'Expected stored email address to be normalized to lowercase.');

    @unlink($dbPath);
});

$test('feedback settings are normalized and persisted', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Settings User',
        'settings@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $feedbacks = new Feedback($database);
    $feedbackId = $feedbacks->create($userId, 'Einstellungen', 'one-per-page');
    $feedbacks->update($feedbackId, $userId, [
        'title' => 'Einstellungen',
        'layout' => 'classic',
        'description' => 'Beschreibung',
        'settings' => [
            'intro_enabled' => true,
            'intro_text' => 'Willkommen',
            'progress_bar' => false,
            'estimated_time_minutes' => 6,
            'limit_one_response_per_device' => true,
        ],
    ]);

    $stored = $feedbacks->findOwned($feedbackId, $userId);
    $settings = Feedback::decodeSettings((string) $stored['settings_json']);

    assertTrue($settings['intro_enabled'] === true, 'Expected intro page setting to persist.');
    assertTrue($settings['intro_text'] === 'Willkommen', 'Expected intro text to persist.');
    assertTrue($settings['progress_bar'] === false, 'Expected progress bar setting to persist.');
    assertTrue($settings['estimated_time_minutes'] === 6, 'Expected estimated time to persist.');
    assertTrue($settings['limit_one_response_per_device'] === true, 'Expected device limit setting to persist.');

    @unlink($dbPath);
});

$test('activity registry exposes seven core activities', static function (): void {
    $registry = new ActivityRegistry();
    $types = array_keys($registry->all());
    sort($types);
    assertTrue($types === ['audio', 'image', 'open_question', 'rating', 'single_choice', 'text', 'video'], 'Unexpected activity registry contents.');
});

$test('single choice validation rejects less than two options', static function (): void {
    $activity = new SingleChoiceActivity();
    $errors = $activity->validateEditorData(['label' => 'Frage', 'options' => ['A']]);
    assertTrue(count($errors) === 1, 'Single choice should require at least two options.');
});

$test('open question required validation enforces an answer', static function (): void {
    $activity = new OpenQuestionActivity();
    $error = $activity->validateResponse(['required' => true], ['value_text' => null, 'media_path' => null]);
    assertTrue($error !== null, 'Required open question should reject empty answers.');
});

$test('reorder page updates sort order deterministically', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Test User',
        'test@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);

    $blocks = new ActivityBlock($database);
    $feedbackId = (new Feedback($database))->create((int) $pdo->lastInsertId(), 'Sort Test', 'one-per-page');
    $firstId = $blocks->create($feedbackId, 'text', 0, 0, ['body' => 'A']);
    $secondId = $blocks->create($feedbackId, 'text', 0, 1, ['body' => 'B']);
    $thirdId = $blocks->create($feedbackId, 'text', 0, 2, ['body' => 'C']);

    $blocks->reorderPage($feedbackId, 0, [$thirdId, $firstId, $secondId]);
    $ordered = array_column($blocks->forFeedback($feedbackId), 'id');

    assertTrue($ordered === [$thirdId, $firstId, $secondId], 'Expected reorderPage to persist the submitted block order.');
    @unlink($dbPath);
});

$test('reorder feedback can move blocks across pages', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Cross Page User',
        'crosspage@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $blocks = new ActivityBlock($database);
    $feedbackId = (new Feedback($database))->create($userId, 'Cross Page Test', 'one-per-page');
    $firstId = $blocks->create($feedbackId, 'text', 0, 0, ['body' => 'A']);
    $secondId = $blocks->create($feedbackId, 'text', 0, 1, ['body' => 'B']);
    $thirdId = $blocks->create($feedbackId, 'text', 1, 0, ['body' => 'C']);

    $blocks->reorderFeedback($feedbackId, [
        ['page_number' => 0, 'ordered_ids' => [$firstId]],
        ['page_number' => 1, 'ordered_ids' => [$thirdId, $secondId]],
    ]);

    $ordered = $blocks->forFeedback($feedbackId);

    assertTrue((int) $ordered[0]['id'] === $firstId && (int) $ordered[0]['page_number'] === 0 && (int) $ordered[0]['sort_order'] === 0, 'Expected first block to stay on page 0.');
    assertTrue((int) $ordered[1]['id'] === $thirdId && (int) $ordered[1]['page_number'] === 1 && (int) $ordered[1]['sort_order'] === 0, 'Expected third block to stay first on page 1.');
    assertTrue((int) $ordered[2]['id'] === $secondId && (int) $ordered[2]['page_number'] === 1 && (int) $ordered[2]['sort_order'] === 1, 'Expected moved block to be appended on the next page.');

    @unlink($dbPath);
});

$test('duplicate block inserts a copy directly after the original', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Duplicate User',
        'duplicate@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $blocks = new ActivityBlock($database);
    $feedbackId = (new Feedback($database))->create($userId, 'Duplicate Test', 'one-per-page');
    $firstId = $blocks->create($feedbackId, 'text', 0, 0, ['content' => '<p>A</p>', 'required' => false]);
    $secondId = $blocks->create($feedbackId, 'text', 0, 1, ['content' => '<p>B</p>', 'required' => false]);

    $copyId = $blocks->duplicate($firstId);
    $ordered = $blocks->forFeedback($feedbackId);

    assertTrue($copyId !== null, 'Expected duplicate() to return a new block id.');
    assertTrue(count($ordered) === 3, 'Expected duplicated block to increase block count.');
    assertTrue((int) $ordered[0]['id'] === $firstId, 'Expected original block to remain first.');
    assertTrue((int) $ordered[1]['id'] === (int) $copyId, 'Expected duplicated block directly after original.');
    assertTrue((int) $ordered[2]['id'] === $secondId, 'Expected following block to shift down.');

    @unlink($dbPath);
});

$test('move to adjacent page keeps reading order stable', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Move Page User',
        'movepage@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $blocks = new ActivityBlock($database);
    $feedbackId = (new Feedback($database))->create($userId, 'Move Page Test', 'one-per-page');
    $firstId = $blocks->create($feedbackId, 'text', 0, 0, ['content' => '<p>A</p>', 'required' => false]);
    $secondId = $blocks->create($feedbackId, 'text', 0, 1, ['content' => '<p>B</p>', 'required' => false]);
    $thirdId = $blocks->create($feedbackId, 'text', 1, 0, ['content' => '<p>C</p>', 'required' => false]);

    $blocks->moveToAdjacentPage($secondId, 'next');
    $blocks->resequence($feedbackId);
    $ordered = $blocks->forFeedback($feedbackId);

    assertTrue((int) $ordered[0]['id'] === $firstId && (int) $ordered[0]['page_number'] === 0, 'Expected first block to remain on page 0.');
    assertTrue((int) $ordered[1]['id'] === $secondId && (int) $ordered[1]['page_number'] === 1 && (int) $ordered[1]['sort_order'] === 0, 'Expected moved block to start the next page.');
    assertTrue((int) $ordered[2]['id'] === $thirdId && (int) $ordered[2]['page_number'] === 1 && (int) $ordered[2]['sort_order'] === 1, 'Expected existing next-page block to move behind the transferred block.');

    $blocks->moveToAdjacentPage($secondId, 'previous');
    $blocks->resequence($feedbackId);
    $orderedBack = $blocks->forFeedback($feedbackId);

    assertTrue((int) $orderedBack[0]['id'] === $firstId && (int) $orderedBack[0]['page_number'] === 0, 'Expected first block to stay first after moving back.');
    assertTrue((int) $orderedBack[1]['id'] === $secondId && (int) $orderedBack[1]['page_number'] === 0 && (int) $orderedBack[1]['sort_order'] === 1, 'Expected moved block to be appended to the previous page.');

    @unlink($dbPath);
});

$test('split from block starts a new page with trailing blocks', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Split User',
        'split@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $blocks = new ActivityBlock($database);
    $feedbackId = (new Feedback($database))->create($userId, 'Split Test', 'one-per-page');
    $firstId = $blocks->create($feedbackId, 'text', 0, 0, ['content' => '<p>A</p>', 'required' => false]);
    $secondId = $blocks->create($feedbackId, 'text', 0, 1, ['content' => '<p>B</p>', 'required' => false]);
    $thirdId = $blocks->create($feedbackId, 'text', 0, 2, ['content' => '<p>C</p>', 'required' => false]);

    $blocks->splitFromBlock($secondId);
    $blocks->resequence($feedbackId);
    $ordered = $blocks->forFeedback($feedbackId);

    assertTrue((int) $ordered[0]['id'] === $firstId && (int) $ordered[0]['page_number'] === 0, 'Expected blocks before the split to stay on the original page.');
    assertTrue((int) $ordered[1]['id'] === $secondId && (int) $ordered[1]['page_number'] === 1 && (int) $ordered[1]['sort_order'] === 0, 'Expected split block to become first block on the next page.');
    assertTrue((int) $ordered[2]['id'] === $thirdId && (int) $ordered[2]['page_number'] === 1 && (int) $ordered[2]['sort_order'] === 1, 'Expected following blocks to move onto the new page in order.');

    @unlink($dbPath);
});

$test('public form bootstraps recorder script in classic layout', static function (): void {
    $activity = new OpenQuestionActivity();
    $block = [
        'id' => 7,
        'activity' => $activity,
        'data' => [
            'label' => 'Videoantwort',
            'help_text' => 'Bitte mit Kamera antworten.',
            'allow_text' => false,
            'allow_audio' => true,
            'allow_video' => true,
            'allow_file' => false,
            'required' => true,
        ],
    ];
    $feedback = [
        'id' => 5,
        'slug' => 'classic-test',
        'title' => 'Classic Test',
        'description' => '',
        'layout' => 'classic',
        'status' => 'live',
    ];
    $pages = [0 => [$block]];
    $submitToken = 'token';
    $errorsByBlock = [];
    $oldAnswers = [];
    $preview = false;

    ob_start();
    require V2_TEMPLATES . '/public_form.php';
    $html = (string) ob_get_clean();

    assertTrue(str_contains($html, 'data-recorder-root'), 'Expected recorder markup to be rendered.');
    assertTrue(str_contains($html, 'root.__activateRecorder'), 'Expected recorder bootstrap script to be available in classic layout.');
    assertTrue(str_contains($html, 'Freigabe erneut anfragen'), 'Expected recorder actions to use the clearer permission wording.');
    assertTrue(str_contains($html, 'public-feedback-shell'), 'Expected classic public form to render inside the redesigned public shell.');
    assertTrue(str_contains($html, 'public-classic-section'), 'Expected classic public form to render redesigned page sections.');
});

$test('text activity renders local rich text editor marker', static function (): void {
    $registry = new ActivityRegistry();
    $html = $registry->get('text')->renderEditorForm([
        'content' => '<p>Hallo</p>',
    ]);

    assertTrue(str_contains($html, 'data-richtext'), 'Expected text activity to render the rich text marker.');
    assertTrue(str_contains($html, 'Text gestalten'), 'Expected text activity label to reflect the rich text editor.');
});

$test('editor template uses rich text content for text block summaries', static function (): void {
    $feedback = [
        'id' => 1,
        'title' => 'Zusammenfassungstest',
        'slug' => 'zusammenfassungstest',
        'layout' => 'one-per-page',
        'status' => 'draft',
        'description' => '',
        'settings_json' => json_encode([]),
    ];
    $settings = [];
    $editorToken = 'token';
    $activities = (new ActivityRegistry())->all();
    $textBlock = [
        'id' => 11,
        'feedback_id' => 1,
        'activity_type' => 'text',
        'page_number' => 0,
        'sort_order' => 0,
        'data' => ['content' => '<h1>Hallo Welt</h1><p>Formatierter Inhalt</p>'],
        'activity' => $activities['text'],
    ];
    $pages = [0 => [$textBlock]];
    $pageSummaries = [
        0 => [
            'block_count' => 1,
            'question_count' => 0,
            'content_count' => 1,
            'preview' => 'Hallo Welt',
        ],
    ];

    ob_start();
    require V2_TEMPLATES . '/feedback_edit.php';
    $html = (string) ob_get_clean();

    assertTrue(str_contains($html, 'Text: Hallo Welt'), 'Expected text block title to use sanitized rich text content.');
    assertTrue(str_contains($html, 'Wird auf dieser Seite direkt als Text angezeigt.'), 'Expected text block summary to detect saved rich text content.');
    assertTrue(!str_contains($html, 'Noch kein Inhalt hinterlegt.'), 'Expected saved text content to avoid empty summary copy.');
});

$test('html sanitizer keeps formatting tables and strips unsafe code', static function (): void {
    $dirty = '<p style="text-align:center;color:#ff0000">Hallo</p><table><tr><td style="background-color:#fff000">Zelle</td></tr></table><script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">Link</a>';
    $clean = \MediaFeedbackV2\Support\HtmlSanitizer::clean($dirty);

    assertTrue(str_contains($clean, '<table>'), 'Expected sanitizer to keep table markup.');
    assertTrue(str_contains($clean, 'text-align: center'), 'Expected sanitizer to keep safe text alignment.');
    assertTrue(!str_contains($clean, '<script'), 'Expected sanitizer to remove script tags.');
    assertTrue(!str_contains($clean, 'onclick='), 'Expected sanitizer to remove event handlers.');
    assertTrue(!str_contains($clean, 'javascript:'), 'Expected sanitizer to strip javascript urls.');
});

$test('open question render advertises mobile capture fallback', static function (): void {
    $activity = new OpenQuestionActivity();
    $html = $activity->renderPublic([
        'id' => 9,
    ], [
        'label' => 'Bitte antworten',
        'help_text' => '',
        'allow_text' => false,
        'allow_audio' => true,
        'allow_video' => true,
        'allow_file' => false,
        'required' => false,
    ]);

    assertTrue(str_contains($html, 'capture="user"'), 'Expected mobile capture hint on media file inputs.');
    assertTrue(str_contains($html, 'Alternativ Video hochladen') || str_contains($html, 'Alternativ Audio hochladen'), 'Expected fallback guidance for mobile browsers.');
    assertTrue(str_contains($html, 'public-upload-trigger'), 'Expected custom mobile-friendly file picker trigger.');
    assertTrue(str_contains($html, 'public-question-head'), 'Expected open question to render redesigned question header.');
    assertTrue(str_contains($html, 'public-upload-card'), 'Expected open question to render redesigned upload card.');
    assertTrue(str_contains($html, 'Wie möchtest du antworten?'), 'Expected open question to ask for the preferred answer mode first when multiple modes are available.');
    assertTrue(str_contains($html, 'data-answer-mode-panel="audio"'), 'Expected answer mode panels to be rendered for conditional display.');
});

$test('rating and single choice render redesigned public selection cards', static function (): void {
    $registry = new ActivityRegistry();

    $ratingHtml = $registry->get('rating')->renderPublic([
        'id' => 12,
    ], [
        'label' => 'Wie war es?',
        'help_text' => 'Bitte bewerte die Erfahrung.',
        'scale' => 5,
        'required' => true,
    ]);

    $singleChoiceHtml = $registry->get('single_choice')->renderPublic([
        'id' => 13,
    ], [
        'label' => 'Was passt am besten?',
        'help_text' => 'Bitte wähle genau eine Option.',
        'options' => ['Option A', 'Option B', 'Option C'],
        'required' => false,
    ]);

    assertTrue(str_contains($ratingHtml, 'public-rating-grid'), 'Expected rating question to render redesigned rating grid.');
    assertTrue(str_contains($ratingHtml, 'public-choice-card'), 'Expected rating question to render selection cards.');
    assertTrue(str_contains($singleChoiceHtml, 'public-choice-grid'), 'Expected single choice question to render redesigned option grid.');
    assertTrue(str_contains($singleChoiceHtml, 'public-question-pill'), 'Expected single choice question to render meta pills.');
});

$test('audio and video activities render editor recorders', static function (): void {
    $registry = new ActivityRegistry();
    $audioHtml = $registry->get('audio')->renderEditorForm([
        'media_path' => '',
        'caption' => '',
    ]);
    $videoHtml = $registry->get('video')->renderEditorForm([
        'media_path' => '',
        'caption' => '',
    ]);

    assertTrue(str_contains($audioHtml, 'data-editor-recorder'), 'Expected audio editor recorder root.');
    assertTrue(str_contains($audioHtml, 'Audio direkt aufnehmen'), 'Expected audio recorder label in editor.');
    assertTrue(str_contains($videoHtml, 'data-editor-recorder'), 'Expected video editor recorder root.');
    assertTrue(str_contains($videoHtml, 'Video direkt aufnehmen'), 'Expected video recorder label in editor.');
});

$test('content activities do not render empty media urls in public view', static function (): void {
    $registry = new ActivityRegistry();

    $imageHtml = $registry->get('image')->renderPublic([], [
        'media_path' => '',
        'alt_text' => '',
        'caption' => '',
    ]);
    $audioHtml = $registry->get('audio')->renderPublic([], [
        'media_path' => '',
        'caption' => '',
    ]);
    $videoHtml = $registry->get('video')->renderPublic([], [
        'media_path' => '',
        'caption' => '',
    ]);

    assertTrue(!str_contains($imageHtml, '/media?file='), 'Expected image public view to skip empty media URLs.');
    assertTrue(!str_contains($audioHtml, '/media?file='), 'Expected audio public view to skip empty media URLs.');
    assertTrue(!str_contains($videoHtml, '/media?file='), 'Expected video public view to skip empty media URLs.');
});

$test('system check exposes upload limit metadata', static function (): void {
    $results = (new SystemCheck())->results();

    assertTrue(isset($results['limits']['upload_max_bytes']), 'Expected upload_max_bytes in system check results.');
    assertTrue(isset($results['limits']['post_max_bytes']), 'Expected post_max_bytes in system check results.');
    assertTrue(isset($results['limits']['video_upload_ready']), 'Expected video_upload_ready flag in system check results.');
});

$test('public form can render a welcome screen with intro settings', static function (): void {
    $feedback = [
        'id' => 9,
        'slug' => 'intro-test',
        'title' => 'Intro Test',
        'description' => 'Beschreibung',
        'layout' => 'one-per-page',
        'status' => 'live',
    ];
    $pages = [0 => []];
    $submitToken = 'token';
    $errorsByBlock = [];
    $oldAnswers = [];
    $preview = false;
    $showIntroOnly = true;
    $settings = [
        'intro_enabled' => true,
        'intro_text' => 'Willkommen zum Test',
        'progress_bar' => true,
        'estimated_time_minutes' => 3,
        'limit_one_response_per_device' => true,
    ];

    ob_start();
    require V2_TEMPLATES . '/public_form.php';
    $html = (string) ob_get_clean();

    assertTrue(str_contains($html, 'Feedback starten'), 'Expected intro screen start button to be rendered.');
    assertTrue(str_contains($html, 'Willkommen zum Test'), 'Expected intro text to be shown.');
    assertTrue(str_contains($html, '3 Min.'), 'Expected estimated time to be shown on intro screen.');
    assertTrue(str_contains($html, '1 Antwort pro Gerät') || str_contains($html, 'genau eine Antwort'), 'Expected one-device hint to be shown on intro screen.');
    assertTrue(str_contains($html, 'public-feedback-hero'), 'Expected redesigned public hero on intro screen.');
    assertTrue(!str_contains($html, 'public-intro-stage'), 'Expected intro screen to rely on the hero instead of a second lower intro stage.');
    assertTrue(str_contains($html, 'Bevor es losgeht'), 'Expected intro text panel in the hero when intro text exists.');
});

$test('editor renders unsaved changes safeguards', static function (): void {
    $registry = new ActivityRegistry();
    $feedback = [
        'id' => 12,
        'slug' => 'editor-guard-test',
        'title' => 'Editor Guard Test',
        'description' => 'Beschreibung',
        'layout' => 'one-per-page',
        'status' => 'draft',
        'settings_json' => json_encode([
            'intro_enabled' => false,
            'intro_text' => '',
            'progress_bar' => true,
            'estimated_time_minutes' => 5,
            'limit_one_response_per_device' => false,
        ], JSON_THROW_ON_ERROR),
    ];
    $settings = Feedback::decodeSettings((string) $feedback['settings_json']);
    $activities = $registry->all();
    $pages = [
        0 => [[
            'id' => 21,
            'activity_type' => 'open_question',
            'sort_order' => 0,
            'activity' => $activities['open_question'],
            'data' => [
                'label' => 'Frage',
                'help_text' => '',
                'allow_text' => true,
                'allow_audio' => false,
                'allow_video' => false,
                'allow_file' => false,
                'required' => true,
            ],
        ]],
    ];
    $pageSummaries = [
        0 => [
            'block_count' => 1,
            'question_count' => 1,
            'content_count' => 0,
            'preview' => 'Offene Frage',
        ],
    ];
    $editorToken = 'token';

    ob_start();
    require V2_TEMPLATES . '/feedback_edit.php';
    $html = (string) ob_get_clean();

    assertTrue(str_contains($html, 'editor-unsaved-warning'), 'Expected unsaved warning container in editor.');
    assertTrue(str_contains($html, 'data-track-dirty'), 'Expected tracked dirty forms in editor.');
    assertTrue(str_contains($html, 'beforeunload'), 'Expected browser safeguard script in editor.');
    assertTrue(str_contains($html, 'editor-page-nav'), 'Expected page navigation to be rendered in editor.');
    assertTrue(str_contains($html, 'Inhalt oder Frage hinzufügen'), 'Expected add-block panel to be rendered in editor.');
    assertTrue(str_contains($html, 'Ab hier neue Seite'), 'Expected block-level page split action in editor.');
    assertTrue(str_contains($html, 'editor-feedback-modal'), 'Expected settings modal to be rendered in editor.');
    assertTrue(str_contains($html, 'editor-status-modal'), 'Expected publishing modal to be rendered in editor.');
    assertTrue(str_contains($html, 'editor-save-row'), 'Expected mobile-friendly editor save row.');
});

$test('helpers can build public feedback and qr URLs', static function (): void {
    $_SERVER['HTTP_HOST'] = 'example.test';
    $_SERVER['HTTPS'] = 'on';

    $feedback = [
        'id' => 15,
        'slug' => 'hilfe-test',
    ];

    assertTrue(public_feedback_url($feedback) === 'https://example.test' . V2_BASE_URL . '/f?slug=hilfe-test', 'Expected absolute public feedback URL.');
    assertTrue(feedback_qr_url($feedback) === 'https://example.test' . V2_BASE_URL . '/share/qr?id=15', 'Expected absolute QR URL.');
});

$test('response model can detect submissions for a device', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $responses = new Response($database);
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Device User',
        'device@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $feedbackId = (new Feedback($database))->create((int) $pdo->lastInsertId(), 'Device Limit Test', 'one-per-page');

    $responses->create($feedbackId, 'session-1', 'mf-abc123');

    assertTrue($responses->existsForFeedbackDevice($feedbackId, 'mf-abc123') === true, 'Expected device hash to be detected for this feedback.');
    assertTrue($responses->existsForFeedbackDevice($feedbackId, 'mf-other') === false, 'Expected other device hash not to match.');

    @unlink($dbPath);
});

$test('feedback deletion removes its records and unreferenced upload files', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Delete User',
        'delete@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $feedbacks = new Feedback($database);
    $blocks = new ActivityBlock($database);
    $responses = new Response($database);
    $answers = new Answer($database);
    $media = new \MediaFeedbackV2\Models\Media($database);

    $sharedName = 'shared-' . bin2hex(random_bytes(6)) . '.png';
    $uniqueName = 'unique-' . bin2hex(random_bytes(6)) . '.wav';
    file_put_contents(V2_DATA . '/uploads/' . $sharedName, 'shared');
    file_put_contents(V2_DATA . '/uploads/' . $uniqueName, 'unique');

    $feedbackId = $feedbacks->create($userId, 'Zu löschen', 'one-per-page');
    $otherFeedbackId = $feedbacks->create($userId, 'Bleibt bestehen', 'one-per-page');

    $blockId = $blocks->create($feedbackId, 'image', 0, 0, [
        'media_path' => $sharedName,
        'alt_text' => 'Alt',
        'caption' => 'Caption',
    ]);
    $blocks->create($otherFeedbackId, 'image', 0, 0, [
        'media_path' => $sharedName,
        'alt_text' => 'Alt 2',
        'caption' => 'Caption 2',
    ]);

    $responseId = $responses->create($feedbackId, 'session-delete', 'mf-delete');
    $answers->create($responseId, $blockId, null, null, $uniqueName);
    $media->record($feedbackId, $blockId, $responseId, 'audio', $uniqueName, 'original.wav', 'audio/wav', 128);

    $feedbacks->deleteWithAssets($feedbackId, $userId);

    assertTrue($feedbacks->findOwned($feedbackId, $userId) === null, 'Expected feedback row to be deleted.');
    assertTrue((int) $pdo->query('SELECT COUNT(*) FROM activity_blocks WHERE feedback_id = ' . $feedbackId)->fetchColumn() === 0, 'Expected activity blocks to cascade-delete.');
    assertTrue((int) $pdo->query('SELECT COUNT(*) FROM responses WHERE feedback_id = ' . $feedbackId)->fetchColumn() === 0, 'Expected responses to cascade-delete.');
    assertTrue((int) $pdo->query('SELECT COUNT(*) FROM media WHERE feedback_id = ' . $feedbackId)->fetchColumn() === 0, 'Expected media rows to cascade-delete.');
    assertTrue(!is_file(V2_DATA . '/uploads/' . $uniqueName), 'Expected unique upload file to be removed from disk.');
    assertTrue(is_file(V2_DATA . '/uploads/' . $sharedName), 'Expected shared upload file to remain when another feedback still references it.');

    @unlink(V2_DATA . '/uploads/' . $sharedName);
    @unlink($dbPath);
});

$test('result controller builds participant and question views from question blocks only', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Result User',
        'results@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $feedbacks = new Feedback($database);
    $blocks = new ActivityBlock($database);
    $responses = new Response($database);
    $answers = new Answer($database);

    $feedbackId = $feedbacks->create($userId, 'Ergebnisse Test', 'one-per-page');
    $blocks->create($feedbackId, 'text', 0, 0, ['content' => '<p>Einleitung</p>']);
    $openQuestionId = $blocks->create($feedbackId, 'open_question', 0, 1, [
        'label' => 'Was nimmst du mit?',
        'help_text' => 'Gib eine kurze Rückmeldung.',
        'allow_text' => true,
        'required' => true,
    ]);
    $ratingId = $blocks->create($feedbackId, 'rating', 1, 0, [
        'label' => 'Wie hilfreich war es?',
        'scale' => 5,
        'required' => true,
    ]);
    $choiceId = $blocks->create($feedbackId, 'single_choice', 1, 1, [
        'label' => 'Passt das Format?',
        'options' => ['Ja', 'Nein'],
        'required' => false,
    ]);

    $firstResponseId = $responses->create($feedbackId, 'session-a', 'mf-device-a');
    $answers->create($firstResponseId, $openQuestionId, 'Sehr hilfreich', null, null);
    $answers->create($firstResponseId, $ratingId, '4', null, null);
    $answers->create($firstResponseId, $choiceId, 'Ja', null, null);

    $secondResponseId = $responses->create($feedbackId, 'session-b', 'mf-device-b');
    $answers->create($secondResponseId, $openQuestionId, null, null, 'aufnahme.webm');

    $controller = new ResultController();
    $reflection = new ReflectionClass($controller);
    $databaseProperty = $reflection->getParentClass()->getProperty('database');
    $databaseProperty->setAccessible(true);
    $databaseProperty->setValue($controller, $database);

    $method = $reflection->getMethod('buildResultData');
    $method->setAccessible(true);
    [$questionBlocks, $responsesView, $questionsView, $exportRows] = $method->invoke($controller, $feedbackId);

    assertTrue(count($questionBlocks) === 3, 'Expected only question blocks in results data.');
    assertTrue(count($responsesView) === 2, 'Expected two grouped participant responses.');
    assertTrue(count($questionsView) === 3, 'Expected three question entries in questions view.');
    assertTrue(count($exportRows) === 2, 'Expected export rows per response.');
    assertTrue($questionBlocks[0]['label'] === 'Was nimmst du mit?', 'Expected open question to remain first visible question.');
    assertTrue(($responsesView[0]['answers'][$openQuestionId]['display_type'] ?? null) === 'media', 'Expected newest open answer with media to normalize as media.');
    assertTrue(($responsesView[1]['answers'][$ratingId]['display_type'] ?? null) === 'rating', 'Expected rating answer to normalize as rating.');
    assertTrue(($responsesView[1]['answers'][$choiceId]['choice_value'] ?? null) === 'Ja', 'Expected single choice answer to keep selected value.');
    assertTrue(count($questionsView[0]['responses']) === 2, 'Expected open question to collect responses across participants.');
    assertTrue(str_contains($exportRows[0]['answers'][$openQuestionId] ?? '', '[Datei:'), 'Expected media answers to be represented in export rows.');

    @unlink($dbPath);
});

$test('results template renders dual views without inline export actions', static function (): void {
    $feedback = [
        'id' => 21,
        'title' => 'Ergebnisansicht Test',
        'layout' => 'one-per-page',
    ];
    $questionBlocks = [
        [
            'id' => 101,
            'label' => 'Was nimmst du mit?',
            'type_label' => 'Offene Antwort',
            'page_number' => 0,
        ],
        [
            'id' => 102,
            'label' => 'Wie hilfreich war es?',
            'type_label' => 'Bewertung',
            'page_number' => 1,
        ],
    ];
    $responsesView = [
        [
            'response_id' => 77,
            'device_hash' => 'mf-device-123456789',
            'submitted_at' => '2026-03-30T10:00:00+00:00',
            'answers' => [
                101 => [
                    'has_answer' => true,
                    'display_type' => 'media',
                    'media_path' => 'clip.webm',
                    'media_kind' => 'video',
                    'media_name' => 'clip.webm',
                    'supplemental_text' => '',
                ],
                102 => [
                    'has_answer' => true,
                    'display_type' => 'rating',
                    'rating_value' => 4,
                ],
            ],
        ],
    ];
    $questionsView = [
        [
            'id' => 101,
            'label' => 'Was nimmst du mit?',
            'type_label' => 'Offene Antwort',
            'help_text' => 'Gib eine kurze Rückmeldung.',
            'responses' => [
                [
                    'response_id' => 77,
                    'device_hash' => 'mf-device-123456789',
                    'submitted_at' => '2026-03-30T10:00:00+00:00',
                    'answer' => [
                        'has_answer' => true,
                        'display_type' => 'media',
                        'media_path' => 'clip.webm',
                        'media_kind' => 'video',
                        'media_name' => 'clip.webm',
                        'supplemental_text' => '',
                    ],
                ],
            ],
        ],
    ];
    $responseCount = count($responsesView);
    $questionCount = count($questionBlocks);
    $exportRows = [];

    ob_start();
    require V2_TEMPLATES . '/results.php';
    $html = (string) ob_get_clean();

    assertTrue(str_contains($html, 'Nach Teilnehmenden'), 'Expected participant tab in redesigned results view.');
    assertTrue(str_contains($html, 'Nach Fragen'), 'Expected question tab in redesigned results view.');
    assertTrue(str_contains($html, 'Zum Editor'), 'Expected editor return action in results header.');
    assertTrue(!str_contains($html, '>CSV<'), 'Expected CSV action to be removed from the results header.');
    assertTrue(!str_contains($html, '>JSON<'), 'Expected JSON action to be removed from the results header.');
    assertTrue(str_contains($html, 'results-workspace'), 'Expected split results workspace layout.');
    assertTrue(str_contains($html, '/v2/media?file=clip.webm'), 'Expected media answers to link through the media controller.');
    assertTrue(str_contains($html, 'Teilnehmer 77'), 'Expected participant navigation and detail labels.');
    assertTrue(str_contains($html, 'Gib eine kurze Rückmeldung.'), 'Expected question help text in question detail view.');
});

$test('result controller keeps answers from removed question blocks visible', static function (): void {
    $dbPath = tempFile('mfv2-db-');
    $database = new Database($dbPath);
    (new MigrationRunner($database))->run();
    $pdo = $database->connect();
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        'Archived Result User',
        'archived-results@example.test',
        'hash',
        'creator',
        date('c'),
        date('c'),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $feedbackId = (new Feedback($database))->create($userId, 'Archivierte Ergebnisse', 'one-per-page');
    $blocks = new ActivityBlock($database);
    $responses = new Response($database);
    $answers = new Answer($database);
    $questionId = $blocks->create($feedbackId, 'open_question', 0, 0, [
        'label' => 'Frühere Frage',
        'allow_text' => true,
    ]);

    $responseId = $responses->create($feedbackId, 'session-archived', 'mf-archived');
    $answers->create($responseId, $questionId, 'Antwort aus älterer Version', null, null);
    $database->connect()->prepare('UPDATE activity_blocks SET activity_type = ?, activity_data = ?, updated_at = ? WHERE id = ?')->execute([
        'text',
        json_encode(['content' => '<p>Später umgebaut</p>'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        date('c'),
        $questionId,
    ]);

    $controller = new ResultController();
    $reflection = new ReflectionClass($controller);
    $databaseProperty = $reflection->getParentClass()->getProperty('database');
    $databaseProperty->setAccessible(true);
    $databaseProperty->setValue($controller, $database);

    $method = $reflection->getMethod('buildResultData');
    $method->setAccessible(true);
    [$questionBlocks, $responsesView, $questionsView] = $method->invoke($controller, $feedbackId);

    assertTrue(count($questionBlocks) === 1, 'Expected archived answer to create a synthetic question entry.');
    assertTrue(!empty($questionBlocks[0]['is_archived']), 'Expected synthetic question entry to be marked as archived.');
    assertTrue(($responsesView[0]['answers'][$questionId]['display_type'] ?? null) === 'text', 'Expected archived answer to stay visible in participant view.');
    assertTrue(count($questionsView[0]['responses']) === 1, 'Expected archived question view to include its existing response.');

    @unlink($dbPath);
});

echo 'OK: ' . implode(', ', $results) . PHP_EOL;
