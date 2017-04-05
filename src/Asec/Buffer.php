<?php

namespace Wazly\ASEC;

class Buffer
{
    public $pooled = [];

    public function isEmpty(): bool
    {
        return $this->pooled === [];
    }

    public function pool(string $action, string $selector, $value = null)
    {
        $this->pooled[] = (object)[
            'action' => $action,
            'selector' => $selector,
            'value' => $value,
        ];
    }
}
