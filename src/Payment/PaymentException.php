<?php

namespace App\Payment;

/**
 * Échec côté prestataire de paiement. Le message technique reste dans les journaux.
 */
class PaymentException extends \RuntimeException
{
}
