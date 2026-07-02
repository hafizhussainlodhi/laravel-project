<?php

namespace App\Nova\Actions;

use App\Models\ReferralLink;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class CreateReferralLink extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Generate Referral Link';

    /**
     * Perform the action on the given models.
     * $models = selected User(s) from User resource index
     */
    public function handle(ActionFields $fields, Collection $models)
    {

        $user = auth()->user();

        if (!$user) {
            return Action::danger('Unauthorized');
        }

        ReferralLink::create([
            'user_id' => $user->id,
            'is_used' => false,
        ]);

        return Action::message('Referral link created!');
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
