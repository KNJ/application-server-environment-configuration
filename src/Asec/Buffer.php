<?php

namespace Wazly\Asec;

class Buffer
{
    public $pooled = [];

    public function pool(string $action, string $selector)
    {
        if (!isset($this->pooled[$action])) {
            $this->pooled[$action] = [];
        }
        $this->pooled[$action][] = $selector;
    }
}
