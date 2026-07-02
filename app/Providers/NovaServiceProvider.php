<?php

namespace App\Providers;

use App\Models\User as ModelsUser;
use App\Nova\Area;
use App\Nova\Buyer;
use App\Nova\Carrier;
use App\Nova\City;
use App\Nova\Country;
use App\Nova\Dashboards\Main;
use App\Nova\Number;
use App\Nova\Order;
use App\Nova\Seller;
use App\Nova\SellerOrder;
use App\Nova\Setting;
use App\Nova\Transaction;
use App\Nova\User;
use App\Nova\ReferralLink;
use App\Nova\Wallet;
use App\Nova\WalletHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Nova::mainMenu(function (Request $request) {


            return [

                MenuSection::dashboard(Main::class)
                    ->icon('chart-bar'),


                MenuSection::make('User Management', [

                    MenuItem::resource(User::class),
                    MenuItem::resource(Buyer::class),
                    MenuItem::resource(Seller::class)

                ])->icon('user')->collapsable()
                    ->canSee(function () {
                        $user = request()->user();

                        return in_array($user->role, [
                            ModelsUser::SUPER_ADMINISTRATOR_ROLE,
                        ]);
                    }),

                MenuSection::resource(Buyer::class, 'Dealer')
                    ->icon('user')
                    ->canSee(function () {
                        return (request()->user()->role == ModelsUser::SELLER_ROLE);
                    }),

                // MenuSection::resource(Seller::class)
                //     ->icon('user')
                //     ->canSee(function () {
                //         return (request()->user()->role == ModelsUser::SELLER_ROLE);
                //     }),

                MenuSection::make('Request Management', [

                    MenuItem::resource(Carrier::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE, ModelsUser::SELLER_ROLE]);
                        }),

                    MenuItem::resource(Number::class)->canSee(function () {
                        return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE, ModelsUser::SELLER_ROLE]);
                    }),

                ])->icon('collection')->collapsable(),

                MenuSection::make('Order Management', [

                    MenuItem::resource(Order::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE, ModelsUser::USER_ROLE]);
                        }),

                    // MenuItem::resource(Order::class)
                    //     ->withBadge(function () {
                    //         $user  = request()->user();
                    //         $query = \App\Models\Order::query()->isPending()->isBuying();

                    //         if ($user->role == \App\Models\User::USER_ROLE) {

                    //             // Child buyer
                    //             if ($user->parent_user_id) {
                    //                 $userIds = collect([$user->id]);

                    //                 $parent = \App\Models\User::find($user->parent_user_id);
                    //                 if ($parent && $parent->role == \App\Models\User::USER_ROLE) {
                    //                     $userIds->push($parent->id);
                    //                 }

                    //                 $query->whereIn('user_id', $userIds);

                    //                 // Admin buyer
                    //             } elseif ($user->is_admin) {
                    //                 $childIds = \App\Models\User::where('parent_user_id', $user->id)
                    //                     ->where('role', \App\Models\User::USER_ROLE)
                    //                     ->pluck('id');

                    //                 $userIds = $childIds->push($user->id);

                    //                 $query->whereIn('user_id', $userIds);

                    //                 // Normal buyer
                    //             } else {
                    //                 $query->where('user_id', $user->id);
                    //             }
                    //         }

                    //         return $query->count();
                    //     })
                    //     ->canSee(function () {
                    //         return in_array(request()->user()->role, [
                    //             ModelsUser::SUPER_ADMINISTRATOR_ROLE,
                    //             ModelsUser::NTS_ADMINISTRATOR_ROLE,
                    //             ModelsUser::USER_ROLE,
                    //         ]);
                    //     }),

                    // MenuItem::resource(SellerOrder::class)
                    //     ->canSee(function () {
                    //         return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE, ModelsUser::SELLER_ROLE]);
                    //     }),

                    MenuItem::resource(Transaction::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                        }),

                ])->icon('shopping-cart')->collapsable()
                    ->canSee(function () {
                        return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                    }),


                // MenuSection::resource(Order::class)
                //     ->withBadge(function () {
                //         return  \App\Models\Order::query()
                //             ->isPending()
                //             ->isBuying()
                //             ->count();
                //     })
                //     ->icon('shopping-cart')
                //     ->canSee(function () {
                //         return in_array(request()->user()->role, [ModelsUser::USER_ROLE]);
                //     }),

                // MenuSection::resource(Order::class)
                //     ->withBadge(function () {
                //         $user  = request()->user();
                //         $query = \App\Models\Order::query()->isPending()->isBuying();

                //         if ($user->role == \App\Models\User::USER_ROLE) {

                //             // Child buyer
                //             if ($user->parent_user_id) {
                //                 $userIds = collect([$user->id]);

                //                 $parent = \App\Models\User::find($user->parent_user_id);
                //                 if ($parent && $parent->role == \App\Models\User::USER_ROLE) {
                //                     $userIds->push($parent->id);
                //                 }

                //                 $query->whereIn('user_id', $userIds);

                //                 // Admin buyer
                //             } elseif ($user->is_admin) {
                //                 $childIds = \App\Models\User::where('parent_user_id', $user->id)
                //                     ->where('role', \App\Models\User::USER_ROLE)
                //                     ->pluck('id');

                //                 $userIds = $childIds->push($user->id);

                //                 $query->whereIn('user_id', $userIds);

                //                 // Normal buyer
                //             } else {
                //                 $query->where('user_id', $user->id);
                //             }
                //         } else  if ($user->role == ModelsUser::SELLER_ROLE) {
                //             $query->where('user_id', $user->id);
                //         }

                //         return $query->count();
                //     })
                //     ->canSee(function () {
                //         return in_array(request()->user()->role, [ModelsUser::USER_ROLE, ModelsUser::SELLER_ROLE]);
                //     }),

                MenuSection::resource(Order::class)
                    ->icon('shopping-cart')
                    ->canSee(function () {
                        return in_array(
                            request()->user()->role,
                            [(request()->user()->role == ModelsUser::SELLER_ROLE || request()->user()->role == ModelsUser::USER_ROLE)]
                        );
                    }),

                MenuSection::resource(SellerOrder::class)

                    ->canSee(function () {
                        return in_array(
                            request()->user()->role,
                            [(request()->user()->role == ModelsUser::SELLER_ROLE)]
                        );
                    }),

                MenuSection::resource(Transaction::class)
                    ->icon('shopping-cart')
                    ->canSee(function () {
                        return in_array(request()->user()->role, [ModelsUser::USER_ROLE, ModelsUser::SELLER_ROLE]);
                    }),


                // adition super admin seller ko bhi wallet show krna hai

                // MenuSection::make('Order Management', [
                //     MenuItem::resource(SellerOrder::class)
                //         ->canSee(function () {
                //             return in_array(
                //                 request()->user()->role,
                //                 [(request()->user()->role == ModelsUser::SELLER_ROLE && request()->user()->is_admin)]
                //             );
                //         }),

                //     MenuItem::resource(Transaction::class)
                //         ->canSee(function () {
                //             return in_array(
                //                 request()->user()->role,
                //                 [(request()->user()->role == ModelsUser::SELLER_ROLE && request()->user()->is_admin)]
                //             );
                //         }),
                // ])->icon('shopping-cart')->collapsable(),



                MenuSection::make('Other Settings', [

                    MenuItem::resource(Setting::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                        }),

                    // MenuItem::resource(Country::class)
                    //     ->canSee(function () {
                    //         return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                    //     }),

                    MenuItem::resource(City::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                        }),

                    MenuItem::resource(Area::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                        }),

                    MenuItem::resource(Wallet::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                        }),
                    MenuItem::resource(WalletHistory::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                        }),
                    MenuItem::resource(ReferralLink::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [
                                ModelsUser::SUPER_ADMINISTRATOR_ROLE,
                            ]);
                        }),
                ])
                    ->collapsable()
                    ->icon('cog'),

                // adition super admin seller ko bhi wallet show krna hai
                MenuSection::make('Other Settings', [
                    MenuItem::resource(Wallet::class)
                        ->canSee(function () {
                            return in_array(
                                request()->user()->role,
                                [(request()->user()->role == ModelsUser::SELLER_ROLE)]
                            );
                        }),

                    MenuItem::resource(WalletHistory::class)
                        ->canSee(function () {
                            return in_array(
                                request()->user()->role,
                                [(request()->user()->role == ModelsUser::SELLER_ROLE)]
                            );
                        }),

                    MenuItem::resource(ReferralLink::class)
                        ->canSee(function () {
                            return in_array(request()->user()->role, [
                                ModelsUser::SELLER_ROLE
                            ]);
                        }),

                ])
                    ->collapsable()
                    ->icon('cog'),

                MenuSection::make('Logs')
                    ->path('/logs')
                    ->icon('document-duplicate')
                    ->canSee(function () {
                        return in_array(request()->user()->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
                    }),
            ];
        });
        parent::boot();
        Nova::remoteStyle(asset('css/nova.css'));
        Nova::remoteScript(asset('js/nova.js'));
        Nova::withoutThemeSwitcher();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()

            ->withAuthenticationRoutes(default: true)
            // ->withPasswordResetRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (ModelsUser $user) {
            return $user && in_array($user->role, [ModelsUser::SUPER_ADMINISTRATOR_ROLE, ModelsUser::SELLER_ROLE, ModelsUser::USER_ROLE, ModelsUser::NTS_ADMINISTRATOR_ROLE]);
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new \App\Nova\Dashboards\Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Tool>
     */
    public function tools(): array
    {
        return [
            new \Stepanenko3\LogsTool\LogsTool
        ];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }
}
