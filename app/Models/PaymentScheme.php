<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentScheme extends Model
{
    public const USER_ENTERED_AMORTIZATION = 1;

    public const BASED_ON_NUMBER_OF_PAYMENTS = 2;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'lu_payment_schemes';

    protected $primaryKey = 'payment_scheme_id';

    protected $keyType = 'int';

    protected $fillable = [
        'payment_scheme_id',
        'payment_scheme',
    ];
}
