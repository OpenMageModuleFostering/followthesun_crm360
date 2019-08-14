<?php
class Followthesun_Crm360_Block_Adminhtml_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_log';
    $this->_blockGroup = 'crm360';
    $this->_headerText = Mage::helper('crm360')->__('Crm360 / Logs');
    parent::__construct();
    $this->_removeButton('add');
  }
}