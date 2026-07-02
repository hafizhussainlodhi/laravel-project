<?php

namespace App\Nova;

use App\Nova\Actions\WalletAmount;
use App\Nova\Traits\UserTrait;
use Eminiarts\Tabs\Tabs;
use Eminiarts\Tabs\Traits\HasTabs;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Http\Requests\NovaRequest;

class Seller extends Resource
{
    use HasTabs, UserTrait;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\User>
     */
    public static $model = \App\Models\User::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    public static function label()
    {
        return 'Distributors';
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'first_name',
        'last_name',
        'phone_number',
        'email',
    ];



    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [

            (new Tabs('Main Details', [
                'Main Details' => [

                    ...$this->commonFields($request, [
                        Hidden::make('Role')
                            ->default(\App\Models\User::SELLER_ROLE),
                    ]),

                ],
                'Wallet' => $this->wallet(),
            ]))->withToolbar(),

            (new Tabs('Additional Details', [
                'Distributor Users' => $this->sellers(),
                'Wallet Histories' => $this->walletHistories(),
                /**
                 * ✅ NEW: Carriers Tab (SAME AS BUYER)
                 */
                'Carriers' => $this->carriers(),
            ])),
        ];
    }


    public function carriers()
    {
        return [
            \Laravel\Nova\Fields\BelongsToMany::make('Carriers', 'carriers', Carrier::class)
                ->fields(function ($request, $relatedModel) {
                    return [

                        \Laravel\Nova\Fields\Currency::make('Rate', 'rate')
                            ->symbol('USD')
                            ->nullable()
                            ->rules(['nullable', 'numeric', 'min:0']),

                        \Laravel\Nova\Fields\Boolean::make('Blocked', 'blocked')
                            ->default(false)
                            ->help('If blocked, seller cannot use this carrier'),

                    ];
                }),
        ];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [

            (new WalletAmount($request->resourceId))
                ->showInline()
                ->canSee(function () use ($request) {
                    return in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]);
                })
        ];
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->where('role', \App\Models\User::SELLER_ROLE);
    }

    public static function canCreate(Request $request)
    {
        $user = $request->user();

        return in_array($user->role, [
            \App\Models\User::SUPER_ADMINISTRATOR_ROLE,
            \App\Models\User::NTS_ADMINISTRATOR_ROLE,
        ]) || ($user->role == \App\Models\User::SELLER_ROLE);
    }

    public static function creationCallback(NovaRequest $request, $model)
    {
        $user = $request->user();

        if ($user->role == \App\Models\User::SELLER_ROLE) {
            $model->parent_user_id = $user->id;
        } elseif ($user->role == \App\Models\User::NTS_ADMINISTRATOR_ROLE) {
            $model->parent_user_id = null;
        }

        return $model;
    }
}
