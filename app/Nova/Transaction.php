<?php

namespace App\Nova;

use App\Models\User as UserModel;
use App\Nova\Actions\ExportTransactions;
use App\Nova\Actions\ExportUserTransactions;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Line;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Stack;
use Illuminate\Support\Facades\DB;
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

            // ── Latest-per-email grouping indicator ───────────────────────────
            Text::make('Latest By Email', function () {
                return optional(optional($this->resource)->user)->email
                    ? ($this->resource->isLatestByEmail() ? 'Latest' : 'Older')
                    : '—';
            })
                ->onlyOnIndex()
                ->canSee(function ($request) {
                    return $request->user()->role == UserModel::SUPER_ADMINISTRATOR_ROLE;
                }),

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
            ])->onlyOnIndex(),

            // ── Index: Wholesale / Profit stack ─────────────────────────────────
            Stack::make('Wholesale Profit', [
                Line::make('Whole Sale Price', function () {
                    $wholeSalePrice = optional(optional($this->order)->carrier)->cost;
                    return $wholeSalePrice !== null ? '$' . number_format((float) $wholeSalePrice, 2) : '—';
                })
                    ->asHeading()
                    ->onlyOnIndex()
                    ->canSee(function ($request) {
                        return $request->user()->role == UserModel::SUPER_ADMINISTRATOR_ROLE;
                    }),

                Line::make('Profit', function () {
                    $wholeSalePrice = (float) optional(optional($this->order)->carrier)->cost;
                    $quantity = optional($this->order)->total_qty ?? 0;
                    $profit = (float) $this->charged_price - ($wholeSalePrice * $quantity);
                    return '$' . number_format($profit, 2);
                })
                    ->asSmall()
                    ->extraClasses('text-80')
                    ->onlyOnIndex()
                    ->canSee(function ($request) {
                        return $request->user()->role == UserModel::SUPER_ADMINISTRATOR_ROLE;
                    }),
            ])->onlyOnIndex(),

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
                ->hideFromIndex()
                ->hideFromDetail(),

            BelongsTo::make('User', 'user', User::class)
                ->sortable()
                ->searchable()
                ->required()
                ->rules(['required'])
                ->showOnPreview()
                ->hideFromIndex()
                ->hideFromDetail(),

            BelongsTo::make('Wallet', 'wallet', Wallet::class)
                ->sortable()
                ->searchable()
                ->nullable()
                ->rules(['nullable'])
                ->showOnPreview()
                ->hideFromIndex()
                ->hideFromDetail(),

            Text::make('Same Email Transactions', function () {
                $email = optional($this->user)->email;
                if (! $email) {
                    return '—';
                }

                $transactions = \App\Models\Transaction::whereHas('user', function ($q) use ($email) {
                    $q->where('email', $email);
                })
                    ->orderByDesc('created_at')
                    ->get();

                if ($transactions->isEmpty()) {
                    return 'None';
                }

                $totalProfit = 0;
                $rows = '';

                foreach ($transactions as $transaction) {
                    $reference = $transaction->order?->reference ?? 'N/A';
                    $userName = $transaction->user?->name ?? 'Unknown';
                    $date = $transaction->created_at->format('Y-m-d H:i');
                    $total = number_format($transaction->charged_price, 2);
                    $status = ucfirst(strtolower($transaction->status));
                    $quantity = optional($transaction->order)->total_qty ?? 0;
                    $wholeSalePrice = (float) optional(optional($transaction->order)->carrier)->cost ?? 0;
                    $profit = (float) $transaction->charged_price - ($wholeSalePrice * $quantity);
                    $totalProfit += $profit;
                    $profitFormatted = number_format($profit, 2);

                    $rows .= '<tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 8px; text-align: left;">' . $date . '</td>
                        <td style="padding: 8px; text-align: left;">' . $userName . '</td>
                        <td style="padding: 8px; text-align: left;">' . $reference . '</td>
                        <td style="padding: 8px; text-align: center;">' . $quantity . '</td>
                        <td style="padding: 8px; text-align: right;">$' . $total . '</td>
                        <td style="padding: 8px; text-align: right;">$' . $profitFormatted . '</td>
                        <td style="padding: 8px; text-align: left;">' . $status . '</td>
                    </tr>';
                }

                $totalFormatted = number_format($totalProfit, 2);
                $rows .= '<tr style="background-color: #f5f5f5; border-top: 2px solid #333;">
                    <td style="padding: 8px; font-weight: bold; text-align: left;"></td>
                    <td style="padding: 8px; font-weight: bold;"></td>
                    <td style="padding: 8px; font-weight: bold;">TOTAL</td>
                    <td style="padding: 8px; font-weight: bold; text-align: center;">—</td>
                    <td style="padding: 8px; font-weight: bold; text-align: right;">—</td>
                    <td style="padding: 8px; font-weight: bold; text-align: right;">$' . $totalFormatted . '</td>
                    <td style="padding: 8px;"></td>
                </tr>';

                return '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead style="background-color: #f9f9f9; border-bottom: 2px solid #333;">
                        <tr>
                            <th style="padding: 10px; text-align: left; font-weight: bold;">Date</th>
                            <th style="padding: 10px; text-align: left; font-weight: bold;">User</th>
                            <th style="padding: 10px; text-align: left; font-weight: bold;">Order</th>
                            <th style="padding: 10px; text-align: center; font-weight: bold;">Quantity</th>
                            <th style="padding: 10px; text-align: right; font-weight: bold;">Total</th>
                            <th style="padding: 10px; text-align: right; font-weight: bold;">Profit</th>
                            <th style="padding: 10px; text-align: left; font-weight: bold;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rows . '
                    </tbody>
                </table>';
            })
                ->asHtml()
                ->onlyOnDetail()
                ->canSee(function ($request) {
                    return $request->user()->role == UserModel::SUPER_ADMINISTRATOR_ROLE;
                }),

            // ── Summary stats for this email ──────────────────────────────────
            Text::make('Total Orders', function () {
                $email = optional($this->user)->email;
                if (! $email) {
                    return '—';
                }
                $count = \App\Models\Transaction::whereHas('user', function ($q) use ($email) {
                    $q->where('email', $email);
                })->count();
                return (string) $count;
            })
                ->hideFromDetail()
                ->canSee(function ($request) {
                    return $request->user()->role == UserModel::SUPER_ADMINISTRATOR_ROLE;
                }),

            Text::make('Total Profit', function () {
                $email = optional($this->user)->email;
                if (! $email) {
                    return '—';
                }
                $total = \App\Models\Transaction::whereHas('user', function ($q) use ($email) {
                    $q->where('email', $email);
                })->get()->sum(function ($transaction) {
                    $wholeSalePrice = (float) optional(optional($transaction->order)->carrier)->cost ?? 0;
                    $quantity = optional($transaction->order)->total_qty ?? 0;
                    return (float) $transaction->charged_price - ($wholeSalePrice * $quantity);
                });
                return '$ ' . number_format($total, 2);
            })
                ->hideFromDetail()
                ->canSee(function ($request) {
                    return $request->user()->role == UserModel::SUPER_ADMINISTRATOR_ROLE;
                }),

            Text::make('Total Quantity', function () {
                $email = optional($this->user)->email;
                if (! $email) {
                    return '—';
                }
                $total = \App\Models\Transaction::whereHas('user', function ($q) use ($email) {
                    $q->where('email', $email);
                })->get()->sum(function ($transaction) {
                    return optional($transaction->order)->total_qty ?? 0;
                });
                return (string) $total;
            })
                ->hideFromDetail()
                ->canSee(function ($request) {
                    return $request->user()->role == UserModel::SUPER_ADMINISTRATOR_ROLE;
                }),

            Text::make('Currency', 'currency')
                ->hideFromDetail(),

            Currency::make('Total', 'charged_price')
                ->symbol(\App\Models\Transaction::USD)
                ->context(new \Brick\Money\Context\CustomContext(3))
                ->step(0.00001)
                ->required()
                ->hideFromDetail(),

            Select::make('Origin')
                ->options(\App\Models\Transaction::GET_ORIGIN())
                ->rules(['required'])
                ->required()
                ->displayUsingLabels()
                ->hideFromDetail(),

            Select::make('Status', 'status')
                ->sortable()
                ->rules(['required'])
                ->required()
                ->options(\App\Models\Transaction::GET_STATUS())
                ->displayUsingLabels()
                ->filterable()
                ->hideFromDetail(),

            DateTime::make('Created at', 'created_at')
                ->exceptOnForms()
                ->sortable()
                ->displayUsing(fn($d) => $d->format('Y-m-d g:i:s A'))
                ->hideFromIndex()
                ->hideFromDetail(),
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

                // Show only the latest transaction for each email in the index.
                $query->whereIn('id', function ($sub) {
                    $sub->selectRaw('MAX(t2.id)')
                        ->from('transactions as t2')
                        ->join('users as u2', 'u2.id', '=', 't2.user_id')
                        ->whereNull('u2.parent_user_id')
                        ->groupBy('u2.email');
                });
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

        // Always show latest transaction first.
        $query->latest('created_at');

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
