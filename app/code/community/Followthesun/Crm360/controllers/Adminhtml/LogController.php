<?php

class Followthesun_Crm360_Adminhtml_LogController extends Mage_Adminhtml_Controller_action {

    protected function _initAction() {

        $this->loadLayout()
               ->_setActiveMenu('crm360/logs');
        return $this;
    }

    public function indexAction() {
        $this->_initAction()
                ->renderLayout();
    }

    public function massDeleteAction() {
        $logIds = $this->getRequest()->getParam('log');
        if (!is_array($logIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select item(s)'));
        } else {
            try {
                foreach ($logIds as $logId) {
                    $log = Mage::getModel('crm360/log')->load($logId);
                    $log->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                        Mage::helper('adminhtml')->__(
                                'Total of %d record(s) were successfully deleted', count($logIds)
                        )
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }
}