<?php

class Followthesun_Crm360_Block_Adminhtml_Log_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      $this->setId('LogGrid');
      $this->setDefaultSort('log_id');
      $this->setDefaultDir('DESC');
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
      $collection = Mage::getModel('crm360/log')->getCollection();
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _prepareColumns()
  {
      $this->addColumn('log_id', array(
          'header'    => Mage::helper('crm360')->__('ID'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'log_id',
      ));

      $this->addColumn('type', array(
          'header'    => Mage::helper('crm360')->__('Type'),
          'index'     => 'type',
          'width'     => '100px',
      ));
      $this->addColumn('status', array(
          'header'    => Mage::helper('crm360')->__('Statut'),
          'index'     => 'status',
      ));

      $this->addColumn('message', array(
          'header'    => Mage::helper('crm360')->__('Message'),
          'index'     => 'message',
      ));

      $this->addColumn('created_at', array(
          'header'    => Mage::helper('crm360')->__('Date de création'),
          'index'     => 'created_at',
          'width'     => '150px',
      ));

      $this->addColumn('updated_at', array(
          'header'    => Mage::helper('crm360')->__('Data de modification'),
          'index'     => 'updated_at',
          'width'     => '150px',
      ));
  
      return parent::_prepareColumns();
  }

  protected function _prepareMassaction()
  {
      $this->setMassactionIdField('log_id');
      $this->getMassactionBlock()->setFormFieldName('log');

      $this->getMassactionBlock()->addItem('delete', array(
           'label'    => Mage::helper('crm360')->__('Delete'),
           'url'      => $this->getUrl('*/*/massDelete'),
           'confirm'  => Mage::helper('crm360')->__('Etes-vous sûr ?')
      ));
      return $this;
  }

  public function getRowUrl($row)
  {
      return '';
  }

}