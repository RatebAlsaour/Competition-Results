<?php

namespace App\Notifications\Messages;

class SyriatelMessage
{
    protected $msg;
    protected $templateCode;

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

}
