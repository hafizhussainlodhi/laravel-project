<?php

namespace App\Nova;

use App\Nova\Actions\CopyOrderNumber;
use App\Nova\Actions\CreateReferralLink;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class ReferralLink extends Resource
{
    /**
     * The model the resource corresponds to.
     */
    public static $model = \App\Models\ReferralLink::class;

    /**
     * Title shown in Nova UI.
     */
    public static $title = 'code';

    /**
     * Searchable columns.
     */
    public static $search = [
        'id',
        'code',
    ];

    /**
     * Display name in Nova sidebar.
     */
    public static function label(): string
    {
        return 'Referral Links';
    }

    public static function singularLabel(): string
    {
        return 'Referral Link';
    }

    /**
     * Fields displayed by the resource.
     * NOTE: Nova "Create" form is intentionally disabled (see authorizedToCreate).
     * Links are created via "Generate Referral Link" action on the User resource.
     */
    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            // BelongsTo::make('User', 'user', \App\Nova\User::class)
            //     ->sortable()
            //     ->searchable(),

            // Text::make('Code')
            //     ->sortable()
            //     ->readonly(),

            Text::make('Referral URL', function () {
                return url('/dashboard/register?ref-code=' . $this->code);
            })
                ->copyable(),

            Boolean::make('Is Used', 'is_used')
                ->sortable(),

            DateTime::make('Used At', 'used_at')
                ->nullable()
                ->hideFromIndex()
                ->readonly(),

            Text::make('Expires In', function () {
                if (!$this->expires_at) {
                    return '-';
                }

                if ($this->expires_at->isPast()) {
                    return 'Expired';
                }

                return Carbon::parse($this->expires_at)->diffForHumans();
            })
                ->onlyOnIndex()
                ->asHtml(),

            // Badge showing status — only on index & detail
            Text::make('Status')
                ->displayUsing(function () {
                    if ($this->is_used) {
                        return 'Used';
                    }
                    if ($this->expires_at && $this->expires_at->isPast()) {
                        return 'Expired';
                    }
                    return 'Active';
                })
                ->onlyOnIndex()
                ->asHtml(),
        ];
    }

    /**
     * Disable Nova's built-in Create button.
     * Links are always created via the Action on User resource.
     */
    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    /**
     * Prevent editing — links should not be manually modified.
     * Only is_used can be toggled if needed; adjust as required.
     */
    // public function authorizedToUpdate(Request $request): bool
    // {
    //     return false;
    // }

    /**
     * Only Super Admin / NTS Admin can delete.
     */
    // public function authorizedToDelete(Request $request): bool
    // {
    //     return $request->user()?->superAdmin() || $request->user()?->ntsAdmin();
    // }

    /**
     * Index query — Super/NTS admins see all, sellers/buyers see only their own.
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        $user = $request->user();

        return $query->where('user_id', $user->id)->with('user');
    }

    public function cards(NovaRequest $request): array
    {
        return [];
    }

    public function filters(NovaRequest $request): array
    {
        return [];
    }

    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * No actions on ReferralLink resource itself.
     * The action lives on User resource.
     */
    public function actions(NovaRequest $request): array
    {
        return [
            (new CreateReferralLink())->standalone()
                ->onlyOnIndex(),

        ];
    }
}
