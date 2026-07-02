<?php

namespace App\Nova\Actions;

use App\Jobs\AutoAssignNumbers;
use App\Jobs\SendNewOrderNotificationEmail;
use App\Models\Number as ModelsNumber;
use App\Models\Order;
use App\Models\User;
use App\Models\Carrier;
use App\Models\UserCarrier;
use App\Models\Area;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Notifications\NovaNotification;
use Laravel\Nova\URL as NovaURL;

class CreateOrder extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function handle(ActionFields $fields, Collection $models)
    {
        $authUser = Auth::user();

        // ── Determine Buyer ───────────────────────────────────────────────────
        $userId = in_array($authUser->role, [User::USER_ROLE, User::SELLER_ROLE])
            ? $authUser->id
            : $fields->buyer;

        if (!$userId) {
            return Action::danger('User not found.');
        }

        $buyer = User::find($userId);
        if (!$buyer) {
            return Action::danger('Buyer not found.');
        }

        $carrierId = $fields->carrier;
        $areaId    = $fields->area;
        $quantity  = (int) ($fields->quantity ?? 0);

        if (!$carrierId || !$areaId || $quantity < 1) {
            return Action::danger('Please fill Carrier, Area and Quantity.');
        }

        // ── Carrier ───────────────────────────────────────────────────────────
        $carrier = Carrier::find($carrierId);
        if (!$carrier) {
            return Action::danger('Carrier not found.');
        }

        // ── Resolve parent & number-owner ─────────────────────────────────────
        $parent     = $buyer->parent_user_id ? User::find($buyer->parent_user_id) : null;
        $superAdmin = User::IsSuperAdminRole()->first();

        $numberOwnerUserId = $this->resolveNumberOwner($buyer, $parent, $superAdmin);

        // ── User-carrier rules ────────────────────────────────────────────────
        $buyerUC  = UserCarrier::where('user_id', $buyer->id)
            ->where('carrier_id', $carrierId)->first();
        $parentUC = $parent
            ? UserCarrier::where('user_id', $parent->id)
            ->where('carrier_id', $carrierId)->first()
            : null;

        // ── Blocked check ─────────────────────────────────────────────────────
        $ucToCheck = $buyerUC ?? $parentUC;
        if ($ucToCheck && $ucToCheck->blocked) {
            return Action::danger('This carrier is not available for your account.');
        }

        // ── Effective price ───────────────────────────────────────────────────
        $effectiveUnitPrice = $this->resolveEffectivePrice($carrier, $buyerUC, $parentUC);

        // ── Scope method (seller vs regular) ──────────────────────────────────
        $scopeMethod = ($parent?->role === User::SELLER_ROLE) ? 'isSellerNotUsed' : 'isNotUsed';

        // ── Fetch available numbers ───────────────────────────────────────────
        $numberIds = ModelsNumber::$scopeMethod()
            ->where('carrier_id', $carrierId)
            ->where('area_id', $areaId)
            ->isNotExpired()
            ->where('user_id', $numberOwnerUserId)
            ->orderBy('expiry', 'asc')
            ->limit($quantity)
            ->pluck('id')
            ->toArray();

        $successQty = count($numberIds);
        $subtotal   = $successQty * $effectiveUnitPrice;
        $total      = $quantity   * $effectiveUnitPrice;

        // ── Create order (in transaction) ─────────────────────────────────────
        $order = DB::transaction(function () use (
            $userId,
            $carrierId,
            $areaId,
            $effectiveUnitPrice,
            $quantity,
            $successQty,
            $subtotal,
            $total,
            $numberIds
        ) {
            $order = Order::create([
                'user_id'     => $userId,
                'carrier_id'  => $carrierId,
                'city_id'     => null,
                'area_id'     => $areaId,
                'price'       => $effectiveUnitPrice,
                'order_type'  => Order::ORDER_TYPE_BUY,
                'total_qty'   => $quantity,
                'success_qty' => $successQty,
                'subtotal'    => $subtotal,
                'total'       => $total,
                'status'      => ($successQty === $quantity)
                    ? Order::STATUS_COMPLETED
                    : Order::STATUS_PENDING,
            ]);

            if ($successQty > 0) {
                $order->numbers()->sync($numberIds);
            }

            return $order;
        });

        // ── Bulk number updates (outside transaction) ──────────────────────────
        if ($successQty > 0) {
            $isSellerParent = $parent && $parent->id === $numberOwnerUserId;

            if ($isSellerParent) {
                ModelsNumber::whereIn('id', $numberIds)
                    ->update(['seller_is_used' => true]);
            } else {
                if ($buyer->role !== User::USER_ROLE) {
                    ModelsNumber::whereIn('id', $numberIds)
                        ->update(['user_id' => $buyer->id, 'is_used' => true]);

                    AutoAssignNumbers::dispatch();
                } else {
                    ModelsNumber::whereIn('id', $numberIds)
                        ->update(['is_used' => true]);
                }
            }
        }

        // ── Notifications ─────────────────────────────────────────────────────
        $isBuyerOrSeller = in_array($authUser->role, [User::USER_ROLE, User::SELLER_ROLE]);
        if ($isBuyerOrSeller && $superAdmin) {
            $superAdmin->notify(
                NovaNotification::make()
                    ->message("New order created by {$authUser->name}")
                    ->action('View Orders', NovaURL::remote('/dashboard/resources/orders'))
                    ->icon('info')
                    ->type('success')
            );

            SendNewOrderNotificationEmail::dispatch(
                config('app.url') . '/dashboard/resources/orders/' . $order->id,
                $authUser->name
            );
        }

        return Action::message('Order created successfully.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveNumberOwner(User $buyer, ?User $parent, ?User $superAdmin): ?int
    {
        if (!$parent) {
            return $superAdmin?->id;
        }

        if ($parent->role === User::USER_ROLE || $parent->id === $buyer->id) {
            return $superAdmin?->id;
        }

        return $parent->id;
    }

    private function resolveEffectivePrice(Carrier $carrier, ?UserCarrier $buyerUC, ?UserCarrier $parentUC): float
    {
        if ($buyerUC && $buyerUC->rate > 0) {
            return (float) $buyerUC->rate;
        }

        if ($parentUC && $parentUC->rate > 0) {
            return (float) $parentUC->rate;
        }

        return (float) $carrier->price;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIELDS
    // ══════════════════════════════════════════════════════════════════════════

    public function fields(NovaRequest $request): array
    {
        $user    = $request->user();
        $isAdmin = in_array($user->role, [
            User::SUPER_ADMINISTRATOR_ROLE,
            User::NTS_ADMINISTRATOR_ROLE,
        ]);
        $buyerId = $isAdmin ? null : $user->id;

        $area = Area::isActive()->pluck('name', 'id');

        if ($isAdmin) {
            $carriers = Carrier::isActive()->pluck('name', 'id');
        } else {
            $parentBlockedCarrierIds = collect();
            if ($user->parent_user_id) {
                $parentBlockedCarrierIds = DB::table('user_carriers')
                    ->where('user_id', $user->parent_user_id)
                    ->where('blocked', true)
                    ->pluck('carrier_id');
            }

            $carriers = Carrier::isActive()
                ->where(function ($query) use ($buyerId) {
                    $query->whereDoesntHave('users', function ($q) use ($buyerId) {
                        $q->where('users.id', $buyerId)
                            ->where('user_carriers.blocked', true);
                    });
                })
                ->when(
                    $parentBlockedCarrierIds->isNotEmpty(),
                    fn($q) =>
                    $q->whereNotIn('id', $parentBlockedCarrierIds)
                )
                ->pluck('name', 'id');
        }

        return [
            // ── Buyer (admin only) ────────────────────────────────────────────
            Select::make('Buyer', 'buyer')
                ->options(User::isBuyerRole()->pluck('name', 'id'))
                ->searchable()
                ->displayUsingLabels()
                ->fullWidth()
                ->nullable()
                ->canSee(fn($req) => in_array($req->user()->role, [
                    User::SUPER_ADMINISTRATOR_ROLE,
                    User::NTS_ADMINISTRATOR_ROLE,
                ])),

            // ── Carrier ───────────────────────────────────────────────────────
            Select::make('Carrier', 'carrier')
                ->options($carriers)
                ->searchable()
                ->displayUsingLabels()
                ->fullWidth()
                ->rules('required'),

            // ── Carrier Price (read-only, reactive) ───────────────────────────
            Currency::make('Carrier Price', 'carrier_price')
                ->fullWidth()
                ->symbol('USD')
                ->dependsOn(
                    ['carrier', 'buyer'],
                    function (Currency $f, NovaRequest $r, $data) use ($isAdmin, $buyerId) {
                        $carrierId = $data->carrier ?? null;
                        if (!$carrierId) {
                            $f->hide();
                            return;
                        }

                        $carrier = Carrier::find($carrierId);
                        if (!$carrier) {
                            $f->hide();
                            return;
                        }

                        $resolvedBuyerId = $isAdmin ? ($data->buyer ?? null) : $buyerId;
                        if (!$resolvedBuyerId) {
                            $f->hide();
                            return;
                        }

                        $buyer = User::find($resolvedBuyerId);
                        if (!$buyer) {
                            $f->hide();
                            return;
                        }

                        $buyerUC  = UserCarrier::where('user_id', $buyer->id)
                            ->where('carrier_id', $carrier->id)->first();
                        $parentUC = $buyer->parent_user_id
                            ? UserCarrier::where('user_id', $buyer->parent_user_id)
                            ->where('carrier_id', $carrier->id)->first()
                            : null;

                        $price = $this->resolveEffectivePrice($carrier, $buyerUC, $parentUC);

                        $f->withMeta(['value' => $price, 'readonly' => true])->show();
                    }
                ),

            // ── Area ──────────────────────────────────────────────────────────
            Select::make('Area', 'area')
                ->options($area)
                ->searchable()
                ->displayUsingLabels()
                ->fullWidth()
                ->rules('required'),

            // ── Quantity ──────────────────────────────────────────────────────
            Number::make('Quantity', 'quantity')
                ->default(1)
                ->min(1)
                ->fullWidth()
                ->rules(['required', 'min:1']),
        ];
    }
}
