<?php

namespace App\Enums;

enum EmailNotificationSendMode: string
{
    /** Send the emails right after the action created them. */
    case Immediate = 'immediate';

    /** Collect the emails in the log and send them together on a schedule. */
    case Batch = 'batch';
}
