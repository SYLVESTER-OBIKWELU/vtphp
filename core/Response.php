<?php

namespace Core;

class Response
{
    protected $content;
    protected $status = 200;
    protected $headers = [];

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function send($content = null)
    {
        if ($content !== null) {
            $this->content = $content;
        }

        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        echo $this->content;
    }

    public function json($data, $status = 200)
    {
        $this->setStatus($status);
        $this->setHeader('Content-Type', 'application/json');
        $this->setContent(json_encode($data));
        $this->send();
    }

    public function redirect($url, $status = 302)
    {
        $this->setStatus($status);
        $this->setHeader('Location', $url);
        $this->send();
        exit;
    }

    public function download($file, $name = null)
    {
        if (!file_exists($file)) {
            throw new \Exception("File not found: {$file}");
        }

        $name = $name ?: basename($file);

        $this->setHeader('Content-Type', 'application/octet-stream');
        $this->setHeader('Content-Disposition', "attachment; filename=\"{$name}\"");
        $this->setHeader('Content-Length', filesize($file));

        readfile($file);
        exit;
    }
}
