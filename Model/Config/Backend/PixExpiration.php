<?php
/**
 * @updated_for_magento_2.4.9
 */

namespace Paghiper\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class PixExpiration Backend Model
 * Updated for Magento 2.4.9 & PHP 8.4+
 */
class PixExpiration extends Value
{
    /**
     * Validate Pix Expiration value before saving to database
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave(): \Magento\Framework\App\Config\Value
    {
        $value = $this->getValue();

        // Garante que o valor não seja nulo e contenha apenas dígitos numéricos
        if ($value === null || !ctype_digit((string) $value)) {
            throw new LocalizedException(__('The value must be an integer.'));
        }

        $value = (int) $value;

        // Valida o intervalo permitido pela API do PagHiper
        if ($value < 1 || $value > 576000) {
            throw new LocalizedException(
                __('The value must be between 1 and 576000 minutes.')
            );
        }

        // Define o valor explicitamente convertido de volta para o objeto
        $this->setValue($value);

        return parent::beforeSave();
    }
}