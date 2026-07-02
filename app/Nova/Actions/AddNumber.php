<?php

namespace App\Nova\Actions;

use App\Imports\NumberImport;
use App\Jobs\ProcessNumberImport;
use App\Models\Number;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Maatwebsite\Excel\Facades\Excel;

class AddNumber extends Action
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        if (!$fields->file) {
            return Action::danger('No file uploaded.');
        }

        // Store the uploaded file in 'storage/app/uploads/'
        $path = $fields->file->store('uploads');

        // Check if file exists before processing
        if (!Storage::exists($path)) {
            return Action::danger("Uploaded file not found: $path");
        }

        // Get full path for import
        $fullPath = Storage::path($path);

        // Process the file using Laravel Excel Import class
        $import = new NumberImport();
        Excel::import($import, $fullPath);

        // Get the extracted records
        $records = $import->getExtractedRecords();

        // Delete the temporary file
        Storage::delete($path);

        if ($records->isEmpty()) {
            return Action::danger('No data extracted from the file.');
        }

        $authUser = Auth::user();
        $userId   = $authUser->id;

        // Check if any numbers already exist
        $isAnyNumberExists = Number::query()
            ->whereIn('phone_number', $records->pluck('number'))
            ->exists();

        if ($isAnyNumberExists) {
            return Action::danger('Number already exists.');
        }
        // Extract serializable fields
        $fieldData = [
            'carrier' => (int) $fields->carrier,
            'city' => $fields->city ? $fields->city->id : null,
            'area' => (int) $fields->area,
        ];
        Log::info('', $fieldData);
        // Dispatch the job with serializable data
        ProcessNumberImport::dispatch($records, $fieldData, (int) $userId, $authUser);

        return Action::message('Numbers added successfully.');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        $numberOfDaysToExpire = SettingService::getNumberOfDaysToExpire();

        $authUser = Auth::user();
        $userId = null;

        if ($authUser->role == User::SELLER_ROLE) {
            if (!$authUser->parent_user_id) {
                $userId = $authUser->id;
            } elseif ($authUser->parent_user_id) {
                $userId = $authUser->parent_user_id;
            }
        }
        return [
            // Select::make('Seller', 'seller')
            //     ->options(\App\Models\User::isSellerRole()->isActive()->pluck('name', 'id'))
            //     ->rules('required')
            //     ->required()
            //     ->displayUsingLabels()
            //     ->searchable()
            //     ->fullWidth()
            //     ->canSee(function ($request) {
            //         return in_array($request->user()->role, [\App\Models\User::SUPER_ADMINISTRATOR_ROLE, \App\Models\User::NTS_ADMINISTRATOR_ROLE]);
            //     }),

            // Hidden::make('User ID', 'user_id')
            //     ->default($userId)
            //     ->canSee(function ($request) {
            //         return in_array($request->user()->role, [
            //             User::SUPER_ADMINISTRATOR_ROLE,
            //             User::SELLER_ROLE,
            //         ]);
            //     }),


            // Select::make('Carrier', 'carrier')
            //     ->dependsOn(['user_id', 'seller'], function (Select $field, NovaRequest $request, FormData $formData) {
            //         if ($formData->user_id || $formData->seller) {
            //             $field->options(
            //                 \App\Models\Carrier::isActive()->pluck('name', 'id')
            //             )
            //                 ->rules('required')
            //                 ->required()
            //                 ->show();
            //             return;
            //         }
            //         $field->nullable()->rules('nullable')->hide();
            //     })
            //     ->displayUsingLabels()
            //     ->searchable()
            //     ->fullWidth(),

            // Select::make('Carrier', 'carrier')
            //     ->options(\App\Models\Carrier::isActive()->pluck('name', 'id'))
            //     ->displayUsingLabels()
            //     ->searchable()
            //     ->fullWidth()
            //     ->canSee(function ($request) {
            //         return in_array($request->user()->role, [
            //             User::SUPER_ADMINISTRATOR_ROLE,
            //             User::SELLER_ROLE,
            //         ]);
            //     }),


            Select::make('Carrier', 'carrier')
                ->options(function () use ($request) {

                    return \App\Models\Carrier::isActive()->pluck('name', 'id');
                })
                ->displayUsingLabels()
                ->searchable()
                ->fullWidth()
                ->canSee(function ($request) {
                    return in_array($request->user()->role, [
                        User::SUPER_ADMINISTRATOR_ROLE,
                    ]);
                }),

            Select::make('Area', 'area')
                ->options(\App\Models\Area::isActive()->pluck('name', 'id'))
                ->rules('required')
                ->required()
                ->displayUsingLabels()
                ->searchable()
                ->fullWidth(),

            // Select::make('City', 'city')
            //     ->options(\App\Models\City::isActive()->pluck('name', 'id'))
            //     ->displayUsingLabels()
            //     ->searchable()
            //     ->fullWidth(),

            // Nova resource mein relationship banana padega
            BelongsTo::make('City', 'city', \App\Nova\City::class)
                ->searchable()
                ->nullable()
                ->fullWidth()
                ->withMeta([
                    'minSearchLength' => 3,
                ]),

            File::make('Excel', 'file')
                ->acceptedTypes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->rules('required', 'max:10240')
                ->required()
                ->fullWidth(),

            Heading::make('<div class="space-y-2 md:flex @md/modal:flex md:flex-row @md/
                modal:flex-row md:space-y-0 @md/modal:space-y-0 py-5"><div class="w-full 
                md:mt-2 @md/modal:mt-2 md:w-1/5 @md/modal:w-1/
                5"><label for="expiry-default-text-field" class="inline-block leading-tight 
                space-x-1"><span>Expiry</span></label></div><div class="w-full 
                md:mt-2 @md/modal:mt-2 md:w-4/5 @md/modal:w-4/
                5"><label for="expiry-default-text-field" class="inline-block leading-tight 
                space-x-1 mt-2"><span>Numbers will expire within <b>' . $numberOfDaysToExpire . ' days</b> from the date of addition.</span></label></div></div>')
                ->fullWidth()
                ->asHtml(),
        ];
    }
}
