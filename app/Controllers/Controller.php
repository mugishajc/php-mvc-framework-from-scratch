<?php

declare(strict_types=1);

namespace App\Controllers;

use Framework\Http\Response;
use Framework\Session\CsrfTokenManager;
use Framework\Session\Session;
use Framework\View\View;

abstract class Controller
{
    public function __construct(
        protected View $view,
        protected Session $session,
        protected CsrfTokenManager $csrf,
    ) {
    }

    /**
     * Renders a template with the flash data and CSRF token every page needs.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        $flash = $this->session->pullFlash();

        return Response::html($this->view->render($template, $data + [
            'flash' => $flash,
            'errors' => $flash['errors'] ?? [],
            'old' => $flash['old'] ?? [],
            'csrfToken' => $this->csrf->token(),
        ]), $status);
    }

    protected function redirect(string $to, ?string $success = null): Response
    {
        if ($success !== null) {
            $this->session->flash('success', $success);
        }

        return Response::redirect($to);
    }

    /**
     * Sends the user back to a form with their input and the errors.
     *
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $old
     */
    protected function back(string $to, array $errors, array $old): Response
    {
        $this->session->flash('errors', $errors);
        $this->session->flash('old', $old);

        return Response::redirect($to);
    }

    protected function notFound(): Response
    {
        return $this->render('errors/404', [], 404);
    }
}
