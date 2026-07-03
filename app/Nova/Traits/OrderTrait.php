<?php


namespace App\Nova\Traits;

use App\Nova\Actions\CopyOrderNumber;
use App\Nova\Actions\ExportOrderNumbers;
use App\Nova\Area;
use App\Nova\Buyer;
use App\Nova\Carrier;
use App\Nova\City;
use App\Nova\Country;
use App\Nova\Number as NovaNumber;
use App\Nova\Seller;
use App\Nova\User;
use App\Nova\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Line;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

use Pavloniym\ActionButtons\ActionButtons;

trait OrderTrait
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Order::class;

    /**
     * Get the fields displayed by the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function commonFields(NovaRequest $request, $extraFields = [])
    {
        return [

            Text::make('Reference', 'reference')
                ->sortable()
                ->rules('required', 'max:255')
                ->exceptOnForms(),

            Stack::make('User', 'user', [
                Line::make('Name', function () {
                    return $this->user ? $this->user->name : '';
                }),
                Line::make('Email', function () {
                    return $this->user ? $this->user->email : '';
                })
                    ->extraClasses('italic font-medium text-80')
                    ->asSmall()
                    ->onlyOnIndex(),
                Line::make('Phone Number', function () {
                    return $this->user ? $this->user->phone_number : '';
                })
                    ->extraClasses('italic font-medium text-80')
                    ->asSmall()
                    ->onlyOnIndex(),
            ])
                ->onlyOnIndex()
                ->sortable(),


            
            Stack::make('Quantity', [

                Text::make('Quantity', function () {

                    return '
                        <span class="nova-tooltip text-green-500">
                            ' . $this->success_qty . '
                            <span class="nova-tooltip-text">' . $this->success_qty . ' Success</span>
                        </span>

                        /

                        <span class="nova-tooltip text-yellow-500">
                            ' . ($this->total_qty - ($this->reject_qty + $this->success_qty)) . '
                            <span class="nova-tooltip-text">' . ($this->total_qty - ($this->reject_qty + $this->success_qty)) . ' Pending</span>
                        </span>

                        /

                        <span class="nova-tooltip text-red-500">
                            ' . $this->reject_qty . '
                            <span class="nova-tooltip-text">' . $this->reject_qty . ' Returned</span>
                        </span>

                        /

                        <span class="nova-tooltip text-sky-600">
                            ' . $this->total_qty . '
                            <span class="nova-tooltip-text">' . $this->total_qty . ' Total</span>
                        </span>
                        ';
                })->asHtml(),

            ])
                ->sortable()
                ->canSee(function () {
                    return $this->order_type == \App\Models\Order::ORDER_TYPE_BUY;
                }),
            BelongsTo::make('User', 'user', User::class)
                ->required()
                ->rules('required')
                ->hideFromIndex()
                ->filterable()
                ->searchable(),

            BelongsTo::make('Carrier', 'carrier', Carrier::class)
                ->required()
                ->rules('required')
                ->searchable()
                ->filterable(),

            BelongsTo::make('Area', 'area', Area::class)
                ->required()
                ->rules('required')
                ->filterable(),

            // BelongsTo::make('City', 'city', City::class)
            //     ->required()
            //     ->rules('required')
            //     ->searchable()
            //     ->filterable(),

            Number::make('Quantity', 'total_qty')
                ->rules('required', 'numeric', 'min_digits:1')
                ->required()
                ->onlyOnForms(),

            Text::make('Currency', 'currency')
                ->rules('required', 'max:255')
                ->required()
                ->onlyOnDetail(),

            // Text::make('Default unit price (carrier)', 'price')
            //     ->displayUsing(function ($value) {
            //         if ($this->order_type == \App\Models\Order::ORDER_TYPE_BUY) {
            //             $amount = 'USD ' . ($value ? number_format($value, 2) : 0);

            //             $help = 'Carrier baseline rate. Your actual charge is based on Sub Total / Total (per-number prices may vary by seller).';
            //             return '<span class="text-green-500">' . $amount . '</span> <br> <span class="text-gray-500">' . $help . '</span>';
            //         }
            //         return 'USD ' . ($value ? number_format($value, 2) : 0);
            //     })
            //     ->asHtml()
            //     ->onlyOnDetail(),

            // Per number cost with dynamic logic based on order type
            
            Currency::make('Per Number Cost', function () {
                if ($this->order_type == \App\Models\Order::ORDER_TYPE_SELL) {
                    // Seller ki set ki hui cost
                    return $this->price;
                }

                if ($this->order_type == \App\Models\Order::ORDER_TYPE_BUY) {
                    // Buyer ka superadmin ka set kiya hua rate
                    $userCarrier = \App\Models\UserCarrier::where('user_id', $this->user_id)
                        ->where('carrier_id', $this->carrier_id)
                        ->first();

                    return $userCarrier && $userCarrier->rate > 0
                        ? $userCarrier->rate
                        : $this->price; // fallback: carrier default price
                }

                return $this->price;
            })
                ->symbol('USD')
                ->exceptOnForms(),

            Currency::make('Whole Sale Price', function () {
                return optional($this->carrier)->cost ?? 0;
            })
                ->symbol('USD')
                ->sortable()
                ->showOnIndex()
                ->showOnDetail()
                ->exceptOnForms()
                ->canSee(function ($request) {
                    return $request->user()->role == \App\Models\User::SUPER_ADMINISTRATOR_ROLE;
                }),

            Currency::make('Sub Total', 'subtotal')
                ->symbol('USD')
                ->rules('required', 'numeric')
                ->required()
                ->onlyOnDetail(),

            Currency::make('Total', 'total')
                ->symbol('USD')
                ->rules('required', 'numeric')
                ->required()
                ->exceptOnForms(),

            Currency::make('Profit', function () {
                $carrierCost = optional($this->carrier)->cost ?? 0;
                $totalCost = $carrierCost * ($this->total_qty ?? 0);
                return $this->total - $totalCost;
            })
                ->symbol('USD')
                ->exceptOnForms()
                ->canSee(function ($request) {
                    return $request->user()->role == \App\Models\User::SUPER_ADMINISTRATOR_ROLE;
                }),

            Select::make('Status', 'status')
                ->options(\App\Models\Order::GET_STATUS())
                ->displayUsingLabels()
                ->rules('required')
                ->filterable()
                ->required(),


            Textarea::make('Notes', 'notes')
                ->rules('nullable')
                ->nullable(),

            Textarea::make('Pending Assignment Notes', 'pending_notes')
                ->help('Explains why some numbers could not be assigned (e.g., insufficient wallet balance).')
                ->onlyOnDetail()
                ->nullable()
                ->canSee(function ($request) {
                    return $this->pending_notes && $this->order_type == \App\Models\Order::ORDER_TYPE_BUY;
                }),

            Boolean::make('Is Refunded', 'is_refunded')
                ->filterable()
                ->sortable()
                ->exceptOnForms()
                ->canSee(function ($request) {
                    return  in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]) && $this->order_type == \App\Models\Order::ORDER_TYPE_BUY;
                }),

            DateTime::make('Refunded At', 'refunded_at')
                ->displayUsing(function ($refunded_at) {
                    return $refunded_at ? Carbon::parse($refunded_at)->format('Y-m-d H:i A') : null;
                })
                ->sortable()
                ->onlyOnDetail()
                ->canSee(function ($request) {
                    return  in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]) && $this->order_type == \App\Models\Order::ORDER_TYPE_BUY;
                }),

            DateTime::make('Created At', 'created_at')
                ->displayUsing(function ($created_at) {
                    return $created_at ? Carbon::parse($created_at)->format('Y-m-d H:i A') : null;
                })
                ->sortable()
                ->exceptOnForms(),

            ...$extraFields
        ];
    }




    public function numbers()
    {
        return BelongsToMany::make('Numbers', 'numbers', NovaNumber::class);
    }
}
