<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Paid = 'paid';
    case Overdue = 'overdue';
}
