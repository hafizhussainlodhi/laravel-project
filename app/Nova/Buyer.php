<?php

namespace App\Nova;

use App\Nova\Actions\WalletAmount;
use App\Nova\Traits\UserTrait;
use Eminiarts\Tabs\Tabs;
use Eminiarts\Tabs\Traits\HasTabs;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Http\Requests\NovaRequest;

class Buyer extends Resource
{
    use UserTrait, HasTabs;

    public static $model = \App\Models\User::class;

    public static $title = 'name';

    public static function label()
    {
        return 'Dealers';
    }

    public static $search = [
        'id',
        'name',
        'first_name',
        'last_name',
        'phone_number',
        'email'
    ];

    public function fields(NovaRequest $request): array
    {
        return [

            /**
             * MAIN TAB
             */
            (new Tabs('Main Details', [
                'Main Details' => [
                    ...$this->commonFields($request, [
                        Hidden::make('Role')
                            ->default(\App\Models\User::USER_ROLE),
                    ]),
                ],

                'Wallet' => $this->wallet(),
            ]))->withToolbar(),


            /**
             * ADDITIONAL DETAILS TAB
             */
            (new Tabs('Additional Details', [

                'Dealer Users' => $this->buyers(),

                // 'Wallet Histories' => $this->walletHistories(),

                /**
                 * ✅ IMPORTANT: Carriers (NEW)
                 */
                'Carriers' => $this->carriers(),

            ])),

        ];
    }

    /**
     * ✅ Carriers relation (MAIN FIX)
     */
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
                            ->help('If blocked, buyer cannot use this carrier'),

                    ];
                }),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [

            (new WalletAmount($request->resourceId))
                ->showInline()
                ->canSee(function () use ($request) {
                    return in_array($request->user()->role, [
                        \App\Models\User::SUPER_ADMINISTRATOR_ROLE,
                        \App\Models\User::NTS_ADMINISTRATOR_ROLE,
                        \App\Models\User::SELLER_ROLE
                    ]);
                }),

        ];
    }

    /**
     * INDEX QUERY
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user() &&
            $request->user()->role == \App\Models\User::SUPER_ADMINISTRATOR_ROLE) {
            $query->whereNull('parent_user_id');
        }

        if ($request->user() &&
            $request->user()->role == \App\Models\User::SELLER_ROLE) {
            $query->where('parent_user_id', $request->user()->id);
        }

        return $query->where('role', \App\Models\User::USER_ROLE);
    }
}