<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930105539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE IF EXISTS geodata.osm2pgsql_properties
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE actor ALTER administrative_scopes SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE admin1_boundary ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE admin3_boundary ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE app_content_comment ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE atlas ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.blocked_period ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS idx_booking_attachment_type
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS idx_booking_attachment_media_object
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.booking_attachment ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE connection_log ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.event_activity_type ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE geo_data ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highlighted_item ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.highlighted_resource ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.information_source ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_object ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.notification ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE page_view ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_project_slug
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_resource ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qgis_map ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qgis_project ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE refresh_tokens ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_BC91F416EC3D194B ON resource (banoc_url)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin DROP CONSTRAINT space_admin_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin ADD id UUID NOT NULL
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
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_highlight ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_statistic ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.status ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_like ALTER id DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_password_token ALTER id DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SCHEMA geodata
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE geodata.osm2pgsql_properties (property TEXT NOT NULL, value TEXT NOT NULL, PRIMARY KEY(property))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.notification_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.notification_id_seq', (SELECT MAX(id) FROM divercity.notification))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.notification ALTER id SET DEFAULT nextval('divercity.notification_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.highlighted_resource_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.highlighted_resource_id_seq', (SELECT MAX(id) FROM divercity.highlighted_resource))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.highlighted_resource ALTER id SET DEFAULT nextval('divercity.highlighted_resource_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.information_source_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.information_source_id_seq', (SELECT MAX(id) FROM divercity.information_source))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.information_source ALTER id SET DEFAULT nextval('divercity.information_source_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.event_activity_type_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.event_activity_type_id_seq', (SELECT MAX(id) FROM divercity.event_activity_type))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.event_activity_type ALTER id SET DEFAULT nextval('divercity.event_activity_type_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE user_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('user_id_seq', (SELECT MAX(id) FROM "user"))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ALTER id SET DEFAULT nextval('user_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE page_view_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('page_view_id_seq', (SELECT MAX(id) FROM page_view))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE page_view ALTER id SET DEFAULT nextval('page_view_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE media_object_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('media_object_id_seq', (SELECT MAX(id) FROM media_object))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media_object ALTER id SET DEFAULT nextval('media_object_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE atlas_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('atlas_id_seq', (SELECT MAX(id) FROM atlas))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE atlas ALTER id SET DEFAULT nextval('atlas_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE app_content_comment_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('app_content_comment_id_seq', (SELECT MAX(id) FROM app_content_comment))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE app_content_comment ALTER id SET DEFAULT nextval('app_content_comment_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE admin3_boundary_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('admin3_boundary_id_seq', (SELECT MAX(id) FROM admin3_boundary))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE admin3_boundary ALTER id SET DEFAULT nextval('admin3_boundary_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE admin1_boundary_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('admin1_boundary_id_seq', (SELECT MAX(id) FROM admin1_boundary))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE admin1_boundary ALTER id SET DEFAULT nextval('admin1_boundary_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.booking_attachment_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.booking_attachment_id_seq', (SELECT MAX(id) FROM divercity.booking_attachment))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.booking_attachment ALTER id SET DEFAULT nextval('divercity.booking_attachment_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_booking_attachment_type ON divercity.booking_attachment (booking_id, type)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_booking_attachment_media_object ON divercity.booking_attachment (file_object_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.blocked_period_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.blocked_period_id_seq', (SELECT MAX(id) FROM divercity.blocked_period))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.blocked_period ALTER id SET DEFAULT nextval('divercity.blocked_period_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_project_slug ON project (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE geo_data_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('geo_data_id_seq', (SELECT MAX(id) FROM geo_data))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE geo_data ALTER id SET DEFAULT nextval('geo_data_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE highlighted_item_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('highlighted_item_id_seq', (SELECT MAX(id) FROM highlighted_item))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highlighted_item ALTER id SET DEFAULT nextval('highlighted_item_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE actor ALTER administrative_scopes DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE connection_log_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('connection_log_id_seq', (SELECT MAX(id) FROM connection_log))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE connection_log ALTER id SET DEFAULT nextval('connection_log_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE project_resource_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('project_resource_id_seq', (SELECT MAX(id) FROM project_resource))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_resource ALTER id SET DEFAULT nextval('project_resource_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE qgis_project_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('qgis_project_id_seq', (SELECT MAX(id) FROM qgis_project))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qgis_project ALTER id SET DEFAULT nextval('qgis_project_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.space_statistic_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.space_statistic_id_seq', (SELECT MAX(id) FROM divercity.space_statistic))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_statistic ALTER id SET DEFAULT nextval('divercity.space_statistic_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE user_password_token_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('user_password_token_id_seq', (SELECT MAX(id) FROM user_password_token))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_password_token ALTER id SET DEFAULT nextval('user_password_token_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE user_like_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('user_like_id_seq', (SELECT MAX(id) FROM user_like))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_like ALTER id SET DEFAULT nextval('user_like_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_space_admin_user_space
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX space_admin_pkey
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin DROP id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_admin ADD PRIMARY KEY (user_id, space_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.space_highlight_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.space_highlight_id_seq', (SELECT MAX(id) FROM divercity.space_highlight))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.space_highlight ALTER id SET DEFAULT nextval('divercity.space_highlight_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE divercity.status_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('divercity.status_id_seq', (SELECT MAX(id) FROM divercity.status))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE divercity.status ALTER id SET DEFAULT nextval('divercity.status_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE refresh_tokens_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('refresh_tokens_id_seq', (SELECT MAX(id) FROM refresh_tokens))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE refresh_tokens ALTER id SET DEFAULT nextval('refresh_tokens_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE qgis_map_id_seq
        SQL);
        $this->addSql(<<<'SQL'
            SELECT setval('qgis_map_id_seq', (SELECT MAX(id) FROM qgis_map))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE qgis_map ALTER id SET DEFAULT nextval('qgis_map_id_seq')
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_BC91F416EC3D194B
        SQL);
    }
}
