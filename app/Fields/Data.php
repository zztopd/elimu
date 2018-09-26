<?php

namespace App\Fields;

use Laravel\Nova\Contracts\ListableField;
use Laravel\Nova\Fields\BelongsToMany;

class Data extends BelongsToMany implements ListableField
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'data';

    public $showOnCreation = false;

    public $showOnUpdate = false;

    public $showOnIndex = false;
}
