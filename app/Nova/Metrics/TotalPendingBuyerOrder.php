<?php

namespace App\Nova\Metrics;

use App\Models\Order;
use App\Models\User;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Nova;

class TotalPendingBuyerOrder extends Value
{
    public function name()
    {
        if (auth()->user()->role == User::SELLER_ROLE) {
            return 'Total Pending Buyer Orders';
        } else {
            return 'Total Pending Orders';
        }
    }

    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    // public function calculate(NovaRequest $request)
    // {
    //     return $this->count($request, Order::query()->isPending()->isBuying()->when($request->user() && $request->user()->role == User::USER_ROLE, function ($query) use ($request) {
    //         $query->where('user_id', $request->user()->parent_user_id ? $request->user()->parent_user_id : $request->user()->id);
    //     }));
    // }

    public function calculate(NovaRequest $request)
    {
        $user = $request->user();

        $query = Order::query()->isPending()->isBuying()
            ->when($user->role == User::SUPER_ADMINISTRATOR_ROLE, function ($query) {
                // Super Admin - sirf wo buyers/sellers jinki parent_user_id null hai
                $directUserIds = User::whereNull('parent_user_id')
                    ->whereIn('role', [User::USER_ROLE, User::SELLER_ROLE])
                    ->pluck('id');
                $query->whereIn('user_id', $directUserIds);
            })->when($user->role == User::USER_ROLE, function ($query) use ($user) {
                // Buyer - sirf apne orders
                $query->where('user_id', $user->id);
            })->when($user->role == User::SELLER_ROLE, function ($query) use ($user) {
                // Seller - apne child buyers ke orders
                $childIds = User::where('parent_user_id', $user->id)->pluck('id');
                $query->whereIn('user_id', $childIds);
            });

        return $this->count($request, $query);
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            'TODAY' => Nova::__('Today'),
            30 => Nova::__('30 Days'),
            60 => Nova::__('60 Days'),
            365 => Nova::__('365 Days'),
            'MTD' => Nova::__('Month To Date'),
            'QTD' => Nova::__('Quarter To Date'),
            'YTD' => Nova::__('Year To Date'),
        ];
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }
}
