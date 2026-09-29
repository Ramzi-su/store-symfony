<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929080100 extends AbstractMigration
{
    // Largest INT value, in cents.
    private const MAX_CENTS = 2147483647;

    public function getDescription(): string
    {
        return 'Store every amount (product price, order totals, order item price) as an integer number of cents';
    }

    public function preUp(Schema $schema): void
    {
        $maxPrice = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(price), 0) FROM product');
        $this->abortIf(
            $maxPrice * 100 > self::MAX_CENTS,
            sprintf('A product costs %d, which does not fit in an INT once converted to cents.', $maxPrice)
        );
    }

    public function up(Schema $schema): void
    {
        // Order amounts are DOUBLE: convert the values while the column can still hold them,
        // then change the type. A plain ALTER would turn $19.99 into 20 (and mean 20 cents).
        $this->addSql('UPDATE orderitems SET price = ROUND(price * 100)');
        $this->addSql('ALTER TABLE orderitems CHANGE price price INT NOT NULL');

        $this->addSql('UPDATE orders SET total = ROUND(total * 100), subtotal = ROUND(subtotal * 100), tax = ROUND(tax * 100), shipping_cost = ROUND(shipping_cost * 100)');
        $this->addSql('ALTER TABLE orders CHANGE total total INT NOT NULL, CHANGE subtotal subtotal INT DEFAULT NULL, CHANGE tax tax INT DEFAULT NULL, CHANGE shipping_cost shipping_cost INT DEFAULT NULL');

        // product.price is DECIMAL(10, 0) (whole dollars): change the type first, since
        // DECIMAL(10, 0) could overflow once multiplied by 100.
        $this->addSql('ALTER TABLE product CHANGE price price INT NOT NULL');
        $this->addSql('UPDATE product SET price = price * 100');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orderitems CHANGE price price DOUBLE PRECISION NOT NULL');
        $this->addSql('UPDATE orderitems SET price = price / 100');

        $this->addSql('ALTER TABLE orders CHANGE total total DOUBLE PRECISION NOT NULL, CHANGE subtotal subtotal DOUBLE PRECISION DEFAULT NULL, CHANGE tax tax DOUBLE PRECISION DEFAULT NULL, CHANGE shipping_cost shipping_cost DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('UPDATE orders SET total = total / 100, subtotal = subtotal / 100, tax = tax / 100, shipping_cost = shipping_cost / 100');

        // Lossy: the old column has no decimals, so cents are rounded to the nearest dollar.
        $this->addSql('UPDATE product SET price = ROUND(price / 100)');
        $this->addSql('ALTER TABLE product CHANGE price price NUMERIC(10, 0) NOT NULL');
    }
}
