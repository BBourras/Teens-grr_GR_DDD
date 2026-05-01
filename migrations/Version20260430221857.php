<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430221857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, report_count INT DEFAULT 0 NOT NULL, status VARCHAR(50) NOT NULL, deleted_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, author_id INT NOT NULL, post_id INT NOT NULL, INDEX IDX_9474526CF675F31B (author_id), INDEX IDX_9474526C4B89032C (post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE moderation_action_log (id INT AUTO_INCREMENT NOT NULL, action_type VARCHAR(255) NOT NULL, previous_status VARCHAR(30) DEFAULT NULL, new_status VARCHAR(30) DEFAULT NULL, reason LONGTEXT DEFAULT NULL, context JSON DEFAULT NULL, created_at DATETIME NOT NULL, moderator_id INT DEFAULT NULL, post_id INT DEFAULT NULL, comment_id INT DEFAULT NULL, INDEX IDX_835117CCD0AFA354 (moderator_id), INDEX IDX_835117CC4B89032C (post_id), INDEX IDX_835117CCF8697D13 (comment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, comment_count INT DEFAULT 0 NOT NULL, report_count INT DEFAULT 0 NOT NULL, reaction_score INT DEFAULT 0 NOT NULL, status VARCHAR(50) NOT NULL, deleted_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, author_id INT NOT NULL, INDEX IDX_5A8A6C8DF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE report (id INT AUTO_INCREMENT NOT NULL, reason VARCHAR(255) NOT NULL, reason_detail VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, post_id INT DEFAULT NULL, comment_id INT DEFAULT NULL, INDEX IDX_C42F7784A76ED395 (user_id), INDEX IDX_C42F77844B89032C (post_id), INDEX IDX_C42F7784F8697D13 (comment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(50) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vote (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, guest_key VARCHAR(64) DEFAULT NULL, guest_ip_hash VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, post_id INT NOT NULL, INDEX IDX_5A108564A76ED395 (user_id), INDEX IDX_5A1085644B89032C (post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE moderation_action_log ADD CONSTRAINT FK_835117CCD0AFA354 FOREIGN KEY (moderator_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE moderation_action_log ADD CONSTRAINT FK_835117CC4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE moderation_action_log ADD CONSTRAINT FK_835117CCF8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DF675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F77844B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A1085644B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C4B89032C');
        $this->addSql('ALTER TABLE moderation_action_log DROP FOREIGN KEY FK_835117CCD0AFA354');
        $this->addSql('ALTER TABLE moderation_action_log DROP FOREIGN KEY FK_835117CC4B89032C');
        $this->addSql('ALTER TABLE moderation_action_log DROP FOREIGN KEY FK_835117CCF8697D13');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DF675F31B');
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F7784A76ED395');
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F77844B89032C');
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F7784F8697D13');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564A76ED395');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A1085644B89032C');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE moderation_action_log');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE report');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE vote');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
