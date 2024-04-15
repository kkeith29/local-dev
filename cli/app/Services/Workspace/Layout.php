<?php declare(strict_types=1);

namespace App\Services\Workspace;

enum Layout: string
{
    case Grid = 'grid';
    case Stack = 'stack';
}
