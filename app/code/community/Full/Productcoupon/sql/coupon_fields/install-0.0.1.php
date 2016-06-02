<?php

/* 
 * Daniele Pastori.
 * daniele.pastori@gmail.com
 */

$connection          = $this->getConnection();
$rulesTable          = $this->getTable('salesrule');
$this->startSetup();
//add column coupon_item_id, for storing free product id
$connection->addColumn(
        $rulesTable,
        'coupon_item_id', 
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_INTEGER, 
            'default' => null,
            'unsigned'  => true,
            'nullable'  => true,
            'primary'   => false,
            'comment'   => 'Free product id'
        )
    );

$this->endSetup();