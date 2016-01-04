<?php

namespace OroB2B\Bundle\MenuBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\AttachmentBundle\Migration\Extension\AttachmentExtension;
use Oro\Bundle\AttachmentBundle\Migration\Extension\AttachmentExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class CreateMenuItemTable implements Migration, AttachmentExtensionAwareInterface
{
    const MAX_MENU_ITEM_IMAGE_SIZE_IN_MB = 1;

    /**
     * @var AttachmentExtension
     */
    protected $attachmentExtension;

    /**
     * {@inheritdoc}
     */
    public function setAttachmentExtension(AttachmentExtension $attachmentExtension)
    {
        $this->attachmentExtension = $attachmentExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /** Tables generation **/
        $this->createOroB2BMenuItemTable($schema);
        $this->createOroB2BMenuItemTitleTable($schema);

        /** Foreign keys generation **/
        $this->addOroB2BMenuItemForeignKeys($schema);
        $this->addOroB2BMenuItemTitleForeignKeys($schema);
    }

    /**
     * Create orob2b_menu_item table
     *
     * @param Schema $schema
     */
    protected function createOroB2BMenuItemTable(Schema $schema)
    {
        $table = $schema->createTable('orob2b_menu_item');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('parent_id', 'integer', ['notnull' => false]);
        $table->addColumn('serialized_data', 'array', ['notnull' => false, 'comment' => '(DC2Type:array)']);
        $table->addColumn('uri', 'text', []);
        $table->addColumn('route', 'string', ['notnull' => false, 'length' => 128]);
        $table->addColumn('route_parameters', 'array', ['notnull' => false, 'comment' => '(DC2Type:array)']);
        $table->addColumn('display', 'boolean', []);
        $table->addColumn('display_children', 'boolean', []);
        $table->addColumn('tree_left', 'integer', []);
        $table->addColumn('tree_level', 'integer', []);
        $table->addColumn('tree_right', 'integer', []);
        $table->addColumn('tree_root', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $this->attachmentExtension->addImageRelation(
            $schema,
            'orob2b_menu_item',
            'image',
            [],
            self::MAX_MENU_ITEM_IMAGE_SIZE_IN_MB
        );
    }

    /**
     * Create orob2b_menu_item_title table
     *
     * @param Schema $schema
     */
    protected function createOroB2BMenuItemTitleTable(Schema $schema)
    {
        $table = $schema->createTable('orob2b_menu_item_title');
        $table->addColumn('menu_item_id', 'integer', []);
        $table->addColumn('localized_value_id', 'integer', []);
        $table->setPrimaryKey(['menu_item_id', 'localized_value_id']);
        $table->addUniqueIndex(['localized_value_id'], 'UNIQ_D67C4C5FEB576E89');
    }

    /**
     * Add orob2b_menu_item foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroB2BMenuItemForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('orob2b_menu_item');
        $table->addForeignKeyConstraint(
            $schema->getTable('orob2b_menu_item'),
            ['parent_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }

    /**
     * Add orob2b_menu_item_title foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroB2BMenuItemTitleForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('orob2b_menu_item_title');
        $table->addForeignKeyConstraint(
            $schema->getTable('orob2b_fallback_locale_value'),
            ['localized_value_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('orob2b_menu_item'),
            ['menu_item_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }
}
