<?php

namespace App\Observers;

use App\Models\ReferralLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReferralLinkObserver
{
    /**
     * Handle the ReferralLink "created" event.
     */

    public function creating(ReferralLink $referralLink): void
    {
        // user_id auto set (IMPORTANT FIX)
        if (!$referralLink->user_id) {
            $referralLink->user_id = auth()->id();
        }

        // code generate
        if (!$referralLink->code) {
            $referralLink->code = Str::random(16);
        }

        // expiry auto set (1 week)
        if (!$referralLink->expires_at) {
            $referralLink->expires_at = now()->addWeek();
        }
        Log::info('rserId' . $referralLink);
    }

    public function created(ReferralLink $referralLink): void
    {
        //
    }

    /**
     * Handle the ReferralLink "updated" event.
     */
    public function updated(ReferralLink $referralLink): void
    {
        //
    }

    /**
     * Handle the ReferralLink "deleted" event.
     */
    public function deleted(ReferralLink $referralLink): void
    {
        //
    }

    /**
     * Handle the ReferralLink "restored" event.
     */
    public function restored(ReferralLink $referralLink): void
    {
        //
    }

    /**
     * Handle the ReferralLink "force deleted" event.
     */
    public function forceDeleted(ReferralLink $referralLink): void
    {
        //
    }
}
