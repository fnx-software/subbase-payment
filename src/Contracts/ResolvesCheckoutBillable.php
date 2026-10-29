<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Application-specific billable subject resolver.
 *
 * Implementations must validate the current actor's membership/ownership
 * before returning another model. Never trust an unverified tenant id from
 * user input, headers, query parameters or a published checkout URL.
 */
interface ResolvesCheckoutBillable
{
    public function resolve(Request $request): Model;
}
