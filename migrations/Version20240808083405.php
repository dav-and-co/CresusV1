<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240808083405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE connexion (id INT AUTO_INCREMENT NOT NULL, id_pc_id INT NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_936BF99C61E6122D (id_pc_id), INDEX IDX_936BF99CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C61E6122D FOREIGN KEY (id_pc_id) REFERENCES ip_pc (id)');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C61E6122D');
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99CA76ED395');
        $this->addSql('DROP TABLE connexion');
    }
}