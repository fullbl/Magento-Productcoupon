<?php

/* 
 * Daniele Pastori.
 * daniele.pastori@gmail.com
 */

/**
 * Shopping cart controller
 */
require_once(Mage::getModuleDir('controllers','Mage_Checkout').DS.'CartController.php');
class Full_Productcoupon_CartController extends Mage_Checkout_CartController
{
    
    /**
     * Initialize coupon with action checkout_cart_coupon_added
     */
    public function couponPostAction()
    {
        /**
         * No reason continue with empty shopping cart
         */
        if (!$this->_getCart()->getQuote()->getItemsCount()) {
            $this->_goBack();
            return;
        }

        $couponCode = (string) $this->getRequest()->getParam('coupon_code');
        if ($this->getRequest()->getParam('remove') == 1) {
            $couponCode = '';
        }
        $oldCouponCode = $this->_getQuote()->getCouponCode();

        if (!strlen($couponCode) && !strlen($oldCouponCode)) {
            $this->_goBack();
            return;
        }

        try {
            $codeLength = strlen($couponCode);
            $isCodeLengthValid = $codeLength && $codeLength <= Mage_Checkout_Helper_Cart::COUPON_CODE_MAX_LENGTH;

            $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
            $this->_getQuote()->setCouponCode($isCodeLengthValid ? $couponCode : '')
                ->collectTotals()
                ->save();

            if ($codeLength) {
                if ($isCodeLengthValid && $couponCode == $this->_getQuote()->getCouponCode()) {
                    Mage::dispatchEvent('checkout_cart_coupon_added',
                        array('quote' => $this->_getQuote())
                    );
                    $this->_getSession()->addSuccess(
                        $this->__('Coupon code "%s" was applied.', Mage::helper('core')->escapeHtml($couponCode))
                    );
                } else {
                    if( Mage::registry( 'coupon_error') ){
                        switch( Mage::registry( 'coupon_error' ) ){

                            case 'usage_limit':
                                $error = $this->__('Coupon code "%s" over maximum usage.', Mage::helper('core')->escapeHtml($couponCode));
                                break;

                            case 'customer_usage_limit':
                                $error = $this->__('You already used coupon "%s".', Mage::helper('core')->escapeHtml($couponCode));
                                break;

                            default:
                                $error = $this->__('Coupon code "%s" is not valid.', Mage::helper('core')->escapeHtml($couponCode));
                                break;

                        }
                        Mage::unregister( 'coupon_error' );

                    }
                    $this->_getSession()->addError( $error );
                }
            } else {
                Mage::dispatchEvent('checkout_cart_coupon_removed',
                    array('quote' => $this->_getQuote(),
                        'coupon_code' => $oldCouponCode )
                );
                $this->_getSession()->addSuccess($this->__('Coupon code was canceled.'));
            }

        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Cannot apply the coupon code.'));
            Mage::logException($e);
        }

        $this->_goBack();
    }
}