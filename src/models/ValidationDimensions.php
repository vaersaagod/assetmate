<?php

namespace vaersaagod\assetmate\models;

use craft\base\Model;

/**
 * @author    Værsågod
 * @package   AssetMate
 * @since     1.0.0
 */
class ValidationDimensions extends Model
{
    public null|string|int $maxWidth = null;
    public null|string|int $maxHeight = null;
    public null|string|int $minWidth = null;
    public null|string|int $minHeight = null;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            
        ];
    }
}
