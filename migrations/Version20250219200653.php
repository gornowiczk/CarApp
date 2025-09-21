<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250219200653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955C3C6F69F');
        $this->addSql('CREATE TABLE car_image (id INT AUTO_INCREMENT NOT NULL, car_id INT NOT NULL, image_path VARCHAR(255) NOT NULL, INDEX IDX_1A968188C3C6F69F (car_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cars (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, brand VARCHAR(100) NOT NULL, model VARCHAR(100) NOT NULL, year INT NOT NULL, registration_number VARCHAR(50) NOT NULL, price_per_day NUMERIC(10, 2) NOT NULL, main_image VARCHAR(255) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, is_available TINYINT(1) NOT NULL, gallery JSON DEFAULT NULL, INDEX IDX_95C71D147E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE car_image ADD CONSTRAINT FK_1A968188C3C6F69F FOREIGN KEY (car_id) REFERENCES cars (id)');
        $this->addSql('ALTER TABLE cars ADD CONSTRAINT FK_95C71D147E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE car DROP FOREIGN KEY FK_773DE69D7E3C61F9');
        $this->addSql('DROP TABLE car');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955C3C6F69F');
        $this->addSql('ALTER TABLE reservation ADD status VARCHAR(20) DEFAULT \'pending\' NOT NULL, ADD comment LONGTEXT DEFAULT NULL, ADD phone_number VARCHAR(20) DEFAULT NULL, ADD rental_location VARCHAR(255) DEFAULT NULL, ADD comments LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955C3C6F69F FOREIGN KEY (car_id) REFERENCES cars (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD full_name VARCHAR(255) DEFAULT NULL, ADD address VARCHAR(255) NOT NULL, ADD phone_number VARCHAR(20) NOT NULL, ADD pesel_or_nip VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955C3C6F69F');
        $this->addSql('CREATE TABLE car (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, brand VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, model VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, plate_number VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, year INT NOT NULL, price_per_day NUMERIC(10, 2) NOT NULL, UNIQUE INDEX UNIQ_773DE69DFCFF3785 (plate_number), INDEX IDX_773DE69D7E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE car ADD CONSTRAINT FK_773DE69D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE car_image DROP FOREIGN KEY FK_1A968188C3C6F69F');
        $this->addSql('ALTER TABLE cars DROP FOREIGN KEY FK_95C71D147E3C61F9');
        $this->addSql('DROP TABLE car_image');
        $this->addSql('DROP TABLE cars');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955C3C6F69F');
        $this->addSql('ALTER TABLE reservation DROP status, DROP comment, DROP phone_number, DROP rental_location, DROP comments');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955C3C6F69F FOREIGN KEY (car_id) REFERENCES car (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE user DROP full_name, DROP address, DROP phone_number, DROP pesel_or_nip');
    }
}
