<?php

/* 
 * Daniele Pastori.
 * daniele.pastori@gmail.com
 */


class Full_Productcoupon_Model_Observer extends Mage_SalesRule_Model_Observer{
    
    /**
     * add gift to cart
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function addFreeProduct( Varien_Event_Observer $observer ){
        $ruleId = $observer->getQuote()->getAppliedRuleIds();
        //foreach( $observer->getQuote()->getAppliedRuleIds() as $ruleId ){
            $rule = Mage::getModel('salesrule/rule')->load($ruleId);
            if( $productId = $rule->getCouponItemId() ){            
                $product = Mage::getModel('catalog/product')->load($productId);
                if ( !$observer->getQuote()->hasProductId($productId)) {                    
                    $item = $observer->getQuote()->addProduct( $product );           
                }
                else{
                    $item = $observer->getQuote()->getItemByProduct($product);                
                }
                /*  @var $item Mage_Sales_Model_Quote_Item*/
                $item->setQty(1);
                $item->setCustomPrice(0);
                $item->setOriginalCustomPrice(0);            
                $item->getProduct()->setIsSuperMode(true); 
                //$item->save();

            }

        //}
    }
    
    /**
     * you can't change free product quantity!
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function controlFreeProduct( Varien_Event_Observer $observer ){
        $quote = $observer->getCart()->getQuote();
        $ruleId = $quote->getAppliedRuleIds();
        //foreach( $observer->getCart()->getQuote()->getAppliedRuleIds() as $ruleId ){
            $rule = Mage::getModel('salesrule/rule')->load($ruleId);
            $productId = $rule->getCouponItemId();
            if( $quote->hasProductId($productId)) {
                $product = Mage::getModel('catalog/product')->load($productId);
                $item = $quote->getItemByProduct($product);            
                if( $item && $item->getId() ){
                    $changes = $observer->getInfo();
                    if( isset( $changes[$item->getId()] ) && $changes[$item->getId()]['qty'] > 1 ){
                        Mage::throwException(Mage::helper('salesrule')->__('Free product maximum quantity is 1'));                        
                    }
                }
                
            }
       // }
    }

    /**
     * save value if cart has coupon code, so it can be retrieved from recalculate
     * save coupon code if set from Full_CategoryLanding_Block_Category_ApplyCoupon
     * @param Varien_Event_Observer $observer
     */
    public function prepareRecalculation( Varien_Event_Observer $observer ){
        if( $observer->getQuote()->getCouponCode() )
            $observer->getQuote()->setOldCouponCode( $observer->getQuote()->getCouponCode() );
        elseif( ( $couponCode = Mage::getSingleton('core/session')->getCouponCode() ) && !Mage::app()->getRequest()->getParam('remove') ){
            $coupon = Mage::getModel('salesrule/coupon')->load( $couponCode, 'code' );
            $observer->getQuote()
                ->setAppliedRuleIds( $coupon->getRuleId() )
                ->setCouponCode( $couponCode );
            $this->addFreeProduct( $observer );
        }

    }

    /**
     * recalculate if cart should have free product and remove it if coupon has been removed
     * @param Varien_Event_Observer $observer
     */
    public function recalculate( Varien_Event_Observer $observer ){
        $quote = $observer->getQuote();
        /* @var $quote Mage_Sales_Model_Quote */
        if( $quote->getOldCouponCode() && !$quote->getCouponCode() ){ //coupon has been removed
            $fakeObserver = new Varien_Event_Observer();
            $fakeObserver->setCouponCode( $quote->getOldCouponCode() );
            $fakeObserver->setQuote( $quote );
            $this->removeFreeProduct( $fakeObserver );
        }
    }
    
    /**
     * remove gift from cart
     * remove coupon from session, if set
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function removeFreeProduct( Varien_Event_Observer $observer ){
        if( Mage::getSingleton('core/session')->getCouponCode() ){
            Mage::getSingleton( 'core/session' )->unsCouponCode();
        }

        $coupon = Mage::getModel('salesrule/coupon')->load( $observer->getCouponCode(), 'code' );
        $rule = Mage::getModel('salesrule/rule')->load( $coupon->getRuleId() );      
        if( $productId = $rule->getCouponItemId() ){   
            if ( $observer->getQuote()->hasProductId($productId)) { 
                $product = Mage::getModel('catalog/product')->load($productId);
                $item = $observer->getQuote()->getItemByProduct($product);
                $observer->getQuote()->removeItem( $item->getId() )->save();
            }
        }
    }


    /**
     * override magento observer, apply also if discount amount is 0
     * Registered callback: called after an order is placed
     *
     * @param Varien_Event_Observer $observer
     */
    public function sales_order_afterPlace($observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!$order) {
            return $this;
        }

        // lookup rule ids
        $ruleIds = explode(',', $order->getAppliedRuleIds());
        $ruleIds = array_unique($ruleIds);

        $ruleCustomer = null;
        $customerId = $order->getCustomerId();

        // use each rule (and apply to customer, if applicable)
        //if ($order->getDiscountAmount() != 0) {
            foreach ($ruleIds as $ruleId) {
                if (!$ruleId) {
                    continue;
                }
                $rule = Mage::getModel('salesrule/rule');
                $rule->load($ruleId);
                if ($rule->getId()) {
                    $rule->setTimesUsed($rule->getTimesUsed() + 1);
                    $rule->save();

                    if ($customerId) {
                        $ruleCustomer = Mage::getModel('salesrule/rule_customer');
                        $ruleCustomer->loadByCustomerRule($customerId, $ruleId);

                        if ($ruleCustomer->getId()) {
                            $ruleCustomer->setTimesUsed($ruleCustomer->getTimesUsed() + 1);
                        }
                        else {
                            $ruleCustomer
                                ->setCustomerId($customerId)
                                ->setRuleId($ruleId)
                                ->setTimesUsed(1);
                        }
                        $ruleCustomer->save();
                    }
                }
            }
            $coupon = Mage::getModel('salesrule/coupon');
            /** @var Mage_SalesRule_Model_Coupon */
            $coupon->load($order->getCouponCode(), 'code');
            if ($coupon->getId()) {
                $coupon->setTimesUsed($coupon->getTimesUsed() + 1);
                $coupon->save();
                if ($customerId) {
                    $couponUsage = Mage::getResourceModel('salesrule/coupon_usage');
                    $couponUsage->updateCustomerCouponTimesUsed($customerId, $coupon->getId());
                }
            }
        //}
    }
}