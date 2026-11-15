<?php

use App\Services\Locations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('city', 120)->nullable()->after('location');
            $table->string('city_key', 120)->nullable()->after('city')->index();
            $table->string('state', 2)->nullable()->after('city_key')->index();
        });

        // Separa cidade e estado das vagas que já existem.
        DB::table('job_postings')->select(['id', 'location'])->whereNotNull('location')->orderBy('id')
            ->chunk(500, function ($jobs) {
                foreach ($jobs as $job) {
                    DB::table('job_postings')->where('id', $job->id)->update(Locations::parse($job->location));
                }
            });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex(['city_key']);
            $table->dropIndex(['state']);
            $table->dropColumn(['city', 'city_key', 'state']);
        });
    }
};
