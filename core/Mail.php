<?php

namespace Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail
{
    protected static $instance;
    protected $mailer;
    protected $config;

    public function __construct($config = [])
    {
        $this->config = $config ?: $this->loadConfig();
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }

    public static function getInstance($config = [])
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    protected function loadConfig()
    {
        return [
            'driver' => env('MAIL_DRIVER', 'smtp'),
            'host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ];
    }

    protected function configure()
    {
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config['username'];
        $this->mailer->Password = $this->config['password'];
        $this->mailer->SMTPSecure = $this->config['encryption'];
        $this->mailer->Port = $this->config['port'];
        $this->mailer->setFrom($this->config['from']['address'], $this->config['from']['name']);
    }

    public function to($address, $name = '')
    {
        $this->mailer->addAddress($address, $name);
        return $this;
    }

    public function cc($address, $name = '')
    {
        $this->mailer->addCC($address, $name);
        return $this;
    }

    public function bcc($address, $name = '')
    {
        $this->mailer->addBCC($address, $name);
        return $this;
    }

    public function subject($subject)
    {
        $this->mailer->Subject = $subject;
        return $this;
    }

    public function body($body)
    {
        $this->mailer->Body = $body;
        $this->mailer->isHTML(true);
        return $this;
    }

    public function text($text)
    {
        $this->mailer->AltBody = $text;
        return $this;
    }

    public function attach($path, $name = '')
    {
        $this->mailer->addAttachment($path, $name);
        return $this;
    }

    public function view($view, $data = [])
    {
        $html = View::render($view, $data);
        return $this->body($html);
    }

    public function send()
    {
        try {
            $this->mailer->send();
            $this->reset();
            return true;
        } catch (Exception $e) {
            throw new \Exception("Mail could not be sent. Error: {$this->mailer->ErrorInfo}");
        }
    }

    protected function reset()
    {
        $this->mailer->clearAddresses();
        $this->mailer->clearAttachments();
        $this->mailer->clearCCs();
        $this->mailer->clearBCCs();
        $this->mailer->clearReplyTos();
    }

    public static function make()
    {
        return new self();
    }
}
