<?php

use App\Services\Locations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inglês deixa de ser tecnologia e vira competência; e as cidades são separadas de novo
 * ("São Paulo Area" do LinkedIn passa a contar como "São Paulo — SP").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('job_postings')->select(['id', 'techs', 'skills', 'location'])->orderBy('id')
            ->chunk(500, function ($jobs) {
                foreach ($jobs as $job) {
                    $techs = json_decode((string) $job->techs, true) ?: [];
                    $skills = json_decode((string) $job->skills, true) ?: [];
                    $changes = $job->location ? Locations::parse($job->location) : [];

                    if (in_array('Inglês', $techs, true)) {
                        $changes['techs'] = json_encode(array_values(array_diff($techs, ['Inglês'])));
                        $changes['skills'] = json_encode(array_values(array_unique([...$skills, 'Inglês'])));
                    }
                    if ($changes) {
                        DB::table('job_postings')->where('id', $job->id)->update($changes);
                    }
                }
            });
    }

    public function down(): void
    {
        // Correção de dados: não há o que desfazer.
    }
};
