<?php

return [
    'customer_has_orders' => 'A customer with orders cannot be deleted. Delete their orders first.',
    'order_has_invoice' => 'The items or customer of an order with a paid invoice cannot be changed.',
    'order_cancelled' => 'A cancelled order cannot be invoiced.',
    'order_paid_delete' => 'An order with a paid invoice cannot be deleted.',
    'range_too_long' => 'The period cannot be longer than one year.',
    'forbidden' => 'You do not have permission to do this.',
    'own_role' => 'You cannot change your own role.',
    'invoice_emailed' => 'The invoice was sent to :email.',
    'invoice_order_locked' => 'An invoice cannot be moved to another order.',
    'invoice_paid_locked' => 'The invoice is paid: only its notes can change. An admin can reopen it.',
    'invoice_paid_delete' => 'A paid invoice cannot be deleted. Reopen it first.',
    'invoice_not_paid' => 'The invoice is not paid.',
    'order_paid_locked' => 'The order has a paid invoice: its date cannot change, and it cannot be cancelled or moved back.',
    'test_error' => 'Test error triggered from the error log.',
];
