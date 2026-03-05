<?php

namespace App\Jobs;

use App\Exports\ResponseDetailsExport;
use App\Models\ExportTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportResponseDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;

    public function __construct(
        public readonly string $taskId,
        public readonly array $filters,
        public readonly int $chunkSize,
        public readonly bool $singleQuery
    ) {
    }

    public function handle(): void
    {
        $task = ExportTask::query()->findOrFail($this->taskId);

        $task->update([
            'status' => 'running',
            'started_at' => now(),
            'error' => null,
        ]);

        $disk = $task->file_disk ?: 'local';
        $dir = 'exports/response-details';
        $fileName = $task->id . '.xlsx';
        $path = $dir . '/' . $fileName;

        if ($disk === 'local') {
            Storage::disk($disk)->makeDirectory($dir);
        }

        Excel::store(
            new ResponseDetailsExport($this->filters, $this->chunkSize, $this->singleQuery),
            $path,
            $disk
        );

        $task->update([
            'status' => 'completed',
            'file_path' => $path,
            'completed_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        ExportTask::query()
            ->where('id', $this->taskId)
            ->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
    }
}
