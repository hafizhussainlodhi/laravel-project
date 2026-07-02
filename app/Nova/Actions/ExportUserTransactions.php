<?php

namespace App\Nova\Actions;

use App\Models\Transaction;
use App\Models\User as UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Services\ExcelExportService;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportUserTransactions extends Action
{
    use Queueable;

    protected ExcelExportService $excelExportService;
    protected $fileName;

    public function __construct()
    {
        $this->excelExportService = new ExcelExportService();
    }

    public $name = 'Export by User';



    public function handle(ActionFields $fields, Collection $models)
    {
        $userId = $fields->user_id;

        if (!$userId) {
            return Action::danger('Please select a user.');
        }

        $user = UserModel::find($userId);

        $this->fileName = 'transactions_' . str($user->name)->slug() . '_' . now()->format('Ymd_His') . '.csv';

        Transaction::whereNotNull('order_id')
            ->where('user_id', $userId)
            ->with(['order', 'user'])
            ->chunk(300, function ($transactions) {
                $this->excelExportService->transactionExport($transactions, $this->fileName);
            });

        $path = storage_path('app/public/' . $this->fileName);

        if (file_exists($path)) {
            return Action::download(
                $this->getDownloadUrl(new BinaryFileResponse($path)),
                $this->fileName
            );
        }

        return Action::danger('No transactions found for this user.');
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
        $users = UserModel::whereNull('parent_user_id')
            ->whereIn('role', [
                UserModel::USER_ROLE,
                UserModel::SELLER_ROLE
            ])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn($u) => [
                $u->id => $u->name . ' (' . $u->email . ')'
            ])
            ->toArray();

        return [
            Select::make('User', 'user_id')
                ->options($users)
                ->searchable()
                ->rules(['required'])
                ->placeholder('Select a user...'),
        ];
    }
}
