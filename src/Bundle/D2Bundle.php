<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Bundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class D2Bundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__, 2);
    }
}
