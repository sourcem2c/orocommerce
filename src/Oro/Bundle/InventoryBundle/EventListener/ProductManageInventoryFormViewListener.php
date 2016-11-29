<?php

namespace Oro\Bundle\InventoryBundle\EventListener;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\UIBundle\Event\BeforeListRenderEvent;
use Oro\Bundle\UIBundle\Fallback\AbstractFallbackFieldsFormView;
use Oro\Bundle\UIBundle\View\ScrollData;

class ProductManageInventoryFormViewListener extends AbstractFallbackFieldsFormView
{
    /**
     * @param BeforeListRenderEvent $event
     */
    public function onProductView(BeforeListRenderEvent $event)
    {
        $product = $this->getEntityFromRequest(Product::class);
        if (!$product) {
            return;
        }

        $template = $event->getEnvironment()->render(
            'OroInventoryBundle:Product:manageInventory.html.twig',
            ['entity' => $product]
        );

        $this->addInventoryToBlock($event->getScrollData(), $template);
    }

    /**
     * @param BeforeListRenderEvent $event
     */
    public function onProductEdit(BeforeListRenderEvent $event)
    {
        $template = $event->getEnvironment()->render(
            'OroInventoryBundle:Product:manageInventoryFormWidget.html.twig',
            ['form' => $event->getFormView()]
        );
        $this->addInventoryToBlock($event->getScrollData(), $template);
    }

    /**
     * @param ScrollData $scrollData
     * @param string $template
     */
    protected function addInventoryToBlock(ScrollData $scrollData, $template)
    {
        $inventoryBlockLabel = $this->translator->trans('oro.product.sections.inventory');

        $data = $scrollData->getData()[ScrollData::DATA_BLOCKS];
        foreach ($data as $blockId => $blockInfo) {
            if ($blockInfo['title'] == $inventoryBlockLabel) {
                $scrollData->addSubBlockData($blockId, 0, $template);

                return;
            }
        }
    }

    /**
     * @return null|Product
     */
    protected function getProductFromRequest()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $productId = (int)$request->get('id');
        if (!$productId) {
            return null;
        }

        return $this->doctrineHelper->getEntityReference(Product::class, $productId);
    }
}
