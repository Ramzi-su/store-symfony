<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929080000 extends AbstractMigration
{
    private const KNOWN_STATUSES = ['pending', 'paid', 'shipped', 'cancelled'];

    public function getDescription(): string
    {
        return 'Check that every orders.status matches the OrderStatus enum (no schema change)';
    }

    public function preUp(Schema $schema): void
    {
        // The column stays a VARCHAR, but Doctrine now hydrates it into App\Enum\OrderStatus:
        // an unknown value (the admin form used to accept free text) would crash every page
        // listing that order. Abort instead of silently rewriting business data.
        $placeholders = implode(', ', array_fill(0, count(self::KNOWN_STATUSES), '?'));
        $unknown = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT status FROM orders WHERE status NOT IN ($placeholders)",
            self::KNOWN_STATUSES
        );

        $this->abortIf(
            $unknown !== [],
            sprintf(
                'Unknown order statuses: %s. Update them to one of %s, then rerun the migration.',
                implode(', ', $unknown),
                implode(', ', self::KNOWN_STATUSES)
            )
        );
    }

    public function up(Schema $schema): void
    {
        // Intentionally empty: this migration only guards the data (see preUp()).
    }

    public function down(Schema $schema): void
    {
    }
}
