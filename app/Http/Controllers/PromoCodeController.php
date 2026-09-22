<?php

namespace App\Http\Controllers;

/**
 * Promo Codes: a scaffold only. No promo-code table or business rule for
 * agency-facing codes exists yet — nothing is fabricated here.
 */
class PromoCodeController extends Controller
{
    public function index()
    {
        return view('promo-codes.index');
    }
}
