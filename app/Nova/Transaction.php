<?php

namespace App\Nova;

use App\Models\User as UserModel;
use App\Nova\Actions\ExportTransactions;
use App\Nova\Actions\ExportUserTransactions;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Line;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Transaction extends Resource
{
    public static $model = \App\Models\Transaction::class;

    public static $title = 'id';

    public function title()
    {
        return optional($this)->user
            ? $this->user->name . ' (' . $this->charged_price . $this->currency . ' - ' . $this->status . ')'
            : $this->id;
    }

    public static $search = [
        'id',
        'order.reference',
        'order.user.name',
        'user.name',
        'user.email',
        'user.phone_number',
    ];

    // ── Per-page default ──────────────────────────────────────────────────────
    public static $perPageOptions = [25, 50, 100];

    public function fields(NovaRequest $request): array
    {
        return [

            // ── Index: User info stack ────────────────────────────────────────
            Stack::make('User', [
                Line::make('Name', fn() => optional($this->user)->name ?? '—')
                    ->asHeading()
                    ->onlyOnIndex(),

                Line::make('Email', fn() => optional($this->user)->email ?? '—')
                    ->asSmall()
                    ->extraClasses('text-80')
                    ->onlyOnIndex(),
            ])->onlyOnIndex(),

            // ── Index: Order info stack ───────────────────────────────────────
            Stack::make('Order \ Created At', 'order_id', [

                BelongsTo::make('Order', 'order', Order::class)
                    ->display(function ($order) {
                        return $order ? $order->reference : '-';
                    })
                    ->onlyOnIndex(),

                Line::make('Created At', 'created_at')
                    ->extraClasses('italic font-medium text-80')
                    ->asSmall()
                    ->displayUsing(function ($created_at) {
                        return $created_at->format('Y-m-d H:i A');
                    })
                    ->onlyOnIndex(),
            ])
                ->onlyOnIndex()
                ->sortable(),

            // ── Index: Numbers ordered ────────────────────────────────────────
            Stack::make('Numbers', [
                Line::make('Requested', fn() => optional($this->order)->total_qty ?? '—')
                    ->asHeading()
                    ->onlyOnIndex(),

                // Line::make('Fulfilled', function () {
                //     $success = optional($this->order)->success_qty;
                //     $total   = optional($this->order)->total_qty;
                //     if (is_null($success) || is_null($total)) return '—';
                //     return $success . ' / ' . $total;
                // })
                //     ->asSmall()
                //     ->extraClasses('text-80')
                //     ->onlyOnIndex(),
            ])->onlyOnIndex(),

            // ── Index: Price stack ────────────────────────────────────────────
            // Stack::make('Amount', [
            //     Line::make('Total', fn() => '$' . number_format((float) $this->charged_price, 2))
            //         ->asHeading()
            //         ->onlyOnIndex(),

                // Line::make('Unit Price', fn() => '$' . number_format((float) optional($this->order)->price, 4) . ' / num')
                //     ->asSmall()
                //     ->extraClasses('text-80')
                //     ->onlyOnIndex(),
            // ])->onlyOnIndex(),

            // ── Index: Status + Platform stack ────────────────────────────────
            // Stack::make('Status', [
            //     Line::make('Status', 'status')
            //         ->asHeading()
            //         ->displayUsing(fn($v) => ucfirst(strtolower(str_replace('_', ' ', $v))))
            //         ->onlyOnIndex(),

            //     //     Line::make('Platform', 'platform')
            //     //         ->asSmall()
            //     //         ->extraClasses('text-80')
            //     //         ->displayUsing(fn($v) => ucwords(strtolower(str_replace('_', ' ', $v))))
            //     //         ->onlyOnIndex(),
            // ])->onlyOnIndex(),

            // ── Index: Date ───────────────────────────────────────────────────
            DateTime::make('Date', 'created_at')
                ->onlyOnIndex()
                ->sortable()
                ->displayUsing(fn($d) => $d->format('Y-m-d g:i A')),

            // ════════════════════════════════════════════════════════════════
            // DETAIL / FORM FIELDS
            // ════════════════════════════════════════════════════════════════

            BelongsTo::make('Order', 'order', Order::class)
                ->sortable()
                ->searchable()
                ->required()
                ->rules(['required'])
                ->showOnPreview()
                ->hideFromIndex(),

            BelongsTo::make('User', 'user', User::class)
                ->sortable()
                ->searchable()
                ->required()
                ->rules(['required'])
                ->showOnPreview()
                ->hideFromIndex(),

            BelongsTo::make('Wallet', 'wallet', Wallet::class)
                ->sortable()
                ->searchable()
                ->nullable()
                ->rules(['nullable'])
                ->showOnPreview()
                ->hideFromIndex(),

            Text::make('Currency', 'currency')
                ->onlyOnDetail(),

            Currency::make('Total', 'charged_price')
                ->symbol(\App\Models\Transaction::USD)
                ->context(new \Brick\Money\Context\CustomContext(3))
                ->step(0.00001)
                ->required(),

            Select::make('Origin')
                ->options(\App\Models\Transaction::GET_ORIGIN())
                ->rules(['required'])
                ->required()
                ->displayUsingLabels(),

            Select::make('Status', 'status')
                ->sortable()
                ->rules(['required'])
                ->required()
                ->options(\App\Models\Transaction::GET_STATUS())
                ->displayUsingLabels()
                ->filterable(),

            DateTime::make('Created at', 'created_at')
                ->exceptOnForms()
                ->sortable()
                ->displayUsing(fn($d) => $d->format('Y-m-d g:i:s A'))
                ->hideFromIndex(),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        $user = $request->user();
        $actions = [];

        $isSuperAdmin = in_array($user->role, [
            UserModel::SUPER_ADMINISTRATOR_ROLE,
            UserModel::NTS_ADMINISTRATOR_ROLE,
        ]);

        // ✅ Standalone — koi row select karne ki zaroorat nahi
        $actions[] = (new ExportTransactions())
            ->standalone()
            ->onlyOnIndex();

        if ($isSuperAdmin) {
            $actions[] = (new ExportUserTransactions())
                ->standalone()
                ->confirmText('Select a user to export their transactions.');
        }

        return $actions;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ROLE-BASED INDEX QUERY
    // ══════════════════════════════════════════════════════════════════════════

    public static function indexQuery(NovaRequest $request, $query)
    {
        $user = $request->user();

        $query->whereNotNull('order_id');

        switch ($user->role) {

            case UserModel::SUPER_ADMINISTRATOR_ROLE:
            case UserModel::NTS_ADMINISTRATOR_ROLE:
                $query->whereHas(
                    'user',
                    fn($q) => $q->whereNull('parent_user_id')
                );
                break;

            case UserModel::USER_ROLE:
                $query->where('user_id', $user->id);
                break;

            case UserModel::SELLER_ROLE:
                $query->where('user_id', $user->id);
                break;

            default:
                $query->whereRaw('1 = 0');
                break;
        }

        return $query;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EAGER LOADS (avoid N+1)
    // ══════════════════════════════════════════════════════════════════════════

    public static function relatableQuery(NovaRequest $request, $query)
    {
        return parent::relatableQuery($request, $query);
    }

    public function cards(NovaRequest $request): array
    {
        return [];
    }
    public function filters(NovaRequest $request): array
    {
        return [];
    }
    public function lenses(NovaRequest $request): array
    {
        return [];
    }
}
