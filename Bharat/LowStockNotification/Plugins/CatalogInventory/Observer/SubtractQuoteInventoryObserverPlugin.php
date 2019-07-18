<?php
/**
 * @author Bharat Patel
 * @package Bharat_LowStockNotification
 */


namespace Bharat\LowStockNotification\Plugins\CatalogInventory\Observer;

use Magento\CatalogInventory\Observer\SubtractQuoteInventoryObserver;
use Magento\CatalogInventory\Observer\ItemsForReindex;
use Bharat\LowStockNotification\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Element\Template;

/**
 * Class SubtractQuoteInventoryObserverPlugin
 */
class SubtractQuoteInventoryObserverPlugin
{
    const CONFIG_PATH_LOW_STOCK_NOTIFICATION = 'low_stock_notification/active';

    const CONFIG_PATH_EMAIL_TO = 'low_stock_notification/email/recipient_email';

    const CONFIG_PATH_SENDER_EMAIL = 'low_stock_notification/email/sender_email';

    const TEMPLATE_FILE = 'Bharat_LowStockNotification::notifications/low_stock_single_alert.phtml';

    /**
     * @var ItemsForReindex
     */
    private $itemsForReindex;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Layout
     */
    private $layout;

    public function __construct(
        ItemsForReindex $itemsForReindex,
        Data $data,
        StoreManagerInterface $storeManager,
        TransportBuilder $transportBuilder,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
        Layout $layout
    ) {
        $this->itemsForReindex = $itemsForReindex;
        $this->helperData = $data;
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->layout = $layout;
    }

    /**
     * @param SubtractQuoteInventoryObserver $subject
     * @param SubtractQuoteInventoryObserver $result
     */
    public function afterExecute($subject, $result)
    {

        if ($this->helperData->getModuleConfig(self::CONFIG_PATH_LOW_STOCK_NOTIFICATION)
            && $emailTo = $this->getEmailTo()
            && $sender = $this->helperData->getModuleConfig(self::CONFIG_PATH_SENDER_EMAIL)
        ) {
            $storeId = $this->storeManager->getStore()->getId();
            $products = $this->getLowStockItems($storeId);
            if (empty($products)) {
                return;
            }
            try {
                $lowStockHtml = $this->getLowStockHtml($products);
                if ($lowStockHtml) {
                    $transport = $this->transportBuilder->setTemplateIdentifier(
                        $this->helperData->getModuleConfig('low_stock_notification/email/email_template')
                    )->setTemplateOptions(
                        ['area' => Area::AREA_FRONTEND, 'store' => $storeId]
                    )->setTemplateVars(
                        [
                            'lowStockHtml' => $lowStockHtml,
                            'qty' => $this->helperData->getModuleConfig('low_stock_notification/qty')
                        ]
                    )->setFrom(
                        $sender
                    )->addTo(
                        $this->getEmailTo()
                    )->getTransport();

                    $transport->sendMessage();
                }
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }
    }

    /**
     * @return array|mixed
     */
    protected function getEmailTo()
    {
        $emailTo = $this->helperData->getModuleConfig(self::CONFIG_PATH_EMAIL_TO);
        if (strpos($emailTo, ',') !== false) {
            $emailTo = explode(',', $emailTo);
        }
        return $emailTo;
    }

    /**
     * @param array $products
     *
     * @return string
     */
    protected function getLowStockHtml($products)
    {
        /** @var Template $lowStockAlert */
        $lowStockAlert = $this->layout->createBlock(Template::class)
            ->setTemplate(self::TEMPLATE_FILE)
            ->setData('lowStockItems', $products);

        return trim($lowStockAlert->toHtml());
    }

    /**
     * @param int $storeId
     *
     * @return array
     */
    protected function getLowStockItems($storeId)
    {
        $products = [];
        foreach ($this->getCollectionItems() as $lowStockItem) {
            if (!$storeId) {
                $storeId = $lowStockItem->getStoreId();
            }

            $product = $this->initProduct($lowStockItem->getProductId(), $storeId);
            $products[] = [
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'quantity' => $lowStockItem->getQty()
            ];
        }

        return $products;
    }

    /**
     * @return array
     */
    protected function getCollectionItems()
    {
        return $this->itemsForReindex->getItems();
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return \Magento\Catalog\Api\Data\ProductInterface
     */
    protected function initProduct($productId, $storeId)
    {
        return $this->productRepository->getById(
            $productId,
            false,
            $storeId
        );
    }
}
