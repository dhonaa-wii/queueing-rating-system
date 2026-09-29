<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by PaymentVerificationService when a reference/receipt number
 * being saved matches one already recorded anywhere in the system — user-
 * directed 2026-09-21: the same reference number must never be accepted
 * twice, across any category, attempt, or payment type.
 */
class DuplicatePaymentReferenceException extends RuntimeException
{
}
