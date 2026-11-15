<?php

use App\Services\JobClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('work_mode', 12)->nullable()->after('remote')->index();
        });

        // Preenche as vagas que já existem a partir do título, local e descrição.
        $classifier = app(JobClassifier::class);
        DB::table('job_postings')->select(['id', 'title', 'location', 'description', 'remote'])->orderBy('id')
            ->chunk(500, function ($jobs) use ($classifier) {
                foreach ($jobs as $job) {
                    $mode = $classifier->workMode($job->title, $job->location, (string) $job->description, $job->remote ? 'remoto' : null);
                    DB::table('job_postings')->where('id', $job->id)->update(['work_mode' => $mode, 'remote' => $mode === 'remoto']);
                }
            });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('work_mode');
        });
    }
};
