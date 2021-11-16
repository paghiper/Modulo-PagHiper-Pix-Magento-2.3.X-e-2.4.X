<?php

namespace Paghiper\Magento2\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface {
  public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context) {
    $setup->startSetup();
    $orderTable = 'sales_order';
    //Order table
    $setup->getConnection()
      ->addColumn(
        $setup->getTable($orderTable),
        'paghiper_pix',
        [
          'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
          'length' => '500',
          'default' => null,
          'nullable' => true,
          'comment' => 'PagHiper Pix'
        ]
      );
      $setup->getConnection()
      ->addColumn(
        $setup->getTable($orderTable),
        'paghiper_chavepix',
        [
          'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
          'length' => '500',
          'default' => null,
          'nullable' => true,
          'comment' => 'PagHiper Chave Pix'
        ]
      );
    $setup->getConnection()
      ->addColumn(
        $setup->getTable($orderTable),
        'paghiper_boleto',
        [
          'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
          'length' => '500',
          'default' => null,
          'nullable' => true,
          'comment' => 'PagHiper Boleto'
        ]
      );

      $setup->getConnection()
      ->addColumn(
        $setup->getTable($orderTable),
        'paghiper_boleto_digitavel',
        [
          'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
          'length' => '500',
          'default' => null,
          'nullable' => true,
          'comment' => 'PagHiper Boleto Linha Digitavel'
        ]
      );

      $setup->getConnection()
      ->addColumn(
        $setup->getTable($orderTable),
        'paghiper_transaction',
        [
          'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
          'length' => '500',
          'default' => null,
          'nullable' => true,
          'comment' => 'PagHiper Transaction'
        ]
      );
    $setup->endSetup();
  }
}
