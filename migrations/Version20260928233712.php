<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928233712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add checkout details and Stripe session id to orders, product options to order items, nullable user for guest orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orderitems ADD color VARCHAR(50) DEFAULT NULL, ADD storage VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD subtotal DOUBLE PRECISION DEFAULT NULL, ADD tax DOUBLE PRECISION DEFAULT NULL, ADD shipping_cost DOUBLE PRECISION DEFAULT NULL, ADD first_name VARCHAR(100) DEFAULT NULL, ADD last_name VARCHAR(100) DEFAULT NULL, ADD email VARCHAR(180) DEFAULT NULL, ADD phone_number VARCHAR(30) DEFAULT NULL, ADD address VARCHAR(255) DEFAULT NULL, ADD city VARCHAR(100) DEFAULT NULL, ADD state VARCHAR(100) DEFAULT NULL, ADD postcode VARCHAR(20) DEFAULT NULL, ADD country VARCHAR(100) DEFAULT NULL, ADD stripe_session_id VARCHAR(255) DEFAULT NULL, CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E52FFDEE1A314A57 ON orders (stripe_session_id)');

        // product.image is mapped but was never part of a migration: existing databases
        // most likely got it through schema:update, fresh ones do not have it yet.
        if (!$schema->getTable('product')->hasColumn('image')) {
            $this->addSql('ALTER TABLE product ADD image VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Fails while guest orders (user_id NULL) exist: delete or reassign them first.
        $this->addSql('ALTER TABLE orderitems DROP color, DROP storage');
        $this->addSql('DROP INDEX UNIQ_E52FFDEE1A314A57 ON orders');
        $this->addSql('ALTER TABLE orders DROP subtotal, DROP tax, DROP shipping_cost, DROP first_name, DROP last_name, DROP email, DROP phone_number, DROP address, DROP city, DROP state, DROP postcode, DROP country, DROP stripe_session_id, CHANGE user_id user_id INT NOT NULL');
        // product.image is intentionally kept: it may predate this migration.
    }
}
