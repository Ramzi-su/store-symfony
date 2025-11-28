<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250516083125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE orderitems ADD CONSTRAINT FK_F1CAA3418D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id)');
        $this->addSql('ALTER TABLE orderitems ADD CONSTRAINT FK_F1CAA3414584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('CREATE INDEX IDX_F1CAA3418D9F6D38 ON orderitems (order_id)');
        $this->addSql('CREATE INDEX IDX_F1CAA3414584665A ON orderitems (product_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE orderitems DROP FOREIGN KEY FK_F1CAA3418D9F6D38');
        $this->addSql('ALTER TABLE orderitems DROP FOREIGN KEY FK_F1CAA3414584665A');
        $this->addSql('DROP INDEX IDX_F1CAA3418D9F6D38 ON orderitems');
        $this->addSql('DROP INDEX IDX_F1CAA3414584665A ON orderitems');
    }
}
