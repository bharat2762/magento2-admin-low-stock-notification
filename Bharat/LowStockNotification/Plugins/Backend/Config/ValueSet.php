<?php

namespace Bharat\LowStockNotification\Plugins\Backend\Config;

use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * Class ValueSet
 */
class ValueSet
{
    /**
     * @var WriterInterface
     */
    private $configWriter;

    public function __construct(
        WriterInterface $configWriter
    ) {
        $this->configWriter = $configWriter;
    }

    /**
     * @param Value $subject
     */
    public function beforeSave(Value $subject)
    {
        $syncFields = [
            'lowstocknotification/low_stock_notification/qty'
            => 'cataloginventory/item_options/notify_stock_qty',
            'cataloginventory/item_options/notify_stock_qty'
            => 'lowstocknotification/low_stock_notification/qty'
        ];

        if (isset($syncFields[$subject->getPath()]) && $subject->getOldValue() != $subject->getValue()) {
            $this->configWriter->save($syncFields[$subject->getPath()], $subject->getValue());
        }
    }
}
