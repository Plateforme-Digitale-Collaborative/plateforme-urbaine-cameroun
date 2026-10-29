<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pages d'acteurs : point focal, administrateurs de page, validation des modifications, historique.
 *
 * - actor_assignment      : affectations (point focal / admin de page) avec période (started_at → ended_at)
 * - actor_change_request  : modifications soumises par les admins de page, en attente / traitées
 * - actor_activity_log    : journal des actions menées sur la page d'un acteur
 * - resource.actor_id     : rattachement d'une ressource à un acteur (nullable)
 *
 * Reprise de l'existant : le créateur d'un acteur qui n'est pas admin PDC (ROLE_ADMIN) devient point focal
 * de cet acteur, à la date de création de l'acteur (comme pour les acteurs créés après cette migration).
 *
 * Écrite à la main (comme les migrations précédentes) : ne touche à rien d'autre que ces objets.
 */
final class Version20261028100800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pages d\'acteurs : point focal, administrateurs de page, validation des modifications, journal d\'activité';
    }

    public function up(Schema $schema): void
    {
        // --- actor_assignment ---------------------------------------------------------------------
        $this->addSql(<<<'SQL'
            CREATE TABLE actor_assignment (
                id UUID NOT NULL,
                actor_id UUID NOT NULL,
                user_id INT DEFAULT NULL,
                assigned_by_id INT DEFAULT NULL,
                ended_by_id INT DEFAULT NULL,
                user_label VARCHAR(255) DEFAULT NULL,
                role VARCHAR(255) NOT NULL,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                end_reason VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql("COMMENT ON COLUMN actor_assignment.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN actor_assignment.actor_id IS '(DC2Type:uuid)'");
        $this->addSql('CREATE INDEX idx_actor_assignment_actor ON actor_assignment (actor_id)');
        $this->addSql('CREATE INDEX idx_actor_assignment_user ON actor_assignment (user_id)');
        $this->addSql('CREATE INDEX idx_actor_assignment_assigned_by ON actor_assignment (assigned_by_id)');
        $this->addSql('CREATE INDEX idx_actor_assignment_ended_by ON actor_assignment (ended_by_id)');
        // Un seul point focal en cours par acteur ; pas de doublon d'affectation en cours.
        $this->addSql("CREATE UNIQUE INDEX uniq_actor_active_focal_point ON actor_assignment (actor_id) WHERE (role = 'focal_point' AND ended_at IS NULL)");
        $this->addSql('CREATE UNIQUE INDEX uniq_actor_active_assignment ON actor_assignment (actor_id, user_id, role) WHERE (ended_at IS NULL)');
        $this->addSql('ALTER TABLE actor_assignment ADD CONSTRAINT fk_actor_assignment_actor FOREIGN KEY (actor_id) REFERENCES actor (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE actor_assignment ADD CONSTRAINT fk_actor_assignment_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE actor_assignment ADD CONSTRAINT fk_actor_assignment_assigned_by FOREIGN KEY (assigned_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE actor_assignment ADD CONSTRAINT fk_actor_assignment_ended_by FOREIGN KEY (ended_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // --- actor_change_request -----------------------------------------------------------------
        $this->addSql(<<<'SQL'
            CREATE TABLE actor_change_request (
                id UUID NOT NULL,
                actor_id UUID NOT NULL,
                requested_by_id INT DEFAULT NULL,
                reviewed_by_id INT DEFAULT NULL,
                target_type VARCHAR(255) NOT NULL,
                target_id VARCHAR(36) NOT NULL,
                target_label VARCHAR(255) DEFAULT NULL,
                operation VARCHAR(255) NOT NULL,
                payload JSON DEFAULT NULL,
                status VARCHAR(255) NOT NULL,
                requested_by_label VARCHAR(255) DEFAULT NULL,
                requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                reviewed_by_label VARCHAR(255) DEFAULT NULL,
                reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                review_comment TEXT DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql("COMMENT ON COLUMN actor_change_request.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN actor_change_request.actor_id IS '(DC2Type:uuid)'");
        $this->addSql('CREATE INDEX idx_actor_change_request_actor_status ON actor_change_request (actor_id, status)');
        $this->addSql('CREATE INDEX idx_actor_change_request_target ON actor_change_request (target_type, target_id)');
        $this->addSql('CREATE INDEX idx_actor_change_request_requested_by ON actor_change_request (requested_by_id)');
        $this->addSql('CREATE INDEX idx_actor_change_request_reviewed_by ON actor_change_request (reviewed_by_id)');
        $this->addSql('ALTER TABLE actor_change_request ADD CONSTRAINT fk_actor_change_request_actor FOREIGN KEY (actor_id) REFERENCES actor (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE actor_change_request ADD CONSTRAINT fk_actor_change_request_requested_by FOREIGN KEY (requested_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE actor_change_request ADD CONSTRAINT fk_actor_change_request_reviewed_by FOREIGN KEY (reviewed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // --- actor_activity_log -------------------------------------------------------------------
        $this->addSql(<<<'SQL'
            CREATE TABLE actor_activity_log (
                id UUID NOT NULL,
                actor_id UUID NOT NULL,
                user_id INT DEFAULT NULL,
                user_label VARCHAR(255) DEFAULT NULL,
                action VARCHAR(255) NOT NULL,
                target_type VARCHAR(255) DEFAULT NULL,
                target_id VARCHAR(36) DEFAULT NULL,
                details JSON DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql("COMMENT ON COLUMN actor_activity_log.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN actor_activity_log.actor_id IS '(DC2Type:uuid)'");
        $this->addSql('CREATE INDEX idx_actor_activity_log_actor_date ON actor_activity_log (actor_id, created_at)');
        $this->addSql('CREATE INDEX idx_actor_activity_log_user ON actor_activity_log (user_id)');
        $this->addSql('ALTER TABLE actor_activity_log ADD CONSTRAINT fk_actor_activity_log_actor FOREIGN KEY (actor_id) REFERENCES actor (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE actor_activity_log ADD CONSTRAINT fk_actor_activity_log_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // --- resource.actor_id --------------------------------------------------------------------
        $this->addSql('ALTER TABLE resource ADD actor_id UUID DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN resource.actor_id IS '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE resource ADD CONSTRAINT fk_resource_actor FOREIGN KEY (actor_id) REFERENCES actor (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_resource_actor ON resource (actor_id)');

        // --- Reprise de l'existant : créateur non-admin d'un acteur = point focal ------------------
        $this->addSql(<<<'SQL'
            INSERT INTO actor_assignment (id, actor_id, user_id, user_label, role, started_at, end_reason)
            SELECT gen_random_uuid(), a.id, a.created_by, TRIM(u.first_name || ' ' || u.last_name), 'focal_point', a.created_at, NULL
            FROM actor a
            JOIN "user" u ON u.id = a.created_by
            WHERE NOT (u.roles::jsonb @> '["ROLE_ADMIN"]'::jsonb)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO actor_activity_log (id, actor_id, user_id, user_label, action, details, created_at)
            SELECT gen_random_uuid(), s.actor_id, s.user_id, s.user_label, 'focal_point_assigned',
                   json_build_object('assignmentId', s.id, 'focalPointId', s.user_id, 'focalPointName', s.user_label, 'reason', 'Reprise de l''existant : créateur de la page de l''acteur'),
                   s.started_at
            FROM actor_assignment s
            WHERE s.role = 'focal_point'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resource DROP CONSTRAINT fk_resource_actor');
        $this->addSql('DROP INDEX idx_resource_actor');
        $this->addSql('ALTER TABLE resource DROP actor_id');
        $this->addSql('DROP TABLE actor_activity_log');
        $this->addSql('DROP TABLE actor_change_request');
        $this->addSql('DROP TABLE actor_assignment');
    }
}