<?php

use App\Services\JobDeduplicator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empresa normalizada ("BEES Brasil" -> "bees") para achar a mesma vaga vinda de outro site.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('company_key', 120)->nullable()->after('company')->index();
        });

        DB::table('job_postings')->whereNotNull('company')->select(['id', 'company'])->orderBy('id')
            ->chunkById(500, function ($jobs): void {
                foreach ($jobs as $job) {
                    DB::table('job_postings')->where('id', $job->id)
                        ->update(['company_key' => mb_substr(JobDeduplicator::companyKey($job->company), 0, 120)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex(['company_key']);
            $table->dropColumn('company_key');
        });
    }
};
