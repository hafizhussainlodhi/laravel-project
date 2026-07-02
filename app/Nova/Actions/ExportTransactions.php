<?php

namespace App\Nova\Actions;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Services\ExcelExportService;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Laravel\Nova\Fields\Select;

class ExportTransactions extends Action
{
    use Queueable;

    protected ExcelExportService $excelExportService;
    protected $fileName;

    public function __construct()
    {
        $this->excelExportService = new ExcelExportService();
        $this->fileName = $this->getFilename();
    }

    public $name = 'Export Transactions';

    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();

        $query = Transaction::whereNotNull('order_id')
            ->with(['order', 'user']);

        if ($fields->type === 'month' && $fields->month && $fields->year) {
            $query->whereMonth('created_at', $fields->month)
                ->whereYear('created_at', $fields->year);
        }

        if (in_array($user->role, [
            \App\Models\User::SUPER_ADMINISTRATOR_ROLE,
            \App\Models\User::NTS_ADMINISTRATOR_ROLE,
        ])) {
            $query->whereHas('user', fn($q) => $q->whereNull('parent_user_id'));
        } else {
            $query->where('user_id', $user->id);
        }

        $query->chunk(300, function ($transactions) {
            $this->excelExportService->transactionExport($transactions, $this->fileName);
        });

        if (file_exists(storage_path('app/public/' . $this->fileName))) {
            return Action::download(
                $this->getDownloadUrl(
                    new BinaryFileResponse(storage_path('app/public/' . $this->fileName))
                ),
                $this->fileName
            );
        }

        return Action::danger('No transactions found');
    }

    protected function getFilename(): string
    {
        return 'transactions-' . now()->format('Ymd_His') . '.csv';
    }

    protected function getDownloadUrl(BinaryFileResponse $response): string
    {
        return URL::temporarySignedRoute(
            'laravel-nova-excel.download',
            now()->addMinutes(1),
            [
                'path' => encrypt($response->getFile()->getPathname()),
                'filename' => $this->fileName,
            ]
        );
    }


    public function fields(NovaRequest $request): array
    {
        return [

            Select::make('Export Type', 'type')
                ->options([
                    'all' => 'All Transactions',
                    'month' => 'By Month',
                ])
                ->displayUsingLabels()
                ->rules('required')
                ->required(),

            Select::make('Month', 'month')
                ->options([
                    '01' => 'January',
                    '02' => 'February',
                    '03' => 'March',
                    '04' => 'April',
                    '05' => 'May',
                    '06' => 'June',
                    '07' => 'July',
                    '08' => 'August',
                    '09' => 'September',
                    '10' => 'October',
                    '11' => 'November',
                    '12' => 'December',
                ])
                ->displayUsingLabels()
                ->dependsOn(['type'], function ($field, $request, $formData) {
                    if ($formData->type === 'month') {
                        $field->show()->rules('required');
                    } else {
                        $field->hide();
                    }
                }),

            Select::make('Year', 'year')
                ->options(
                    collect(range(now()->year, now()->year - 5))
                        ->mapWithKeys(fn($year) => [$year => $year])
                        ->toArray()
                )
                ->displayUsingLabels()
                ->dependsOn(['type'], function ($field, $request, $formData) {
                    if ($formData->type === 'month') {
                        $field->show()->rules('required');
                    } else {
                        $field->hide();
                    }
                }),
        ];
    }
}
