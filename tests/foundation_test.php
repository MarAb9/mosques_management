<?php

declare(strict_types=1);

/**
 * MVC foundation smoke test — run inside the app container:
 *   docker compose exec -T app php tests/foundation_test.php
 *
 * Verifies the kernel boots, config resolves env vars, the DB connects
 * through the new Database service, routing + middleware dispatch works,
 * and the view renderer renders.
 */

@session_start();

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0;
$fail = 0;
$check = function (string $name, bool $ok) use (&$pass, &$fail): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . "\n";
    $ok ? $pass++ : $fail++;
};

$check('App boots and is a singleton', $app === App\Core\App::instance());
$check('Config loads app.php', is_string($app->config->get('app.name')));
$check('Config dot access with default', $app->config->get('nope.nope', 'x') === 'x');
$check('Config env DB defaults', $app->config->get('database.name') !== null);
$check('Upload path resolves below the public root', str_ends_with(
    str_replace('\\', '/', (string) $app->config->get('uploads.mosques_dir')),
    '/public/uploads/mosques'
));

// Database connects and matches application row counts
$connection = $app->database->pdo();
$check('Database connects', $connection instanceof PDO);
$count = (int) $connection->query('SELECT COUNT(*) FROM mosques')->fetchColumn();
$check("Mosques readable via new Database ($count rows)", $count > 0);
$check('Database connection stays encapsulated', !isset($GLOBALS['pdo']));

// Session facade uses the same keys
$app->session->flash('success', 'test');
$check('Session flash uses legacy key', ($_SESSION['success'] ?? null) === 'test');
$check('Session pullFlash clears', $app->session->pullFlash('success') === 'test' && !isset($_SESSION['success']));
$check('CSRF token uses legacy key', $app->session->csrfToken() === ($_SESSION['csrf_token'] ?? null));

// Router matching, 404/405 behavior
$router = new App\Core\Router();
$router->get('mosques.php', [stdClass::class, 'x'], [App\Middleware\Authenticate::class]);
$router->post('delete_mosque.php', [stdClass::class, 'x']);
$router->alias('mosques', 'mosques.php');
$check('Router matches GET', $router->match('GET', 'mosques.php')['action'][0] === stdClass::class);
$check('Router alias resolves', $router->match('GET', '/mosques')['action'][0] === stdClass::class);

try {
    $router->match('GET', 'delete_mosque.php');
    $check('Router 405 on wrong method', false);
} catch (App\Exceptions\HttpException $e) {
    $check('Router 405 on wrong method', $e->status() === 405);
}

try {
    $router->match('GET', 'missing.php');
    $check('Router 404 on unknown path', false);
} catch (App\Exceptions\HttpException $e) {
    $check('Router 404 on unknown path', $e->status() === 404);
}

// View renderer
$tmpViews = sys_get_temp_dir() . '/views_' . uniqid();
mkdir($tmpViews . '/layouts', 0777, true);
file_put_contents($tmpViews . '/hello.php', 'Hello <?= $view->e($name) ?>');
file_put_contents($tmpViews . '/layouts/wrap.php', '[<?= $content ?>]');
$view = new App\Core\View($tmpViews);
$check('View renders with escaped data', $view->render('hello', ['name' => '<x>']) === 'Hello &lt;x&gt;');
$check('View renders inside layout', $view->render('hello', ['name' => 'a'], 'layouts.wrap') === '[Hello a]');

// Middleware pipeline via a fake dispatch
$request = new App\Core\Request([], ['csrf_token' => 'bad'], [], ['REQUEST_METHOD' => 'POST']);
try {
    (new App\Middleware\VerifyCsrf($app->session))->handle($request, fn ($r) => App\Core\Response::html('no'));
    $check('VerifyCsrf rejects bad token', false);
} catch (App\Exceptions\HttpException $e) {
    $check('VerifyCsrf rejects bad token', $e->status() === 403);
}

$goodRequest = new App\Core\Request(
    [],
    ['csrf_token' => $app->session->csrfToken()],
    [],
    ['REQUEST_METHOD' => 'POST']
);
$resp = (new App\Middleware\VerifyCsrf($app->session))->handle($goodRequest, fn ($r) => App\Core\Response::html('ok'));
$check('VerifyCsrf passes good token', $resp instanceof App\Core\Response);

unset($_SESSION['user_id']);
$resp = (new App\Middleware\Authenticate($app->session))->handle($goodRequest, fn ($r) => App\Core\Response::html('in'));
$check('Authenticate redirects guests (302)', $resp->status() === 302);

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'editor';
try {
    (new App\Middleware\RequireAdmin($app->session))->handle($goodRequest, fn ($r) => App\Core\Response::html('in'));
    $check('RequireAdmin blocks editor', false);
} catch (App\Exceptions\HttpException $e) {
    $check('RequireAdmin blocks editor', $e->status() === 403);
}
$_SESSION['role'] = 'admin';
$resp = (new App\Middleware\RequireAdmin($app->session))->handle($goodRequest, fn ($r) => App\Core\Response::html('in'));
$check('RequireAdmin passes admin', $resp instanceof App\Core\Response);
unset($_SESSION['user_id'], $_SESSION['role']);

echo "\n--- $pass passed, $fail failed ---\n";
exit($fail > 0 ? 1 : 0);
