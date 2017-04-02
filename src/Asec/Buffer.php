<?php

namespace Wazly\Asec;

class Buffer
{
    public $pooled = [];

    public function pool(string $action, string $selector)
    {
        $this->pooled[] = (object)[
            'action' => $action,
            'selector' => $selector,
        ];
    }
}
