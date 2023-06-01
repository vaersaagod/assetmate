<?php

namespace vaersaagod\assetmate\models;

use craft\base\Model;

/**
 * @author    Værsågod
 * @package   AssetMate
 * @since     2.1.0
 */
class ResizeSettings extends Model
{
    public ?int $maxWidth = null;
    public ?int $maxHeight = null;
    public int $quality = 95;

    // Public Methods
    // =========================================================================

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            
        ];
    }
}
