<?php
namespace Paghiper\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class PixExpiration extends Value
{
  public function beforeSave()
  {
    $value = $this->getValue();

    if (!ctype_digit((string) $value)) {
      throw new LocalizedException(__('The value must be an integer.'));
    }

    $value = (int) $value;

    if ($value < 1 || $value > 576000) {
      throw new LocalizedException(
        __('The value must be between 1 and 576000 minutes.')
      );
    }

    return parent::beforeSave();
  }
}
