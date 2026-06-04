<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les informations de contact et preferences d affichage utilisateur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ADD address VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(30) DEFAULT NULL, ADD theme_mode VARCHAR(20) NOT NULL DEFAULT 'light', ADD accent_color VARCHAR(7) NOT NULL DEFAULT '#00bc77'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP address, DROP phone, DROP theme_mode, DROP accent_color');
    }
}
