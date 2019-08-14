<?php

class Followthesun_Crm360_Helper_Data extends Mage_Core_Helper_Abstract {

    /*
     * Check if login/password is correct
     * return Boolean
     */
    public function identificationCheck($login, $password) {
        $config_login = Mage::getStoreConfig('crm360_config/identification/login');
        $config_password = Mage::getStoreConfig('crm360_config/identification/password');
        if ($config_login === $login && $config_password === $password) {
            return true;
        }
        return false;
    }

    /*
     * Return tmp dir
     */
    public function getTmpDir() {
        $path = Mage::getBaseDir('media') . DS . 'crm360' . DS;
        if(!is_dir($path)) {
            mkdir($path,0777);
        }
        return $path;
    }

    /*
     * Return customer last billing address use OR default billing address OR false
     */
    public function getCustommerAddress($_customer) {

        /* search last order */
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', $_customer->getId())
            ->addAttributeToSort('created_at', 'DESC')
            ->setPageSize(1);
        $lastOrder = $orders->getFirstItem();
        if($lastOrder->getId()) {
            return Mage::getModel('sales/order_address')->load($lastOrder->getBillingAddressId());
        }
        elseif($defaultBillingAddress = $_customer->getDefaultBillingAddress())
        {
            return $defaultBillingAddress;
        }
        return false;
    }

    /*
     * return Array with country code (ISO2, ISO3)
     */
    public function prepareArrayCountries() {
        $coll = Mage::getModel('directory/country')->getCollection();
        $array = "";
        foreach($coll as $country) {
            $array[$country['country_id']]['iso2_code'] = $country->getData('iso2_code');
            $array[$country['country_id']]['iso3_code'] = $country->getData('iso3_code');
        }
        return $array;
    }

    /*
     * return format price
     * ex: 15,55 => 15.55
     * 9 => 9.00
     */
    public function formatPrice($value) {
        $value = Mage::getModel('directory/currency')->format(
            $value,
            array('display'=>Zend_Currency::NO_SYMBOL,'precision'=>2),
            false
        );
        return str_replace(',','.',$value);
    }
}