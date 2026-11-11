<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Das gelernte Gedächtnis eines Engrams, kuratierbar aus der Org:
 *  - memory_docs: Spiegel der steuernden Vault-Dateien (Arbeitsweise, Index, offene Fragen, Playbooks),
 *    vom Daemon gepusht — die Org liest, schreibt aber nie direkt hinein.
 *  - curations: Kuratier-Aufträge (Datei ersetzen/stilllegen). Der Daemon holt sie ab, wendet sie nur auf
 *    unverändertem Stand an (base_sha), sichert vorher und quittiert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_agent_memory_docs')) {
            Schema::create('organization_agent_memory_docs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('organization_entity_id')->index();
                $t->string('path', 255);          // relativ zum Vault, z. B. routine.md, playbook/x.md
                $t->string('kind', 24);           // routine | index | questions | playbook
                $t->string('title')->nullable();
                $t->char('sha', 64);
                $t->longText('content');
                $t->timestamp('file_modified_at')->nullable();
                $t->timestamps();
                $t->unique(['organization_entity_id', 'path'], 'org_agent_memory_docs_entity_path');
            });
        }

        if (! Schema::hasTable('organization_agent_curations')) {
            Schema::create('organization_agent_curations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('organization_entity_id')->index();
                $t->string('path', 255);
                $t->string('op', 16);              // replace | retire
                $t->char('base_sha', 64);          // Stand, auf den sich der Auftrag bezieht
                $t->longText('content')->nullable(); // neuer Inhalt (replace)
                $t->text('note')->nullable();      // warum — landet beim Engram im Protokoll
                $t->string('status', 16)->default('pending')->index(); // pending | applied | stale | rejected | cancelled
                $t->text('result')->nullable();
                $t->unsignedBigInteger('created_by_user_id')->nullable();
                $t->timestamp('applied_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_agent_curations');
        Schema::dropIfExists('organization_agent_memory_docs');
    }
};
