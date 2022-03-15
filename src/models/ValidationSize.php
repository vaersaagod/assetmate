<?php

namespace vaersaagod\assetmate\models;

use craft\base\Model;

/**
 * @author    Værsågod
 * @package   AssetMate
 * @since     1.0.0
 */
class ValidationSize extends Model
{
    public null|string|int $max = null;
    public null|string|int $min = null;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            
        ];
    }
}
