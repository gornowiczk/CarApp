<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250906151936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A76ED395');
        $this->addSql('ALTER TABLE reservation ADD created_at DATETIME NOT NULL, DROP phone_number, DROP rental_location');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD first_name VARCHAR(100) DEFAULT NULL, ADD last_name VARCHAR(100) DEFAULT NULL, DROP full_name, DROP pesel_or_nip, CHANGE address address VARCHAR(255) DEFAULT NULL, CHANGE phone_number phone_number VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_user_email TO UNIQ_8D93D649E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD full_name VARCHAR(120) NOT NULL, ADD pesel_or_nip VARCHAR(20) DEFAULT NULL, DROP first_name, DROP last_name, CHANGE address address VARCHAR(255) NOT NULL, CHANGE phone_number phone_number VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d649e7927c74 TO uniq_user_email');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A76ED395');
        $this->addSql('ALTER TABLE reservation ADD phone_number VARCHAR(20) DEFAULT NULL, ADD rental_location VARCHAR(255) DEFAULT NULL, DROP created_at');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
