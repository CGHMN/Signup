<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251229183813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wireguard_peer (id INT AUTO_INCREMENT NOT NULL, tunnel_ip VARCHAR(16) NOT NULL, allowed_ips JSON NOT NULL, pub_key LONGTEXT NOT NULL, preshared_key LONGTEXT NOT NULL, router_id BIGINT NOT NULL, user_id INT NOT NULL, INDEX IDX_7AB05EA2A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wireguard_peer ADD CONSTRAINT FK_7AB05EA2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wireguard_peer DROP FOREIGN KEY FK_7AB05EA2A76ED395');
        $this->addSql('DROP TABLE wireguard_peer');
    }
}
