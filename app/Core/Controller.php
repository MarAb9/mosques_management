<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller: thin helpers for views, JSON, and redirects.
 */
abstract class Controller
{
    public function __construct(
        protected readonly View $view,
        protected readonly Session $session,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = [], ?string $layout = 'layouts.main'): Response
    {
        if ($layout !== null) {
            $data += [
                'csrfToken' => $this->session->csrfToken(),
                'isAdmin' => $this->session->role() === 'admin',
            ];
        }

        return Response::html($this->view->render($view, $data, $layout));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    protected function redirectWithFlash(string $url, string $type, string $message): Response
    {
        $this->session->flash($type, $message);

        return Response::redirect($url);
    }
}
