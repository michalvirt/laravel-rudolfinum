<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
}
