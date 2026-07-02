<?php

namespace App\Nova\Traits;

use App\Nova\Buyer;
use App\Nova\Country;
use App\Nova\Seller;
use App\Nova\User;
use App\Nova\Wallet;
use App\Nova\WalletHistory;
use Illuminate\Validation\Rules;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

trait UserTrait
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\User::class;

    /**
     * Get the fields displayed by the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function commonFields(NovaRequest $request, $extraFields = [])
    {
        return [
            // BelongsTo::make('Parent User', 'parant', $request->resource instanceof User ? User::class : Seller::class)
            //     ->exceptOnForms()
            //     ->nullable()
            //     ->rules('nullable')
            //     ->canSee(function ($request) {
            //         return in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]) && $request->viaResource != 'carriers';
            //     })
            //     ->filterable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255')
                ->exceptOnForms(),

            Text::make('First Name')
                ->required()
                ->rules('required', 'max:255')
                ->onlyOnForms(),

            Text::make('Last Name')
                ->nullable()
                ->rules('nullable', 'max:255')
                ->onlyOnForms(),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}')
                ->copyable(),

            Password::make('Password')
                ->onlyOnForms()
                ->creationRules('required', Rules\Password::defaults())
                ->updateRules('nullable', Rules\Password::defaults()),


            // BelongsTo::make('Country', 'country', Country::class)
            //     ->filterable()
            //     ->required()
            //     ->rules('required')
            //     ->sortable(),

            Hidden::make('country_id')
                ->default(234),

            Text::make('Country', fn() => 'USA')
                ->onlyOnForms(),

            Text::make('Phone Number', 'phone_number')
                ->required()
                ->rules(
                    'required',
                    'numeric',
                    'regex:/^1[0-9]{10}$/', // 👈 USA only
                    'unique:users,phone_number,NULL,id,deleted_at,NULL'
                )
                ->help('Only USA phone number are allowed')
                ->copyable(),

            Text::make('Company Name', 'company_name')
    ->nullable()
    ->rules('nullable', 'max:255')
    ->onlyOnForms()
    ->showOnDetail()
    ->showOnIndex(),


            Currency::make('Wallet', function () {
                return $this->wallet ? $this->wallet->available :  null;
            })
                ->nullable()
                ->symbol('USD')
                ->rules('nullable', 'max:255')
                ->exceptOnForms()
                ->canSee(function ($request) {
                    $user = $request->user();

                    return (
                        in_array($user->role, [
                            \App\Models\User::SUPER_ADMINISTRATOR_ROLE,
                            \App\Models\User::NTS_ADMINISTRATOR_ROLE,
                        ])
                        || ($user->role == \App\Models\User::SELLER_ROLE )
                    );
                }),

            DateTime::make('Created At')
                ->displayUsing(function ($value) {
                    return $value ? $value->format('Y-m-d H:i') : null;
                })
                ->exceptOnForms()
                ->canSee(function ($request) {
                    return  $request->viaResource != 'carriers';
                }),

            ...$extraFields
        ];
    }

    // public function wallet()
    // {
    //     return [
    //         HasOne::make('Wallet', 'wallet', Wallet::class)
    //             ->onlyOnDetail()
    //             ->canSee(function ($request) {
    //                 return  in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]) && $this->parent_user_id == null && $this->role == \App\Models\User::USER_ROLE;
    //             }),
    //     ];
    // }

    public function wallet()
    {
        return [
            HasOne::make('Wallet', 'wallet', Wallet::class)
                ->onlyOnDetail()
                ->canSee(function ($request) {
                    $user = $request->user();

                    return (
                        in_array($user->role, [
                            \App\Models\User::SUPER_ADMINISTRATOR_ROLE,
                            \App\Models\User::NTS_ADMINISTRATOR_ROLE,
                        ])
                        || ($user->role == \App\Models\User::SELLER_ROLE )
                    );
                }),
        ];
    }

    public function walletHistories()
    {
        return [
            HasMany::make('Wallet Histories', 'walletHistories', WalletHistory::class)
                ->onlyOnDetail()
                ->canSee(function ($request) {
                    return  in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]) && $this->parent_user_id == null && $this->role == \App\Models\User::USER_ROLE;
                }),
        ];
    }

    public function buyers()
    {
        return [
            HasMany::make('Buyers', 'users', Buyer::class)
                ->canSee(function ($request) {
                    return in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]) && $this->role == \App\Models\User::USER_ROLE;
                }),
        ];
    }

    public function sellers()
    {
        return [
            HasMany::make('Sellers', 'users', Seller::class)
                ->canSee(function ($request) {
                    return  in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]) && $this->role == \App\Models\User::SELLER_ROLE;
                }),
        ];
    }
}
