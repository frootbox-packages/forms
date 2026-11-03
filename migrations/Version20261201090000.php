<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Frootbox\Database\Migration;
use Frootbox\Database\Schema\Blueprint;

/**
 * The entries of forms and the count of sendings for the spam protection
 * (decision 0222). Forms themselves live in revisions. The role "editor"
 * gets frootbox/forms/form.*, the entries stay with the admins.
 */
final class Version20261201090000 extends Migration
{
    private const string RULE = 'frootbox/forms/form.*';

    public function getDescription(): string
    {
        return 'Adds the tables of the forms';
    }

    public function up(Schema $schema): void
    {
        $this->createTable('frootbox_forms_entries', function (Blueprint $table): void {
            $table->ulid();
            $table->ulid('form_id')->index();
            $table->string('language', 35);
            $table->json('fields');
            $table->ulid('page_id')->nullable();
            $table->dateTime('created_at')->index();
        });

        $this->createTable('frootbox_forms_sendings', function (Blueprint $table): void {
            $table->id();
            $table->string('key_hash', 64)->index();
            $table->dateTime('sent_at')->index();
        });

        $this->change(static fn(array $rules): array => \in_array(self::RULE, $rules, true) || \in_array('*', $rules, true) ? $rules : [...$rules, self::RULE]);
    }

    public function down(Schema $schema): void
    {
        $this->change(static fn(array $rules): array => array_values(array_filter($rules, static fn(mixed $rule): bool => self::RULE !== $rule)));
        $this->dropTable('frootbox_forms_sendings');
        $this->dropTable('frootbox_forms_entries');
    }

    private function change(callable $change): void
    {
        $rules = $this->connection->fetchOne('SELECT permissions FROM roles WHERE name = ?', ['editor']);

        if (!\is_string($rules)) {
            return;
        }

        $rules = json_decode($rules, true, flags: \JSON_THROW_ON_ERROR);

        if (\is_array($rules) && array_is_list($rules)) {
            $this->addSql('UPDATE roles SET permissions = ? WHERE name = ?', [json_encode($change($rules), \JSON_THROW_ON_ERROR), 'editor']);
        }
    }
}
