<?php

namespace App\Nova\Metrics;

use App\Models\Order;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use Laravel\Nova\Nova;

class TotalOrderUsd extends Value
{
    public function name()
    {
        if (auth()->user()->role == User::SELLER_ROLE) {
            return 'Total Buyer Order USD';
        } else {
            return 'Total Order USD';
        }
    }
    /**
     * Calculate the value of the metric.
     */
    // public function calculate(NovaRequest $request): ValueResult
    // {
    //     return $this->sum($request, Order::query()->isBuying()->when($request->user() && $request->user()->role == User::USER_ROLE, function ($query) use ($request) {
    //         $query->where('user_id', $request->user()->parent_user_id ? $request->user()->parent_user_id : $request->user()->id);
    //     }), 'total')->format('0,0000')->prefix('$');
    // }

    public function calculate(NovaRequest $request): ValueResult
    {
        $user = $request->user();

        $query = Order::query()->isBuying()
            ->when($user->role == User::SUPER_ADMINISTRATOR_ROLE, function ($query) {
                // Super Admin 
                $directUserIds = User::whereNull('parent_user_id')
                    ->whereIn('role', [User::USER_ROLE, User::SELLER_ROLE])
                    ->pluck('id');
                $query->whereIn('user_id', $directUserIds);
            })->when($user->role == User::USER_ROLE, function ($query) use ($user) {
                // Buyer 
                $query->where('user_id', $user->id);
            })->when($user->role == User::SELLER_ROLE, function ($query) use ($user) {
                // Seller
                $childIds = User::where('parent_user_id', $user->id)->pluck('id');
                $query->whereIn('user_id', $childIds);
            });

        return $this->sum($request, $query, 'total')->format('0,0000')->prefix('$');
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array<int|string, string>
     */
    public function ranges(): array
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
     */
    public function cacheFor(): DateTimeInterface|null
    {
        // return now()->addMinutes(5);

        return null;
    }
}
