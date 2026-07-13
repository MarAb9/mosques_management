<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Throwable;

/**
 * Centralized error/exception handling.
 *
 * Technical details go to the log (storage/logs/app.log when writable,
 * otherwise PHP's error_log). Users only ever see a safe Arabic message.
 * With app.debug=true (APP_DEBUG env) full details are shown instead.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly bool $debug,
        private readonly string $logFile,
    ) {
    }

    public function register(): void
    {
        ini_set('display_errors', $this->debug ? '1' : '0');
        error_reporting(E_ALL);

        set_exception_handler(function (Throwable $e): void {
            $this->handleException($e)->send();
        });
    }

    public function handleException(Throwable $e): Response
    {
        if ($e instanceof HttpException) {
            if ($e->redirectTo() !== null) {
                // Legacy pattern: flash the error and bounce back to a list page.
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['error'] = $e->getMessage();
                }

                return Response::redirect($e->redirectTo());
            }

            return Response::html($this->renderErrorPage($e->getMessage()), $e->status());
        }

        $this->log($e);

        if ($this->debug) {
            $body = '<pre dir="ltr">' . htmlspecialchars(
                get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
                ENT_QUOTES,
                'UTF-8'
            ) . '</pre>';

            return Response::html($body, 500);
        }

        return Response::html(
            $this->renderErrorPage('حدث خطأ غير متوقع. يرجى المحاولة لاحقاً'),
            500
        );
    }

    public function log(Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        $dir = dirname($this->logFile);
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }

    private function renderErrorPage(string $message): string
    {
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head><meta charset="UTF-8"><title>خطأ</title></head>
            <body style="font-family: 'Tajawal','Segoe UI',sans-serif; text-align:center; padding-top:4rem;">
                <h1 style="color:#dc3545;">{$safe}</h1>
                <p><a href="index.php">العودة إلى الصفحة الرئيسية</a></p>
            </body>
            </html>
            HTML;
    }
}
