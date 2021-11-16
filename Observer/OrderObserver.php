<?php

namespace Paghiper\Magento2\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderObserver implements ObserverInterface {

  public function execute(\Magento\Framework\Event\Observer $observer) {
    $order = $observer->getEvent()->getOrder();
    $order->setStatus("pending");
    $order->setState("new");
    $order->save();
  }

}
