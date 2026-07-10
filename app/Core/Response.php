<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response value object.
 *
 * Also supports "streamed" responses (file downloads produced by
 * PhpSpreadsheet/PhpWord writers) via a callback body.
 */
final class Response
{
    /** @var callable|null */
    private $streamCallback = null;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    /**
     * Response whose body is produced by a callback writing directly to
     * the output stream (Excel/Word download writers).
     *
     * @param array<string, string> $headers
     */
    public static function stream(callable $callback, array $headers = [], int $status = 200): self
    {
        $response = new self('', $status, $headers);
        $response->streamCallback = $callback;

        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        if ($this->streamCallback !== null) {
            ($this->streamCallback)();

            return;
        }

        echo $this->body;
    }
}
