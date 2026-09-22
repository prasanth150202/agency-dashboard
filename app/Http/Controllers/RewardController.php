<?php

namespace App\Http\Controllers;

/**
 * Rewards: a scaffold only. No reward table or monetary rule exists yet.
 * Milestones an agency has actually reached are already real (see
 * Gamification on the Overview page); this page is where a future reward
 * could attach to one of them — nothing is invented here in the meantime.
 */
class RewardController extends Controller
{
    public function index()
    {
        return view('rewards.index');
    }
}
