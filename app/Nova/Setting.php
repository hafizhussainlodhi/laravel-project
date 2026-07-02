<?php

namespace App\Nova;

use Alexwenzel\DependencyContainer\DependencyContainer;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Trix;

class Setting extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Setting>
     */
    public static $model = \App\Models\Setting::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make('Name', 'name')
            ->rules(['required'])
            ->required(),

     
        Select::make('Type', 'type')
            ->rules(['required'])
            ->required()
            ->options(\App\Models\Setting::$typesLabels)
            ->displayUsingLabels(),

        //Only On onlyOnIndex Value
        Text::make('Value', 'value')
            ->displayUsing(function ($text) {
                return Str::limit($text, 30);
            })
            ->exceptOnForms()
            ->onlyOnIndex(),

        DependencyContainer::make([

            Text::make('Value', 'value')
                ->rules(['required'])
                ->required()
                ->onlyOnForms(),

        ])
            ->dependsOn('type', \App\Models\Setting::TEXT),

        DependencyContainer::make([

            Trix::make('Value', 'value')
                ->rules(['required'])
                ->required()
                ->onlyOnForms(),
        ])
            ->dependsOn('type', \App\Models\Setting::LONG_TEXT),

        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
