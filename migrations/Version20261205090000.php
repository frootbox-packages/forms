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
 * What an entry is about (decision 0230): the target of the page the form
 * was sent from when it is no plain page, e.g. a job, with its title at
 * that moment.
 */
final class Version20261205090000 extends Migration
{
    public function getDescription(): string
    {
        return 'Adds what an entry of a form is about';
    }

    public function up(Schema $schema): void
    {
        $this->alterTable('frootbox_forms_entries', function (Blueprint $table): void {
            $table->string('about', 150)->nullable();
            $table->string('about_title', 300)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $this->alterTable('frootbox_forms_entries', function (Blueprint $table): void {
            $table->dropColumn('about', 'about_title');
        });
    }
}
