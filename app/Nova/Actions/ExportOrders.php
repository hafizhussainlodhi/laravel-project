<?php

namespace App\Nova\Actions;

use App\Services\ExcelExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportOrders extends Action
{
    use InteractsWithQueue, Queueable;
    protected $userId;
    protected $resource;
    protected ExcelExportService $excelExportService;
    protected $fileName;

    public $name;

    protected function getActionName(): string
    {
        return match ($this->resource) {
            'orders' => 'Export Orders',
            'seller-orders' => 'Export Distributor Orders',
            default => 'Export Orders',
        };
    }

    public function __construct($userId, $resource)
    {
        $this->userId = $userId;
        $this->resource = $resource;
        $this->excelExportService = new ExcelExportService();
        $this->fileName = $this->getFilename();

        $this->name = $this->getActionName();
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();

        $query = \App\Models\Order::query()->isBuying();

        // 👇 ROLE BASED FILTER
        if (in_array($user->role, [
            \App\Models\User::SUPER_ADMINISTRATOR_ROLE,
            \App\Models\User::NTS_ADMINISTRATOR_ROLE,
        ])) {

            // Super Admin
            $query->whereHas('user', function ($q) {
                $q->whereIn('role', [
                    \App\Models\User::USER_ROLE,
                    \App\Models\User::SELLER_ROLE
                ])
                    ->whereNull('parent_user_id');
            });
        } else {

            // Seller / Buyer → sirf apne orders
            if ($this->resource == 'orders') {
                $query->where('user_id', $user->id);
            } else if ($this->resource == 'seller-orders') {
                $query->whereIn('user_id', function ($q) use ($user) {
                    $q->select('id')
                        ->from('users')
                        ->where('parent_user_id', $user->id)
                        ->where('role', \App\Models\User::USER_ROLE);
                });
            }
        }

        //  export
        $query->with(['user', 'carrier', 'city', 'area'])
            ->chunk(300, function ($orders) {
                $this->excelExportService->orderExport($orders, $this->fileName);
            });

        if (file_exists(storage_path('app/public/' . $this->fileName))) {
            return Action::download(
                $this->getDownloadUrl(
                    new BinaryFileResponse(storage_path('app/public/' . $this->fileName))
                ),
                $this->fileName
            );
        }

        return Action::danger('No orders found');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }


    protected function getDownloadUrl(BinaryFileResponse $response): string

    {
        return URL::temporarySignedRoute('laravel-nova-excel.download', now()->addMinutes(1), [
            'path' => encrypt($response->getFile()->getPathname()),
            'filename' => $this->getFilename(),
        ]);
    }

    /**
     * @return string|null
     */
    protected function getFilename(): ?string
    {
        $prefix = 'export-';

        $timestamp = now()->format('Ymd_His');

        return $prefix . "orders-" . $timestamp . '.csv';
    }
}
