<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * DiverCity : divercity.space_admin passe d'une clé primaire composite
 * (user_id, space_id) à une clé primaire UUID, avec une contrainte d'unicité
 * sur (user_id, space_id).
 *
 * Version réécrite à la main. La migration générée contenait aussi, entre autres :
 * - des ALTER ... DROP DEFAULT sur les colonnes id (qui retireraient les séquences
 *   réparées en production),
 * - un DROP TABLE geodata.osm2pgsql_properties (table de l'import OSM),
 * - un SET NOT NULL sur actor.administrative_scopes (1 ligne NULL en production),
 * - un index unique sur resource.banoc_url (23 chaînes vides en production).
 * Ces points sont à traiter dans des migrations séparées, avec nettoyage des données.
 */
final class Version20260930105539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DiverCity : space_admin avec identifiant UUID';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin DROP CONSTRAINT space_admin_pkey
        SQL);

        // Colonne d'abord nullable, remplie ensuite : fonctionne que la table soit vide ou non.
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin ADD id UUID
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE divercity.space_admin SET id = gen_random_uuid() WHERE id IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin ALTER id SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN divercity.space_admin.id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_space_admin_user_space ON divercity.space_admin (user_id, space_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin ADD PRIMARY KEY (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX divercity.uniq_space_admin_user_space
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin DROP CONSTRAINT space_admin_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin DROP id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin ADD PRIMARY KEY (user_id, space_id)
        SQL);
    }
}