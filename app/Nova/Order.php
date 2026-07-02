<?php

namespace App\Nova;

use App\Nova\Actions\CopyOrderNumber;
use App\Nova\Actions\CreateOrder;
use App\Nova\Actions\ExportOrderNumbers;
use App\Nova\Actions\ExportOrders;
use App\Nova\Metrics\TotalOrder;
use App\Nova\Metrics\TotalOrderUsd;
use App\Nova\Traits\OrderTrait;
use Eminiarts\Tabs\Tabs;
use Eminiarts\Tabs\Traits\HasTabs;
use Laravel\Nova\Http\Requests\ActionRequest;
use Laravel\Nova\Http\Requests\NovaRequest;


class Order extends Resource
{
    use OrderTrait, HasTabs;
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Order>
     */
    public static $model = \App\Models\Order::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'reference';

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
        'reference',
        'order_type',
        'status',
        'currency',
        'user.name',
    ];


    public static function label(): string
    {
        return 'Orders';
    }

    public static function singularLabel(): string
    {
        return 'Buyer Order';
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            (new Tabs('Main Detials', [
                'Main Detials' => [
                    ...$this->commonFields($request),
                ]
            ]))->withToolbar(),

            new Tabs('Numbers', [
                'Numbers' => $this->numbers(),
            ]),
        ];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        // return [
        //     TotalOrderUsd::make()
        //         ->width('1/2')
        //         ->canSee(function ($request) {
        //             return in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE, \App\Models\User::USER_ROLE]);
        //         }),

        //     TotalOrder::make()
        //         ->width('1/2')
        //         ->canSee(function ($request) {
        //             return in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE, \App\Models\User::USER_ROLE]);
        //         }),
        // ];

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
            (new ExportOrders($request->user()->id, $request->resource))
                ->standalone()
                ->onlyOnIndex(),

            (new CreateOrder())
                ->standalone()
                ->onlyOnIndex()
                ->canSee(function () use ($request) {
                    return in_array($request->user()->role, [\App\Models\User::USER_ROLE, \App\Models\User::SELLER_ROLE,]);
                }),

            (new ExportOrderNumbers($request->user()->id))
                ->showInline()
                ->canSee(function () use ($request) {
                    return  $request instanceof ActionRequest ||  $this->numbers->count() > 0;
                }),

            (new CopyOrderNumber())
                ->withoutConfirmation()
                ->showInline(),
        ];
    }


    public static function indexQuery(NovaRequest $request, $query)
    {
        $user = $request->user();

        if ($request->viaRelationship == null && $user) {

            $query->isBuying();

            if ($user->role == \App\Models\User::USER_ROLE || $user->role == \App\Models\User::SELLER_ROLE) {

                $query->where('user_id', $user->id);
            } elseif ($user->role == \App\Models\User::SUPER_ADMINISTRATOR_ROLE) {

                $userIds = \App\Models\User::whereNull('parent_user_id')
                    ->pluck('id');

                $query->whereIn('user_id', $userIds);
            }
        }

        return $query;
    }
}
