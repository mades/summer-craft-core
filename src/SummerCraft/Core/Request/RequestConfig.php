<?php

namespace SummerCraft\Core\Request;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class RequestConfig implements SharedComponent
{
    /**
     * Allowed URL Characters
     */
    public string $permittedUriChars = 'A-Za-zА-яЁё0-9~%.:_\-\+\,';
}