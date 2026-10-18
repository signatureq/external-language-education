<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        Schema::create('content_documents', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('kind');
            $t->string('name');
            $t->longText('template');
            $t->json('schema');
            $t->json('draft');
            $t->json('published');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('content_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('content_document_id')->constrained()->cascadeOnDelete();
            $t->json('data');
            $t->string('author');
            $t->timestamps();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('path')->unique();
            $t->string('alt')->default('');
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_documents');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
