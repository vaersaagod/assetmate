<?php

namespace vaersaagod\assetmate\models;

use craft\base\Model;

/**
 * @author    Værsågod
 * @package   AssetMate
 * @since     1.0.0
 */
class ValidationSettings extends Model
{
    public array $kinds = [];
    public array $extensions = [];
    public ?ValidationSize $size = null;

    // Public Methods
    // =========================================================================

    /**
     * Settings constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        if (isset($config['size'])) {
            $config['size'] = new ValidationSize($config['size']);
        }
        
        parent::__construct($config);
        
        $this->init();
    }
    
    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            
        ];
    }
}
