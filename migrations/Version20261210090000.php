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
 * The spam folder and the proofs of work used once (decision 0261).
 */
final class Version20261210090000 extends Migration
{
    public function getDescription(): string
    {
        return 'Adds the spam folder of the forms and the proofs of work';
    }

    public function up(Schema $schema): void
    {
        $this->alterTable('frootbox_forms_entries', function (Blueprint $table): void {
            $table->string('spam', 300)->nullable();
        });

        $this->createTable('frootbox_forms_proofs', function (Blueprint $table): void {
            $table->id();
            $table->string('key_hash', 64)->unique();
            $table->dateTime('used_at')->index();
        });
    }

    public function down(Schema $schema): void
    {
        $this->dropTable('frootbox_forms_proofs');
        $this->alterTable('frootbox_forms_entries', function (Blueprint $table): void {
            $table->dropColumn('spam');
        });
    }
}
