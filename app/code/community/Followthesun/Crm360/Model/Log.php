<?php

class Followthesun_Crm360_Model_Log extends Mage_Core_Model_Abstract
{
    CONST STATUS_PENDING = 'En attente';
    CONST STATUS_PROCESSING = 'En cours de traitement';
    CONST STATUS_SUCCESS = 'Terminé avec succès';
    CONST STATUS_FAILED = 'Erreur lors du traitement';

    public function _construct()
    {
        parent::_construct();
        $this->_init('crm360/log');
    }
}