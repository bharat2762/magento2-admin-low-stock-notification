<?php

namespace Bharat\LowStockNotification\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Data
 * @package Bharat\LowStockNotification\Helper
 */
class Data extends AbstractHelper
{

    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * @param $path
     * @param int $storeId
     * @return mixed
     */
    public function getModuleConfig($path)
    {
        return $this->scopeConfig->getValue(
            'lowstocknotification/' . $path,
            ScopeInterface::SCOPE_STORE
        );
    }
}
