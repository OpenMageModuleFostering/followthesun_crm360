<?php

class Followthesun_Crm360_IndexController extends Mage_Core_Controller_Front_Action {

    var $timestamp;
    var $ftp_url;
    var $ftp_login;
    var $ftp_password;
    var $ftp_path;
    var $tmp_dir;
    var $errors;

    /**
     * Predispatch: update tmp_dir AND check identification
     *
     * @return Mage_Core_Controller_Front_Action
     */
    public function preDispatch() {
        parent::preDispatch();

        $helper = Mage::helper('crm360');
        $this->tmp_dir = $helper->getTmpDir();

        $params = $this->getRequest()->getParams();
        $login = $params['login'];
        $password = $params['password'];
        if (!$helper->identificationCheck($login, $password)) { /* identification failed */
            $action = Mage::app()->getRequest()->getActionName();
            if ($action === 'export') {
                $logModel = Mage::getModel('crm360/log');
                $logModel->setCreatedAt(date('Y-m-d H:i:s'))
                        ->setStatus($logModel::STATUS_FAILED)
                        ->setMessage($this->__('Erreur de login ou mot de passe'))
                        ->save();
            }
            $result['response']['error'] = true;
            $result['response']['message'] = $this->__('Erreur de login ou de mot de passe');

            $this->getResponse()
                    ->clearHeaders()
                    ->setHeader('Content-Type', 'application/json')
                    ->setBody(json_encode($result));

            $this->setFlag('', 'no-dispatch', true);
        }
    }

    public function exportAction() {

        $params = $this->getRequest()->getParams();
        $this->type = $params['type'];

        $result = $this->checkParams($params);
        if(empty($result) || (is_array($result) && !isset($result['error']))) {
            $logModel = Mage::getModel('crm360/log');
            $logModel->setCreatedAt(date('Y-m-d H:i:s'))
                    ->setType($this->type)
                    ->setStatus($logModel::STATUS_PENDING)
                    ->save();
            $params['id'] = $logModel->getId();
            
            /* launch Export => erreur commande wget not found */
            $wget_path = Mage::getStoreConfig('crm360_config/config/wget_path');
            $return = shell_exec($wget_path.' -O /dev/null "' . Mage::getUrl('*/*/launchExport', array('_query'=>$params)) . '" &');

            $result['response']['id'] = $logModel->getId();
            $result['response']['status'] = $logModel->getStatus();    
        }       
        $this->getResponse()
                ->clearHeaders()
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode($result));
    }

    /*
     *
     */
    public function launchExportAction() {

        $params = $this->getRequest()->getParams();
        $id = $params['id'];
        $this->timestamp = (isset($params['timestamp'])) ? $params['timestamp'] : null;
        $this->type = $params['type'];
        $this->ftp_url = $params['ftp_url'];
        $this->ftp_login = $params['ftp_login'];
        $this->ftp_password = $params['ftp_password'];
        $this->ftp_path = $params['ftp_path'];

        $logModel = Mage::getModel('crm360/log')->load($id);

        /* prévention du max_execution_time */
        register_shutdown_function(array($this, 'handleFatalError'),$logModel);

        if (!$logModel || !$logModel->getId()) {
            $this->error[] = $this->__('La demande numéro %s n\'existe pas', $id);
        } elseif ($logModel->getStatus() !== $logModel::STATUS_PENDING) {
            $this->error[] = $this->__('La demande numéro %s est déjà en cours de traitement ou terminée', $id);
        } else {
            $logModel->setStatus($logModel::STATUS_PROCESSING)->save();
            $pathFiles = '';
            switch ($this->type) {
                case 'customers' :
                    $pathFiles = $this->_prepareCustomersFile($logModel->getId());
                    break;
                case 'orders' :
                    $pathFiles = $this->_prepareOrdersFile($logModel->getId());
                    break;
                case 'orderDetails' :
                    $pathFiles = $this->_prepareOrderDetailsFile($logModel->getId());
                    break;    
                case 'products' :
                    $pathFiles = $this->_prepareProductsFile($logModel->getId());
                    break;
                case 'categories' :
                    $pathFiles = $this->_prepareCategoriesFile($logModel->getId());
                    break;
                default :
                    $this->error[] = $this->__('Le type d\'export "'.$this->type.'" n\'existe pas.');
                    break;
            }
        }

        if (is_array($this->error)) {
            $logModel->setStatus($logModel::STATUS_FAILED)
                    ->setMessage(implode("\n", $this->error));
        } elseif ($pathFiles) {
            if(!is_array($pathFiles)) $pathFiles = array($pathFiles);
            $this->ftpCopy($pathFiles);
            foreach($pathFiles as $pathFile)
            {
                unlink($pathFile);
            }
            $logModel->setStatus($logModel::STATUS_SUCCESS);
        }
        $logModel->setUpdatedAt(date('Y-m-d H:i:s'));
        $logModel->save();
    }

      /**
       * Méthode appelée à la détection d'une fatal error.
       */
      public function handleFatalError($logModel)
      {
        $lastError = error_get_last();
        if(isset($lastError['type']) && $lastError['type'] === E_ERROR)
        {
            //var_dump($logModel);
            $logModel->setStatus($logModel::STATUS_FAILED)
                    ->setUpdatedAt(date('Y-m-d H:i:s'))
                    ->setMessage($lastError['message'])
                    ->save();
        }
      }

    protected function checkParams($params) {
        $result = '';
        if (!isset($params['type']) || !isset($params['ftp_url']) || !isset($params['ftp_login']) || !isset($params['ftp_password']) || !isset($params['ftp_path'])) {
            $result['error'] = "Erreur de paramètre";
        } else {
            $type = $params['type'];
            if(!in_array($type, array('customers','orders','orderDetails','products','categories'))) {
                $result['error'] = $this->__('Le type d\'export "'.$this->type.'" n\'existe pas.');
            } else {
                $conn_id = ftp_connect($params['ftp_url']);

                try {
                    // login with username and password
                    $login_result = ftp_login($conn_id, $params['ftp_login'], $params['ftp_password']);
                    if (!$login_result) {
                        $result['error'] = $this->__('Erreur lors de la connexion FTP');
                    }
                } catch(Exception $e) {
                }
            }
        } 
        return $result;
    }

    /*
     * Create csv file with customers info
     * return path file
     */
    protected function _prepareCustomersFile($logModelId) {
        $pathFile = $this->tmp_dir . 'Contact-' . date('Y-m-d') .'-'.$logModelId.'.csv';
        $helper = Mage::helper('crm360');

        try {

            /* prepare array with country code */
            $arrayCountries = $helper->prepareArrayCountries();

            $fp = fopen($pathFile, 'w');
            $coll = Mage::getModel('customer/customer')->getCollection();
            $coll->addAttributeToSelect(array('firstname', 'lastname', 'email', 'dob', 'is_subscribed'));
            if ($this->timestamp !== null) {
                $coll->addAttributeToFilter('updated_at', array('from' => $this->timestamp));
            }
            foreach ($coll as $customer) {
                $customerBillingAddress = $helper->getCustommerAddress($customer);
                $data = array(
                    $customer->getId(),
                    $customer->getPrefix(),
                    $customer->getFirstname(),
                    $customer->getLastname(),
                    ($customerBillingAddress && $customerBillingAddress->getId()) ? $customerBillingAddress->getStreet(1) : '',
                    ($customerBillingAddress && $customerBillingAddress->getId()) ? $customerBillingAddress->getStreet(2) : '',
                    ($customerBillingAddress && $customerBillingAddress->getId()) ? $customerBillingAddress->getData('postcode') : '',
                    ($customerBillingAddress && $customerBillingAddress->getId()) ? $customerBillingAddress->getData('city') : '',
                    ($customerBillingAddress && $customerBillingAddress->getId()) ? $customerBillingAddress->getRegion() : '',
                    ($customerBillingAddress && $customerBillingAddress->getId() && isset($arrayCountries[$customerBillingAddress->getData('country_id')])) ? $arrayCountries[$customerBillingAddress->getData('country_id')]['iso3_code'] : '',
                    $customer->getEmail(),
                    (int)$customer->getIsSubscribed(),
                    '',
                    ($customerBillingAddress && $customerBillingAddress->getId()) ? $customerBillingAddress->getData('telephone') : '',
                    $customer->getDob() ? date('d/m/Y', strtotime($customer->getDob())) : '',
                    ($customerBillingAddress && $customerBillingAddress->getId()) ? $customerBillingAddress->getCompany() : '',
                    '',
                    date('d/m/Y H:i:s', strtotime($customer->getUpdatedAt())),
                    '',
                    '',
                    ($customerBillingAddress && $customerBillingAddress->getId() && isset($arrayCountries[$customerBillingAddress->getData('country_id')])) ? $arrayCountries[$customerBillingAddress->getData('country_id')]['iso3_code'] : ''
                );
                //fputcsv($fp, $data, '|');
                fputs($fp,implode($data, '|').PHP_EOL);
            }
            fclose($fp);
            return $pathFile;
        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            return false;
        }
    }

    /*
     * Create csv file with orders info
     * return Array path file header and detail
     */
    protected function _prepareOrdersFile($logModelId) {
        try {
            $helper = Mage::helper('crm360');
            $time = date('Y-m-d');

            $pathFileHeader = $this->tmp_dir . 'PurchaseHeader-' . $time .'-'.$logModelId.'.csv';
            $fpHeader = fopen($pathFileHeader, 'w');

            $coll = Mage::getModel('sales/order')->getCollection();
            if ($this->timestamp !== null) {
                $coll->addAttributeToFilter('updated_at', array('from' => $this->timestamp));
            }
            foreach ($coll as $order) {
                /* Order Header */
                $dataHeader = array(
                    $order->getCustomerId(),
                    date('d/m/Y H:i:s', strtotime($order->getUpdatedAt())),
                    $order->getStoreId(),
                    $order->getIncrementId(),
                    $order->getBaseCurrencyCode(),
                    $helper->formatPrice($order->getGrandTotal()),
                    $helper->formatPrice($order->getGrandTotal()-$order->getTaxAmount()),
                    $helper->formatPrice($order->getDiscountAmount()),
                    $order->getStatus()
                );
                //fputcsv($fpHeader, $dataHeader, '|');
                fputs($fpHeader,implode($dataHeader, '|').PHP_EOL);

            }
            fclose($fpHeader);

            return array($pathFileHeader);
        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            return false;
        }
    }

    /*
     * Create csv file with orders info
     * return Array path file header and detail
     */
    protected function _prepareOrderDetailsFile($logModelId) {
        try {
            $helper = Mage::helper('crm360');
            $time = date('Y-m-d');

            $pathFileDetail = $this->tmp_dir . 'PurchaseDetail-' . $time .'-'.$logModelId.'.csv';
            $fpDetail = fopen($pathFileDetail, 'w');

            $coll = Mage::getModel('sales/order')->getCollection();
            if ($this->timestamp !== null) {
                $coll->addAttributeToFilter('updated_at', array('from' => $this->timestamp));
            }
            foreach ($coll as $order) {
                /* Order Detail */
                $items = $order->getAllItems();
                foreach ($items as $item) {
                    $dataDetail = array(
                        date('d/m/Y H:i:s', strtotime($order->getUpdatedAt())),
                        $order->getStoreId(),
                        $order->getIncrementId(),
                        $item->getSku(),
                        $helper->formatPrice($item->getRowTotalInclTax()),
                        $helper->formatPrice($item->getRowTotal()),
                        $helper->formatPrice($item->getDiscountAmount()),
                        $item->getQtyOrdered()
                    );
                    //fputcsv($fpDetail, $dataDetail, '|');
                    fputs($fpDetail,implode($dataDetail, '|').PHP_EOL);
                }
            }
            fclose($fpDetail);

            return array($pathFileDetail);
        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            return false;
        }
    }

    /*
     * Create csv file with products info
     * return path file
     */
    protected function _prepareProductsFile($logModelId) {
        $pathFile = $this->tmp_dir . 'PurchaseGood-' . date('Y-m-d') .'-'.$logModelId.'.csv';

        try {
            $fp = fopen($pathFile, 'w');
            $coll = Mage::getModel('catalog/product')->getCollection();
            $coll->addAttributeToSelect(array('sku','name', 'price'))
                    ->addFinalPrice();
            if ($this->timestamp !== null) {
                $coll->addAttributeToFilter('updated_at', array('from' => $this->timestamp));
            }
            foreach ($coll as $product) {
                $data = array(
                    $product->getSku(),
                    $product->getName(),
                    implode(',', $product->getCategoryIds())
                );
                //fputcsv($fp, $data, '|');
                fputs($fp,implode($data, '|').PHP_EOL);
            }
            fclose($fp);
            return $pathFile;
        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            return false;
        }
    }

    /*
     * Create csv file with categories info
     * return path file
     */
    protected function _prepareCategoriesFile($logModelId) {
        $pathFile = $this->tmp_dir . 'PurchaseGoodCategory-' . date('Y-m-d').'-'.$logModelId.'.csv';

        try {
            $fp = fopen($pathFile, 'w');
            $coll = Mage::getModel('catalog/category')->getCollection(); //->addFieldToFilter('parent_id',array('neq'=>'0'))
            $coll->addAttributeToSelect(array('name'));
            if ($this->timestamp !== null) {
                $coll->addAttributeToFilter('updated_at', array('from' => $this->timestamp));
            }
            foreach ($coll as $category) {
                $data = array(
                    $category->getId(),
                    $category->getParentId(),
                    $category->getName(),
                );
                //fputcsv($fp, $data, '|');
                fputs($fp,implode($data, '|').PHP_EOL);
            }
            fclose($fp);
            return $pathFile;
        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            return false;
        }
    }

    /*
     * copy file in FTP
     */
    protected function ftpCopy($pathFiles) {
        // set up basic connection
        $conn_id = ftp_connect($this->ftp_url);

        try {
            // login with username and password
            $login_result = ftp_login($conn_id, $this->ftp_login, $this->ftp_password);
            if (!$login_result) {
                $this->error[] = $this->__('Erreur lors de la connexion FTP');
                return false;
            }
            foreach($pathFiles as $pathFile) {
                // upload a file
                $remote_file = $this->ftp_path . basename($pathFile);
                if (ftp_put($conn_id, $remote_file, $pathFile, FTP_ASCII)) {

                } else {
                    $this->error[] = $this->__("Une erreur est survenue lors du transfère du fichier");
                }
            }
            // close the connection
            ftp_close($conn_id);
        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            return false;
        }
    }

    /*
     * Return status of task
     */
    public function statusAction() {
        $params = $this->getRequest()->getParams();
        $result = '';
        if (!isset($params['id'])) {
            $result['error'] = "Erreur de paramètre";
        } else {
            $id = $params['id'];
            $logModel = Mage::getModel('crm360/log')->load($id);
            if ($logModel && $logModel->getId()) {
                $result['response']['status'] = $logModel->getStatus();
                /* if status failed, add message to response */
                if ($logModel->getStatus() == $logModel::STATUS_FAILED) {
                    $result['response']['message'] = $logModel->getMessage();
                }
            } else {
                $result['response']['error'] = true;
                $result['response']['message'] = $this->__('La demande numéro %s n\'existe pas', $id);
            }    
        }
        $this->getResponse()
                ->clearHeaders()
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode($result));
    }

}