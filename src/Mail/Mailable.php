<?php

declare(strict_types=1);

namespace VtPhp\Mail;

/**
 * Base class for Blade-rendered emails, e.g. `App\Mail\WelcomeEmail`.
 */
abstract class Mailable
{
    /** @var array<int, string> */
    protected array $to = [];

    protected string $subject = '';

    protected string $view = '';

    /** @var array<string, mixed> */
    protected array $data = [];

    abstract public function build(): void;

    public function to(string $address): static
    {
        $this->to[] = $address;

        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->data = $data;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function recipients(): array
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function render(): string
    {
        return view($this->view, $this->data);
    }
}
