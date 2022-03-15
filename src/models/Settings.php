<?php

namespace vaersaagod\assetmate\models;

use craft\base\Model;

/**
 * @author    Værsågod
 * @package   AssetMate
 * @since     1.0.0
 */
class Settings extends Model
{
    public array $volumes = [];

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            
        ];
    }
}
