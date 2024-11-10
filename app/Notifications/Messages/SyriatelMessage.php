<?php

namespace App\Notifications\Messages;

class SyriatelMessage
{
    protected string $msg;
    protected string $templateCode;

    public static function create()
    {
        return new static();
    }

    public function setMsg(string $msg)
    {
        $this->msg = $msg;
        return $this;
    }

    public function setTemplateCode(string $templateCode)
    {
        $this->templateCode = $templateCode;
        return $this;
    }

    public function getMsg(): string
    {
        return $this->msg;
    }

    public function getTemplateCode(): string
    {
        return $this->templateCode;
    }

}
