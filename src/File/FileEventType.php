<?php

declare(strict_types=1);

namespace Aol\File;

enum FileEventType: string
{
    case Created = 'created';
    case Modified = 'modified';
    case Deleted = 'deleted';
}
