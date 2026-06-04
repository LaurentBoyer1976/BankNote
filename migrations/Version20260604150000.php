<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create budget tracking schema for Symfony migration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_APP_USER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE account (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(150) NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(3) NOT NULL, description VARCHAR(80) NOT NULL, account_number VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_7D3656A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(100) NOT NULL, color VARCHAR(20) NOT NULL, INDEX IDX_64C19C16A76ED395 (user_id), UNIQUE INDEX UNIQ_CATEGORY_USER_NAME (user_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE payment_method (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(40) NOT NULL, INDEX IDX_7B61A1F6A76ED395 (user_id), UNIQUE INDEX UNIQ_PAYMENT_METHOD_USER_NAME (user_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE bank_transaction (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, account_id INT NOT NULL, category_id INT DEFAULT NULL, payment_method_id INT DEFAULT NULL, label VARCHAR(150) NOT NULL, amount NUMERIC(12, 2) NOT NULL, type VARCHAR(20) NOT NULL, executed_at DATE NOT NULL COMMENT '(DC2Type:date_immutable)', note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_BB2EAB68A76ED395 (user_id), INDEX IDX_BB2EAB689B6B5FBA (account_id), INDEX IDX_BB2EAB6812469DE2 (category_id), INDEX IDX_BB2EAB685AA1164F (payment_method_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_ACCOUNT_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_CATEGORY_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_method ADD CONSTRAINT FK_PAYMENT_METHOD_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bank_transaction ADD CONSTRAINT FK_TRANSACTION_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bank_transaction ADD CONSTRAINT FK_TRANSACTION_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bank_transaction ADD CONSTRAINT FK_TRANSACTION_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE bank_transaction ADD CONSTRAINT FK_TRANSACTION_PAYMENT_METHOD FOREIGN KEY (payment_method_id) REFERENCES payment_method (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bank_transaction DROP FOREIGN KEY FK_TRANSACTION_PAYMENT_METHOD');
        $this->addSql('ALTER TABLE bank_transaction DROP FOREIGN KEY FK_TRANSACTION_CATEGORY');
        $this->addSql('ALTER TABLE bank_transaction DROP FOREIGN KEY FK_TRANSACTION_ACCOUNT');
        $this->addSql('ALTER TABLE bank_transaction DROP FOREIGN KEY FK_TRANSACTION_USER');
        $this->addSql('ALTER TABLE payment_method DROP FOREIGN KEY FK_PAYMENT_METHOD_USER');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_CATEGORY_USER');
        $this->addSql('ALTER TABLE account DROP FOREIGN KEY FK_ACCOUNT_USER');
        $this->addSql('DROP TABLE bank_transaction');
        $this->addSql('DROP TABLE payment_method');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE app_user');
    }
}
