<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260806111822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync DiverCity module entities (Space, Booking, BlockedPeriod, EventActivityFavorite, Notification, SpaceAdmin, Status, EventActivityType, InformationSource) with existing divercity schema';
    }

    public function up(Schema $schema): void
    {
        // Migration obsolète : écrite le 06/08 avant la découverte du problème de
        // séquences manquantes sur le schéma divercity (NotNullConstraintViolationException).
        // Les ALTER ... DROP DEFAULT qu'elle contenait annuleraient le correctif manuel
        // appliqué en prod le 20/09 (voir scripts/check-sequences-puc-prod.sh), qui ajoute
        // au contraire les séquences attendues par #[ORM\GeneratedValue] (stratégie AUTO)
        // sur Notification, InformationSource, EventActivityType, Status, BlockedPeriod.
        // Neutralisée volontairement, ne pas réactiver son contenu d'origine.
    }

    public function down(Schema $schema): void
    {
        // Volontairement vide, voir up().
    }
}
