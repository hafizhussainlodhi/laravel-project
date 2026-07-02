<?php

namespace App\Nova;

use App\Nova\Repeater\UserCarrierRate;
use Eminiarts\Tabs\Tabs;
use Eminiarts\Tabs\Traits\HasTabs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Repeater;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tabs\TabsGroup;

class Carrier extends Resource
{
    use HasTabs;
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Carrier>
     */
    public static $model = \App\Models\Carrier::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    protected function shouldAddActionsField($request)
    {
        return false;
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name'
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
                    // ID::make()->sortable(),

                    Image::make('Logo', 'image')
                        ->path('/images/carriers')
                        ->disk(config('filesystems.default'))
                        ->nullable()
                        ->creationRules('nullable'),

                    Text::make('Name')
                        ->sortable()
                        ->rules('required', 'max:255'),

                    Currency::make('Rate', 'price')
                        ->symbol('USD')
                        ->onlyOnForms()
                        ->canSee(function ($request) {
                            return $request->user()->role == \App\Models\User::SUPER_ADMINISTRATOR_ROLE;
                        }),

                    Currency::make('Rate', function () use ($request) {

                        $user = $request->user();

                        // Seller case
                        if ($user->role == \App\Models\User::SELLER_ROLE) {
                            $pivot = $this->sellers()
                                ->where('user_id', $user->id)
                                ->first();

                            return $pivot ? $pivot->pivot->rate : $this->price;
                        }

                        // Buyer case
                        if ($user->role == \App\Models\User::USER_ROLE) {
                            $parentId = $user->parent_user_id ?? $user->id;

                            $pivot = $this->buyers()
                                ->where('user_id', $parentId)
                                ->first();

                            return $pivot ? $pivot->pivot->rate : $this->price;
                        }

                        // Super Admin fallback
                        return $this->price;
                    })
                        ->symbol('USD')
                        ->sortable(),

                    Boolean::make('Active', 'is_active')
                        ->default(true),
                ]
            ]))->withToolbar(),

            (new Tabs('Additional Details', [

                'Dealers' => BelongsToMany::make('Dealers', 'users', Buyer::class)
                    ->fields(function ($request, $relatedModel) {
                        return [
                            Currency::make('Rate', 'rate')
                                ->symbol('USD')
                                ->default(0)
                                ->nullable()
                                ->rules(['nullable', 'numeric', function ($attribute, $value, $fail) use ($request) {
                                    $resource = $request->resource;
                                    $resourceId = $request->resourceId;
                                    $viaResource = $request->viaResource;
                                    $viaResourceId = $request->viaResourceId;
                                    $viaRelationship =  $request->viaRelationship;

                                    if ($value < 0) {
                                        $fail('The rate must be greater than or equal to 0.');
                                    }
                                    $carrierId = $viaResource === 'carriers' ? $viaResourceId : ($resource === 'carriers' ? $resourceId : null);

                                    if ($carrierId) {
                                        $carrier = \App\Models\Carrier::find($carrierId);
                                        if (!$carrier) {
                                            $fail('The carrier is not found.');
                                        }
                                    }
                                }]),
                            Boolean::make('Blocked', 'blocked')
                                ->nullable()
                                ->rules('nullable')
                                ->help('If blocked, the user will not be able to use this carrier')
                                ->default(false),
                        ];
                    })
                    ->canSee(function ($request) {

                        // Log::info('User role: ' . $request->user()->role);

                        return in_array($request->user()->role, [
                            \App\Models\User::SUPER_ADMINISTRATOR_ROLE,
                            \App\Models\User::NTS_ADMINISTRATOR_ROLE,
                        ]) || ($request->user()->role == \App\Models\User::SELLER_ROLE );
                    }),

               "Distributors" => BelongsToMany::make('Distributors', 'sellers', Seller::class)
                    ->fields(function ($request, $relatedModel) {
                        return [
                            Currency::make('Rate', 'rate')
                                ->symbol('USD')
                                ->default(0)
                                ->nullable()
                                ->rules(['nullable', 'numeric', function ($attribute, $value, $fail) use ($request) {
                                    $resource = $request->resource;
                                    $resourceId = $request->resourceId;
                                    $viaResource = $request->viaResource;
                                    $viaResourceId = $request->viaResourceId;
                                    $viaRelationship =  $request->viaRelationship;

                                    if ($value < 0) {
                                        $fail('The rate must be greater than 0.');
                                    }
                                    $carrierId = $viaResource === 'carriers' ? $viaResourceId : ($resource === 'carriers' ? $resourceId : null);

                                    if ($carrierId) {
                                        $carrier = \App\Models\Carrier::find($carrierId);
                                        if (!$carrier) {
                                            $fail('The carrier is not found.');
                                        }
                                    }
                                }]),
                            Boolean::make('Blocked', 'blocked')
                                ->nullable()
                                ->rules('nullable')
                                ->help('If blocked, the user will not be able to use this carrier')
                                ->default(false),
                        ];
                    })
                    ->canSee(function ($request) {
                        // Only super admin or NTS admin can attach sellers
                        return in_array($request->user()->role, [
                            \App\Models\User::SUPER_ADMINISTRATOR_ROLE,
                            \App\Models\User::NTS_ADMINISTRATOR_ROLE
                        ]);
                    })
            ])),

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
        return [];
    }


    public static function indexQuery(NovaRequest $request, $query)
    {
        $user = $request->user();

        // Super Admin sees all
        if ($user->role == \App\Models\User::SUPER_ADMINISTRATOR_ROLE) {
            return $query;
        }

        if ($user->role == \App\Models\User::SELLER_ROLE || $user->role == \App\Models\User::USER_ROLE) {

            $hasParent = $user->parent_user_id ? true : false;
            $userId    = $hasParent ? $user->parent_user_id : $user->id;

            // Agar parent hai, to parent ke blocked carrier IDs nikalo
            $parentBlockedCarrierIds = collect();
            if ($hasParent) {
                $parentBlockedCarrierIds = DB::table('user_carriers')
                    ->where('user_id', $user->parent_user_id)
                    ->where('blocked', true)
                    ->pluck('carrier_id');
            }


            return $query->where(function ($q) use ($userId, $parentBlockedCarrierIds) {

                $q->whereDoesntHave('users', function ($q) use ($userId) {
                    $q->where('users.id', $userId);
                })
                    ->orWhereHas('users', function ($q) use ($userId) {
                        $q->where('users.id', $userId)
                            ->where('user_carriers.blocked', false);
                    });
            })
                // Parent ke blocked carriers ko exclude karo
                ->when($parentBlockedCarrierIds->isNotEmpty(), function ($q) use ($parentBlockedCarrierIds) {
                    $q->whereNotIn('id', $parentBlockedCarrierIds);
                });
        }

        return $query;
    }
}
